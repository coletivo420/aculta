"""Unit tests for ACULTA's staging-only Private Message source preparer."""
from __future__ import annotations

import hashlib
import importlib.util
import io
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / "prepare_private_message_port.py"
spec = importlib.util.spec_from_file_location("prepare_private_message_port", SCRIPT)
assert spec is not None and spec.loader is not None
port = importlib.util.module_from_spec(spec)
spec.loader.exec_module(port)


def tar_fixture(members: dict[str, bytes]) -> bytes:
    blob = io.BytesIO()
    with tarfile.open(fileobj=blob, mode="w:gz") as archive:
        for name, content in members.items():
            info = tarfile.TarInfo(name)
            info.size = len(content)
            archive.addfile(info, io.BytesIO(content))
    return blob.getvalue()


class PreparePrivateMessagePortTest(unittest.TestCase):
    def test_classification_excludes_notify_and_uninstallers(self):
        self.assertEqual(port.classify("modules/private_message_notify/src/Service/X.php"), "excluded_optional")
        self.assertEqual(port.classify("src/Service/PrivateMessageUninstaller.php"), "merge_manual")
        self.assertEqual(port.classify("src/Entity/PrivateMessage.php"), "ready_not_active")
        self.assertEqual(port.classify("private_message.services.yml"), "merge_manual")

    def test_namespace_changes_do_not_touch_persisted_ids(self):
        source = b'namespace Drupal\\private_message\\Entity; /* Drupal\\Tests\\private_message\\ */ id="private_message_thread";'
        result, count = port.transform("src/Entity/Test.php", source)
        self.assertEqual(count, 2)
        self.assertIn(b"Drupal\\aculta_messenger\\Entity", result)
        self.assertIn(b'id="private_message_thread"', result)

    def test_rejects_tampered_archive_before_writing(self):
        with tempfile.TemporaryDirectory() as tmp:
            tarball = Path(tmp) / "bad.tar.gz"
            tarball.write_bytes(b"not the signed upstream")
            with self.assertRaisesRegex(ValueError, "SHA256|tarball"):
                port.run(tarball, Path(tmp) / "output", None)
            self.assertFalse((Path(tmp) / "output").exists())

    def test_stages_fixture_without_enabling_module(self):
        members = {
            "private_message/LICENSE.txt": b"GPL-2.0-or-later\n",
            "private_message/src/Entity/PrivateMessage.php": b"<?php namespace Drupal\\private_message\\Entity;\n",
            "private_message/private_message.services.yml": b"services:\n",
            "private_message/modules/private_message_notify/test.php": b"<?php\n",
        }
        with tempfile.TemporaryDirectory() as tmp:
            tarball = Path(tmp) / "fixture.tar.gz"
            tarball.write_bytes(tar_fixture(members))
            out = Path(tmp) / "output"
            with patch.object(port, "UPSTREAM_SHA256", hashlib.sha256(tarball.read_bytes()).hexdigest()):
                result = port.run(tarball, out, None)
            self.assertEqual(len(result["items"]), 4)
            self.assertTrue((out / "ready/src/Entity/PrivateMessage.php").is_file())
            self.assertTrue((out / "manual/private_message.services.yml").is_file())
            self.assertFalse((out / "ready/modules/private_message_notify/test.php").exists())
            self.assertEqual(result["namespace_edits"], 1)
            self.assertEqual(result["status"], "STAGING ONLY - DO NOT DEPLOY OR ENABLE")

    def test_rejects_unsafe_member_path(self):
        content = tar_fixture({
            "private_message/LICENSE.txt": b"GPL\n",
            "private_message/../../outside.txt": b"bad\n",
        })
        with tempfile.TemporaryDirectory() as tmp:
            tarball = Path(tmp) / "fixture.tar.gz"
            tarball.write_bytes(content)
            with patch.object(port, "UPSTREAM_SHA256", hashlib.sha256(content).hexdigest()):
                with self.assertRaisesRegex(ValueError, "Unsafe|unsafe"):
                    port.run(tarball, Path(tmp) / "output", None)


if __name__ == "__main__":
    unittest.main()
