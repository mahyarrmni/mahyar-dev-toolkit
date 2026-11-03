import os
import shutil
import tempfile
import zipfile
from urllib.parse import urljoin

import requests
import streamlit as st
from bs4 import BeautifulSoup
from PIL import Image, ImageDraw, ImageEnhance, ImageFont

st.set_page_config(page_title="Image Optimizer", layout="wide", page_icon="🖼️")
st.markdown("""
    <div style='text-align: center;'>
        <h1 style='color: #0099ff;'>🖼️ ابزار حرفه‌ای بهینه‌سازی تصاویر</h1>
        <h4>تبدیل فرمت، تغییر اندازه، فشرده‌سازی، بهبود کیفیت و افزودن واترمارک</h4>
    </div>
""", unsafe_allow_html=True)

SUPPORTED_FORMATS = ["WEBP", "AVIF", "JPEG", "PNG", "JPG"]

@st.cache_data
def fetch_images_from_url(url):
    try:
        res = requests.get(url, timeout=10)
        soup = BeautifulSoup(res.content, "html.parser")
        imgs = soup.find_all("img")
        image_files = []
        for img in imgs:
            src = img.get("src")
            if src:
                full_url = urljoin(url, src)
                r = requests.get(full_url, stream=True, timeout=10)
                if r.status_code == 200:
                    suffix = os.path.splitext(src)[-1] or ".jpg"
                    tmp_file = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
                    with open(tmp_file.name, 'wb') as f:
                        shutil.copyfileobj(r.raw, f)
                    image_files.append(tmp_file.name)
        return image_files
    except Exception as e:
        st.error(f"خطا در دریافت تصاویر: {e}")
        return []

def add_watermark(image, text=None, logo_path=None, full=False):
    watermark_layer = Image.new("RGBA", image.size, (255, 255, 255, 0))
    draw = ImageDraw.Draw(watermark_layer)

    if logo_path:
        logo = Image.open(logo_path).convert("RGBA")
        ratio = image.width // 5 / logo.width
        logo = logo.resize((int(logo.width * ratio), int(logo.height * ratio)))
        pos = (image.width - logo.width - 10, image.height - logo.height - 10)
        watermark_layer.paste(logo, pos, logo)
    elif text:
        font_size = int(min(image.size) * 0.05)
        try:
            font = ImageFont.truetype("arial.ttf", font_size)
        except:
            font = ImageFont.load_default()
        bbox = draw.textbbox((0, 0), text, font=font)
        textwidth = bbox[2] - bbox[0]
        textheight = bbox[3] - bbox[1]
        if full:
            for y in range(0, image.height, textheight * 4):
                for x in range(0, image.width, textwidth * 4):
                    draw.text((x, y), text, font=font, fill=(255, 255, 255, 50))
        else:
            x = image.width - textwidth - 10
            y = image.height - textheight - 10
            draw.text((x, y), text, font=font, fill=(255, 255, 255, 128))

    return Image.alpha_composite(image.convert("RGBA"), watermark_layer)

def convert_and_optimize_image(file_path, format, resize_width, resize_height, quality, enhance, remove_bg, enhance_color, watermark, wm_text, wm_logo, wm_full):
    try:
        img = Image.open(file_path)

        if enhance_color:
            enhancer = ImageEnhance.Color(img)
            img = enhancer.enhance(1.3)

        if resize_width and resize_height:
            img = img.resize((resize_width, resize_height))

        if enhance:
            enhancer = ImageEnhance.Sharpness(img)
            img = enhancer.enhance(1.5)

        if watermark:
            img = add_watermark(img, text=wm_text, logo_path=wm_logo, full=wm_full)

        output_path = file_path + f"_optimized.{format.lower()}"
        img = img.convert("RGB")
        img.save(output_path, format=format, quality=quality)
        return output_path
    except Exception as e:
        st.error(f"خطا در پردازش تصویر: {e}")
        return None

