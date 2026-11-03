# -*- coding: utf-8 -*-
import base64
import os

import flet as ft
import requests

# کلید Replicate از متغیر محیطی خوانده می‌شود (هرگز کلید را در کد کامیت نکنید):
#   export REPLICATE_API_TOKEN=...
REPLICATE_API_TOKEN = os.environ.get("REPLICATE_API_TOKEN", "")

def main(page: ft.Page):
    page.title = "دکوراسیون با هوش مصنوعی - نسخه واقعی"
    page.theme = ft.Theme(font_family="Tahoma")
    page.window_width = 800
    page.window_height = 700
    page.scroll = ft.ScrollMode.AUTO

    uploaded_image = ft.Image(width=400, height=300, fit=ft.ImageFit.CONTAIN)
    ai_image = ft.Image(width=400, height=300, fit=ft.ImageFit.CONTAIN)
    status_text = ft.Text("📥 لطفاً تصویری از فضای خانه آپلود کنید")

    def on_file_picked(e: ft.FilePickerResultEvent):
        if not e.files:
            return
        file_path = e.files[0].path
        uploaded_image.src = file_path
        status_text.value = "✅ تصویر انتخاب شد. در حال ارسال به مدل..."
        page.update()
        process_image_with_ai(file_path)

    def process_image_with_ai(image_path):
        try:
            with open(image_path, "rb") as img_file:
                img_data = base64.b64encode(img_file.read()).decode("utf-8")

            headers = {
                "Authorization": f"Token {REPLICATE_API_TOKEN}",
                "Content-Type": "application/json",
            }

            json_data = {
                "version": "fdf78fcf49b9e4a4e8e8c4f386be123c0d89b6767fc3403bafcd7b35a78b70b3",
                "input": {
                    "image": f"data:image/jpeg;base64,{img_data}",
                    "prompt": "modern interior design, cozy and stylish",
                }
            }

            status_text.value = "🤖 در حال پردازش تصویر با مدل هوش مصنوعی..."
            page.update()

            response = requests.post(
                "https://api.replicate.com/v1/predictions",
                headers=headers,
                json=json_data
            )
            response.raise_for_status()
            prediction = response.json()

            # دریافت URL تصویر خروجی
            get_result(prediction["urls"]["get"])

        except Exception as err:
            status_text.value = f"❌ خطا: {err}"
            page.update()

    def get_result(url):
        import time
        while True:
            r = requests.get(url, headers={"Authorization": f"Token {REPLICATE_API_TOKEN}"})
            output = r.json()
            status = output["status"]

            if status == "succeeded":
                img_url = output["output"]
                ai_image.src = img_url
                status_text.value = "🎉 تصویر بازطراحی‌شده آماده است!"
                page.update()
                break
            elif status == "failed":
                status_text.value = "❌ پردازش تصویر شکست خورد."
                page.update()
                break

            status_text.value = "⏳ در حال پردازش..."
            page.update()
            time.sleep(2)

    file_picker = ft.FilePicker(on_result=on_file_picked)
    page.overlay.append(file_picker)

    page.add(
        ft.Column(
            [
                status_text,
                ft.ElevatedButton("📂 انتخاب تصویر", on_click=lambda _: file_picker.pick_files(file_types=["image/*"])),
                ft.Row([uploaded_image, ai_image], alignment=ft.MainAxisAlignment.CENTER),
            ],
            expand=True,
            spacing=20
        )
    )

if __name__ == "__main__":
    ft.app(target=main)
