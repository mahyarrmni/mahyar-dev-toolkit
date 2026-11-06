FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY asige ./asige
ENV PYTHONUNBUFFERED=1
RUN useradd -m asige && chown -R asige /app
USER asige
EXPOSE 8000
CMD ["uvicorn", "asige.api:app", "--host", "0.0.0.0", "--port", "8000"]
