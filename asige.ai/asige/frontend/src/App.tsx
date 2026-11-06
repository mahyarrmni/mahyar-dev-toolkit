import { useCallback, useEffect, useState } from "react";
import { api, Crawl, getKey, Project, setKey } from "./api";
import { Links, Opportunities, Overview, Recommendations, Reports, Roadmap } from "./views";

const TABS = ["Setup", "Overview", "Recommendations", "Opportunities", "Links", "Roadmap", "Reports"] as const;

export default function App() {
  const [tab, setTab] = useState<(typeof TABS)[number]>("Setup");
  const [projects, setProjects] = useState<Project[]>([]);
  const [pid, setPid] = useState<number | null>(null);
  const [crawls, setCrawls] = useState<Crawl[]>([]);
  const [cid, setCid] = useState<number | null>(null);
  const [msg, setMsg] = useState("");
  const [name, setName] = useState(""), [url, setUrl] = useState("https://"), [maxPages, setMaxPages] = useState(200), [brand, setBrand] = useState("");
  const [analysisState, setAnalysisState] = useState("");

  const run = useCallback(async (f: () => Promise<unknown>) => { try { setMsg(""); await f(); } catch (e) { setMsg((e as Error).message); } }, []);
  const loadProjects = useCallback(() => run(async () => setProjects(await api.projects())), [run]);
  useEffect(() => { loadProjects(); }, [loadProjects]);
  useEffect(() => { if (pid) run(async () => { const c = await api.crawls(pid); setCrawls(c); if (cid === null && c.length) setCid(c[0].id); }); }, [pid, run]);
  useEffect(() => {  // poll while a crawl or analysis is running
    if (!cid) return;
    const t = setInterval(async () => {
      try {
        const c = await api.crawl(cid);
        setCrawls((cs) => cs.map((x) => (x.id === cid ? c : x)));
        setAnalysisState((await api.analysis<{ state: string }>(cid, "status").catch(() => ({ state: "" }))).state);
      } catch { /* transient */ }
    }, 2000);
    return () => clearInterval(t);
  }, [cid]);
  const crawl = crawls.find((c) => c.id === cid);

  return <>
    <header><h1>ASIGE · SEO Intelligence</h1><nav>{TABS.map((t) => <button key={t} className={t === tab ? "on" : ""} onClick={() => setTab(t)}>{t}</button>)}</nav></header>
    <main>
      {msg && <div className="card err">{msg}</div>}
      {tab === "Setup" && <>
        <div className="card"><b>API key</b> <input type="password" defaultValue={getKey()} placeholder="X-API-Key (if enabled)" onChange={(e) => setKey(e.target.value)} /></div>
        <div className="card"><div className="row"><b>Project</b>
          <select value={pid ?? ""} onChange={(e) => { setPid(+e.target.value || null); setCid(null); }}><option value="">— select —</option>{projects.map((p) => <option key={p.id} value={p.id}>{p.name} ({p.site_url})</option>)}</select>
          <input placeholder="name" value={name} onChange={(e) => setName(e.target.value)} /><input placeholder="https://example.com" value={url} onChange={(e) => setUrl(e.target.value)} size={28} />
          <button className="act" onClick={() => run(async () => { const p = await api.createProject(name || url, url); await loadProjects(); setPid(p.id); })}>Create</button></div></div>
        {pid && <div className="card"><div className="row"><b>1 · Crawl</b>
          <label>max pages <input type="number" value={maxPages} min={1} max={50000} onChange={(e) => setMaxPages(+e.target.value)} style={{ width: 80 }} /></label>
          <button className="act" onClick={() => run(async () => { const c = await api.startCrawl(pid, { max_pages: maxPages }); setCrawls(await api.crawls(pid)); setCid(c.id); })}>Start crawl</button>
          <select value={cid ?? ""} onChange={(e) => setCid(+e.target.value || null)}>{crawls.map((c) => <option key={c.id} value={c.id}>crawl #{c.id} — {c.status}</option>)}</select></div>
          {crawl && <div className="muted">status: {crawl.status} {crawl.error} {crawl.stats && `· pages ${crawl.stats.pages} · ${crawl.stats.pages_per_s}/s · budget exhausted: ${String(crawl.stats.budget_exhausted)}`}</div>}</div>}
        {pid && <div className="card"><div className="row"><b>2 · Search Console (optional)</b>
          <input type="file" accept=".csv,.xlsx" onChange={(e) => e.target.files?.[0] && run(async () => { const r = await api.uploadGsc(pid, e.target.files![0]); setMsg(`Imported ${r.rows} GSC rows`); })} /></div>
          <div className="muted">Export from Search Console → Performance → Export (query + page). Without it, query-based analyses are skipped (and reported as such).</div></div>}
        {cid && <div className="card"><div className="row"><b>3 · Analyze</b><input placeholder="brand terms (comma-separated)" value={brand} onChange={(e) => setBrand(e.target.value)} />
          <button className="act" disabled={crawl?.status !== "done"} onClick={() => run(async () => { await api.analyze(cid, { brand_terms: brand.split(",").map((s) => s.trim()).filter(Boolean) }); setAnalysisState("queued"); })}>Run analysis</button>
          <span className="muted">analysis: {analysisState || "not run"}</span></div></div>}
      </>}
      {tab !== "Setup" && !cid && <div className="card muted">Select a crawl in Setup first.</div>}
      {cid && tab === "Overview" && <Overview cid={cid} key={analysisState} />}
      {cid && tab === "Recommendations" && <Recommendations cid={cid} key={analysisState} />}
      {cid && tab === "Opportunities" && <Opportunities cid={cid} key={analysisState} />}
      {cid && tab === "Links" && <Links cid={cid} key={analysisState} />}
      {cid && tab === "Roadmap" && <Roadmap cid={cid} key={analysisState} />}
      {cid && tab === "Reports" && <Reports cid={cid} key={analysisState} />}
    </main></>;
}
