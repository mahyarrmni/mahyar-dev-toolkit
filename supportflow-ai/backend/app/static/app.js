const $ = s => document.querySelector(s);
const h = (tag, props = {}, ...kids) => { const e = Object.assign(document.createElement(tag), props); e.append(...kids); return e; };
const FA = {
  priority: { low: "کم", medium: "متوسط", high: "زیاد", urgent: "فوری" },
  sentiment: { positive: "مثبت", neutral: "خنثی", negative: "منفی" },
  status: { auto_resolved: "پاسخ خودکار", escalated: "ارجاع‌شده", resolved: "حل‌شده" },
  category: { billing: "مالی", technical: "فنی", account: "حساب کاربری", shipping: "ارسال", product: "محصول",
    complaint: "شکایت", legal: "حقوقی", security: "امنیتی", other: "سایر" },
};
let tab = "queue";

const toast = m => { const t = $("#toast"); t.textContent = m; t.hidden = false; setTimeout(() => (t.hidden = true), 3500); };
const api = async (path, opts = {}) => {
  const r = await fetch("/api" + path, { ...opts, headers: { "X-API-Key": sessionStorage.getItem("sf_key") || "", "Content-Type": "application/json" } });
  if (!r.ok) throw new Error(r.status === 401 ? "کلید API نامعتبر است" : r.status === 429 ? "تعداد درخواست‌ها زیاد است" : `خطا (${r.status})`);
  return r.status === 204 ? null : r.json();
};
const run = async fn => { try { await fn(); } catch (e) { toast(e.message); } };

function ticketCard(t, withReply) {
  const card = h("article", { className: "card ticket" },
    h("header", {}, h("strong", { textContent: `#${t.id} ${t.subject}` }),
      h("span", { className: `badge p-${t.priority}`, textContent: FA.priority[t.priority] })),
    h("p", { className: "muted", textContent: [FA.category[t.category], FA.sentiment[t.sentiment], FA.status[t.status], t.customer].filter(Boolean).join(" · ") }),
    h("p", { textContent: t.body }));
  if (t.escalation_reasons.length) card.append(h("p", { className: "muted", textContent: "دلیل ارجاع: " + t.escalation_reasons.join("، ") }));
  if (t.ai_reply) card.append(h("details", {}, h("summary", { textContent: "پاسخ/پیش‌نویس هوش مصنوعی" }), h("p", { textContent: t.ai_reply })));
  if (t.human_reply) card.append(h("p", { className: "reply", textContent: "پاسخ کارشناس: " + t.human_reply }));
  if (withReply && t.status === "escalated") {
    const box = h("textarea", { rows: 3, placeholder: "پاسخ کارشناس...", value: t.ai_reply || "" });
    card.append(box, h("button", { textContent: "ارسال و بستن", onclick: () => run(async () => {
      await api(`/tickets/${t.id}/resolve`, { method: "POST", body: JSON.stringify({ reply: box.value }) });
      toast("تیکت بسته شد"); refresh();
    }) }));
  }
  return card;
}

async function loadStats() {
  const s = await api("/stats");
  const rate = s.total ? Math.round((100 * s.auto_resolved) / s.total) : 0;
  $("#stats").replaceChildren(...[["کل تیکت‌ها", s.total], ["حل خودکار", rate + "٪"], ["در صف ارجاع", s.escalated], ["احساس منفی", s.negative]]
    .map(([k, v]) => h("div", { className: "card stat" }, h("b", { textContent: String(v) }), h("span", { textContent: k }))));
}

async function renderKb(p) {
  const title = h("input", { placeholder: "عنوان", maxLength: 200 });
  const content = h("textarea", { rows: 6, placeholder: "متن سیاست‌ها، پرسش‌های متداول و پاسخ‌های آماده..." });
  const add = h("button", { textContent: "افزودن به پایگاه دانش", onclick: () => run(async () => {
    add.disabled = true;
    try { await api("/kb", { method: "POST", body: JSON.stringify({ title: title.value, content: content.value }) }); toast("اضافه شد"); render(); }
    finally { add.disabled = false; }
  }) });
  const docs = await api("/kb");
  p.append(h("div", { className: "card form" }, title, content, add),
    ...docs.map(d => h("div", { className: "card row" }, h("span", { textContent: `${d.title} (${d.chunks} بخش)` }),
      h("button", { textContent: "حذف", onclick: () => run(async () => { await api(`/kb/${d.id}`, { method: "DELETE" }); render(); }) }))));
}

function renderTest(p) {
  const subject = h("input", { placeholder: "موضوع", maxLength: 300 });
  const body = h("textarea", { rows: 5, placeholder: "متن پیام مشتری..." });
  const out = h("div");
  const send = h("button", { textContent: "ارسال", onclick: () => run(async () => {
    send.disabled = true;
    try {
      const t = await api("/tickets", { method: "POST", body: JSON.stringify({ subject: subject.value, body: body.value }) });
      out.replaceChildren(ticketCard(t, false), h("p", { className: "reply", textContent: "پاسخ ارسالی به مشتری: " + t.customer_reply }));
      loadStats();
    } finally { send.disabled = false; }
  }) });
  p.append(h("div", { className: "card form" }, subject, body, send), out);
}

async function render() {
  const p = $("#panel"); p.replaceChildren();
  await run(async () => {
    if (tab === "queue" || tab === "all") {
      const list = await api(tab === "queue" ? "/tickets?status=escalated&limit=100" : "/tickets?limit=100");
      p.append(...(list.length ? list.map(t => ticketCard(t, tab === "queue")) : [h("p", { className: "muted", textContent: "موردی وجود ندارد." })]));
    } else if (tab === "kb") await renderKb(p);
    else renderTest(p);
  });
}

async function refresh() { try { await loadStats(); } catch (e) { toast(e.message); return; } render(); }

$("#tabs").onclick = e => {
  const b = e.target.closest("button"); if (!b) return;
  tab = b.dataset.tab;
  document.querySelectorAll("#tabs button").forEach(x => x.classList.toggle("on", x === b));
  render();
};
$("#auth").onsubmit = e => { e.preventDefault(); sessionStorage.setItem("sf_key", $("#key").value); $("#key").value = ""; refresh(); };
if (sessionStorage.getItem("sf_key")) refresh();
