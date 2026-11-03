# -*- coding: utf-8 -*-

# --- مرحله 1: نصب کتابخانه‌ها ---
# pip install flet duckduckgo_search

# --- مرحله 2: وارد کردن کتابخانه‌های لازم ---
import flet as ft
import threading
import time
import sys

try:
    from duckduckgo_search import DDGS
except ImportError:
    print("خطا: کتابخانه 'duckduckgo_search' یافت نشد.")
    print("لطفا با دستور 'pip install duckduckgo_search' آن را نصب کنید.")
    sys.exit(1)

# --- مرحله 3: تابع بررسی مرتبط بودن سوال (بدون تغییر) ---
def is_interior_design_question(question_fa):
    # ... (کپی همان تابع از کدهای قبلی) ...
    keywords_fa = [
        "دکوراسیون", "چیدمان", "مبلمان", "رنگ", "نورپردازی",
        "طراحی داخلی", "دیزاین داخلی", "معماری داخلی", "فضا",
        "دیوار", "کفپوش", "پرده", "آشپزخانه", "اتاق خواب",
        "نشیمن", "سبک", "مدرن", "کلاسیک", "مینیمال", "بوهمی",
        "روستیک", "صنعتی", "کاغذ دیواری", "پارتیشن", "اکسسوری",
        "فرش", "کابینت", "میز", "صندلی", "کاناپه", "ترند",
        "فنگ شویی", "پالت رنگی", "هارمونی", "کنتراست"
    ]
    question_lower = question_fa.lower()
    for keyword in keywords_fa:
        if keyword in question_lower:
            return True
    if ("چطور" in question_lower and ("بچینم" in question_lower or "دیزاین کنم" in question_lower or "ست کنم" in question_lower)) or \
       ("بهترین" in question_lower and ("برای اتاق" in question_lower or "برای خانه" in question_lower or "برای آشپزخانه" in question_lower)):
        return True
    return False

