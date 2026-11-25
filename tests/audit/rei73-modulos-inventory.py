#!/usr/bin/env python3
"""List tracked app/ and services/ blobs and textual markers; never execute them."""
import csv
import re
import subprocess
import sys


def git(*args):
    return subprocess.check_output(["git", *args])


PATTERNS = {
    "include-require": r"\b(?:include|include_once|require|require_once)\b",
    "import": r"^\s*(?:use\s+[^;(]+;|import\s|from\s+\S+\s+import\s)",
    "auth-politica": r"ApiAccessGuard|AccessEvaluator|PageAccessRegistry|EffectiveAccessProfiles|requireAuth|isAuthenticated",
    "sessao": r"session_start|\$_SESSION|SessionManager",
    "csrf": r"csrf",
    "metodo-http": r"REQUEST_METHOD",
    "persistencia": r"\bPDO\b|\b(?:SELECT|INSERT|UPDATE|DELETE)\b|Conection|beginTransaction|commit\(|rollBack\(",
    "rede": r"curl_|file_get_contents\s*\(|https?://",
    "erros": r"\b(?:catch|throw|error_log)\b",
}


def main():
    if len(sys.argv) != 2:
        sys.exit("uso: python tests/audit/rei73-modulos-inventory.py <commit>")
    # Use stable bytes even when Python runs on Windows with newline translation.
    sys.stdout.reconfigure(encoding="utf-8", newline="\n")
    commit = git("rev-parse", "--verify", sys.argv[1] + "^{commit}").decode().strip()
    writer = csv.writer(sys.stdout, lineterminator="\n")
    writer.writerow(["commit", "familia", "tipo", "caminho", "git_blob", "marcadores_textuais_linhas"])
    for entry in git("ls-tree", "-rz", "--full-tree", commit, "--", "app/", "services/").split(b"\0"):
        if not entry:
            continue
        info, path_bytes = entry.split(b"\t", 1)
        mode, kind, oid = info.decode("ascii").split()
        path = path_bytes.decode("utf-8")
        if kind != "blob" or mode == "120000":
            continue
        parts = path.split("/")
        family = "/".join(parts[:3] if parts[:2] == ["app", "views"] and len(parts) > 3 else parts[:2])
        extension = parts[-1].rsplit(".", 1)
        filetype = extension[-1].lower() if len(extension) == 2 else "sem-extensao"
        content = git("cat-file", "blob", oid).decode("utf-8", errors="replace")
        marks = []
        for label, pattern in PATTERNS.items():
            lines = [str(n) for n, line in enumerate(content.splitlines(), 1) if re.search(pattern, line, re.I)]
            if lines:
                marks.append(label + ":" + ",".join(lines))
        writer.writerow([commit, family, filetype, path, oid, "; ".join(marks) or "sem marcador pesquisado"])


if __name__ == "__main__":
    main()
