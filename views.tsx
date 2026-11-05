const BASE = "/api";
export const getKey = () => localStorage.getItem("asige_key") || "";
export const setKey = (k: string) => localStorage.setItem("asige_key", k);

async function req<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = { ...(init.headers as Record<string, string>) };
  if (getKey()) headers["X-API-Key"] = getKey();
  if (init.body && !(init.body instanceof FormData)) headers["Content-Type"] = "application/json";
  const r = await fetch(BASE + path, { ...init, headers });
  if (!r.ok) throw new Error(`${r.status}: ${(await r.text()).slice(0, 300)}`);
  return (r.headers.get("content-type") || "").includes("json") ? r.json() : ((await r.text()) as unknown as T);
}

export interface Provenance { module: string; algorithm: string; source: string; metrics: Record<string, unknown>; evidence_urls: string[]; params: Record<string, unknown>; engine_version: string }
export interface Rec {
  id: string; type: string; title: string; category: string; description: string; actions: string[]; targets: string[]; priority: number;
  impact: { score: number; est_clicks_per_period: number | null; basis: string }; confidence: { score: number; reasons: string[] };
  effort: { hours: number }; dependencies: string[]; provenance: Provenance[];
}
export interface Issue { rule: string; severity: string; category: string; message: string; fix: string; count: number; urls: string[] }
export interface Crawl { id: number; status: string; error?: string; stats?: Record<string, number | boolean> }
export interface Project { id: number; name: string; site_url: string }

export const api = {
  projects: () => req<Project[]>("/projects"),
  createProject: (name: string, site_url: string) => req<Project>("/projects", { method: "POST", body: JSON.stringify({ name, site_url }) }),
  crawls: (pid: number) => req<Crawl[]>(`/projects/${pid}/crawls`),
  startCrawl: (pid: number, body: object) => req<{ id: number }>(`/projects/${pid}/crawls`, { method: "POST", body: JSON.stringify(body) }),
  crawl: (cid: number) => req<Crawl>(`/crawls/${cid}`),
  uploadGsc: (pid: number, f: File) => { const fd = new FormData(); fd.append("file", f); return req<{ rows: number }>(`/projects/${pid}/gsc/upload`, { method: "POST", body: fd }); },
  analyze: (cid: number, body: object) => req<unknown>(`/crawls/${cid}/analyze`, { method: "POST", body: JSON.stringify(body) }),
  analysis: <T,>(cid: number, kind: string) => req<T>(`/crawls/${cid}/analysis/${kind}`),
  report: (cid: number, which: string) => req<string>(`/crawls/${cid}/report/${which}`),
};
