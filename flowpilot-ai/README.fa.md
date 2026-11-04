# FlowPilot AI (فلوپایلوت)

موتور سبک و کاملاً محلی برای **تصمیم‌گیری خودکار با هوش مصنوعی**. یک متن (ایمیل، پیام مشتری، تیکت و...) می‌گیرد، نوع و اولویت آن را تشخیص می‌دهد، یک تصمیم ساختاریافته (JSON) می‌سازد و بر اساس آن یک workflow در n8n را اجرا می‌کند.

بدون API پولی، بدون ارسال داده به بیرون، با کمترین وابستگی.

---

## این پروژه دقیقاً چه کاری می‌کند؟

```
متن ورودی → FastAPI (/analyze) → Ollama (مدل محلی) → اعتبارسنجی JSON
                                        ↓ (در صورت خطا)
                                  تشخیص کلمه‌کلیدی (جایگزین)
                                        ↓
                          ذخیره در SQLite  +  ارسال به webhook ‌n8n
```

1. متن را به `POST /analyze` می‌فرستید.
2. متن به مدل زبانی محلی (Ollama) داده می‌شود و مدل یک JSON برمی‌گرداند:
   - **intent**: نوع درخواست
   - **priority**: اولویت
   - **summary**: خلاصه کوتاه
   - **confidence**: میزان اطمینان (۰ تا ۱)
3. خروجی مدل بررسی می‌شود. اگر نامعتبر بود یا Ollama در دسترس نبود، تشخیص ساده بر پایه کلمات کلیدی جایگزین می‌شود، پس سرویس هیچ‌وقت از کار نمی‌افتد.
4. بر اساس intent یک **action** انتخاب می‌شود.
5. تصمیم در پایگاه‌داده SQLite ذخیره می‌شود.
6. تصمیم به webhook ‌n8n فرستاده می‌شود تا workflow اجرا شود.

### نوع درخواست‌ها و اقدام متناظر

| intent | action | توضیح |
|---|---|---|
| support | route_to_support | درخواست پشتیبانی |
| sales | notify_sales | استعلام قیمت، خرید، دمو |
| billing | route_to_finance | فاکتور، بازپرداخت، پرداخت |
| complaint | escalate_to_manager | شکایت |
| bug_report | create_ticket | گزارش باگ و خطا |
| feedback | log_feedback | پیشنهاد و بازخورد |
| spam | discard | اسپم |
| other | manual_review | بررسی دستی |

**اولویت‌ها:** `low` ، `medium` ، `high` ، `critical`

### مثال خروجی

```json
{
  "id": 1,
  "decision": {
    "intent": "complaint",
    "priority": "critical",
    "summary": "قطعی سرویس و نارضایتی مشتریان",
    "action": "escalate_to_manager",
    "confidence": 0.9,
    "source": "llm"
  },
  "workflow": {"status": "sent", "http": 200}
}
```

فیلد `source` مشخص می‌کند چه کسی تصمیم گرفته:
- `llm` یعنی مدل هوش مصنوعی
- `heuristic` یعنی تشخیص جایگزین با کلمات کلیدی (دقت کمتر)

---

## ساختار پروژه

```
flowpilot-ai/
├── app/
│   ├── main.py        # API (FastAPI)
│   ├── engine.py      # پرامپت، پردازش خروجی مدل، تشخیص جایگزین
│   ├── llm.py         # ارتباط با Ollama
│   ├── workflows.py   # ارسال به n8n
│   ├── db.py          # ذخیره‌سازی SQLite
│   ├── models.py      # مدل‌های داده
│   └── config.py      # تنظیمات (متغیر محیطی)
├── tests/             # تست‌ها
├── n8n/flowpilot_workflow.json   # workflow نمونه
├── requirements.txt
├── .env.example
└── README.md / README.fa.md
```

---

## راهنمای نصب و اجرا (مرحله به مرحله)

### پیش‌نیازها
- Python نسخه ۳.۹ یا بالاتر
- حدود ۴ تا ۸ گیگابایت رم آزاد برای مدل
- (اختیاری) Node.js برای اجرای n8n

### مرحله ۱: نصب Ollama و دانلود مدل

1. از سایت **ollama.com** برنامه را برای سیستم‌عامل خود نصب کنید.
2. مدل را دانلود کنید:

```bash
ollama pull llama3.2
```

3. مطمئن شوید Ollama روشن است:

```bash
curl http://localhost:11434
```

باید پیام `Ollama is running` را ببینید.

### مرحله ۲: آماده‌سازی پروژه

```bash
cd flowpilot-ai
python -m venv venv
```

