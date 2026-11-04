import sqlite3
from contextlib import closing

import pandas as pd

from . import config


def save_capacity(cap: pd.DataFrame):
    with closing(sqlite3.connect(config.DB_PATH)) as c:
        cap.to_sql("warehouses", c, if_exists="replace", index=False)


def save(inv: pd.DataFrame, cap):
    with closing(sqlite3.connect(config.DB_PATH)) as c:
        inv.assign(last_movement=inv.last_movement.dt.strftime("%Y-%m-%d")).to_sql(
            "inventory", c, if_exists="replace", index=False)
    if cap is not None:
        save_capacity(cap)


def load():
    """Returns (inventory, capacity) or (None, None) if nothing stored."""
    try:
        with closing(sqlite3.connect(config.DB_PATH)) as c:
            inv = pd.read_sql("SELECT * FROM inventory", c)
            try:
                cap = pd.read_sql("SELECT * FROM warehouses", c)
            except (pd.errors.DatabaseError, sqlite3.Error):
                cap = None
    except (pd.errors.DatabaseError, sqlite3.Error):
        return None, None
    inv["last_movement"] = pd.to_datetime(inv["last_movement"])
    return inv, cap
