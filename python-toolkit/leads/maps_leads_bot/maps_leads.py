#!/usr/bin/env python3
"""Author: Mahyar Momeni (مهیار مومنی)
Maps Leads Bot - collects business name/phone/address for a category in a city
using the OFFICIAL Google Places API (New). Usage:
  export GOOGLE_API_KEY=xxxx
  python maps_leads.py "لباس فروشی" "بوتیک" --city tehran --grid 4 -o tehran.csv
"""
import argparse, csv, os, sys, time
import requests

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

URL = "https://places.googleapis.com/v1/places:searchText"
FIELDS = ("places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,"
          "places.internationalPhoneNumber,places.websiteUri,places.rating,"
          "places.userRatingCount,places.googleMapsUri,nextPageToken")
# city -> (south, west, north, east)
CITIES = {
    "tehran": (35.58, 51.20, 35.83, 51.62),
    "mashhad": (36.20, 59.45, 36.42, 59.72),
    "isfahan": (32.55, 51.55, 32.75, 51.80),
    "shiraz": (29.55, 52.45, 29.70, 52.65),
    "tabriz": (38.00, 46.20, 38.15, 46.40),
}

def tiles(box, n):
    s, w, nn, e = box
    dy, dx = (nn - s) / n, (e - w) / n
    for i in range(n):
        for j in range(n):
            yield (s + i * dy, w + j * dx, s + (i + 1) * dy, w + (j + 1) * dx)

def search(key, query, rect, lang):
    out, token = [], None
    for _ in range(3):  # Google returns max 3 pages (60 results) per query
        body = {"textQuery": query, "languageCode": lang, "pageSize": 20,
                "locationRestriction": {"rectangle": {
                    "low": {"latitude": rect[0], "longitude": rect[1]},
                    "high": {"latitude": rect[2], "longitude": rect[3]}}}}
        if token:
            body["pageToken"] = token
        r = requests.post(URL, json=body, timeout=30, headers={
            "X-Goog-Api-Key": key, "X-Goog-FieldMask": FIELDS})
        if r.status_code != 200:
            sys.exit(f"API error {r.status_code}: {r.text[:300]}")
        d = r.json()
        out += d.get("places", [])
        token = d.get("nextPageToken")
        if not token:
            break
        time.sleep(1.5)
    return out

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("queries", nargs="+", help="business categories, e.g. 'لباس فروشی' بوتیک")
    ap.add_argument("--city", default="tehran", choices=CITIES)
    ap.add_argument("--grid", type=int, default=4, help="NxN tiles; higher = more results, more API cost")
    ap.add_argument("--lang", default="fa")
    ap.add_argument("--with-phone-only", action="store_true")
    ap.add_argument("--key", help="API key (or use GOOGLE_API_KEY env var)")
    ap.add_argument("-o", "--out", default="leads.csv")
    a = ap.parse_args()
    key = a.key or os.environ.get("GOOGLE_API_KEY") or sys.exit("ERROR: API key not set. See README step 4.")

    seen = {}
    tl = list(tiles(CITIES[a.city], a.grid))
    for q in a.queries:
        for k, rect in enumerate(tl, 1):
            for p in search(key, q, rect, a.lang):
                seen.setdefault(p["id"], p)
            print(f"[{q}] tile {k}/{len(tl)} -> {len(seen)} unique", end="\r", flush=True)
        print()

    rows = []
    for p in seen.values():
        phone = p.get("nationalPhoneNumber") or p.get("internationalPhoneNumber") or ""
        if a.with_phone_only and not phone:
            continue
        rows.append([p.get("displayName", {}).get("text", ""), phone,
                     p.get("formattedAddress", ""), p.get("websiteUri", ""),
                     p.get("rating", ""), p.get("userRatingCount", ""), p.get("googleMapsUri", "")])
    rows.sort(key=lambda r: r[0])
    with open(a.out, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f)
        w.writerow(["name", "phone", "address", "website", "rating", "reviews", "maps_url"])
        w.writerows(rows)
    print(f"Done: {len(rows)} businesses -> {a.out}")

if __name__ == "__main__":
    main()
