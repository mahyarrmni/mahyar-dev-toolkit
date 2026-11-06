"""SimHash fingerprints + LSH banding for near-duplicate detection without O(n^2) comparisons."""
import hashlib
from collections import defaultdict
from itertools import combinations

import numpy as np


def _h(s: str) -> int:
    return int.from_bytes(hashlib.blake2b(s.encode(), digest_size=8).digest(), "big")


def simhash(tokens: list, shingle: int = 1) -> int:
    if not tokens:
        return 0
    grams = [" ".join(tokens[i:i + shingle]) for i in range(len(tokens) - shingle + 1)] if len(tokens) >= shingle else tokens
    h = np.fromiter((_h(g) for g in grams), dtype=np.uint64, count=len(grams))
    bits = (h[:, None] >> np.arange(64, dtype=np.uint64)) & np.uint64(1)
    s = bits.sum(axis=0).astype(np.int64) * 2 - len(grams)
    fp = 0
    for i in np.nonzero(s > 0)[0]:
        fp |= 1 << int(i)
    return fp


def hamming(a: int, b: int) -> int:
    return (a ^ b).bit_count()


def find_near_duplicates(fps: dict, max_dist: int = 3, max_bucket: int = 300):
    """Pigeonhole LSH: split 64 bits into max_dist+1 bands; any pair within max_dist agrees on >=1 band.
    Returns (pairs [(a, b, dist)], skipped_buckets). Exact for buckets <= max_bucket."""
    bands = max_dist + 1
    edges = [round(i * 64 / bands) for i in range(bands + 1)]
    buckets = defaultdict(list)
    for key, fp in fps.items():
        if fp == 0:
            continue
        for b in range(bands):
            lo, hi = edges[b], edges[b + 1]
            buckets[(b, (fp >> lo) & ((1 << (hi - lo)) - 1))].append(key)
    pairs, skipped = {}, 0
    for members in buckets.values():
        if len(members) < 2:
            continue
        if len(members) > max_bucket:
            skipped += 1
            continue
        for a, b in combinations(sorted(members, key=str), 2):
            if (a, b) not in pairs:
                d = hamming(fps[a], fps[b])
                if d <= max_dist:
                    pairs[(a, b)] = d
    return sorted(((a, b, d) for (a, b), d in pairs.items()), key=lambda x: (str(x[0]), str(x[1]))), skipped


def brute_force_pairs(fps: dict, max_dist: int = 3):
    keys = sorted((k for k, v in fps.items() if v), key=str)
    return sorted(((a, b, hamming(fps[a], fps[b])) for a, b in combinations(keys, 2) if hamming(fps[a], fps[b]) <= max_dist), key=lambda x: (str(x[0]), str(x[1])))
