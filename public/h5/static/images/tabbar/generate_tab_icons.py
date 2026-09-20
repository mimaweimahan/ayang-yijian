# -*- coding: utf-8 -*-
"""生成底栏 Tab 用 PNG（81x81），与 pages.json / bk-tabbar 路径一致。"""
import math
import os

from PIL import Image, ImageDraw

W = H = 81
OUT = os.path.dirname(os.path.abspath(__file__))
GRAY = "#6b6b6b"
BLUE = "#003580"


def star_points(cx, cy, outer, inner, n=5):
    pts = []
    for i in range(n * 2):
        a = math.pi / 2 + i * math.pi / n
        r = outer if i % 2 == 0 else inner
        pts.append((cx + r * math.cos(a), cy - r * math.sin(a)))
    return pts


def draw_home(im, color, fill_roof=False):
    d = ImageDraw.Draw(im)
    roof = [(40, 14), (16, 36), (64, 36)]
    if fill_roof:
        d.polygon(roof, fill=color)
    else:
        d.line(roof + [roof[0]], fill=color, width=4)
    d.rectangle([24, 36, 56, 62], outline=color, width=4)


def draw_star(im, color, fill=False):
    d = ImageDraw.Draw(im)
    pts = [tuple(round(x) for x in p) for p in star_points(40, 40, 24, 10)]
    if fill:
        d.polygon(pts, fill=color)
    else:
        for i in range(len(pts)):
            d.line([pts[i], pts[(i + 1) % len(pts)]], fill=color, width=3)


def draw_calendar(im, color, fill_header=False):
    d = ImageDraw.Draw(im)
    d.rectangle([16, 18, 64, 64], outline=color, width=4)
    if fill_header:
        d.rectangle([17, 19, 63, 33], fill=color)
        d.line([28, 44, 52, 44], fill="#ffffff", width=2)
        d.line([28, 52, 52, 52], fill="#ffffff", width=2)
    else:
        d.line([16, 34, 64, 34], fill=color, width=3)
        d.line([28, 44, 52, 44], fill=color, width=3)
        d.line([28, 52, 52, 52], fill=color, width=3)


def draw_account(im, color, fill_head=False):
    d = ImageDraw.Draw(im)
    if fill_head:
        d.ellipse([28, 16, 52, 40], fill=color)
    else:
        d.ellipse([28, 16, 52, 40], outline=color, width=3)
    d.arc([18, 32, 62, 78], start=200, end=340, fill=color, width=4)


def make_pair(name, draw_fn):
    for sel, col, kw in (
        (False, GRAY, {"fill_roof": False, "fill": False, "fill_header": False, "fill_head": False}),
        (True, BLUE, {"fill_roof": True, "fill": True, "fill_header": True, "fill_head": True}),
    ):
        im = Image.new("RGBA", (W, H), (255, 255, 255, 0))
        if name == "home":
            draw_home(im, col, fill_roof=kw["fill_roof"])
        elif name == "rating":
            draw_star(im, col, fill=kw["fill"])
        elif name == "stay":
            draw_calendar(im, col, fill_header=kw["fill_header"])
        else:
            draw_account(im, col, fill_head=kw["fill_head"])
        suffix = "_sel" if sel else ""
        path = os.path.join(OUT, f"tb_{name}{suffix}.png")
        im.save(path, "PNG")
        print(path)


def main():
    os.makedirs(OUT, exist_ok=True)
    make_pair("home", draw_home)
    make_pair("rating", draw_star)
    make_pair("stay", draw_calendar)
    make_pair("mine", draw_account)


if __name__ == "__main__":
    main()
