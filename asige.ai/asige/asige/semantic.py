"""Semantic engine: embeddings (LSA offline / SBERT optional), keywords, entities, clustering, knowledge graph."""
import math
import re
from collections import Counter, defaultdict
from itertools import combinations

import networkx as nx
import numpy as np
from sklearn.cluster import AgglomerativeClustering, MiniBatchKMeans
from sklearn.decomposition import TruncatedSVD
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.preprocessing import normalize as l2

from . import config
from .text import STOP_EN, STOP_FA, normalize, tokenize

_RAW = re.compile(r"[^\W_]+", re.UNICODE)


def page_text(p: dict) -> str:
    """Title x3 + first H1 x2 + meta + body (first 6k chars): headline signals weigh more in TF-IDF."""
    t, h = p.get("title") or "", (p.get("h1s") or [""])[0]
    return " ".join([t, t, t, h, h, p.get("meta_description") or "", (p.get("text") or "")[:6000]])


class SemanticIndex:
    def __init__(self, backend=None, dim=128, seed=config.SEED, sbert_model=None):
        self.backend, self.dim, self.seed = backend or config.EMBEDDINGS, dim, seed
        self.sbert_model = sbert_model or config.SBERT_MODEL

    def fit(self, texts):
        n = len(texts)
        self.vec = TfidfVectorizer(tokenizer=tokenize, lowercase=False, token_pattern=None, ngram_range=(1, 2),
                                   sublinear_tf=True, min_df=1 if n < 40 else 2, max_df=0.9 if n >= 10 else 1.0,
                                   max_features=80_000, dtype=np.float32)
        self.X = self.vec.fit_transform(texts)
        self.terms = self.vec.get_feature_names_out()
        self.svd = self.sbert = None
        if self.backend == "sbert":
            from sentence_transformers import SentenceTransformer  # optional heavy dependency
            self.sbert = SentenceTransformer(self.sbert_model)
            self.E = self.sbert.encode(texts, normalize_embeddings=True, batch_size=32, show_progress_bar=False)
        else:
            k = min(self.dim, self.X.shape[0] - 1, self.X.shape[1] - 1)
            if k >= 2:
                self.svd = TruncatedSVD(k, random_state=self.seed, n_iter=7)
                self.E = l2(self.svd.fit_transform(self.X))
            else:
                self.E = l2(self.X.toarray())
        return self

    def transform(self, texts):
        if self.sbert is not None:
            return self.sbert.encode(texts, normalize_embeddings=True, show_progress_bar=False)
        X = self.vec.transform(texts)
        return l2(self.svd.transform(X)) if self.svd is not None else l2(X.toarray())

    def top_terms(self, i, k=10):
        row = self.X.getrow(i)
        order = np.argsort(-row.data)[:k]
        return [(self.terms[row.indices[j]], round(float(row.data[j]), 4)) for j in order]

    def centroid_terms(self, members, k=6):
        c = np.asarray(self.X[members].mean(axis=0)).ravel()
        return [self.terms[j] for j in np.argsort(-c)[:k] if c[j] > 0]


def topk_cosine(Q, E, k=5, chunk=2000):
    """Chunked top-k cosine (rows already L2-normalized). Memory O(chunk * n)."""
    k = min(k, E.shape[0])
    idx, sims = [], []
    for s in range(0, Q.shape[0], chunk):
        S = Q[s:s + chunk] @ E.T
        part = np.argpartition(-S, k - 1, axis=1)[:, :k]
        ps = np.take_along_axis(S, part, 1)
        o = np.argsort(-ps, axis=1)
        idx.append(np.take_along_axis(part, o, 1))
        sims.append(np.take_along_axis(ps, o, 1))
    return np.vstack(idx), np.vstack(sims)


def _cluster_nonzero(V, distance_threshold, max_exact, seed):
    n = len(V)
    if n == 1:
        return np.array([0])
    if n <= max_exact:
        kw = dict(n_clusters=None, linkage="average", distance_threshold=distance_threshold)
        try:
            return AgglomerativeClustering(metric="cosine", **kw).fit_predict(V)
        except TypeError:
            return AgglomerativeClustering(affinity="cosine", **kw).fit_predict(V)
    return MiniBatchKMeans(max(2, int(math.sqrt(n / 2))), random_state=seed, n_init=3).fit_predict(V)


def cluster_vectors(V, distance_threshold=0.6, max_exact=3000, seed=config.SEED, texts=None):
    """Average-linkage cosine clustering (exact up to max_exact items, MiniBatchKMeans beyond).
    Zero vectors (all terms out-of-vocabulary, typical for unseen queries) cannot be compared by cosine; they are
    grouped lexically (shared tokens, union-find) when `texts` is given, else kept as singletons."""
    n = len(V)
    if n == 0:
        return np.array([], dtype=int)
    zero = ~np.any(V, axis=1)
    labels = np.full(n, -1, dtype=int)
    nz = np.nonzero(~zero)[0]
    if len(nz):
        labels[nz] = _cluster_nonzero(V[nz], distance_threshold, max_exact, seed)
    off = int(labels.max()) + 1
    zi = np.nonzero(zero)[0]
    parent = {i: i for i in zi}

    def find(x):
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    if texts is not None:
        owner = {}
        for i in zi:
            for t in set(tokenize(texts[i])):
                if t in owner:
                    parent[find(i)] = find(owner[t])
                else:
                    owner[t] = i
    roots = {}
    for i in zi:
        labels[i] = off + roots.setdefault(find(i), len(roots))
    return labels


def medoid(V, members):
    sub = V[members]
    return members[int(np.argmax((sub @ sub.T).mean(axis=1)))]


