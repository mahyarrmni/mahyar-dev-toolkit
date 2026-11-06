"""Celery worker entrypoint: `celery -A asige.tasks worker -l info` (requires REDIS_URL)."""
from celery import Celery

from . import config, jobs

app = Celery("asige", broker=config.REDIS_URL, backend=config.REDIS_URL)
app.conf.update(task_acks_late=True, worker_prefetch_multiplier=1, task_track_started=True)


@app.task(name="asige.crawl")
def crawl_task(crawl_id):
    jobs.run_crawl_job(crawl_id)


@app.task(name="asige.analyze")
def analyze_task(crawl_id, params=None):
    jobs.run_analysis_job(crawl_id, params)
