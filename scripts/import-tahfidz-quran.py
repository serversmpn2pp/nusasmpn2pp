"""Import official Tanzil text verbatim; package Juz 30 for native offline use.

Download the source files from the URLs recorded below, then run:
python scripts/import-tahfidz-quran.py --source-dir mobile/build/tahfidz-quran-source \
    --output-dir mobile/assets/quran --retrieved-at 2026-11-05
No Arabic normalization, diacritic removal, or spelling substitutions are made.
"""

import argparse
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

TEXT_URL = "https://tanzil.net/pub/download/index.php?quranType=uthmani&outType=txt-2&marks=true&sajdah=true&tatweel=true&agree=true"
METADATA_URL = "https://tanzil.net/res/text/metadata/quran-data.xml"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    parser.add_argument("--retrieved-at", required=True)
    args = parser.parse_args()
    raw = (args.source_dir / "quran-uthmani.txt").read_bytes()
    metadata = (args.source_dir / "quran-data.xml").read_bytes()
    lines = raw.decode("utf-8-sig").splitlines()
    license_start = next(i for i, line in enumerate(lines) if line.startswith("#"))
    copyright_notice = "\n".join(lines[license_start:])
    assert "Tanzil Quran Text (Uthmani, Version 1.1)" in copyright_notice
    assert "CHANGING IT IS NOT ALLOWED" in copyright_notice
    verses = {}
    for line in lines[:license_start]:
        if not line:
            continue
        surah, verse, text = line.split("|", 2)
        key = (int(surah), int(verse))
        assert key not in verses
        verses[key] = text
    assert len(verses) == 6236, "Unexpected source verse count"
    surah_meta = {int(s.attrib["index"]): s.attrib for s in ET.fromstring(metadata).findall("./suras/sura")}
    basmala = verses[1, 1]
    result = []
    for number in range(78, 115):
        meta = surah_meta[number]
        count = int(meta["ayas"])
        # Preserve each source opening exactly, including its diacritics.
        opening = " ".join(verses[number, 1].split(" ", 4)[:4])
        assert opening in (basmala, basmala[0] + "\u0651" + basmala[1:])
        assert verses[number, 1].startswith(opening + " ")
        result.append({"number": number, "name": meta["tname"], "arabic_name": meta["name"], "verse_count": count, "opening": opening,
                       "verses": [{"number": v, "text": verses[number, v]} for v in range(1, count + 1)]})
    assert len(result) == 37 and sum(s["verse_count"] for s in result) == 564
    source = {"name": "Tanzil Project", "edition": "Uthmani 1.1", "url": "https://tanzil.net/",
              "license_url": "https://tanzil.net/docs/text_license", "download_url": TEXT_URL,
              "metadata_url": METADATA_URL, "retrieved_at": args.retrieved_at,
              "text_sha256": hashlib.sha256(raw).hexdigest(), "metadata_sha256": hashlib.sha256(metadata).hexdigest(),
              "copyright_notice": copyright_notice}
    payload = {"source": source, "basmala": basmala, "surahs": result}
    encoded = json.dumps(payload, ensure_ascii=False, indent=2) + "\n"
    decoded = json.loads(encoded)
    for s in decoded["surahs"]:
        for v in s["verses"]:
            assert v["text"].encode("utf-8") == verses[s["number"], v["number"]].encode("utf-8")
    args.output_dir.mkdir(parents=True, exist_ok=True)
    (args.output_dir / "juz30.json").write_text(encoded, encoding="utf-8", newline="\n")
    (args.output_dir / "tanzil-license.txt").write_text(copyright_notice + "\n", encoding="utf-8", newline="\n")
    print(json.dumps({"surahs": len(result), "verses": 564, "source_sha256": source["text_sha256"],
                      "asset_sha256": hashlib.sha256(encoded.encode("utf-8")).hexdigest()}))


if __name__ == "__main__":
    main()