فعال‌سازی محیط مجازی:

```bash
# لینوکس و مک
source venv/bin/activate

# ویندوز
venv\Scripts\activate
```

نصب وابستگی‌ها:

```bash
pip install -r requirements.txt
```

### مرحله ۳: تنظیمات (اختیاری)

مقادیر پیش‌فرض برای اجرای محلی کافی است. برای تغییر، متغیرهای محیطی را تنظیم کنید (نمونه در `.env.example`):

| متغیر | پیش‌فرض | توضیح |
|---|---|---|
| OLLAMA_URL | http://localhost:11434 | آدرس Ollama |
| OLLAMA_MODEL | llama3.2 | نام مدل |
| N8N_WEBHOOK_URL | http://localhost:5678/webhook/flowpilot | آدرس webhook ‌n8n |
| DB_PATH | flowpilot.db | مسیر فایل پایگاه‌داده |

مثال:

```bash
export OLLAMA_MODEL=llama3.2
```

### مرحله ۴: اجرای سرور

```bash
uvicorn app.main:app --reload
```

سرور روی `http://localhost:8000` بالا می‌آید. مستندات تعاملی API در `http://localhost:8000/docs` است.

### مرحله ۵: اولین تست

```bash
curl -X POST http://localhost:8000/analyze \
  -H "Content-Type: application/json" \
  -d '{"text": "سایت ما از دیشب پایین است و مشتری‌ها عصبانی‌اند", "trigger": false}'
```

`"trigger": false` یعنی فعلاً n8n را صدا نزن. اگر n8n را هنوز راه نینداخته‌اید، همین را بگذارید.

برای دیدن تاریخچه تصمیم‌ها:

```bash
curl http://localhost:8000/decisions
```

### مرحله ۶: راه‌اندازی n8n (اختیاری)

1. n8n را اجرا کنید:

```bash
npx n8n
```

2. در مرورگر `http://localhost:5678` را باز کنید.
3. از منو گزینه **Import from File** را بزنید و فایل `n8n/flowpilot_workflow.json` را انتخاب کنید.
4. workflow را **Active** کنید.
5. حالا درخواست را بدون `"trigger": false` بفرستید:

```bash
curl -X POST http://localhost:8000/analyze \
  -H "Content-Type: application/json" \
  -d '{"text": "لطفاً فاکتور اشتباه من را اصلاح کنید و مبلغ را برگردانید"}'
```

در پاسخ باید `"workflow": {"status": "sent"}` ببینید.

**نکته:** در حالت تست داخل ویرایشگر n8n، آدرس webhook با `/webhook-test/flowpilot` شروع می‌شود. آن را در `N8N_WEBHOOK_URL` بگذارید.

workflow نمونه فقط اولویت `high` و `critical` را به شاخه **Escalate** و بقیه را به **Queue** می‌فرستد. برای کار واقعی، به جای این دو گره، گره‌های ایمیل، Slack، تلگرام یا تیکت اضافه کنید.

### مرحله ۷: اجرای تست‌ها

```bash
pip install -r requirements-dev.txt
pytest
```

---

## مشکلات رایج

| مشکل | علت و راه‌حل |
|---|---|
| همیشه `source: heuristic` می‌آید | Ollama روشن نیست یا مدل دانلود نشده. `ollama list` را بزنید و آدرس `OLLAMA_URL` را بررسی کنید. |
| `workflow: failed` | n8n اجرا نیست، workflow فعال نشده، یا آدرس `N8N_WEBHOOK_URL` اشتباه است. |
| پاسخ کند است | مدل بزرگ است یا رم کم است. مدل کوچک‌تر مثل `llama3.2:1b` را امتحان کنید. |
| دقت پایین برای متن فارسی | مدل را عوض کنید (مثلاً `qwen2.5`) و در `OLLAMA_MODEL` بگذارید. |

---

## استفاده با وردپرس

این پروژه پایتون است و روی هاست اشتراکی PHP اجرا نمی‌شود. راه درست این است که FlowPilot و Ollama را روی یک VPS یا کامپیوتر جدا اجرا کنید و از وردپرس با `wp_remote_post` به `/analyze` درخواست بفرستید. حتماً سرور را با HTTPS و یک کلید احراز هویت محافظت کنید و `/analyze` را بدون محافظت روی اینترنت باز نگذارید.

---

## محدودیت‌ها

- احراز هویت (API key) در نسخه فعلی وجود ندارد و برای استفاده عمومی باید اضافه شود.
- کیفیت تشخیص به مدل انتخابی وابسته است.
- workflow نمونه فقط مسیریابی ساده دارد.

## مجوز

MIT
