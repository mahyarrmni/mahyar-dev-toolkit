#!/usr/bin/env sh
# Loads a small generic knowledge base so the demo can answer questions.
set -eu
KEY=$(grep '^API_KEY=' .env | cut -d= -f2-)
URL=${API_URL:-http://127.0.0.1:8000}
post() { curl -fsS -X POST "$URL/api/kb" -H "X-API-Key: $KEY" -H 'Content-Type: application/json' -d "$1"; echo; }
post '{"title":"سیاست بازگشت وجه","content":"مشتری تا ۷ روز پس از خرید می‌تواند درخواست بازگشت وجه ثبت کند، به شرط آن‌که از محصول یا خدمت استفاده نشده باشد. وجه ظرف ۵ تا ۷ روز کاری به همان روش پرداخت برگردانده می‌شود."}'
post '{"title":"بازیابی رمز عبور","content":"برای بازیابی رمز عبور روی گزینه «فراموشی رمز عبور» در صفحه ورود بزنید و ایمیل یا شماره موبایل خود را وارد کنید. لینک بازیابی تا ۳۰ دقیقه معتبر است. اگر پیام دریافت نشد، پوشه هرزنامه را بررسی کنید."}'
post '{"title":"Support hours","content":"Support is available Saturday to Wednesday, 9:00-17:00. Tickets sent outside these hours are answered the next working day. Urgent issues are escalated to a human agent."}'
