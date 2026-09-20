# -*- coding: utf-8 -*-
"""
生成饱和页「氛围板」位图：多层柔焦椭圆 + 底部抬亮 + 微粒噪点，与 $bk-primary / $bk-primary-light / $bk-accent 同源。
运行：python gen_deco.py
"""
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

OUT = Path(__file__).parent
# 竖版画板（aspectFill 铺满）；尺寸控制体积，柔焦层不依赖超高分辨率
W, H = 600, 800


def _ellipse(canvas: Image.Image, bbox, fill: tuple, blur: int) -> None:
    layer = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    ImageDraw.Draw(layer).ellipse(bbox, fill=fill)
    if blur > 0:
        layer = layer.filter(ImageFilter.GaussianBlur(blur))
    canvas.paste(Image.alpha_composite(canvas, layer))


def _radial_stack() -> Image.Image:
    """由远到近叠柔光（先铺大氛围，再点高光）。"""
    im = Image.new("RGBA", (W, H), (0, 0, 0, 0))

    # 1 左下深海锚点 — 稳住视觉重心
    _ellipse(
        im,
        (-int(W * 0.18), int(H * 0.52), int(W * 0.62), int(H * 1.12)),
        (0, 53, 128, 46),
        98,
    )
    # 2 中下「地面雾气」— 与浅灰页面底衔接，避免死平
    _ellipse(
        im,
        (int(W * 0.08), int(H * 0.72), int(W * 0.94), int(H * 1.18)),
        (232, 240, 250, 72),
        88,
    )
    # 3 中右品牌蓝薄雾 — 低存在感
    _ellipse(
        im,
        (int(W * 0.38), int(H * 0.28), int(W * 1.12), int(H * 0.88)),
        (0, 113, 194, 22),
        108,
    )
    # 4 右上 CTA 金 — 小面积、低饱和，点睛不抢
    _ellipse(
        im,
        (int(W * 0.58), -int(H * 0.06), int(W * 1.05), int(H * 0.34)),
        (254, 187, 2, 26),
        72,
    )
    # 5 左上冷高光 — 模拟天光扫过纸面
    _ellipse(
        im,
        (-int(W * 0.22), -int(H * 0.08), int(W * 0.48), int(H * 0.42)),
        (255, 255, 255, 34),
        78,
    )
    # 6 垂直中轴极淡提亮 — 引导视线向内容区
    _ellipse(
        im,
        (int(W * 0.22), int(H * 0.12), int(W * 0.78), int(H * 0.62)),
        (248, 251, 255, 18),
        120,
    )
    return im


def _film_grain(base: Image.Image) -> Image.Image:
    """低频微粒：小图生成再放大 + 强模糊，避免全幅高频噪点撑爆 PNG。"""
    gw, gh = max(W // 5, 120), max(H // 5, 160)
    np.random.seed(2026)
    a = np.random.randint(6, 16, (gh, gw), dtype=np.uint8)
    n = np.full((gh, gw), 250, dtype=np.uint8)
    arr = np.dstack([n, n, n, a]).astype(np.uint8)
    grain = Image.fromarray(arr, "RGBA")
    grain = grain.resize((W, H), getattr(Image, "LANCZOS", Image.BICUBIC))
    grain = grain.filter(ImageFilter.GaussianBlur(2.4))
    return Image.alpha_composite(base, grain)


def main():
    art = _radial_stack()
    art = _film_grain(art)
    path = OUT / "deco-ambient.png"
    # compress_level=9 显著减小体积，渐变柔焦仍可接受
    art.save(path, optimize=True, compress_level=9)

    # 旧版三图已弃用，避免误用低质感资源
    for old in ("deco-wave.png", "deco-accent.png", "deco-shapes.png"):
        p = OUT / old
        if p.exists():
            p.unlink()

    print("written:", path.name, "size:", path.stat().st_size // 1024, "KB")


if __name__ == "__main__":
    main()
