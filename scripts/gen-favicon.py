#!/usr/bin/env python3
"""Собирает иконки сайта из scripts/favicon-source.png (круг на сером фоне):
вырезает круг, убирает фон и пишет favicon.ico, PNG разных размеров, иконку для iOS и маскируемую иконку для Android.
Запуск: python3 scripts/gen-favicon.py   (нужен Pillow)"""
from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "scripts" / "favicon-source.png"
ASSETS = ROOT / "site" / "themes" / "kolerovka" / "assets"
PUBLIC = ROOT / "site" / "public"
BG = (247, 247, 247)  # цвет фона исходной картинки: подложка для iOS и маскируемой иконки

src = Image.open(SRC).convert("RGB")
# Круг занимает пиксели 26..281 исходной картинки, остальное серый фон.
disc = src.crop((26, 26, 282, 282))


def circle_mask(size: int, inset: float = 0.5) -> Image.Image:
    k = 8
    m = Image.new("L", (size * k, size * k), 0)
    ImageDraw.Draw(m).ellipse((inset * k, inset * k, (size - inset) * k - 1, (size - inset) * k - 1), fill=255)
    return m.resize((size, size), Image.LANCZOS)


def round_icon(size: int) -> Image.Image:
    """Круг во весь холст, углы прозрачные."""
    img = disc.resize((size, size), Image.LANCZOS).convert("RGBA")
    # Чуть втянутая маска, чтобы по краю не осталась серая кайма исходного фона.
    img.putalpha(circle_mask(size, inset=max(0.5, size / 256)))
    return img


def padded_icon(size: int, scale: float) -> Image.Image:
    """Круг на сплошной подложке с полем (iOS, maskable)."""
    base = Image.new("RGBA", (size, size), BG + (255,))
    d = round(size * scale)
    base.alpha_composite(round_icon(d), ((size - d) // 2, (size - d) // 2))
    return base


for s in (16, 32, 48, 192, 512):
    round_icon(s).save(ASSETS / f"favicon-{s}.png", optimize=True)
padded_icon(180, 0.86).convert("RGB").save(ASSETS / "apple-touch-icon.png", optimize=True)
padded_icon(512, 0.72).convert("RGB").save(ASSETS / "icon-maskable-512.png", optimize=True)

PUBLIC.mkdir(parents=True, exist_ok=True)
round_icon(256).save(PUBLIC / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
padded_icon(180, 0.86).convert("RGB").save(PUBLIC / "apple-touch-icon.png", optimize=True)
print("готово")
