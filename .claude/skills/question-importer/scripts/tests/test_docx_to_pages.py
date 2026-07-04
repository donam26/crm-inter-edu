# tests/test_docx_to_pages.py
import os, glob, subprocess, sys
HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SAMPLE = "/Users/hnam/code/phongthi/Chủ đề 1. Ester - Lipid.docx"

def test_renders_at_least_three_pages(tmp_path):
    out = str(tmp_path)
    subprocess.run([sys.executable, os.path.join(HERE, "docx_to_pages.py"),
                    SAMPLE, "--outdir", out, "--dpi", "150"], check=True)
    pngs = sorted(glob.glob(os.path.join(out, "page-*.png")))
    assert len(pngs) >= 3
    assert os.path.getsize(pngs[0]) > 1000
