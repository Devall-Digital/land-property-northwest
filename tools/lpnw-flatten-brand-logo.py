#!/usr/bin/env python3
"""
Flatten LPNW brand raster exports that bake in a grey/white checkerboard margin.

Expects a wide canvas with a centred square squircle logo (as some generators export).
Outputs a 512x512 opaque PNG: checkerboard connected to image corners is replaced with
navy #1B2A4A; logo interior is left unchanged.

Usage (repo root):
  python tools/lpnw-flatten-brand-logo.py path/to/wide-export.png theme/lpnw-theme/assets/img/lpnw-brand-logo.png
"""
from __future__ import annotations

import sys
from collections import deque

from PIL import Image
import numpy as np


NAVY = np.array([27, 42, 74], dtype=np.uint8)
OUT_SIZE = 512


def navy_dist(r: int, g: int, b: int) -> int:
    return (r - 27) ** 2 + (g - 42) ** 2 + (b - 74) ** 2


def passable_checker(r: int, g: int, b: int) -> bool:
    lum = (r + g + b) / 3
    mx, mn = max(r, g, b), min(r, g, b)
    if navy_dist(r, g, b) < 1200:
        return False
    if lum < 165:
        return False
    if mx - mn > 38:
        return False
    return lum > 188


def flood_from_corners(arr: np.ndarray) -> np.ndarray:
    h, w, _ = arr.shape
    vis = np.zeros((h, w), dtype=bool)
    q: deque[tuple[int, int]] = deque()
    for x, y in ((0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1)):
        r, g, b = arr[y, x].tolist()
        if passable_checker(r, g, b):
            vis[y, x] = True
            q.append((x, y))
    while q:
        x, y = q.popleft()
        for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
            if nx < 0 or ny < 0 or nx >= w or ny >= h or vis[ny, nx]:
                continue
            r, g, b = arr[ny, nx].tolist()
            if passable_checker(r, g, b):
                vis[ny, nx] = True
                q.append((nx, ny))
    return vis


def radial_scrub(out: np.ndarray, radius_frac: float = 0.42) -> None:
    """Remove residual light fringes outside a central disk (anti-alias halos)."""
    h, w, _ = out.shape
    cxp, cyp = w // 2, h // 2
    r_thresh = w * radius_frac
    for y in range(h):
        for x in range(w):
            if ((x - cxp) ** 2 + (y - cyp) ** 2) ** 0.5 < r_thresh:
                continue
            r, g, b = out[y, x].tolist()
            lum = (r + g + b) / 3
            mx, mn = max(r, g, b), min(r, g, b)
            if lum > 175 and (mx - mn) < 40 and navy_dist(r, g, b) > 600:
                out[y, x] = NAVY


def center_square_crop(im: Image.Image) -> Image.Image:
    """If not square, crop to largest centred square (wide exports)."""
    w, h = im.size
    if w == h:
        return im
    side = min(w, h)
    x0 = (w - side) // 2
    y0 = (h - side) // 2
    return im.crop((x0, y0, x0 + side, y0 + side))


def process(src: str, dest: str) -> None:
    im = Image.open(src).convert("RGB")
    im = center_square_crop(im)
    arr = np.array(im)
    vis = flood_from_corners(arr)
    out = arr.copy()
    out[vis] = NAVY
    radial_scrub(out)
    out_img = Image.fromarray(out).resize((OUT_SIZE, OUT_SIZE), Image.Resampling.LANCZOS)
    out_img.save(dest, optimize=True)


def main() -> None:
    if len(sys.argv) != 3:
        print(__doc__.strip())
        sys.exit(1)
    process(sys.argv[1], sys.argv[2])
    print("Wrote", sys.argv[2])


if __name__ == "__main__":
    main()
