"""Inspect PDFs produced by supervisi-print.mjs without modifying app data."""
import json
import re
from pathlib import Path

import pdfplumber
from pypdf import PdfReader

root = Path('storage/logs/supervisi-qa')
names = ['cetak-awal', 'cetak-laporan', 'cetak-kolektif', 'cetak-panjang',
         'cetak-rekap', 'cetak-cakupan', 'cetak-rekap-panjang']
texts = {}
counts = {}
for name in names:
    path = root / f'{name}.pdf'
    reader = PdfReader(path)
    counts[name] = len(reader.pages)
    texts[name] = [re.sub(r'\s+', ' ', page.extract_text()) for page in reader.pages]
    with pdfplumber.open(path) as document:
        for i, page in enumerate(document.pages):
            body = re.sub(r'Halaman \d+ / \d+', '', texts[name][i]).strip()
            assert len(body) > 60, f'Blank/orphan page: {name}#{i+1}'
            assert not re.search(r'@(if|endif|else|endforeach|endunless)\b', body), name
            for word in page.extract_words():
                assert -1 <= word['x0'] <= word['x1'] <= page.width + 1, (name, i, word)
                assert -1 <= word['top'] <= word['bottom'] <= page.height + 1, (name, i, word)
    assert f'Halaman {len(reader.pages)} / {len(reader.pages)}' in texts[name][-1], name

assert 'Ruang tanda tangan diisi setelah pemeriksaan.' in texts['cetak-laporan'][0]
assert 'PENANDA AKHIR CATATAN' in ' '.join(texts['cetak-panjang'])
assert 'Akhir laporan sesi #1' in texts['cetak-panjang'][-1]
assert 'Bukti pengamatan indikator.' in ' '.join(texts['cetak-laporan'])
assert ' '.join(texts['cetak-laporan']).count('Bukti pengamatan indikator.') == 79
starts = [i for i, text in enumerate(texts['cetak-kolektif'])
          if 'LAPORAN SUPERVISI AKADEMIK' in text]
assert starts == [0, counts['cetak-laporan']], starts
for text in texts['cetak-rekap-panjang'][1:]:
    assert 'Guru / jadwal / mapel' in text, 'Recap header must repeat on later pages'
assert 'Belum memiliki jadwal terbit' in ' '.join(texts['cetak-cakupan'])
print(json.dumps({'result': 'passed', 'pages': counts, 'checks': [
    'all-indicators-present', 'long-notes-complete', 'signatures-first-page',
    'no-template-literals', 'no-out-of-bounds-text', 'no-blank-pages',
    'collective-page-breaks', 'page-numbers', 'repeating-table-headers']}))
