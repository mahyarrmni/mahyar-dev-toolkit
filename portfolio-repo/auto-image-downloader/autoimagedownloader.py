# unsplash_downloader_app.py
import streamlit as st
import requests
from bs4 import BeautifulSoup
import os
import time
import urllib.parse

def download_images_from_unsplash(keyword, count=5, folder="downloaded_images"):
    encoded_keyword = urllib.parse.quote(keyword)
    search_url = f"https://unsplash.com/s/photos/{encoded_keyword}"
    
    headers = {
        "User-Agent": "Mozilla/5.0"
    }

    try:
        response = requests.get(search_url, headers=headers)
        if response.status_code != 200:
            st.error(f"❌ اتصال به Unsplash برای '{keyword}' موفق نبود. وضعیت: {response.status_code}")
            return
        
        soup = BeautifulSoup(response.text, 'html.parser')
        img_tags = soup.find_all("img", {"srcset": True})
        img_urls = []

        for img in img_tags:
            srcset = img.get("srcset")
            if srcset:
                last_url = srcset.split(",")[-1].split(" ")[0]
                if last_url.startswith("https://images.unsplash.com"):
                    img_urls.append(last_url)

        # ایجاد پوشه برای هر کلمه
        keyword_folder = os.path.join(folder, keyword.replace(" ", "_"))
        os.makedirs(keyword_folder, exist_ok=True)

        downloaded = 0
        for img_url in img_urls:
            if downloaded >= count:
                break
            try:
                img_data = requests.get(img_url, headers=headers).content
                file_path = os.path.join(keyword_folder, f"{keyword.replace(' ', '_')}_{downloaded + 1}.jpg")
                with open(file_path, "wb") as f:
                    f.write(img_data)
                downloaded += 1
                time.sleep(1)  # تاخیر برای جلوگیری از بلاک شدن
            except Exception as e:
                st.warning(f"خطا در دانلود تصویر: {e}")
        
        st.success(f"✅ {downloaded} تصویر برای '{keyword}' با موفقیت ذخیره شد.")

    except Exception as e:
        st.error(f"❌ خطا: {e}")

# رابط کاربری Streamlit
st.set_page_config(page_title="دانلود تصاویر از Unsplash", layout="centered")

st.title("📥 دانلود تصاویر مرتبط با قانون از Unsplash (بدون API)")
st.markdown("این ابزار به شما کمک می‌کند تصاویر مرتبط با کلمات کلیدی خاص (مثل 'judge', 'court', 'lawyer') را از سایت Unsplash دانلود کنید.")

keywords_input = st.text_area("🔍 کلمات کلیدی (هر خط یک کلمه)", height=200, value="judge\ncourtroom\nlawyer\ngavel\njustice")
image_count = st.slider("📸 تعداد تصویر برای هر کلمه کلیدی", min_value=1, max_value=50, value=5)

if st.button("🚀 شروع دانلود"):
    keywords = [kw.strip() for kw in keywords_input.strip().split("\n") if kw.strip()]
    if not keywords:
        st.warning("لطفاً حداقل یک کلمه کلیدی وارد کنید.")
    else:
        st.info("لطفاً چند لحظه صبر کنید تا دانلودها انجام شود...")
        for kw in keywords:
            download_images_from_unsplash(kw, count=image_count)
        st.success("🎉 همه دانلودها انجام شد. تصاویر در پوشه 'downloaded_images' ذخیره شدند.")
