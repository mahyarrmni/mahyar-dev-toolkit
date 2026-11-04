"""Natural-language reporting: deterministic Persian report + optional local Ollama."""
import json
import urllib.request

from . import config


def build_facts(d, abc, low, dead, cu, today, currency=None) -> dict:
    cls = abc.groupby("abc").agg(skus=("sku", "count"), share=("share", "sum"))
    tv, dv = float(d.stock_value.sum()), float(dead.stock_value.sum())
    return {
        "date": today.strftime("%Y-%m-%d"), "currency": currency or config.CURRENCY,
        "skus": int(d.sku.nunique()), "units": int(d.quantity.sum()), "warehouses": int(d.warehouse.nunique()),
        "stock_value": round(tv), "abc": {k: {"skus": int(cls.skus.get(k, 0)),
                                              "annual_value_share_pct": round(float(cls.share.get(k, 0)) * 100, 1)} for k in "ABC"},
        "low_stock": {"rows": int(len(low)), "out": int((low.status == "out").sum()),
                      "critical": int((low.status == "critical").sum()),
                      "top": low.head(5)[["sku", "name", "warehouse", "quantity", "reorder_point", "suggested_order"]].to_dict("records")},
        "dead_stock": {"rows": int(len(dead)), "value": round(dv), "pct_of_stock_value": round(dv / tv * 100, 1) if tv else 0.0,
                       "top": dead.head(5)[["sku", "name", "warehouse", "quantity", "days_idle", "stock_value"]].to_dict("records")},
        "capacity": [{"warehouse": r.warehouse, "used_m3": round(float(r.used_m3), 1),
                      "utilization_pct": None if r.utilization != r.utilization else round(float(r.utilization) * 100, 1),
                      "status": r.status} for r in cu.itertuples()],
    }


def template_fa(f: dict) -> str:
    c, cur = f["low_stock"], f["currency"]
    dd = f["dead_stock"]
    L = [f"### گزارش وضعیت انبار — {f['date']}",
         f"- **موجودی کل:** {f['skus']:,} کالا، {f['units']:,} واحد، به ارزش {f['stock_value']:,} {cur} در {f['warehouses']} انبار.",
         "- **ABC:** " + "، ".join(f"کلاس {k}: {v['skus']} کالا ({v['annual_value_share_pct']}٪ ارزش مصرف)" for k, v in f["abc"].items()) + ".",
         f"- **کم‌موجودی:** {c['rows']} ردیف زیر نقطه سفارش؛ {c['out']} تمام‌شده و {c['critical']} بحرانی."]
    if c["top"]:
        L.append("  - فوری‌ترین: " + "، ".join(f"{t['name']} ({t['warehouse']}، موجودی {t['quantity']:,.0f})" for t in c["top"][:3]))
    L.append(f"- **کالای راکد:** {dd['rows']} ردیف با ارزش {dd['value']:,} {cur} ({dd['pct_of_stock_value']}٪ ارزش موجودی).")
    for r in f["capacity"]:
        u = "ظرفیت نامشخص" if r["utilization_pct"] is None else f"{r['utilization_pct']}٪ اشغال"
        L.append(f"- **{r['warehouse']}:** {u} ({r['used_m3']:,} m³).")
    tips = []
    if c["out"] + c["critical"]:
        tips.append("سفارش فوری برای کالاهای تمام‌شده و بحرانی (ستون «سفارش پیشنهادی»).")
    if dd["pct_of_stock_value"] > 10:
        tips.append("بخش قابل‌توجهی از سرمایه در کالای راکد بسته شده؛ تخفیف، انتقال یا تسویه را بررسی کنید.")
    if any(r["status"] in ("high", "over") for r in f["capacity"]):
        tips.append("برخی انبارها نزدیک به ظرفیت‌اند؛ انتقال کالای راکد یا کم‌گردش را در نظر بگیرید.")
    return "\n".join(L + (["", "**توصیه‌ها:**"] + [f"1. {t}" for t in tips] if tips else []))


def _generate(prompt: str, model: str) -> str:
    body = json.dumps({"model": model, "prompt": prompt, "stream": False, "options": {"temperature": 0.2}}).encode()
    req = urllib.request.Request(f"{config.OLLAMA_URL}/api/generate", body, {"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            return json.load(r)["response"].strip()
    except Exception as e:
        raise RuntimeError(f"اتصال به Ollama ممکن نشد ({e}). مطمئن شوید Ollama روشن و مدل دانلود شده است.")


def ai_report(f: dict, model: str = None) -> str:
    return _generate("شما تحلیلگر انبار هستید. فقط با استفاده از داده‌های زیر، یک گزارش مدیریتی کوتاه و کاربردی "
                     "به فارسی بنویسید. عدد یا واقعیت جدید نسازید.\n" + json.dumps(f, ensure_ascii=False, default=str),
                     model or config.OLLAMA_MODEL)


def ask(question: str, f: dict, model: str = None) -> str:
    return _generate("به فارسی و فقط بر اساس داده‌های زیر به پرسش پاسخ دهید. اگر پاسخ در داده‌ها نیست، بگویید "
                     "نمی‌دانید.\nداده‌ها: " + json.dumps(f, ensure_ascii=False, default=str) + f"\nپرسش: {question}",
                     model or config.OLLAMA_MODEL)
