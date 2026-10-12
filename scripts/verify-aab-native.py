"""Read-only AAB checks: ZIP integrity, 64-bit ELF alignment and AOT markers.

This is NOT bundletool validation, APK zipalign validation, or a runtime test.
The raw RELRO-end metric is deliberately informational: a suffix/entire LOAD
segment can be safe even without a 16 KB-aligned RELRO end. Use VerifyAabElf.java
with Android Studio's checker for its section-aware LOAD/RELRO verification.
Run with a trusted Python 3: python scripts/verify-aab-native.py path/to/app.aab
No files, keystores, or credentials are extracted/written by this script.
"""

import hashlib
import json
import struct
import sys
import zipfile
from pathlib import Path


def check_bundle(path):
    libraries = []
    aot = []
    markers = [
        "https://nusa.smpn2padangpanjang.sch.id/api/v1/",
        "lampiran-perilaku-sts",
        "sts-save",
        "login-account-deletion",
        "profile-account-deletion",
        "penghapusan-akun",
        "kebijakan-privasi",
    ]
    with zipfile.ZipFile(path) as bundle:
        names = bundle.namelist()
        if len(names) != len(set(names)):
            raise ValueError("Duplicate ZIP entries")
        if names.count("META-INF/MANIFEST.MF") != 1:
            raise ValueError("Expected exactly one signing manifest")
        bad = bundle.testzip()
        if bad:
            raise ValueError("ZIP CRC failure: " + bad)
        for name in names:
            if not name.startswith("base/lib/") or not name.endswith(".so"):
                continue
            data = bundle.read(name)
            if name.endswith("/libapp.so"):
                found = {marker: marker.encode() in data for marker in markers}
                aot.append({"library": name, "markers": found})
            if data[:4] != b"\x7fELF" or data[4] != 2:
                continue  # Only 64-bit ELF is subject to this check.
            order = "<" if data[5] == 1 else ">"
            offset = struct.unpack_from(order + "Q", data, 32)[0]
            size, count = struct.unpack_from(order + "HH", data, 54)
            loads, relro = [], []
            for index in range(count):
                kind, _, file_offset, virtual, _, _, memory_size, align = struct.unpack_from(
                    order + "IIQQQQQQ", data, offset + index * size
                )
                if kind == 1:
                    loads.append({
                        "alignment": align,
                        "aligned_16kb": align >= 16384 and (virtual - file_offset) % 16384 == 0,
                    })
                if kind == 0x6474E552:
                    relro.append({"end_aligned_16kb": (virtual + memory_size) % 16384 == 0})
            if not loads:
                raise ValueError("ELF without LOAD segments: " + name)
            libraries.append({"library": name, "loads": loads, "relro": relro})
    result = {
        "file": str(path.resolve()),
        "bytes": path.stat().st_size,
        "sha256": sha256(path),
        "zip_integrity": True,
        "elf_64bit_count": len(libraries),
        "load_alignment_16kb": bool(libraries) and all(
            segment["aligned_16kb"] for lib in libraries for segment in lib["loads"]
        ),
        "raw_relro_end_alignment_16kb_informational": all(
            segment["end_aligned_16kb"] for lib in libraries for segment in lib["relro"]
        ),
        "libraries": libraries,
        "aot": aot,
    }
    print(json.dumps(result, indent=2))
    return 0 if result["load_alignment_16kb"] and all(
        all(lib["markers"].values()) for lib in aot
    ) and aot else 1


def sha256(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


if __name__ == "__main__":
    sys.exit(check_bundle(Path(sys.argv[1])))
