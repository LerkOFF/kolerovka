#!/usr/bin/env python3
"""Генерирует site/themes/kolerovka/assets/ral.css из data/ral.json.

CSP сайта запрещает атрибут style, поэтому цвет каждой плитки задаётся классом
.ral-<код> через переменную --c. Запуск: python3 scripts/gen-ral.py

Источник кодов и значений hex: Dezzpil/RAL-JSON, файл colors.json (лицензия MIT),
https://github.com/Dezzpil/RAL-JSON. Русские названия: colorscheme.ru/ral-colors/ral-classic.html,
недостающие переведены с официальных английских.
"""
import json
import re
import sys
from pathlib import Path

root = Path(__file__).resolve().parent.parent
theme = root / "site" / "themes" / "kolerovka"
data = json.loads((theme / "data" / "ral.json").read_text(encoding="utf-8"))

codes = [c["code"] for c in data]
if len(data) != 213 or len(set(codes)) != 213:
    sys.exit("Ожидалось 213 уникальных кодов RAL Classic, получено %d" % len(set(codes)))
for c in data:
    if not re.fullmatch(r"\d{4}", c["code"]) or not re.fullmatch(r"#[0-9a-f]{6}", c["hex"]):
        sys.exit("Неверная запись: %r" % c)

lines = [
    "/* Сгенерировано scripts/gen-ral.py из data/ral.json, вручную не править. */",
]
lines += [".ral-%s { --c: %s; }" % (c["code"], c["hex"]) for c in sorted(data, key=lambda c: c["code"])]
(theme / "assets" / "ral.css").write_text("\n".join(lines) + "\n", encoding="utf-8")
print("ral.css: %d правил" % len(data))
