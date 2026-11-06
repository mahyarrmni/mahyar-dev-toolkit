import hashlib
import json
import math

import numpy as np
import pandas as pd


def jsonable(o):
    """Recursively convert numpy/pandas/sets/NaN into plain JSON types (deterministic ordering for sets)."""
    if isinstance(o, dict):
        return {str(k): jsonable(v) for k, v in o.items()}
    if isinstance(o, (list, tuple)):
        return [jsonable(v) for v in o]
    if isinstance(o, (set, frozenset)):
        return sorted(jsonable(v) for v in o)
    if isinstance(o, np.ndarray):
        return jsonable(o.tolist())
    if isinstance(o, (np.integer,)):
        return int(o)
    if isinstance(o, (np.floating, float)):
        return None if math.isnan(o) or math.isinf(o) else float(o)
    if isinstance(o, (np.bool_,)):
        return bool(o)
    if isinstance(o, (pd.Timestamp,)):
        return o.isoformat()
    return o


def digest(obj) -> str:
    return hashlib.sha256(json.dumps(jsonable(obj), sort_keys=True, default=str).encode()).hexdigest()[:16]
