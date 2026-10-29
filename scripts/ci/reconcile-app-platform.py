#!/usr/bin/env python3
"""Patch a live App Platform spec: DOCR repo + digest; optionally add missing components from contract."""

from __future__ import annotations

import argparse
import copy
import sys
from typing import Any

import yaml


def load_yaml(path: str) -> dict[str, Any]:
    with open(path, encoding="utf-8") as f:
        data = yaml.safe_load(f)
    if not isinstance(data, dict):
        raise SystemExit(f"expected mapping in {path}")
    return data


def dump_yaml(path: str, data: dict[str, Any]) -> None:
    with open(path, "w", encoding="utf-8") as f:
        yaml.safe_dump(data, f, default_flow_style=False, sort_keys=False)


def component_lists(spec: dict[str, Any]) -> list[tuple[str, list[dict[str, Any]]]]:
    out: list[tuple[str, list[dict[str, Any]]]] = []
    for key in ("services", "workers", "jobs"):
        items = spec.get(key)
        if isinstance(items, list):
            out.append((key, items))
    return out


def find_component(
    spec: dict[str, Any], name: str
) -> tuple[str, dict[str, Any]] | None:
    for key, items in component_lists(spec):
        for item in items:
            if isinstance(item, dict) and item.get("name") == name:
                return key, item
    return None


def set_docr_image(component: dict[str, Any], repository: str, digest: str) -> None:
    image = component.get("image")
    if not isinstance(image, dict):
        image = {}
        component["image"] = image
    # Drop fields that conflict with DOCR digest pinning.
    for key in (
        "tag",
        "registry",
        "registry_credentials",
        "deploy_on_push",
    ):
        image.pop(key, None)
    image["registry_type"] = "DOCR"
    image["repository"] = repository
    image["digest"] = digest


def apply_images(
    spec: dict[str, Any], names: list[str], repository: str, digest: str
) -> list[str]:
    updated: list[str] = []
    for name in names:
        found = find_component(spec, name)
        if not found:
            continue
        _, component = found
        set_docr_image(component, repository, digest)
        updated.append(name)
    return updated


def ensure_from_contract(
    live: dict[str, Any],
    contract: dict[str, Any],
    names: list[str],
    repository: str,
    digest: str,
) -> list[str]:
    """Add missing named components from contract (image only rewritten). Never copy envs/dbs."""
    added: list[str] = []
    for name in names:
        if find_component(live, name):
            continue
        found = find_component(contract, name)
        if not found:
            raise SystemExit(
                f"component {name!r} missing from live spec and contract"
            )
        key, component = found
        block = copy.deepcopy(component)
        set_docr_image(block, repository, digest)
        live.setdefault(key, [])
        if not isinstance(live[key], list):
            raise SystemExit(f"live spec {key} is not a list")
        live[key].append(block)
        added.append(name)
    return added


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--live", required=True, help="Path to live app spec YAML")
    parser.add_argument("--out", required=True, help="Path to write patched spec")
    parser.add_argument(
        "--repository",
        required=True,
        help="DOCR repository, e.g. padeliz/padeliz-frontend-manager",
    )
    parser.add_argument(
        "--digest",
        required=True,
        help="Image digest including sha256: prefix",
    )
    parser.add_argument(
        "--components",
        required=True,
        help="Comma-separated component names to pin (e.g. web or web,worker,scheduler,migrate)",
    )
    parser.add_argument(
        "--contract",
        default="",
        help="Optional contract YAML to copy missing components from",
    )
    args = parser.parse_args()

    digest = args.digest.strip()
    if not digest.startswith("sha256:"):
        raise SystemExit(f"digest must start with sha256:, got {digest!r}")

    names = [n.strip() for n in args.components.split(",") if n.strip()]
    if not names:
        raise SystemExit("no component names provided")

    live = load_yaml(args.live)
    updated = apply_images(live, names, args.repository, digest)
    added: list[str] = []
    if args.contract:
        contract = load_yaml(args.contract)
        added = ensure_from_contract(
            live, contract, names, args.repository, digest
        )

    missing = [n for n in names if n not in updated and n not in added]
    if missing:
        raise SystemExit(
            "components still missing after patch "
            f"(updated={updated}, added={added}, missing={missing})"
        )

    dump_yaml(args.out, live)
    print(
        f"patched spec -> {args.out} "
        f"(updated={updated}, added={added}, repository={args.repository}, digest={digest})"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
