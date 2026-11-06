"""Competitor intelligence from authorized inputs: competitor crawl (robots-respecting) and user-supplied SERP exports."""
from collections import defaultdict

import numpy as np
import pandas as pd

from .semantic import SemanticIndex, cluster_vectors, medoid, page_text
from .urls import host_key


def topic_gaps(own_pages, comp_pages, threshold=0.35, backend=None, cluster_threshold=0.7):
    """Embed both sites in ONE space; competitor pages whose best match among own pages is < threshold are uncovered topics."""
    if not own_pages or not comp_pages:
        return {"coverage": None, "gaps": []}
    texts = [page_text(p) for p in own_pages + comp_pages]
    idx = SemanticIndex(backend=backend).fit(texts)
    A, B = idx.E[: len(own_pages)], idx.E[len(own_pages):]
    S = B @ A.T
    best, arg = S.max(axis=1), S.argmax(axis=1)
    unc = np.nonzero(best < threshold)[0]
    gaps = []
    if len(unc):
        lab = cluster_vectors(B[unc], cluster_threshold)
        for c in sorted(set(lab)):
            mem = unc[lab == c]
            m = int(medoid(B, mem.tolist()))
            terms = idx.centroid_terms([len(own_pages) + int(i) for i in mem], 5)
            gaps.append({"label": comp_pages[m]["title"] or terms[0], "terms": terms, "competitor_pages": [comp_pages[int(i)]["url"] for i in mem][:10],
                         "n_pages": int(len(mem)), "max_similarity_to_own": round(float(best[mem].max()), 3)})
        gaps.sort(key=lambda g: -g["n_pages"])
    return {"coverage": round(float((best >= threshold).mean()), 3), "threshold": threshold, "competitor_pages": len(comp_pages), "gaps": gaps}


def load_serp(data, filename="serp.csv") -> pd.DataFrame:
    """User-supplied SERP export (e.g., from a tool you are licensed to use): keyword, position, url[, volume]."""
    import io
    df = data if isinstance(data, pd.DataFrame) else pd.read_csv(io.StringIO(data.decode("utf-8-sig") if isinstance(data, bytes) else data))
    df.columns = [c.strip().lower() for c in df.columns]
    need = {"keyword", "position", "url"}
    if not need <= set(df.columns):
        raise ValueError(f"SERP file needs columns {sorted(need)} (optional: volume)")
    df = df.assign(position=pd.to_numeric(df.position, errors="coerce"), volume=pd.to_numeric(df.get("volume", 1), errors="coerce").fillna(1))
    df["domain"] = df.url.map(host_key)
    return df.dropna(subset=["position"])


def share_of_voice(serp, ctr_fn, own_domain):
    """SoV(domain) = sum(volume * CTR(position)) / total over all domains; plus keyword gap (competitor top-10, own not top-20)."""
    s = serp.assign(w=serp.volume * ctr_fn(serp.position.values))
    tot = s.w.sum()
    sov = s.groupby("domain").w.sum().sort_values(ascending=False)
    own = host_key(own_domain if "//" in own_domain else f"https://{own_domain}")
    gap = []
    for kw, g in serp.groupby("keyword"):
        mine = g[g.domain == own]
        if (mine.empty or mine.position.min() > 20) and (g[(g.domain != own) & (g.position <= 10)].shape[0] > 0):
            top = g[g.domain != own].sort_values("position").head(3)
            gap.append({"keyword": kw, "volume": float(g.volume.iloc[0]), "competitors": [{"domain": r.domain, "position": float(r.position), "url": r.url} for r in top.itertuples()]})
    return {"share_of_voice": {d: round(float(v / tot), 4) for d, v in sov.head(15).items()}, "own_domain": own,
            "own_share": round(float(sov.get(own, 0) / tot), 4) if tot else 0.0, "keyword_gap": sorted(gap, key=lambda g: -g["volume"])[:50]}
