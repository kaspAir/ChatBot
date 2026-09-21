"""Prepare searchable text with explicit provenance. Run locally, not on PHP hosting."""
import argparse
import hashlib
import json
from pathlib import Path
import fitz


def is_green(color):
    red, green, blue = (color >> 16) & 255, (color >> 8) & 255, color & 255
    return green > 40 and green > red * 1.2 and green > blue * 1.2


def prepare(source, output, version):
    output.mkdir(parents=True, exist_ok=False)
    document = fitz.open(source)
    additions, pages = [], []
    for number, page in enumerate(document, 1):
        lines = [f'REFERENZHANDBUCH | Wissensstand: {version} | PDF-Seite: {number}',
                 'PDF-Seite bezeichnet die Dateiseite, nicht die gedruckte Seitenzahl.']
        for block in page.get_text('dict', sort=True)['blocks']:
            for line in block.get('lines', []):
                parts = []
                for span in line['spans']:
                    text = span['text']
                    if text.strip() and is_green(span['color']):
                        parts.append('[ERGÄNZUNG KASPAR/BKI: ' + text + ']')
                        additions.append({'pdf_page': number, 'text': text, 'color': f"#{span['color']:06x}"})
                    else:
                        parts.append(text)
                if ''.join(parts).strip():
                    lines.append(''.join(parts))
        pages.append('\n'.join(lines))
    (output / 'referenzhandbuch.txt').write_text('\n\n'.join(pages), encoding='utf-8')
    manifest = {'version': version, 'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
                'pages': len(document), 'green_spans': additions,
                'notice': 'Farberkennung ist eine Heuristik. Ergänzungen, Tabellen und Lesereihenfolge vor Upload prüfen.'}
    (output / 'review.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'{len(document)} Seiten; {len(additions)} grüne Textabschnitte. Prüfen: {output / "review.json"}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('pdf', type=Path)
    parser.add_argument('output', type=Path)
    parser.add_argument('--version', required=True)
    args = parser.parse_args()
    prepare(args.pdf, args.output, args.version)
