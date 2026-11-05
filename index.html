import { useEffect, useState } from "react";
import Plot from "./plot";
import { api, Issue, Rec } from "./api";

type Any = any; // analysis payloads are large JSON documents; typed at the edges that matter (Rec, Issue)
const short = (u: string) => { try { return decodeURIComponent(new URL(u).pathname) || "/"; } catch { return u; } };

export function useAnalysis<T>(cid: number | null, kind: string) {
  const [data, setData] = useState<T | null>(null);
  const [err, setErr] = useState("");
  useEffect(() => {
    setData(null); setErr("");
    if (cid) api.analysis<T>(cid, kind).then(setData).catch((e) => setErr(String(e.message)));
  }, [cid, kind]);
  return { data, err };
}

const Empty = ({ err }: { err: string }) => <div className="card muted">{err ? "Not available yet — run the analysis first. " + err.slice(0, 120) : "Loading…"}</div>;

export function Overview({ cid }: { cid: number }) {
  const { data, err } = useAnalysis<Any>(cid, "technical");
  if (!data) return <Empty err={err} />;
  const sev = data.summary.by_severity as Record<string, number>;
  return <>
    <div className="row">
      <div className="card"><div className="muted">Technical health</div><div className="kpi">{data.health_score}</div></div>
      <div className="card"><div className="muted">Indexable / crawled</div><div className="kpi">{data.summary.indexable} / {data.summary.pages_crawled}</div></div>
      <div className="card" style={{ flex: 1 }}>
        <Plot data={[{ type: "bar", x: Object.keys(sev), y: Object.values(sev), marker: { color: ["#b00020", "#e4572e", "#f3a712", "#4c9f70", "#7d8597"] } }]}
              layout={{ height: 180, margin: { t: 10, b: 30, l: 40, r: 10 }, title: { text: "Affected URLs by severity", font: { size: 12 } } }} config={{ displayModeBar: false }} style={{ width: "100%" }} />
      </div>
    </div>
    <div className="card"><table><thead><tr><th>Severity</th><th>Issue</th><th>URLs</th><th>Examples</th></tr></thead><tbody>
      {(data.issues as Issue[]).map((i) => <tr key={i.rule}><td><span className={`pill ${i.severity}`}>{i.severity}</span></td><td title={i.fix}>{i.message}</td><td>{i.count}</td>
        <td dir="auto">{i.urls.slice(0, 2).map(short).join(", ")}</td></tr>)}
    </tbody></table></div>
  </>;
}

export function Recommendations({ cid }: { cid: number }) {
  const { data, err } = useAnalysis<Rec[]>(cid, "recommendations");
  const [open, setOpen] = useState<string | null>(null);
  if (!data) return <Empty err={err} />;
  return <div className="card"><table><thead><tr><th>#</th><th>Recommendation</th><th>Impact</th><th>Conf.</th><th>Hours</th><th>Priority</th><th>Est. clicks</th><th>Deps</th></tr></thead><tbody>
    {data.slice(0, 80).map((r, n) => <>
      <tr key={r.id} className="click" onClick={() => setOpen(open === r.id ? null : r.id)}>
        <td>{n + 1}</td><td dir="auto">{r.title}</td><td>{r.impact.score} <span className="muted">({r.impact.basis})</span></td><td>{r.confidence.score}</td>
        <td>{r.effort.hours}</td><td><b>{r.priority}</b></td><td>{r.impact.est_clicks_per_period ?? "n/a"}</td><td>{r.dependencies.length}</td></tr>
      {open === r.id && <tr key={r.id + "d"}><td colSpan={8}>
        <p>{r.description}</p><ol>{r.actions.map((a, i) => <li key={i} dir="auto">{a}</li>)}</ol>
        <div className="muted">Confidence reasons: {r.confidence.reasons.join(" · ")}</div>
        <div className="muted">Depends on: {r.dependencies.join(", ") || "—"}</div>
        <b>Provenance</b><pre>{JSON.stringify(r.provenance, null, 2)}</pre></td></tr>}
    </>)}
  </tbody></table></div>;
}

export function Opportunities({ cid }: { cid: number }) {
  const { data, err } = useAnalysis<Any>(cid, "predictive");
  if (!data) return <Empty err={err || "No Search Console data was imported for this project."} />;
  const sd = data.opportunities.striking_distance as Any[];
  const curve = Object.entries(data.curve as Record<string, number>);
  return <>
    <div className="card"><b>Striking-distance queries</b> <span className="muted">(CTR curve source: {data.curve_source})</span>
      <Plot data={[{ type: "scatter", mode: "markers", x: sd.map((r) => r.position), y: sd.map((r) => r.impressions), text: sd.map((r) => `${r.query}<br>+${r.gain} clicks`),
        marker: { size: sd.map((r) => Math.max(6, Math.min(40, 6 + r.gain))), color: sd.map((r) => r.gain), colorscale: "Viridis" }, hoverinfo: "text" }]}
        layout={{ height: 320, xaxis: { title: { text: "Average position" } }, yaxis: { title: { text: "Impressions" }, type: "log" }, margin: { t: 10 } }} style={{ width: "100%" }} />
    </div>
    <div className="row"><div className="card" style={{ flex: 1 }}>
      <Plot data={[{ type: "scatter", mode: "lines+markers", x: curve.map(([p]) => +p), y: curve.map(([, c]) => c) }]}
        layout={{ height: 260, title: { text: `CTR by position (${data.curve_source})`, font: { size: 12 } }, yaxis: { tickformat: ".0%" }, margin: { t: 30 } }} style={{ width: "100%" }} /></div>
    <div className="card" style={{ flex: 1 }}><b>Scenarios</b><table><thead><tr><th>Scenario</th><th>Extra clicks</th><th>90% interval</th></tr></thead><tbody>
      {(data.scenarios as Any[]).map((s) => <tr key={s.name}><td>{s.name}</td><td>{s.projected_extra_clicks}</td><td>{s.interval_90 ? s.interval_90.join(" … ") : "n/a (generic prior)"}</td></tr>)}
    </tbody></table>
    {data.cross_validation && <p className="muted">Cross-validated WAPE: {JSON.stringify(data.cross_validation.wape)}</p>}</div></div>
  </>;
}

