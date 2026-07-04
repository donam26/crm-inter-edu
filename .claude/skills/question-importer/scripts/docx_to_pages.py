"""docx -> PDF (LibreOffice headless) -> page PNGs (pdftoppm). No Mathpix."""
import argparse, glob, os, shutil, subprocess, sys, tempfile


def docx_to_pdf(docx: str, outdir: str) -> str:
    subprocess.run(["soffice", "--headless", "--convert-to", "pdf",
                    "--outdir", outdir, docx], check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    pdfs = glob.glob(os.path.join(outdir, "*.pdf"))
    if not pdfs:
        sys.exit("LibreOffice produced no PDF")
    return pdfs[0]


def pdf_to_pngs(pdf: str, outdir: str, dpi: int) -> list[str]:
    prefix = os.path.join(outdir, "page")
    subprocess.run(["pdftoppm", "-png", "-r", str(dpi), pdf, prefix], check=True)
    # pdftoppm emits page-1.png, page-01.png ... normalize to zero-padded
    return sorted(glob.glob(os.path.join(outdir, "page-*.png")))


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("docx")
    ap.add_argument("--outdir", required=True)
    ap.add_argument("--dpi", type=int, default=200)
    args = ap.parse_args()
    os.makedirs(args.outdir, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        pdf = docx_to_pdf(args.docx, tmp)
        shutil.copy(pdf, os.path.join(args.outdir, "source.pdf"))
        pngs = pdf_to_pngs(os.path.join(args.outdir, "source.pdf"), args.outdir, args.dpi)
    print("\n".join(pngs))


if __name__ == "__main__":
    main()
