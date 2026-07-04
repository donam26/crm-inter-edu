# tests/test_crop_region.py
import os, subprocess, sys
from PIL import Image
HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def test_crops_fractional_bbox(tmp_path):
    src = tmp_path / "page.png"
    Image.new("RGB", (1000, 800), "white").save(src)
    out = tmp_path / "crop.png"
    subprocess.run([sys.executable, os.path.join(HERE, "crop_region.py"),
                    str(src), "--bbox", "0.1,0.2,0.5,0.4", "--out", str(out),
                    "--no-trim"], check=True)
    w, h = Image.open(out).size
    assert abs(w - 400) <= 2 and abs(h - 160) <= 2   # 0.5-0.1=0.4*1000, 0.4-0.2=0.2*800
