#!/usr/bin/env python3
"""Inventory tracked non-API surfaces from an immutable Git tree; never runs them."""
import csv
import re
import subprocess
import sys


def git(*args):
    return subprocess.check_output(["git", *args])


def category(path):
    if path.startswith("public/") and not path.startswith("public/api/") and path.endswith(".php"):
        return "public-php"
    if path.startswith("api/") and path.endswith(".php"):
        return "api-legada"
    if path.startswith("scripts/"):
        return "scripts"
    if path.startswith("tools/") and path.endswith((".php", ".py", ".sh", ".ps1")):
        return "tools-executaveis"
    return None


PATTERNS = {
    "sapi-cli": r"PHP_SAPI|php_sapi_name",
    "sessao": r"session_start|\$_SESSION|SessionManager",
    "auth": r"ApiAccessGuard|AccessEvaluator|PageAccessRegistry|isAuthenticated|requireAuth|checkAuth|auth_guard",
    "csrf": r"csrf|CSRF",
    "metodo": r"REQUEST_METHOD",
    "config-ou-conexao": r"config\.php|Conection|PDO|Database",
    "crontab-ou-agendador": r"crontab|ScheduledTask|schtasks",
    "envio-email": r"sendResumo|Mailer|mail\(",
}


def main():
    if len(sys.argv) != 2:
        sys.exit("uso: python tests/audit/rei73-nonapi-inventory.py <commit>")
    commit = git("rev-parse", "--verify", sys.argv[1] + "^{commit}").decode().strip()
    entries = git("ls-tree", "-rz", "--full-tree", commit).split(b"\0")
    writer = csv.writer(sys.stdout, lineterminator="\n")
    writer.writerow(["commit", "categoria", "caminho", "git_blob", "marcadores_textuais_linhas"])
    for entry in entries:
        if not entry:
            continue
        info, path_bytes = entry.split(b"\t", 1)
        mode, kind, oid = info.decode("ascii").split()
        path = path_bytes.decode("utf-8")
        group = category(path)
        if not group or kind != "blob" or mode == "120000":
            continue
        content = git("cat-file", "blob", oid).decode("utf-8", errors="replace")
        marks = []
        for label, pattern in PATTERNS.items():
            lines = [str(n) for n, line in enumerate(content.splitlines(), 1) if re.search(pattern, line, re.I)]
            if lines:
                marks.append(label + ":" + ",".join(lines))
        writer.writerow([commit, group, path, oid, "; ".join(marks) or "sem marcador pesquisado"])


if __name__ == "__main__":
    main()
