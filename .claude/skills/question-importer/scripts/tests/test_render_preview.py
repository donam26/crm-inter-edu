# tests/test_render_preview.py
import json, os, subprocess, sys
from PIL import Image
HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def _render(tmp_path, data, stem):
    j = tmp_path / f"{stem}.json"
    j.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
    out = tmp_path / f"{stem}.png"
    subprocess.run([sys.executable, os.path.join(HERE, "render_preview.py"),
                    "--question", str(j), "--out", str(out)], check=True)
    return out


def test_renders_question_to_png(tmp_path):
    data = {"text": "Công thức của ethyl propionate là $C_2H_5COOC_2H_5$",
            "options": [{"key":"A","value":"$CH_3COOC_2H_5$"},{"key":"D","value":"$C_2H_5COOC_2H_5$"}]}
    out = _render(tmp_path, data, "q")
    assert out.exists() and Image.open(out).size[0] > 100


def test_renders_passage_as_raw_html(tmp_path):
    # passage content is raw HTML injected via innerHTML — not parsed as Markdown.
    data = {
        "passage": "<p>Scientists have recently discovered a <strong>new species</strong> of deep-sea fish.</p>"
                   "<p>The creature lives at depths of over 3,000 metres.</p>",
        "text": "According to the passage, where does the creature live?",
        "options": [{"key": "A", "value": "In rivers"}, {"key": "B", "value": "In deep seas"}],
    }
    out = _render(tmp_path, data, "passage")
    img = Image.open(out)
    # Image must be wide enough to contain the passage block
    assert img.size[0] > 100
    assert out.stat().st_size > 0