# ---------------- entities ----------------
def _chunks(text):
    """Token runs broken at stopwords: candidate phrase spans."""
    run = []
    for t in _RAW.findall(normalize(text)):
        if t in STOP_EN or t in STOP_FA or len(t) < 2 or t.isdigit():
            if run:
                yield run
            run = []
        else:
            run.append(t)
    if run:
        yield run


def collocations(texts, min_count=3, min_df=2, npmi_min=0.3):
    uni, bi, bi_docs, total = Counter(), Counter(), defaultdict(set), 0
    for d, text in enumerate(texts):
        for run in _chunks(text[:8000]):
            for i, t in enumerate(run):
                uni[t] += 1
                total += 1
                if i:
                    bg = f"{run[i - 1]} {t}"
                    bi[bg] += 1
                    bi_docs[bg].add(d)
    out = {}
    for bg, c in bi.items():
        if c < min_count or len(bi_docs[bg]) < min_df:
            continue
        a, b = bg.split(" ")
        pxy, px, py = c / total, uni[a] / total, uni[b] / total  # same normalizer N keeps NPMI in [-1, 1]
        npmi = math.log(pxy / (px * py)) / -math.log(pxy) if pxy < 1 else 0
        if npmi >= npmi_min:
            out[bg] = {"count": c, "df": len(bi_docs[bg]), "npmi": round(npmi, 3), "docs": bi_docs[bg]}
    return out


PROPER = re.compile(r"^[A-Z][\w'-]+$")


def proper_nouns(text):
    found = []
    for sent in re.split(r"(?<=[.!?])\s+", text[:20000]):
        toks, run = sent.split(), []
        for i, tok in enumerate(toks):
            w = tok.strip(",.;:!?()\"'")
            if i > 0 and PROPER.match(w) and w.lower() not in STOP_EN:
                run.append(w)
            else:
                if run:
                    found.append(" ".join(run))
                run = []
        if run:
            found.append(" ".join(run))
    return found


def extract_entities(pages, per_page=20):
    """Heuristic entities (no pretrained NER): schema.org names, English proper-noun runs, statistical collocations
    (works for Persian). Returns ({url: [entity]}, corpus [(text, type, df)])."""
    texts = [p["text"] for p in pages]
    colloc = collocations(texts)
    per, df = defaultdict(dict), Counter()
    kind = {}
    for d, p in enumerate(pages):
        u = p["url"]
        for b in p.get("jsonld") or []:
            for key in ("name", "brand", "author", "publisher"):
                v = b.get(key)
                name = v.get("name") if isinstance(v, dict) else v
                if isinstance(name, str) and 2 < len(name) < 80:
                    t = str(b.get("@type") if key == "name" else key.capitalize())
                    per[u][name.lower()] = {"text": name, "type": t, "source": "jsonld", "score": 1.0}
        for e in set(proper_nouns(p["text"])):
            per[u].setdefault(e.lower(), {"text": e, "type": "PROPER", "source": "capitalization", "score": 0.5})
    for bg, v in colloc.items():
        for d in v["docs"]:
            per[pages[d]["url"]].setdefault(bg, {"text": bg, "type": "PHRASE", "source": "collocation", "score": v["npmi"]})
    for u, ents in per.items():
        for k in ents:
            df[k] += 1
            kind[k] = ents[k]["type"]
    for u in per:
        for k, e in per[u].items():
            e["df"] = df[k]
        per[u] = sorted(per[u].values(), key=lambda e: -(e["score"] * (1 + math.log(e["df"]))))[:per_page]
    corpus = sorted(((k, kind[k], c) for k, c in df.items()), key=lambda x: -x[2])
    return dict(per), corpus


# ---------------- knowledge graph ----------------
def build_knowledge_graph(page_entities, topics_of_page=None, min_cooc=2, max_entities=300):
    """Bipartite page-entity graph + entity co-occurrence (NPMI) edges + optional topic nodes."""
    df = Counter(e["text"].lower() for ents in page_entities.values() for e in ents)
    keep = {k for k, _ in df.most_common(max_entities)}
    G, n = nx.Graph(), max(1, len(page_entities))
    cooc = Counter()
    for u, ents in page_entities.items():
        names = sorted({e["text"].lower() for e in ents} & keep)
        G.add_node(f"p:{u}", kind="page", label=u)
        for e in names:
            G.add_node(f"e:{e}", kind="entity", label=e, df=df[e])
            G.add_edge(f"p:{u}", f"e:{e}", rel="mentions")
        cooc.update(combinations(names, 2))
        if topics_of_page and u in topics_of_page:
            G.add_node(f"t:{topics_of_page[u]}", kind="topic", label=topics_of_page[u])
            G.add_edge(f"p:{u}", f"t:{topics_of_page[u]}", rel="in_topic")
    for (a, b), c in cooc.items():
        if c >= min_cooc:
            pxy, pa, pb = c / n, df[a] / n, df[b] / n
            npmi = math.log(pxy / (pa * pb)) / -math.log(pxy) if pxy < 1 else 1.0
            if npmi > 0.2:
                G.add_edge(f"e:{a}", f"e:{b}", rel="cooccurs", weight=round(npmi, 3), count=c)
    return G


def graph_summary(G, top=15):
    if G.number_of_nodes() == 0:
        return {"nodes": 0, "edges": 0, "central_entities": []}
    pr = nx.pagerank(G, weight="weight")
    ents = sorted((n for n, d in G.nodes(data=True) if d["kind"] == "entity"), key=lambda n: -pr[n])[:top]
    return {"nodes": G.number_of_nodes(), "edges": G.number_of_edges(),
            "central_entities": [{"entity": G.nodes[n]["label"], "pagerank": round(pr[n], 5), "pages": G.nodes[n]["df"]} for n in ents]}
