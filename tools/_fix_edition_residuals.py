#!/usr/bin/env python3
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIR_NAMES = {"vendor", "node_modules", ".git", "storage", "tools", "public"}
EXTS = {".php", ".json", ".md", ".xml", ".yml", ".yaml"}

REPLS = [
    ("EditionFinalizeController", "EventFinalizeController"),
    ("EditionPosterController", "EventPosterController"),
    ("EditionPosterInvalidationGuard", "EventPosterInvalidationGuard"),
    ("indexForEdition", "indexForEvent"),
    ("forEdition(", "forEvent("),
    ("function forEdition", "function forEvent"),
    ("{edition}", "{event}"),
    ("whereNumber('edition')", "whereNumber('event')"),
    ('whereNumber("edition")', 'whereNumber("event")'),
    ("Manager: Editions", "Manager: Events"),
    ("App: Editions", "App: Events"),
    ('title="Editions"', 'title="Events"'),
    ("Editions deleted", "Events deleted"),
]

CLASS_TAIL = re.compile(r"\bEdition([A-Z][A-Za-z0-9_]*)")


def iter_files():
    for path in ROOT.rglob("*"):
        try:
            if not path.is_file() or path.is_symlink():
                continue
        except OSError:
            continue
        if any(part in SKIP_DIR_NAMES for part in path.parts):
            continue
        if "migrations" in path.parts:
            continue
        if path.suffix not in EXTS and not path.name.endswith(".blade.php"):
            continue
        yield path


def main() -> None:
    updated = 0
    for path in iter_files():
        text = path.read_text(encoding="utf-8")
        orig = text
        for old, new in REPLS:
            text = text.replace(old, new)
        text = CLASS_TAIL.sub(r"Event\1", text)
        text = text.replace("EventEvent", "Event")
        # Variable rename $edition → $event (careful with comments)
        text = re.sub(r"\$edition\b", "$event", text)
        text = text.replace("PadelEvent $edition", "PadelEvent $event")
        if text != orig:
            path.write_text(text, encoding="utf-8")
            updated += 1
    print(f"updated {updated} files")


if __name__ == "__main__":
    main()
