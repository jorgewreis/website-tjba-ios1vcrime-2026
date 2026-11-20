"""Prepare an empty vendor directory only after validating bind mount inputs."""
import os
from pathlib import Path
import stat
import sys


def prepare(workspace: Path) -> None:
    root = Path(os.path.abspath(workspace))
    if root.resolve(strict=True) != root or root != Path.cwd():
        raise RuntimeError("workspace contém link simbólico")
    for name in ("composer.json", "composer.lock"):
        item = root / name
        mode = item.lstat().st_mode
        if not stat.S_ISREG(mode) or item.resolve(strict=True) != item:
            raise RuntimeError("manifesto não é arquivo regular")
    # mkdir is exclusive: a preexisting directory, symlink or junction is refused.
    os.mkdir(root / "vendor", mode=0o700)
    vendor = root / "vendor"
    if not stat.S_ISDIR(vendor.lstat().st_mode) or vendor.resolve(strict=True) != vendor:
        raise RuntimeError("vendor não é diretório seguro")


if __name__ == "__main__":
    try:
        prepare(Path(os.environ.get("PWD", os.getcwd())))
    except (OSError, RuntimeError):
        print("Entradas Composer recusadas.", file=sys.stderr)
        raise SystemExit(1)
    print("PASS entradas Composer sem links; vendor novo")
