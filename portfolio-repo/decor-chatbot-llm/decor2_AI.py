# -*- coding: utf-8 -*-

# --- مرحله 1: نصب کتابخانه‌ها ---
# pip install flet transformers torch

# --- مرحله 2: وارد کردن کتابخانه‌های لازم ---
import flet as ft
import threading
import time
import sys

try:
    from transformers import pipeline
except ImportError:
    print("خطا: کتابخانه transformers یافت نشد.")
    print("لطفا با دستور 'pip install transformers torch' آن را نصب کنید.")
    sys.exit(1)

# --- بارگذاری مدل زبانی فارسی ---
try:
    text_generator = pipeline("text-generation", model="HooshvareLab/gpt2-fa", tokenizer="HooshvareLab/gpt2-fa")
except Exception as model_err:
    print("خطا در بارگذاری مدل:", model_err)
    sys.exit(1)

# --- تابع بررسی مرتبط بودن سوال با دکوراسیون ---
def is_interior_design_question(question_fa):
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

# --- تابع اصلی برنامه Flet ---
def main(page: ft.Page):
    page.title = "🤖 دستیار دکوراسیون داخلی (LLM فارسی)"
    page.vertical_alignment = ft.MainAxisAlignment.START
    page.horizontal_alignment = ft.CrossAxisAlignment.CENTER
    page.theme = ft.Theme(font_family="Tahoma")
    page.theme_mode = ft.ThemeMode.LIGHT
    page.window_width = 750
    page.window_height = 650
    page.window_resizable = True

    chat_history = ft.ListView(expand=True, spacing=10, auto_scroll=True)

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
        try:
            page.update()
        except Exception as update_err:
            print(f"Error updating page in add_message_to_chat: {update_err}")

    def perform_llm_response_threaded(question):
        results_data = {"text": "", "type": "error"}
        try:
            update_ui_on_search_start()
            time.sleep(0.1)

            generated = text_generator(question, max_length=150, num_return_sequences=1)[0]['generated_text']
            results_text = "🤖 پاسخ مدل زبانی:\n" + ("-" * 20) + "\n" + generated
            results_data = {"text": results_text, "type": "ai"}

        except Exception as e:
            print(f"Model Error: {e}")
            results_data = {"text": f"❌ خطایی رخ داد: {e}", "type": "error"}
        finally:
            update_ui_on_search_end(results_data)

    def update_ui_on_search_start():
        input_textfield.disabled = True
        send_button.disabled = True
        add_message_to_chat("ai", "⏳ درحال پردازش سوال شما هستم...", "info")

    def update_ui_on_search_end(results_data):
        add_message_to_chat("ai", results_data["text"], results_data["type"])
        input_textfield.disabled = False
        send_button.disabled = False
        input_textfield.focus()
        page.update()

    def send_message_click(e):
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
            thread = threading.Thread(target=perform_llm_response_threaded, args=(user_question,), daemon=True)
            thread.start()
        else:
            add_message_to_chat("ai", "⚠️ فقط به سوالات مربوط به دکوراسیون داخلی پاسخ می‌دهم!", "error")
            input_textfield.focus()

        page.update()

    page.add(
        chat_history,
        ft.Row(
            controls=[input_textfield, send_button],
            alignment=ft.MainAxisAlignment.START,
        )
    )

    add_message_to_chat("ai", "سلام! 👋 سوالات خود را درباره دکوراسیون داخلی بپرسید...", "info")
    input_textfield.focus()
    page.update()

# --- اجرای برنامه ---
if __name__ == "__main__":
    ft.app(target=main)
