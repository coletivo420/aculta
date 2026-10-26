"""Synthetic tests for stable Growroom legacy author identities."""
import importlib.util
import json
import tempfile
from collections import Counter
from pathlib import Path
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "growroom" / "build_mapping.py"
SPEC = importlib.util.spec_from_file_location("aculta_growroom_build_mapping", SCRIPT)
MAPPING = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MAPPING)
VERIFY_SCRIPT = SCRIPT.with_name("verificar_importacao.py")
VERIFY_SPEC = importlib.util.spec_from_file_location("aculta_growroom_verify", VERIFY_SCRIPT)
VERIFY = importlib.util.module_from_spec(VERIFY_SPEC)
assert VERIFY_SPEC.loader is not None
VERIFY_SPEC.loader.exec_module(VERIFY)


class LegacyAuthorOriginIdTest(unittest.TestCase):

    def test_prefers_exact_ips4_source_id(self):
        self.assertEqual("ips4-12345", MAPPING.legacy_author_origin_id("Pessoa", ips_id="12345"))

    def test_prefers_exact_thw_source_id(self):
        self.assertEqual("thw-678", MAPPING.legacy_author_origin_id("Pessoa", thw_uid="678"))

    def test_name_fallback_is_stable_and_normalized(self):
        first = MAPPING.legacy_author_origin_id("João da Silva")
        second = MAPPING.legacy_author_origin_id("JOAO   da Silva")
        self.assertEqual(first, second)
        self.assertEqual(64, len(first))
        self.assertTrue(first.startswith("nome-"))

    def test_rejects_conflicting_platform_ids(self):
        with self.assertRaises(ValueError):
            MAPPING.legacy_author_origin_id("Pessoa", ips_id="1", thw_uid="2")

    def test_rejects_email_and_unusable_name_without_source_id(self):
        for name in ("autor@example.org", "!!!"):
            with self.subTest(name=name), self.assertRaises(ValueError):
                MAPPING.legacy_author_origin_id(name)

    def test_unresolved_authors_remain_visible_in_manifest(self):
        unresolved = {"!!!": {"nomes": Counter({"!!!": 1}), "topicos": {"topic-z"}}}
        self.assertEqual(
            [{"origem_id": None, "nome": "!!!", "nomes_observados": ["!!!"],
              "topicos": ["topic-z"], "estado": "revisar"}],
            MAPPING.registros_autor_origem({}, unresolved),
        )

    def test_manifest_gate_accepts_synthetic_record(self):
        source_id = MAPPING.legacy_author_origin_id("Pessoa sem ID")
        record = {"origem_id": source_id, "nome": "Pessoa sem ID", "estado": "pronto", "topicos": ["topic-1"]}
        with tempfile.TemporaryDirectory() as directory:
            VERIFY.R = directory
            mapping_dir = Path(directory) / "mapeamento"
            mapping_dir.mkdir()
            (mapping_dir / "autor-origem.json").write_text(json.dumps([record]), encoding="utf-8")
            (Path(directory) / "conjunto-importacao.json").write_text(
                json.dumps([{"url": "topic-1"}]), encoding="utf-8")
            self.assertTrue(VERIFY.c10_autores_origem({"topic-1": []})["ok"])

    def test_manifest_gate_rejects_topics_outside_selected_set(self):
        source_id = MAPPING.legacy_author_origin_id("Pessoa")
        record = {"origem_id": source_id, "nome": "Pessoa", "estado": "pronto", "topicos": ["topic-2"]}
        with tempfile.TemporaryDirectory() as directory:
            VERIFY.R = directory
            mapping_dir = Path(directory) / "mapeamento"
            mapping_dir.mkdir()
            (mapping_dir / "autor-origem.json").write_text(json.dumps([record]), encoding="utf-8")
            (Path(directory) / "conjunto-importacao.json").write_text(
                json.dumps([{"url": "topic-1"}]), encoding="utf-8")
            result = VERIFY.c10_autores_origem({"topic-1": [], "topic-2": []})
            self.assertFalse(result["ok"])
            self.assertEqual(1, result["fora_selecao"])

    def test_manifest_gate_rejects_unresolved_origin(self):
        record = {"origem_id": None, "nome": "Pessoa", "estado": "revisar", "topicos": ["topic-1"]}
        with tempfile.TemporaryDirectory() as directory:
            VERIFY.R = directory
            mapping_dir = Path(directory) / "mapeamento"
            mapping_dir.mkdir()
            (mapping_dir / "autor-origem.json").write_text(json.dumps([record]), encoding="utf-8")
            (Path(directory) / "conjunto-importacao.json").write_text(
                json.dumps([{"url": "topic-1"}]), encoding="utf-8")
            result = VERIFY.c10_autores_origem({"topic-1": []})
            self.assertFalse(result["ok"])
            self.assertEqual(1, result["pendentes_revisao"])

    def test_origin_records_are_deterministic_and_keep_name_variants(self):
        source = {
            "nome-b": {
                "nomes": Counter({"Zé": 1, "Jose": 2}),
                "topicos": {"topic-b", "topic-a"},
            },
        }
        self.assertEqual(
            [{
                "origem_id": "nome-b",
                "nome": "Jose",
                "nomes_observados": ["Jose", "Zé"],
                "topicos": ["topic-a", "topic-b"],
                "estado": "pronto",
            }],
            MAPPING.registros_autor_origem(source),
        )


if __name__ == "__main__":
    unittest.main()
