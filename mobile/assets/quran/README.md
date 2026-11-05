# Quran reading assets for Tahfidz

- Arabic text: **Tanzil Quran Text, Uthmani 1.1**, retrieved 2026-11-05.
- Source: https://tanzil.net/
- Download: https://tanzil.net/pub/download/index.php?quranType=uthmani&outType=txt-2&marks=true&sajdah=true&tatweel=true&agree=true
- Metadata: https://tanzil.net/res/text/metadata/quran-data.xml
- Text terms: https://tanzil.net/docs/text_license
- Original text SHA-256: `6933e133dd56db778c801bf738848454e43648105a151e8d84d86a7cae39ec5f`
- Metadata SHA-256 and complete original copyright block: see `juz30.json`.
- The original copyright block is also reproduced in `tanzil-license.txt`.

`juz30.json` contains surahs 78–114, with 564 complete verses. Arabic characters, diacritics, pause marks, and source openings are preserved verbatim. The reader only lays out the opening separately from numbered verse 1; joining the opening, a space, and verse 1's displayed body reconstructs its original source text exactly.

To regenerate, download the source text to `mobile/build/tahfidz-quran-source/quran-uthmani.txt` and the metadata to `quran-data.xml` in the same directory. Run from the repository root:

```sh
python scripts/import-tahfidz-quran.py --source-dir mobile/build/tahfidz-quran-source --output-dir mobile/assets/quran --retrieved-at 2026-11-05
```

The importer checks verse order/counts and compares every packaged Arabic verse with its source bytes after the JSON round trip. Review source changes before updating the bundled edition.

Font: **Amiri Quran**, https://github.com/google/fonts/tree/main/ofl/amiriquran

- File: `../fonts/AmiriQuran-Regular.ttf`
- SHA-256: `e2a47644762d16bdfb6d33e0d8db8c6ff30beae84150ef5a705316bbd829455c`
- License: `../fonts/amiri-quran-OFL.txt` (SIL Open Font License 1.1).