with st.expander("🌐 دریافت تصاویر از یک سایت"):
    url_input = st.text_input("آدرس سایت را وارد کنید:")
    if st.button("📥 دریافت تصاویر") and url_input:
        image_paths = fetch_images_from_url(url_input)
        if image_paths:
            st.success(f"{len(image_paths)} تصویر دریافت شد و آماده دانلود است.")
            if 'url_images' not in st.session_state:
                st.session_state['url_images'] = []
            st.session_state['url_images'].extend(image_paths)

            zip_path = tempfile.NamedTemporaryFile(delete=False, suffix=".zip").name
            with zipfile.ZipFile(zip_path, 'w') as zipf:
                for img_path in image_paths:
                    zipf.write(img_path, os.path.basename(img_path))
            st.download_button("📦 دانلود یکجا تصاویر", open(zip_path, 'rb'), file_name="downloaded_images.zip")

uploaded_files = st.file_uploader("آپلود حداکثر 20 تصویر", accept_multiple_files=True, type=["jpg", "jpeg", "png", "bmp", "tiff", "webp", "jfif", "avif"], key="upload")

all_files = uploaded_files if uploaded_files else []
if 'url_images' in st.session_state:
    for path in st.session_state['url_images']:
        all_files.append(open(path, 'rb'))

if all_files:
    st.markdown("---")
    st.subheader("⚙️ تنظیمات بهینه‌سازی")

    format_img = st.selectbox("فرمت خروجی:", SUPPORTED_FORMATS)
    custom_size = st.checkbox("📏 تغییر سایز دستی")
    resize_width, resize_height = None, None
    if custom_size:
        resize_width = st.number_input("عرض جدید (پیکسل):", min_value=1, step=1)
        resize_height = st.number_input("ارتفاع جدید (پیکسل):", min_value=1, step=1)
    quality = st.slider("درصد کیفیت تصویر:", min_value=10, max_value=100, value=85, step=5)
    enhance = st.checkbox("✨ بهبود وضوح تصویر (Sharpen)")
    enhance_color = st.checkbox("🎨 بهبود رنگ تصویر (Color Enhance)")
    watermark = st.checkbox("💧 افزودن واترمارک")

    wm_text = None
    wm_logo = None
    wm_full = False
    if watermark:
        wm_text = st.text_input("متن واترمارک (اختیاری)", value="")
        wm_logo_file = st.file_uploader("لوگوی واترمارک (اختیاری)", type=["png", "jpg", "jpeg"], key="logo")
        wm_full = st.checkbox("🔁 نمایش واترمارک در کل تصویر")
        if wm_logo_file:
            tmp_logo = tempfile.NamedTemporaryFile(delete=False, suffix=os.path.splitext(wm_logo_file.name)[-1])
            tmp_logo.write(wm_logo_file.read())
            wm_logo = tmp_logo.name

    st.markdown("---")
    st.subheader("📸 پیش‌نمایش و دانلود")

    if st.button("🚀 انجام بهینه‌سازی تصاویر"):
        zip_outputs = []

        def process_and_display(file):
            with tempfile.NamedTemporaryFile(delete=False, suffix=os.path.splitext(file.name)[-1]) as tmp:
                tmp.write(file.read())
                tmp_path = tmp.name

            preview = Image.open(tmp_path)
            st.image(preview, caption="پیش‌نمایش تصویر اصلی", use_container_width=True)

            out_path = convert_and_optimize_image(tmp_path, format_img, resize_width, resize_height, quality, enhance, False, enhance_color, watermark, wm_text, wm_logo, wm_full)
            if out_path:
                zip_outputs.append(out_path)
                st.download_button("⬇️ دانلود تصویر بهینه‌شده", open(out_path, "rb"), file_name=os.path.basename(out_path))

        for file in all_files[:20]:
            process_and_display(file)

        if zip_outputs:
            zip_file_path = tempfile.NamedTemporaryFile(delete=False, suffix=".zip").name
            with zipfile.ZipFile(zip_file_path, 'w') as zipf:
                for optimized_file in zip_outputs:
                    zipf.write(optimized_file, os.path.basename(optimized_file))
            st.download_button("📦 دانلود همه تصاویر بهینه‌شده به‌صورت ZIP", open(zip_file_path, 'rb'), file_name="optimized_images.zip")
