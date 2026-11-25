#!/usr/bin/env python3
"""Verify inventory reproducibility and its tracked path/blob set, without executing blobs."""
import csv
import io
import pathlib
import subprocess
import sys


def git(*args):
    return subprocess.check_output(["git", *args])


def main():
    if len(sys.argv) != 3:
        sys.exit("uso: python tests/audit/rei73-modulos-verify.py <commit> <csv>")
    commit = git("rev-parse", "--verify", sys.argv[1] + "^{commit}").decode().strip()
    checked_out = pathlib.Path(sys.argv[2]).read_bytes()
    # Git may check out text with CRLF; compare the canonical LF representation.
    actual = checked_out.replace(b"\r\n", b"\n")
    if b"\r" in actual:
        sys.exit("CSV contém CR isolado")
    generated = subprocess.check_output([sys.executable, "tests/audit/rei73-modulos-inventory.py", commit])
    if actual != generated:
        sys.exit("CSV normalizado para LF difere da regeneração byte a byte")
    rows = list(csv.DictReader(io.StringIO(actual.decode("utf-8"), newline="")))
    expected = {}
    for entry in git("ls-tree", "-rz", "--full-tree", commit, "--", "app/", "services/").split(b"\0"):
        if not entry:
            continue
        info, path = entry.split(b"\t", 1)
        mode, kind, oid = info.decode("ascii").split()
        if kind == "blob" and mode != "120000":
            expected[path.decode("utf-8")] = oid
    observed = {row["caminho"]: row["git_blob"] for row in rows}
    if len(observed) != len(rows) or observed != expected or any(row["commit"] != commit for row in rows):
        sys.exit("caminhos, blobs ou commit não correspondem à árvore")
    print(f"OK: {len(rows)} caminhos e blobs correspondem a {commit}; CSV em LF reproduzido byte a byte")


if __name__ == "__main__":
    main()
