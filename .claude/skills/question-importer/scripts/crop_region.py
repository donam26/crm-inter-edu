"""Crop a fractional bbox (x1,y1,x2,y2 in 0..1) from a page PNG; optional whitespace trim."""
import argparse
from PIL import Image, ImageChops


def trim_white(im: Image.Image) -> Image.Image:
    bg = Image.new(im.mode, im.size, (255, 255, 255))
    diff = ImageChops.difference(im.convert("RGB"), bg)
    bbox = diff.getbbox()
    return im.crop(bbox) if bbox else im


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("page")
    ap.add_argument("--bbox", required=True, help="x1,y1,x2,y2 fractions 0..1")
    ap.add_argument("--out", required=True)
    ap.add_argument("--no-trim", action="store_true")
    args = ap.parse_args()

    im = Image.open(args.page).convert("RGB")
    W, H = im.size
    x1, y1, x2, y2 = (float(v) for v in args.bbox.split(","))
    crop = im.crop((round(x1 * W), round(y1 * H), round(x2 * W), round(y2 * H)))
    if not args.no_trim:
        crop = trim_white(crop)
    crop.save(args.out)
    print(args.out)


if __name__ == "__main__":
    main()
