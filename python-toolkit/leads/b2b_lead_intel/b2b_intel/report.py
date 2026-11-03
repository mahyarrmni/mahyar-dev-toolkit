"""Self-contained interactive HTML dashboard (no external assets)."""
from __future__ import annotations
import json
from collections import Counter
from datetime import datetime

from .models import Lead

TEMPLATE = r"""<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>B2B Lead Intelligence</title>
<style>
:root{--a:#6366f1;--b:#06b6d4;--t:#0f172a;--m:#64748b;--l:#e2e8f0;--bg:#f8fafc}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Tahoma,Vazirmatn,system-ui,sans-serif;color:var(--t);font-size:14px}
.w{max-width:1200px;margin:auto;padding:22px 14px}h1{margin:0;font-size:26px}h1 i{display:inline-block;width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--a),var(--b));vertical-align:middle;margin-inline-end:8px}
.m{color:var(--m);margin:6px 0 18px}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px}
.c{background:#fff;border:1px solid var(--l);border-radius:16px;padding:16px}.n{font-size:28px;font-weight:800}.k{color:var(--m);font-size:12px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}@media(max-width:800px){.row2{grid-template-columns:1fr}}
.bar{position:relative;display:flex;justify-content:space-between;padding:7px 10px;margin:5px 0;border-radius:8px;background:#f1f5f9;overflow:hidden;font-size:13px}
.bar span,.bar b{position:relative;z-index:1}.bar i{position:absolute;inset:0 auto 0 0;background:#c7d2fe;opacity:.75}
.tools{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}input{flex:1;min-width:220px;border:1px solid var(--l);border-radius:12px;padding:10px 14px;font:inherit}
.chip{border:1px solid var(--l);background:#fff;border-radius:99px;padding:8px 14px;cursor:pointer;font:inherit}.chip.on{background:linear-gradient(135deg,var(--a),var(--b));color:#fff;border-color:transparent}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--l);border-radius:14px;overflow:hidden}
th{background:#f1f5f9;color:var(--m);font-size:11px;text-align:start;padding:10px;cursor:pointer;user-select:none;white-space:nowrap}
td{padding:10px;border-top:1px solid var(--l);vertical-align:top}tr.r{cursor:pointer}tr.r:hover td{background:#f8fafc}
.gd{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:9px;color:#fff;font-weight:800}.A{background:#16a34a}.B{background:#0ea5e9}.C{background:#f59e0b}.D{background:#94a3b8}
.tag{display:inline-block;margin:2px;padding:2px 8px;border-radius:99px;font-size:11px;background:#eef2ff;color:#3730a3}
.s{font-weight:700}.sub{color:var(--m);font-size:12px;direction:ltr;text-align:right}.mono{direction:ltr;unicode-bidi:plaintext;font-family:ui-monospace,Consolas,monospace;font-size:12px}
tr.d td{background:#fafafa}.d ul{margin:4px 0;padding-inline-start:18px}.d h4{margin:8px 0 2px;font-size:12px;color:var(--m)}
a{color:var(--a);text-decoration:none}button.x{border:0;background:linear-gradient(135deg,var(--a),var(--b));color:#fff;border-radius:10px;padding:8px 14px;font:inherit;cursor:pointer}
</style><div class="w"><h1><i></i>B2B Lead Intelligence</h1>
<div class="m">__META__ • ساخته شده توسط مهیار مومنی</div>
<div class="g" id="kpi"></div>
<div class="row2"><div class="c"><b>توزیع گرید</b><div id="grades"></div></div><div class="c"><b>پرکاربردترین تکنولوژی‌ها</b><div id="techs"></div></div></div>
<div class="tools"><input id="q" placeholder="جستجو: نام، دامنه، ایمیل، تکنولوژی..."><button class="x" onclick="exportCsv()">⬇ خروجی CSV (نتایج فیلتر شده)</button></div>
<div class="tools" id="gf"></div>
<table><thead><tr><th onclick="srt('grade')">گرید<th onclick="srt('name')">شرکت<th onclick="srt('score')">امتیاز<th onclick="srt('emails')">ایمیل<th onclick="srt('phones')">تلفن<th>شبکه‌ها<th>تکنولوژی</tr></thead><tbody id="tb"></tbody></table></div>
<script>
const D=__DATA__;let G="",S="",sk="score",dir=-1,OPEN={};const $=i=>document.getElementById(i);
const esc=s=>String(s).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
const ok=D.filter(x=>x.status=="ok");
function kpi(){const e=ok.filter(x=>x.emails.length).length,p=ok.filter(x=>x.phones.length).length;
$("kpi").innerHTML=[[D.length,"کل دامنه‌ها"],[ok.length,"بررسی موفق"],[e,"دارای ایمیل"],[p,"دارای تلفن"],[ok.filter(x=>x.grade=="A").length,"سرنخ درجه A"]].map(([n,t])=>`<div class=c><div class=n>${n}</div><div class=k>${t}</div></div>`).join("");
const gc={A:0,B:0,C:0,D:0};ok.forEach(x=>gc[x.grade]++);const mg=Math.max(1,...Object.values(gc));
$("grades").innerHTML=Object.entries(gc).map(([g,n])=>`<div class=bar><span>گرید ${g}</span><b>${n}</b><i style="width:${n/mg*100}%"></i></div>`).join("");
const tc={};ok.forEach(x=>x.tech.forEach(t=>tc[t.name]=(tc[t.name]||0)+1));const te=Object.entries(tc).sort((a,b)=>b[1]-a[1]).slice(0,7),mt=te.length?te[0][1]:1;
$("techs").innerHTML=te.map(([k,n])=>`<div class=bar><span>${esc(k)}</span><b>${n}</b><i style="width:${n/mt*100}%"></i></div>`).join("")||"—";
$("gf").innerHTML="";["A","B","C","D"].forEach(g=>{const b=document.createElement("button");b.className="chip"+(G==g?" on":"");b.textContent="گرید "+g;b.onclick=()=>{G=G==g?"":g;draw();kpi2()};$("gf").appendChild(b)})}
function kpi2(){}
function val(x,k){return k=="emails"?x.emails.length:k=="phones"?x.phones.length:k=="name"?(x.name||x.domain):x[k]}
function rows(){const s=S.toLowerCase();return D.filter(x=>(!G||x.grade==G)&&(!s||JSON.stringify([x.name,x.domain,x.emails.map(e=>e.address),x.tech.map(t=>t.name),x.phones]).toLowerCase().includes(s)))
.sort((a,b)=>{const A=val(a,sk),B=val(b,sk);return (typeof A=="number"?A-B:String(A).localeCompare(String(B),"fa"))*dir})}
function srt(k){dir=sk==k?-dir:-1;sk=k;draw()}
const SOC={linkedin:"in",instagram:"ig",twitter:"x",facebook:"fb",youtube:"yt",telegram:"tg",whatsapp:"wa",github:"gh",aparat:"ap"};
function draw(){const r=rows().slice(0,1000);$("tb").innerHTML=r.map((x,i)=>{
const em=x.emails.slice(0,2).map(e=>`<div class=mono>${esc(e.address)}</div>`).join("")||"—";
const ph=x.phones.slice(0,2).map(p=>`<div class=mono>${esc(p)}</div>`).join("")||"—";
const so=Object.entries(x.socials).map(([k,u])=>`<a class=tag href="${esc(u)}" target=_blank onclick="event.stopPropagation()">${SOC[k]||k}</a>`).join("")||"—";
const te=x.tech.slice(0,4).map(t=>`<span class=tag>${esc(t.name)}</span>`).join("");
const st=x.status!="ok"?` <span class=tag style="background:#fee2e2;color:#991b1b">${x.status}</span>`:"";
let h=`<tr class=r onclick="OPEN['${esc(x.domain)}']=!OPEN['${esc(x.domain)}'];draw()"><td><span class="gd ${x.grade}">${x.grade}</span><td><div class=s>${esc(x.name||x.domain)}${st}</div><div class=sub>${esc(x.domain)}</div><td class=s>${x.score}<td>${em}<td>${ph}<td>${so}<td>${te}</tr>`;
if(OPEN[x.domain])h+=`<tr class=d><td colspan=7><div class=row2><div><h4>همه ایمیل‌ها</h4><ul>${x.emails.map(e=>`<li class=mono>${esc(e.address)} <small>(${e.kind}${e.same_domain?"، دامنه شرکت":""})</small></li>`).join("")||"<li>—</li>"}</ul>
<h4>همه تلفن‌ها</h4><ul>${x.phones.map(p=>`<li class=mono>${esc(p)}</li>`).join("")||"<li>—</li>"}</ul><h4>آدرس / توضیحات</h4><div>${esc(x.address||"—")}<br><small>${esc(x.description||"")}</small></div></div>
<div><h4>زاویه فروش پیشنهادی</h4><ul>${x.opportunities.map(o=>`<li>${esc(o)}</li>`).join("")||"<li>—</li>"}</ul><h4>دلایل امتیاز</h4><ul>${x.score_reasons.map(o=>`<li>${esc(o)}</li>`).join("")}</ul>
<h4>تکنولوژی‌ها</h4>${x.tech.map(t=>`<span class=tag>${esc(t.name)} · ${esc(t.category)}</span>`).join("")||"—"}</div></div></tr>`;return h}).join("")}
function exportCsv(){const q=v=>'"'+String(v??"").replace(/"/g,'""')+'"';const hd=["domain","name","grade","score","best_email","emails","phones","linkedin","instagram","tech"];
const L=rows().map(x=>[x.domain,x.name,x.grade,x.score,(x.emails[0]||{}).address||"",x.emails.map(e=>e.address).join(" | "),x.phones.join(" | "),x.socials.linkedin||"",x.socials.instagram||"",x.tech.map(t=>t.name).join(" | ")].map(q).join(","));
const b=new Blob(["﻿"+[hd.join(",")].concat(L).join("\n")],{type:"text/csv"});const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download="leads.csv";a.click()}
$("q").oninput=e=>{S=e.target.value;draw()};kpi();draw();
</script></html>"""


def build(leads: list[Lead], path: str) -> None:
    data = json.dumps([l.to_dict() for l in leads], ensure_ascii=False).replace("</", "<\\/")
    ok = [l for l in leads if l.status == "ok"]
    meta = f"{len(leads)} دامنه • {len(ok)} موفق • {datetime.now():%Y-%m-%d %H:%M}"
    with open(path, "w", encoding="utf-8") as f:
        f.write(TEMPLATE.replace("__DATA__", data).replace("__META__", meta))
