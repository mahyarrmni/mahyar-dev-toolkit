:root { font-family: system-ui, "Segoe UI", Tahoma, sans-serif; color: #1c2430; background: #f6f7f9; }
body { margin: 0; }
header { background: #14213d; color: #fff; padding: 12px 24px; display: flex; align-items: center; gap: 24px; }
header h1 { font-size: 18px; margin: 0; }
nav button { background: none; border: 0; color: #c9d3e6; padding: 8px 12px; cursor: pointer; font-size: 14px; border-bottom: 2px solid transparent; }
nav button.on { color: #fff; border-color: #fca311; }
main { padding: 24px; max-width: 1280px; margin: 0 auto; }
.card { background: #fff; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px #0001; }
.row { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
table { border-collapse: collapse; width: 100%; font-size: 13px; }
th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
th { color: #5b6677; font-weight: 600; }
tr.click { cursor: pointer; } tr.click:hover { background: #f1f4fa; }
input, select, button.act { padding: 6px 10px; border: 1px solid #c8ced9; border-radius: 6px; font-size: 14px; }
button.act { background: #14213d; color: #fff; cursor: pointer; } button.act:disabled { opacity: .5; }
.kpi { font-size: 40px; font-weight: 700; } .muted { color: #6b7686; font-size: 12px; }
.pill { padding: 2px 8px; border-radius: 10px; font-size: 11px; color: #fff; }
.critical { background: #b00020; } .high { background: #e4572e; } .medium { background: #f3a712; } .low { background: #4c9f70; } .info { background: #7d8597; }
pre { background: #0d1b2a; color: #e0e1dd; padding: 12px; border-radius: 6px; overflow: auto; font-size: 12px; max-height: 360px; }
.err { color: #b00020; }