# --- مرحله 4: تابع اصلی برنامه Flet ---
def main(page: ft.Page):

    # --- تنظیمات اولیه صفحه ---
    page.title = "🤖 دستیار دکوراسیون داخلی (Flet)"
    page.vertical_alignment = ft.MainAxisAlignment.START
    page.horizontal_alignment = ft.CrossAxisAlignment.CENTER
    # تنظیم فونت پیش‌فرض برای کل صفحه (بسیار مهم برای فارسی)
    page.fonts = {
         "Vazirmatn": "fonts/Vazirmatn-Regular.ttf" # اگر فونت وزیرمتن را دانلود و در پوشه fonts قرار دادید
    }
    # اگر فونت بالا را ندارید یا مسیرش فرق دارد، از فونت سیستمی استفاده کنید:
    page.theme = ft.Theme(font_family="Tahoma") # یا Vazirmatn یا هر فونت فارسی نصب شده دیگر

    page.theme_mode = ft.ThemeMode.LIGHT # تم روشن
    page.window_width = 750
    page.window_height = 650
    page.window_resizable = True

    # --- کنترل‌های رابط کاربری ---
    chat_history = ft.ListView(
        expand=True,
        spacing=10,
        auto_scroll=True,
        reverse=False
    )

    input_textfield = ft.TextField(
        hint_text="سوال خود را اینجا بپرسید...",
        expand=True,
        filled=True,
        border_radius=15,
        border_color=ft.colors.with_opacity(0.5, ft.colors.BLACK),
        rtl=True,
        text_align=ft.TextAlign.RIGHT,
        on_submit=lambda e: send_message_click(e),
        shift_enter=True,
        min_lines=1,
        max_lines=3,
    )

    send_button = ft.IconButton(
        icon=ft.icons.SEND_ROUNDED,
        tooltip="ارسال سوال",
        icon_color=ft.colors.PRIMARY,
        on_click=lambda e: send_message_click(e),
    )

    # --- توابع کمکی ---
    def add_message_to_chat(sender: str, message: str, msg_type: str):
        icon = ft.icons.PERSON_OUTLINE if sender == "user" else ft.icons.COMPUTER_OUTLINED
        icon_color = ft.colors.BLUE if sender == "user" else ft.colors.GREEN_800
        bg_color = ft.colors.BLUE_50 if sender == "user" else ft.colors.GREEN_50

        if msg_type == "error":
            icon = ft.icons.ERROR_OUTLINE
            icon_color = ft.colors.RED_600
            bg_color = ft.colors.RED_50
        elif msg_type == "info":
             icon = ft.icons.INFO_OUTLINE
             icon_color = ft.colors.GREY_600
             bg_color = ft.colors.GREY_200

        chat_message = ft.Container(
            content=ft.Row(
                [
                    ft.Icon(name=icon, color=icon_color),
                    ft.Text(
                        message,
                        selectable=True,
                        rtl=True,
                        text_align=ft.TextAlign.RIGHT,
                        expand=True,
                        width=page.window_width * 0.8 if page.window_width else 550
                    ),
                ],
                vertical_alignment=ft.CrossAxisAlignment.START,
                alignment=ft.MainAxisAlignment.START
            ),
            bgcolor=bg_color,
            border_radius=10,
            padding=10,
            margin=ft.margin.only(left=5, right=5, top=2, bottom=2)
        )
        chat_history.controls.append(chat_message)
        # اطمینان از آپدیت صفحه در اینجا (هرچند در توابع اصلی هم فراخوانی می‌شود)
        try:
            page.update()
        except Exception as update_err:
            # این ممکن است اگر از نخ دیگری بدون هماهنگی صدا زده شود رخ دهد، اما اینجا باید امن باشد
            print(f"Error updating page in add_message_to_chat: {update_err}")


    def perform_search_threaded(question):
        """جستجو در نخ جداگانه برای جلوگیری از قفل شدن UI"""
        results_data = {"text": "", "type": "error"} # پیش‌فرض خطا
        try:
            # *** تغییر کلیدی: صدا زدن مستقیم تابع آپدیت UI از این نخ ***
            update_ui_on_search_start()
            # --------------------------------------------------------

            # تاخیر کوچک برای نمایش بهتر وضعیت (اختیاری)
            time.sleep(0.1)

            with DDGS(timeout=15) as ddgs:
                query = f"{question} دکوراسیون داخلی نکات و ایده ها"
                results = ddgs.text(query, region='wt-wt', safesearch='moderate', max_results=3)

                if results:
                    results_text = "نتایج یافت شده:\n" + ("-"*20) + "\n"
                    for i, result in enumerate(results, 1):
                        title = result.get('title', 'بدون عنوان')
                        body = result.get('body', 'بدون توضیحات').replace('\n', ' ')
                        results_text += f"\n📌 {title}\n   {body}\n"
                    results_text += ("-"*20) + "\nℹ️ این خلاصه‌ای از نتایج برتر است."
                    results_data = {"text": results_text, "type": "ai"}
                else:
                    results_data = {"text": "⚠️ نتیجه مشخصی یافت نشد. سوال خود را واضح‌تر بپرسید.", "type": "error"}

        except Exception as search_error:
            print(f"Search Error: {search_error}") # لاگ خطا در کنسول
            results_data = {"text": f"❌ خطایی در هنگام جستجو رخ داد: {search_error}", "type": "error"}
        finally:
            # *** تغییر کلیدی: صدا زدن مستقیم تابع آپدیت UI با نتایج از این نخ ***
            update_ui_on_search_end(results_data)
            # ------------------------------------------------------------------

    def update_ui_on_search_start():
        """غیرفعال کردن ورودی‌ها هنگام شروع جستجو (در نخ اصلی اجرا نمی‌شود اما Flet مدیریت می‌کند)"""
        input_textfield.disabled = True
        send_button.disabled = True
        add_message_to_chat("ai", "⏳ سید درحال پردازش است!", "info")
        # page.update() در add_message_to_chat فراخوانی می‌شود

    def update_ui_on_search_end(results_data):
        """نمایش نتایج و فعال کردن ورودی‌ها پس از پایان جستجو (در نخ اصلی اجرا نمی‌شود اما Flet مدیریت می‌کند)"""
        add_message_to_chat("ai", results_data["text"], results_data["type"])
        input_textfield.disabled = False
        send_button.disabled = False
        input_textfield.focus() # فوکوس دوباره روی کادر ورودی
        page.update() # آپدیت نهایی صفحه

    def send_message_click(e):
        """پردازش کلیک دکمه ارسال یا زدن Enter"""
        user_question = input_textfield.value.strip()
        if not user_question:
            return

        add_message_to_chat("user", user_question, "user")
        input_textfield.value = ""

        if user_question.lower() == "خروج":
            add_message_to_chat("ai", "خدانگهدار!", "info")
            time.sleep(1)
            page.window_destroy()
            return

        if is_interior_design_question(user_question):
            thread = threading.Thread(target=perform_search_threaded, args=(user_question,), daemon=True)
            thread.start()
        else:
            add_message_to_chat("ai", "⚠️ فقط در زمینه دکوراسیون داخلی پاسخ میدهم!", "error")
            input_textfield.focus()

        page.update()

    # --- افزودن کنترل‌ها به صفحه ---
    page.add(
        chat_history,
        ft.Row(
            controls=[
                input_textfield,
                send_button,
            ],
            alignment=ft.MainAxisAlignment.START,
        )
    )

    add_message_to_chat("ai", "سلام! 👋 سوالات دکوراسیون داخلی خود را بپرسید...", "info")
    input_textfield.focus()
    page.update()

# --- مرحله 5: اجرای برنامه Flet ---
if __name__ == "__main__":
    # اگر فونت سفارشی دارید و در پوشه assets قرار دارد:
    # ft.app(target=main, assets_dir="assets")
    # در غیر این صورت:
    ft.app(target=main)