export function Links({ cid }: { cid: number }) {
  const { data, err } = useAnalysis<Any>(cid, "linking");
  if (!data) return <Empty err={err} />;
  const g = data.graph, pos: Record<string, [number, number]> = {};
  g.nodes.forEach((n: Any) => (pos[n.url] = [n.x, n.y]));
  const ex: (number | null)[] = [], ey: (number | null)[] = [];
  g.edges.forEach(([a, b]: [string, string]) => { if (pos[a] && pos[b]) { ex.push(pos[a][0], pos[b][0], null); ey.push(pos[a][1], pos[b][1], null); } });
  return <>
    <div className="card"><Plot data={[{ type: "scatter", mode: "lines", x: ex, y: ey, line: { width: 0.4, color: "#aab" }, hoverinfo: "none" },
      { type: "scatter", mode: "markers", x: g.nodes.map((n: Any) => n.x), y: g.nodes.map((n: Any) => n.y), text: g.nodes.map((n: Any) => short(n.url)),
        marker: { size: g.nodes.map((n: Any) => 6 + n.pagerank * 600), color: g.nodes.map((n: Any) => n.pagerank), colorscale: "YlOrRd" }, hoverinfo: "text" }]}
      layout={{ height: 480, showlegend: false, xaxis: { visible: false }, yaxis: { visible: false }, margin: { t: 10, b: 10, l: 10, r: 10 } }} style={{ width: "100%" }} />
      <div className="muted">{data.stats.pages} pages · {data.stats.edges} links · {data.stats.orphans} orphans · max depth {data.stats.max_depth}
        {data.simulation && ` · simulation: mean target PageRank ${data.simulation.mean_target_pagerank_change_pct}%, orphans ${data.simulation.orphans_before}→${data.simulation.orphans_after}`}</div></div>
    <div className="card"><b>Suggested internal links</b><table><thead><tr><th>From</th><th>To</th><th>Anchor</th><th>Similarity</th><th>Placement</th></tr></thead><tbody>
      {(data.suggestions as Any[]).slice(0, 30).map((s, i) => <tr key={i}><td dir="auto">{short(s.source)}</td><td dir="auto">{short(s.target)}</td><td dir="auto">{s.evidence.anchor}</td>
        <td>{s.evidence.semantic_similarity}</td><td dir="auto">{s.evidence.has_existing_mention ? "…" + s.evidence.placement + "…" : "add a sentence"}</td></tr>)}
    </tbody></table></div></>;
}

export function Roadmap({ cid }: { cid: number }) {
  const { data, err } = useAnalysis<Any>(cid, "roadmap");
  if (!data) return <Empty err={err} />;
  const tasks = Object.values(data.tasks).filter((t: Any) => t.finish_week) as Any[];
  const start: Record<string, number> = {};
  data.schedule.forEach((w: Any) => w.tasks.forEach((t: Any) => { if (!(t.id in start)) start[t.id] = w.week; }));
  const shown = tasks.sort((a, b) => start[a.id] - start[b.id] || b.priority - a.priority).slice(0, 25);
  return <>
    <div className="card"><pre style={{ maxHeight: 160 }}>{data.narrative}</pre>
      <Plot data={[{ type: "bar", orientation: "h", y: shown.map((t) => t.title.slice(0, 48)), base: shown.map((t) => start[t.id] - 1), x: shown.map((t) => t.finish_week - start[t.id] + 1),
        marker: { color: shown.map((t) => ({ "1-stabilize": "#b00020", "2-quick-wins": "#f3a712", "3-growth": "#4c9f70" } as Record<string, string>)[t.phase]) }, text: shown.map((t) => t.phase), hoverinfo: "text+y" }]}
        layout={{ height: 40 + shown.length * 22, xaxis: { title: { text: "Week" } }, yaxis: { autorange: "reversed" }, margin: { l: 280, t: 10 } }} style={{ width: "100%" }} />
      <div className="muted">{data.totals.est_clicks_note}</div></div>
    <div className="card"><table><thead><tr><th>Task</th><th>Phase</th><th>Hours</th><th>Done in week</th><th>Verify</th></tr></thead><tbody>
      {shown.map((t) => <tr key={t.id}><td dir="auto">{t.title}</td><td>{t.phase}</td><td>{t.effort_hours}</td><td>{t.finish_week}</td><td>{t.verify}</td></tr>)}</tbody></table></div></>;
}

export function Reports({ cid }: { cid: number }) {
  const [which, setWhich] = useState("strategic"), [txt, setTxt] = useState(""), [err, setErr] = useState("");
  useEffect(() => { setTxt(""); setErr(""); api.report(cid, which).then(setTxt).catch((e) => setErr(String(e.message))); }, [cid, which]);
  const dl = () => { const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([txt], { type: "text/markdown" })); a.download = `asige-${which}.md`; a.click(); };
  return <div className="card"><div className="row"><select value={which} onChange={(e) => setWhich(e.target.value)}><option>strategic</option><option>technical</option></select>
    <button className="act" onClick={dl} disabled={!txt}>Download .md</button></div>{err ? <p className="err">{err}</p> : <pre style={{ maxHeight: 600 }} dir="auto">{txt}</pre>}</div>;
}
