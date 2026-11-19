#!/usr/bin/env python3
"""Stage the verified Private Message 4.0.0-alpha5 source for ACULTA.

Never writes to the active Drupal module, Composer, DB or Runtime.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import sys
import tarfile

UPSTREAM_SHA256 = "488090c43d270ea76f42440cb800575ab5f9f830d9456dd81153236f5c308925"
TEXT_SUFFIXES = {".php", ".twig", ".yml", ".yaml", ".js", ".css", ".scss", ".md", ".txt", ".inc"}
READY_PREFIXES = ("src/", "tests/src/", "js/", "css/", "templates/", "images/")
DANGEROUS_FILES = {
    "src/Service/PrivateMessageUninstaller.php",
    "src/Service/PrivateMessageUninstallerInterface.php",
    "src/Form/AdminUninstallForm.php",
    "src/Drush/Commands/PrivateMessageCommands.php",
}
MERGE_FILES = {
    "private_message.module", "private_message.install", "private_message.theme.inc",
    "private_message.tokens.inc", "private_message.routing.yml", "private_message.services.yml",
    "private_message.permissions.yml", "private_message.libraries.yml",
    "private_message.links.menu.yml", "private_message.links.action.yml",
    "private_message.links.task.yml", "private_message.links.contextual.yml",
    "private_message.post_update.php", "private_message.views.inc",
    "private_message.api.php", "private_message.info.yml",
}


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def classify(path: str) -> str:
    if path.startswith("modules/private_message_notify/"):
        return "excluded_optional"
    if path in MERGE_FILES or path in DANGEROUS_FILES or path.startswith("config/"):
        return "merge_manual"
    if path.startswith(READY_PREFIXES):
        return "ready_not_active"
    return "reference_only"


def transform(path: str, content: bytes) -> tuple[bytes, int]:
    """Replace class namespaces, never persisted entity/route/service IDs."""
    if Path(path).suffix not in TEXT_SUFFIXES:
        return content, 0
    text = content.decode("utf-8")
    replacements = (
        ("Drupal\\Tests\\private_message\\", "Drupal\\Tests\\aculta_messenger\\"),
        ("Drupal\\private_message\\", "Drupal\\aculta_messenger\\"),
    )
    count = 0
    for old, new in replacements:
        count += text.count(old)
        text = text.replace(old, new)
    return text.encode("utf-8"), count


def run(tarball: Path, output: Path, module_root: Path | None = None) -> dict:
    if not tarball.is_file():
        raise ValueError(f"Tarball not found: {tarball}")
    digest = sha256(tarball.read_bytes())
    if digest != UPSTREAM_SHA256:
        raise ValueError(f"Incorrect upstream archive SHA256: {digest}")
    if output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise ValueError(f"Output must be an empty directory: {output}")
    manifest: dict = {
        "source": "private_message-4.0.0-alpha5.tar.gz",
        "upstream_sha256": digest,
        "module": "aculta_messenger",
        "status": "STAGING ONLY - DO NOT DEPLOY OR ENABLE",
        "items": [],
        "collisions": [],
        "namespace_edits": 0,
        "cautions": [
            "Never activate ready source beside installed private_message entities.",
            "Merge services, routes, hooks, config dependencies and schema manually.",
            "Transfer installed entity providers and field storage ownership only on a DB clone.",
            "Never uninstall private_message before snapshot, migration and rollback gates.",
            "Do not activate private_message_notify or email templates containing message bodies.",
            "Audit JS for no-jQuery policy, accessibility and ACULTA UI Suite.",
            "Upstream tests do not replace integration and migration tests.",
        ],
    }
    staged: list[tuple[Path, bytes]] = []
    with tarfile.open(tarball, mode="r:gz") as archive:
        for member in archive.getmembers():
            if not member.isfile():
                if member.issym() or member.islnk() or member.isdev():
                    raise ValueError(f"Unsafe tar member: {member.name}")
                continue
            raw = PurePosixPath(member.name)
            if raw.is_absolute() or ".." in raw.parts or not raw.parts or raw.parts[0] != "private_message":
                raise ValueError(f"Unsafe tar path: {member.name}")
            relative = PurePosixPath(*raw.parts[1:]).as_posix()
            if not relative or relative == ".":
                continue
            stream = archive.extractfile(member)
            if stream is None:
                raise ValueError(f"Unreadable tar member: {member.name}")
            original = stream.read()
            category = classify(relative)
            item = {"path": relative, "classification": category, "sha256": sha256(original)}
            if category != "excluded_optional":
                content, edits = transform(relative, original) if category == "ready_not_active" else (original, 0)
                manifest["namespace_edits"] += edits
                target = Path("ready" if category == "ready_not_active" else "manual" if category == "merge_manual" else "reference") / relative
                if category == "ready_not_active" and module_root and (module_root / relative).exists():
                    manifest["collisions"].append(relative)
                staged.append((target, content))
                item.update({"staged_sha256": sha256(content), "namespace_edits": edits})
            manifest["items"].append(item)
    if not any(x["path"] == "LICENSE.txt" for x in manifest["items"]):
        raise ValueError("Missing upstream LICENSE.txt")
    for relative, content in staged:
        target = output / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(content)
    (output / "PORT-MANIFEST.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    (output / "README.md").write_text(
        "# Private Message - ACULTA staging\n\n"
        "Inactive source preparation only. No Drupal runtime changes have been applied.\n"
        "ready/ holds relocated classes/assets/tests; manual/ requires intentional merging; "
        "reference/ records upstream materials and license. Do not copy ready/ to an active "
        "site while the old entity provider is still installed. See PORT-MANIFEST.json.\n",
        encoding="utf-8",
    )
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--tar", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--module-root", type=Path, default=None)
    args = parser.parse_args()
    try:
        result = run(args.tar, args.output, args.module_root)
    except (OSError, ValueError, UnicodeError, tarfile.TarError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2
    counts: dict[str, int] = {}
    for item in result["items"]:
        category = item["classification"]
        counts[category] = counts.get(category, 0) + 1
    print(json.dumps({
        "source_sha256": result["upstream_sha256"],
        "counts": counts,
        "namespace_edits": result["namespace_edits"],
        "collisions": result["collisions"],
        "output": str(args.output),
    }, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
