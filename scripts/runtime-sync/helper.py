#!/usr/bin/env python3
""" Utilitario interno do ACULTA Runtime Sync v2.

Centraliza operacoes sensiveis de:
- Leitura, validacao e escrita atomica (0600, anti-symlink) do estado persistente
  e do historico de execucoes (schema v2), com redacao automatica de segredos.
- Classificacao cumulativa de risco (R0, R1, R2, R3, R4, RX) usando
  git diff --raw -z --find-renames e inspecao semantica de diffs (hooks,
  schemas, core.extension, composer.lock, operacoes destrutivas).
- Normalizacao segura de saidas do Drush (status, updatedb:status,
  config:status, deploy:hook-status).
- Geracao de planos estruturais verificaveis (structural-plan), incluindo
  deteccao de casos de ordem nao padrao (ex.: commerce_shipping).
- Preparacao e validacao de worktree isolada de Runtime (F4), sem executar
  cutover do Apache nem alterar symlinks servidos.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import sys
import tempfile
from pathlib import Path
from typing import Any

SCHEMA_VERSION = 2
RISK_ORDER = {"R0": 0, "R1": 1, "R2": 2, "R3": 3, "R4": 4, "RX": 5}
ORDER_RISK = {v: k for k, v in RISK_ORDER.items()}
VALID_OUTCOMES = {
    "PLANNED",
    "READY",
    "NOOP",
    "BLOCKED",
    "PARTIAL",
    "FAILED",
    "VALIDATED",
    "INDETERMINATE",
}
VALID_GATE_RESULTS = {"PASS", "FAIL", "SKIP", "DEFERRED", "N/A"}

SITE_KEYS_DEV = {
    "main": "https://aculta.toca.net.br",
    "account": "https://conta.aculta.toca.net.br",
    "support": "https://apoio.aculta.toca.net.br",
    "magazine": "https://revista.aculta.toca.net.br",
    "wiki": "https://wiki420.aculta.toca.net.br",
    "shop": "https://loja.aculta.toca.net.br",
    "courses": "https://cursos.aculta.toca.net.br",
    "forum": "https://growroom420.aculta.toca.net.br",
    "links": "https://links.aculta.toca.net.br",
    "eventos": "https://eventos.aculta.toca.net.br",
}

SECRET_PATTERNS = [
    re.compile(r"(?i)(password|passwd|pwd|secret|token|api[_-]?key|authorization)\s*[:=]\s*([^\s,;\"']+)"),
    re.compile(r"(?i)(mysql|pgsql|sqlite|redis|amqp|smtps?)://[^\s\"']+"),
    re.compile(r"\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b"),
]


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def redact_text(value: str) -> str:
    if not value:
        return ""
    out = value
    out = SECRET_PATTERNS[0].sub(r"\1=[REDACTED]", out)
    out = SECRET_PATTERNS[1].sub(r"\1://[REDACTED]", out)
    out = SECRET_PATTERNS[2].sub("[REDACTED_EMAIL]", out)
    return out


def sanitize_obj(obj: Any) -> Any:
    if isinstance(obj, str):
        return redact_text(obj)
    if isinstance(obj, list):
        return [sanitize_obj(x) for x in obj]
    if isinstance(obj, dict):
        cleaned: dict[str, Any] = {}
        for k, v in obj.items():
            if re.search(r"(?i)(password|secret|token|dsn|credential|private_key)", str(k)):
                cleaned[str(k)] = "[REDACTED]"
            else:
                cleaned[str(k)] = sanitize_obj(v)
        return cleaned
    return obj


def is_sha40(val: Any) -> bool:
    return isinstance(val, str) and bool(re.fullmatch(r"[0-9a-f]{40}", val))


def check_no_symlink_chain(target: Path, stop_at: Path) -> tuple[bool, str]:
    """Garante que nenhum componente entre stop_at e target seja symlink."""
    try:
        stop_resolved = stop_at.resolve(strict=False)
    except OSError as exc:
        return False, f"falha ao resolver raiz base: {exc}"

    curr = target
    to_check: list[Path] = []
    while True:
        to_check.append(curr)
        if curr == stop_at or curr == curr.parent:
            break
        curr = curr.parent

    for p in reversed(to_check):
        if p.is_symlink():
            return False, f"symlink inesperado recusado no caminho de estado: {p.name}"
    return True, "ok"


def ensure_secure_state_dir(state_dir: Path, repo_root: Path) -> tuple[bool, str]:
    ok, reason = check_no_symlink_chain(state_dir, repo_root)
    if not ok:
        return False, reason
    try:
        state_dir.mkdir(parents=True, exist_ok=True)
        if state_dir.is_symlink():
            return False, "diretorio de estado e um symlink"
        os.chmod(state_dir, 0o700)
        runs_dir = state_dir / "runs"
        runs_dir.mkdir(parents=True, exist_ok=True)
        if runs_dir.is_symlink():
            return False, "diretorio runs e um symlink"
        os.chmod(runs_dir, 0o700)
    except OSError as exc:
        return False, f"erro ao preparar diretorio de estado: {exc}"
    return True, "ok"


def atomic_write_json(path: Path, data: dict[str, Any], repo_root: Path) -> tuple[bool, str]:
    ok, reason = check_no_symlink_chain(path, repo_root)
    if not ok:
        return False, reason
    parent = path.parent
    ok, reason = ensure_secure_state_dir(parent if parent.name != "runs" else parent.parent, repo_root)
    if not ok:
        return False, reason
    if path.exists() and path.is_symlink():
        return False, f"arquivo alvo e symlink: {path.name}"

    sanitized = sanitize_obj(data)
    payload = json.dumps(sanitized, indent=2, ensure_ascii=True, sort_keys=False) + "\n"
    fd = -1
    tmp_path: Path | None = None
    try:
        fd, tmp_name = tempfile.mkstemp(prefix=f".{path.name}.", suffix=".tmp", dir=str(parent))
        tmp_path = Path(tmp_name)
        os.fchmod(fd, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            fd = -1
            fh.write(payload)
            fh.flush()
            os.fsync(fh.fileno())
        os.replace(str(tmp_path), str(path))
        os.chmod(path, 0o600)
    except OSError as exc:
        if fd != -1:
            try:
                os.close(fd)
            except OSError:
                pass
        if tmp_path and tmp_path.exists():
            try:
                tmp_path.unlink()
            except OSError:
                pass
        return False, f"falha na escrita atomica de {path.name}: {exc}"
    return True, "ok"


def default_state() -> dict[str, Any]:
    return {
        "schema_version": SCHEMA_VERSION,
        "updated_at": None,
        "remote_sha": "UNKNOWN",
        "checkout_sha": "UNKNOWN",
        "last_code_sync_sha": "UNKNOWN",
        "last_cache_success_sha": "UNKNOWN",
        "last_validated_sha": "UNKNOWN",
        "pending_structural_sha": None,
        "pending_structural_risk": None,
        "drift_baseline": {
            "updatedb_pending_count": None,
            "deploy_hook_pending_count": None,
            "config_drift_count": None,
            "config_drift_hash": None,
        },
        "last_operation": {
            "run_id": None,
            "mode": None,
            "outcome": "UNKNOWN",
            "phase": None,
            "timestamp": None,
            "risk": "RX",
            "summary": "Nenhum estado persistente validado registrado ainda.",
        },
        "history": [],
        "secrets_redacted": True,
    }


def read_state(state_dir: Path, repo_root: Path) -> tuple[dict[str, Any], str]:
    """Retorna (state_dict, status_code) onde status_code e OK, MISSING ou CORRUPT."""
    ok, reason = check_no_symlink_chain(state_dir, repo_root)
    if not ok:
        st = default_state()
        st["state_error"] = reason
        return st, "CORRUPT"

    state_file = state_dir / "state.json"
    if state_file.is_symlink():
        st = default_state()
        st["state_error"] = "state.json e um symlink"
        return st, "CORRUPT"
    if not state_file.exists():
        return default_state(), "MISSING"
    if not state_file.is_file():
        st = default_state()
        st["state_error"] = "state.json nao e arquivo regular"
        return st, "CORRUPT"

    try:
        raw = state_file.read_text(encoding="utf-8")
        parsed = json.loads(raw)
    except Exception as exc:
        st = default_state()
        st["state_error"] = f"JSON invalido em state.json: {exc}"
        return st, "CORRUPT"

    if not isinstance(parsed, dict) or parsed.get("schema_version") != SCHEMA_VERSION:
        st = default_state()
        st["state_error"] = f"schema_version incompativel ou ausente em state.json: {parsed.get('schema_version') if isinstance(parsed, dict) else 'nao-objeto'}"
        return st, "CORRUPT"

    for sha_key in ("remote_sha", "checkout_sha", "last_code_sync_sha", "last_cache_success_sha", "last_validated_sha"):
        val = parsed.get(sha_key, "UNKNOWN")
        if val is not None and val != "UNKNOWN" and not is_sha40(val):
            st = default_state()
            st["state_error"] = f"campo {sha_key} corrompido em state.json"
            return st, "CORRUPT"

    merged = default_state()
    merged.update(parsed)
    return merged, "OK"


def run_git(repo_root: Path, args: list[str], check: bool = False) -> subprocess.CompletedProcess[bytes]:
    return subprocess.run(
        ["git", *args],
        cwd=str(repo_root),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=check,
    )


def commit_exists(repo_root: Path, sha: str) -> bool:
    if not is_sha40(sha):
        return False
    res = run_git(repo_root, ["cat-file", "-e", f"{sha}^{{commit}}"])
    return res.returncode == 0


def is_ancestor(repo_root: Path, anc: str, desc: str) -> bool:
    if not (is_sha40(anc) and is_sha40(desc)):
        return False
    res = run_git(repo_root, ["merge-base", "--is-ancestor", anc, desc])
    return res.returncode == 0


def parse_raw_diff_z(raw_bytes: bytes) -> list[dict[str, Any]]:
    """Faz parse de `git diff --raw -z --find-renames` suportando newlines e espacos nos caminhos."""
    if not raw_bytes:
        return []
    tokens = raw_bytes.split(b"\x00")
    # O ultimo elemento apos split por \x00 costuma ser vazio
    if tokens and tokens[-1] == b"":
        tokens.pop()

    entries: list[dict[str, Any]] = []
    idx = 0
    n = len(tokens)
    while idx < n:
        header = tokens[idx].decode("utf-8", errors="surrogateescape")
        idx += 1
        if not header.startswith(":"):
            continue
        # Formato: :oldmode newmode oldsha newsha status
        parts = header[1:].split()
        if len(parts) < 5:
            continue
        old_mode, new_mode, old_sha, new_sha, status_full = parts[:5]
        status_code = status_full[0]
        score = int(status_full[1:]) if len(status_full) > 1 and status_full[1:].isdigit() else None

        path1 = tokens[idx].decode("utf-8", errors="surrogateescape") if idx < n else ""
        idx += 1
        path2: str | None = None
        if status_code in ("R", "C"):
            path2 = tokens[idx].decode("utf-8", errors="surrogateescape") if idx < n else ""
            idx += 1

        entries.append(
            {
                "old_mode": old_mode,
                "new_mode": new_mode,
                "old_sha": old_sha,
                "new_sha": new_sha,
                "status": status_code,
                "score": score,
                "path": path2 if path2 is not None else path1,
                "old_path": path1 if path2 is not None else None,
            }
        )
    return entries


def classify_single_path(path: str) -> tuple[str, str]:
    """Classifica um unico caminho em (nivel, motivo)."""
    if "\n" in path or "\r" in path or "\x00" in path:
        return "RX", "caminho contem caractere de controle ou quebra de linha"

    # R4: infra, settings, segredos, htaccess, dados privados
    if (
        path.startswith("web/sites/")
        or path.startswith("secrets/")
        or path.startswith("private/")
        or path in ("web/.htaccess", ".htaccess", "config/secrets-contract.json")
    ):
        return "R4", "infraestrutura, settings, segredos ou servidor web"

    # R3: estrutural Drupal (config/sync, install, post_update, deploy, info.yml, permissions, schemas, drush)
    if (
        path.startswith("config/sync/")
        or path.startswith("drush/")
        or path.endswith(".install")
        or path.endswith(".post_update.php")
        or path.endswith(".deploy.php")
        or path.endswith(".info.yml")
        or path.endswith(".permissions.yml")
        or path.endswith(".links.menu.yml")
        or path.endswith(".links.task.yml")
        or path.endswith(".links.action.yml")
        or "/config/install/" in path
        or "/config/schema/" in path
        or "/config/optional/" in path
        or path.startswith("recipes/")
    ):
        return "R3", "configuracao Drupal, manifesto, permissao, schema ou hook de instalacao/update/deploy"

    # R2: Composer, lockfiles, patches, npm/yarn, bibliotecas externas
    if (
        path in ("composer.json", "composer.lock", "patches-aculta.json", "patches.lock.json", "package.json", "package-lock.json", "yarn.lock", "pnpm-lock.yaml")
        or path.startswith("patches/")
        or path.startswith("web/libraries/")
        or path.endswith("/package.json")
        or path.endswith("/package-lock.json")
    ):
        return "R2", "dependencias, lockfile, patch ou biblioteca"

    # R0: exclusivamente documental ou testes automatizados (fora do runtime de producao/dev)
    if (
        path.startswith("docs/")
        # Workflows do GitHub Actions rodam no CI, nunca no servidor: sem efeito no Runtime.
        or path.startswith(".github/workflows/")
        or path.startswith(".agents/")
        or path.startswith(".claude/")
        or path.startswith(".codex/")
        or path in ("README.md", "TODO.md", "AGENTS.md", "CLAUDE.md", "phpunit.xml", "phpunit.performance.xml", "phpstan-baseline.neon")
        or path.startswith("tests/")
        or "/tests/" in path
        or "/docs/" in path
        or path.endswith("/README.md")
        or path.endswith("/TODO.md")
        or path.endswith("/CHANGELOG.md")
        or path.endswith("/AGENTS.md")
    ):
        return "R0", "documentacao, skills ou testes offline"

    # R1: codigo de aplicacao, templates, CSS, JS, SDC, services/routing comuns e scripts
    if (
        (path.startswith("web/modules/custom/") or path.startswith("web/themes/custom/"))
        and path.endswith((".php", ".module", ".theme", ".inc", ".twig", ".js", ".mjs", ".css", ".yml", ".json", ".svg"))
    ) or path.startswith("scripts/"):
        return "R1", "codigo, template, asset ou script do aplicativo"

    return "RX", "caminho fora das regras conhecidas do manifesto (fail-closed)"


def inspect_patch_semantics(repo_root: Path, base_sha: str, target_sha: str, entry: dict[str, Any]) -> tuple[str, list[str]]:
    """Inspeciona o conteudo do diff para detectar mudancas estruturais/destrutivas em arquivos .php/.module/.yml."""
    reasons: list[str] = []
    elevated = "R0"
    path = entry["path"]
    status = entry["status"]

    def bump(level: str, reason: str) -> None:
        nonlocal elevated
        if RISK_ORDER[level] > RISK_ORDER[elevated]:
            elevated = level
        if reason not in reasons:
            reasons.append(reason)

    # Gitlinks / submodules (mode 160000)
    if entry["old_mode"] == "160000" or entry["new_mode"] == "160000":
        bump("RX", f"submodulo/gitlink detectado em {path}")
        return elevated, reasons

    # Mudanca de modo executavel ou symlink (120000) fora de skills
    if (entry["old_mode"] == "120000" or entry["new_mode"] == "120000") and not (
        path.startswith(".claude/skills/") or path.startswith(".codex/skills/") or path == "docs/agent-skills"
    ):
        bump("R4", f"alteracao de symlink versionado em {path}")

    if entry["old_mode"] != "000000" and entry["new_mode"] != "000000" and entry["old_mode"] != entry["new_mode"]:
        if not path.startswith("scripts/"):
            bump("R2", f"alteracao de modo de arquivo ({entry['old_mode']} -> {entry['new_mode']}) em {path}")

    # Renomeacoes: classificar tambem o caminho antigo e elevar se envolver codigo/config estrutural
    if status in ("R", "C") and entry.get("old_path"):
        old_level, old_reason = classify_single_path(entry["old_path"])
        bump(old_level, f"origem de rename ({entry['old_path']}): {old_reason}")
        if path.startswith(("web/modules/custom/", "web/themes/custom/")) and path.endswith(".php"):
            # Rename de classe PHP pode exigir rebuild de container / autoload / mapeamento
            bump("R1", f"rename de classe/arquivo PHP ({entry['old_path']} -> {path})")

    # Remocao de modulo, servico, rota ou config
    if status == "D":
        if path.endswith((".services.yml", ".routing.yml", ".module")):
            bump("R3", f"remocao de arquivo estrutural de modulo ({path})")
        elif path.startswith("config/sync/"):
            bump("R4", f"remocao de configuracao sincronizada ({path})")

    # Inspecao de conteudo para arquivos relevantes
    if status != "D" and path.endswith((".php", ".module", ".install", ".yml", ".json")) and not (
        path.startswith("tests/") or "/tests/" in path
    ):
        diff_res = run_git(repo_root, ["diff", "-U0", base_sha, target_sha, "--", path])
        patch_text = diff_res.stdout.decode("utf-8", errors="replace")

        if path == "config/sync/core.extension.yml":
            if re.search(r"^[+-]\s+[a-zA-Z0-9_]+:\s*-?\d+", patch_text, re.M):
                bump("R4", "alteracao de modulos/temas habilitados em core.extension.yml")

        if path.endswith((".install", ".post_update.php", ".deploy.php")):
            if re.search(r"(?i)(dropTable|dropField|delete\(|uninstall|DROP\s+TABLE|TRUNCATE\s+TABLE)", patch_text):
                bump("R4", f"operacao potencialmente destrutiva em {path}")

        if path.endswith((".php", ".module")) and path.startswith("web/modules/custom/"):
            # Detectar novos hooks de schema/update/entity/field em arquivos PHP comuns
            if re.search(
                r"(#\[Hook\(['\"](schema|update_\d+|post_update_|entity_base_field_info|entity_bundle_field_info|entity_type_build|field_storage_config)['\"]|function\s+[a-z0-9_]+_(schema|update_\d+|post_update_))",
                patch_text,
            ):
                bump("R3", f"hook estrutural ou de schema detectado em {path}")

    return elevated, reasons


def classify_delta(
    repo_root: Path,
    base_sha: str,
    target_sha: str,
    state_status: str = "OK",
) -> dict[str, Any]:
    """Classifica o delta cumulativo entre base_sha e target_sha."""
    result: dict[str, Any] = {
        "base_sha": base_sha,
        "target_sha": target_sha,
        "risk": "RX",
        "allow_sync_code": False,
        "requires_cache_rebuild": False,
        "requires_structural_plan": True,
        "diff_available": False,
        "is_fast_forward": False,
        "file_count": 0,
        "counts_by_risk": {"R0": 0, "R1": 0, "R2": 0, "R3": 0, "R4": 0, "RX": 0},
        "files": [],
        "reasons": [],
        "affected_site_keys": [],
        "special_runbooks": [],
    }

    if state_status == "CORRUPT":
        result["reasons"].append("Estado persistente corrompido ou violacao de seguranca no diretorio de estado.")
        return result

    if not is_sha40(target_sha) or not commit_exists(repo_root, target_sha):
        result["reasons"].append("Commit alvo ausente no armazenamento Git local (execute plan --refresh ou git fetch autorizado).")
        return result

    if base_sha == "UNKNOWN" or not is_sha40(base_sha):
        result["reasons"].append("Baseline validado (last_validated_sha) e UNKNOWN; adocao supervisionada necessaria.")
        return result

    if not commit_exists(repo_root, base_sha):
        result["reasons"].append(f"Commit de baseline {base_sha[:12]} nao encontrado nos objetos Git locais.")
        return result

    if base_sha == target_sha:
        result.update(
            {
                "risk": "R0",
                "allow_sync_code": True,
                "requires_cache_rebuild": False,
                "requires_structural_plan": False,
                "diff_available": True,
                "is_fast_forward": True,
                "reasons": ["Nenhum arquivo alterado entre o baseline validado e o commit alvo."],
            }
        )
        return result

    if not is_ancestor(repo_root, base_sha, target_sha):
        result["reasons"].append("Commit alvo nao e descendente (fast-forward) do baseline comparado.")
        return result

    result["is_fast_forward"] = True
    raw_res = run_git(repo_root, ["diff", "--raw", "-z", "--find-renames", base_sha, target_sha, "--"])
    if raw_res.returncode != 0:
        result["reasons"].append("Falha ao executar git diff entre baseline e commit alvo.")
        return result

    entries = parse_raw_diff_z(raw_res.stdout)
    result["diff_available"] = True
    result["file_count"] = len(entries)

    if not entries:
        result.update(
            {
                "risk": "R0",
                "allow_sync_code": True,
                "requires_cache_rebuild": False,
                "requires_structural_plan": False,
                "reasons": [" commits intermediarios sem alteracao liquida de arquivos (R0)."],
            }
        )
        return result

    max_risk = "R0"
    site_keys: set[str] = set()
    commerce_cutover_detected = False

    for entry in entries:
        path = entry["path"]
        lvl, reason = classify_single_path(path)
        reasons_list = [reason]

        sem_lvl, sem_reasons = inspect_patch_semantics(repo_root, base_sha, target_sha, entry)
        if RISK_ORDER[sem_lvl] > RISK_ORDER[lvl]:
            lvl = sem_lvl
        reasons_list.extend(sem_reasons)

        result["counts_by_risk"][lvl] = result["counts_by_risk"].get(lvl, 0) + 1
        if RISK_ORDER[lvl] > RISK_ORDER[max_risk]:
            max_risk = lvl

        # Inferir site_keys impactados para recomendacao de smoke
        if "aculta_wiki" in path or "wiki" in path:
            site_keys.add("wiki")
        elif "aculta_forum" in path or "forum" in path:
            site_keys.add("forum")
        elif "aculta_lms" in path or "courses" in path:
            site_keys.add("courses")
        elif "aculta_commerce" in path or "shop" in path:
            site_keys.add("shop")
            site_keys.add("main")
        elif "aculta_events" in path or "aculta_calendar" in path or "eventos" in path:
            site_keys.add("eventos")
        elif "aculta_magazine" in path or "revista" in path:
            site_keys.add("magazine")
        elif "aculta_linkweed" in path or "links" in path:
            site_keys.add("links")
        elif "aculta_donation" in path or "support" in path:
            site_keys.add("support")
        elif "aculta_user" in path or "account" in path:
            site_keys.add("account")
        elif path.startswith(("web/modules/custom/aculta_portal/", "web/themes/custom/")):
            site_keys.update(SITE_KEYS_DEV.keys())

        if "commerce_shipping" in path or "aculta_commerce" in path or path in ("composer.json", "composer.lock"):
            diff_check = run_git(repo_root, ["diff", base_sha, target_sha, "--", path]).stdout.decode("utf-8", errors="ignore")
            if "commerce_shipping" in diff_check or "migrate_shipping_methods" in diff_check or "retarget_shipments" in diff_check:
                commerce_cutover_detected = True

        result["files"].append(
            {
                "path": path,
                "old_path": entry.get("old_path"),
                "status": entry["status"],
                "risk": lvl,
                "reasons": reasons_list,
            }
        )

    if commerce_cutover_detected:
        result["special_runbooks"].append(
            {
                "id": "commerce_shipping_cutover",
                "runbook": "docs/operations/RUNTIME-UPDATE-WINDOW.md",
                "order": [
                    "1. Executar drush updatedb com o vendor antigo que ainda contem drupal/commerce_shipping.",
                    "2. Conferir migracao (blockersForUninstall() vazio e pedidos preservados).",
                    "3. Executar drush pm:uninstall commerce_shipping mediante aprovacao explicita do operador.",
                    "4. Somente ao final executar composer install com o novo composer.lock sem commerce_shipping.",
                ],
            }
        )
        if RISK_ORDER[max_risk] < RISK_ORDER["R4"]:
            max_risk = "R4"
        result["reasons"].append("Detectado cutover de commerce_shipping: exige ordem nao convencional (updatedb com vendor antigo -> uninstall -> composer install).")

    allow_sync = max_risk in ("R0", "R1")
    req_cache = max_risk == "R1" or result["counts_by_risk"]["R1"] > 0
    req_struct = RISK_ORDER[max_risk] >= RISK_ORDER["R2"]

    result.update(
        {
            "risk": max_risk,
            "allow_sync_code": allow_sync,
            "requires_cache_rebuild": req_cache,
            "requires_structural_plan": req_struct,
            "affected_site_keys": sorted(site_keys),
        }
    )
    if not result["reasons"]:
        result["reasons"].append(
            f"Delta classificado como {max_risk} ({len(entries)} arquivo(s): "
            + ", ".join(f"{k}={v}" for k, v in result["counts_by_risk"].items() if v > 0)
            + ")."
        )
    return result


def extract_json_payload(raw_text: str) -> Any:
    """Extrai o objeto ou array JSON final mesmo quando o Drush imprime notices/warnings antes."""
    text = raw_text.strip()
    if not text:
        return None
    # Tentar parse direto primeiro
    try:
        return json.loads(text)
    except Exception:
        pass
    # Procurar do fim para o inicio por linhas que iniciam '{' ou '['
    lines = text.splitlines()
    for i in range(len(lines)):
        candidate_Start = lines[i].lstrip()
        if candidate_Start.startswith("{") or candidate_Start.startswith("["):
            chunk = "\n".join(lines[i:]).strip()
            try:
                return json.loads(chunk)
            except Exception:
                continue
    raise ValueError("Saida nao contem JSON valido")


def parse_drush_checks(
    updb_rc: int,
    updb_out: str,
    cfg_rc: int,
    cfg_out: str,
    deploy_rc: int,
    deploy_out: str,
) -> dict[str, Any]:
    """Interpreta as saidas de updatedb:status, config:status e deploy:hook-status como dados."""
    info: dict[str, Any] = {
        "ok": True,
        "updatedb_pending": [],
        "updatedb_pending_count": 0,
        "config_drift_items": [],
        "config_drift_count": 0,
        "config_drift_hash": None,
        "deploy_hooks_pending": [],
        "deploy_hook_pending_count": 0,
        "missing_schema_modules": [],
        "errors": [],
    }

    # Extrair modulos faltantes em system.schema se houver nos notices
    for m in re.finditer(r"Module\s+([a-zA-Z0-9_]+)\s+has an entry in the system\.schema", updb_out + "\n" + cfg_out):
        mod = m.group(1)
        if mod not in info["missing_schema_modules"]:
            info["missing_schema_modules"].append(mod)

    if updb_rc != 0:
        info["ok"] = False
        info["errors"].append(f"updatedb:status retornou codigo {updb_rc}")
    else:
        try:
            parsed_updb = extract_json_payload(updb_out) if updb_out.strip() else []
            if isinstance(parsed_updb, dict):
                for k, v in parsed_updb.items():
                    if isinstance(v, dict):
                        info["updatedb_pending"].append(
                            {
                                "id": str(k),
                                "module": str(v.get("module", "")),
                                "update_id": str(v.get("update_id", "")),
                                "type": str(v.get("type", "hook_update_n")),
                            }
                        )
                    else:
                        info["updatedb_pending"].append({"id": str(k)})
            elif isinstance(parsed_updb, list):
                for item in parsed_updb:
                    if isinstance(item, dict):
                        info["updatedb_pending"].append(
                            {
                                "id": str(item.get("id") or item.get("module", "update")),
                                "module": str(item.get("module", "")),
                                "update_id": str(item.get("update_id", "")),
                            }
                        )
            else:
                info["ok"] = False
                info["errors"].append("saida de updatedb:status malformada")
        except Exception as exc:
            info["ok"] = False
            info["errors"].append(f"saida de updatedb:status invalida: {exc}")

    info["updatedb_pending_count"] = len(info["updatedb_pending"])

    if cfg_rc != 0:
        info["ok"] = False
        info["errors"].append(f"config:status retornou codigo {cfg_rc}")
    else:
        try:
            parsed_cfg = extract_json_payload(cfg_out) if cfg_out.strip() else []
            if isinstance(parsed_cfg, dict):
                for k, v in sorted(parsed_cfg.items()):
                    state_val = v.get("state", "Different") if isinstance(v, dict) else str(v)
                    info["config_drift_items"].append({"name": str(k), "state": str(state_val)})
            elif isinstance(parsed_cfg, list):
                for item in parsed_cfg:
                    if isinstance(item, dict) and item.get("name"):
                        info["config_drift_items"].append(
                            {"name": str(item["name"]), "state": str(item.get("state", "Different"))}
                        )
            else:
                info["ok"] = False
                info["errors"].append("saida de config:status malformada")
        except Exception as exc:
            info["ok"] = False
            info["errors"].append(f"saida de config:status invalida: {exc}")

    info["config_drift_count"] = len(info["config_drift_items"])
    drift_canon = json.dumps(info["config_drift_items"], sort_keys=True, ensure_ascii=True)
    info["config_drift_hash"] = hashlib.sha256(drift_canon.encode("utf-8")).hexdigest()[:16]

    if deploy_rc == 0 and deploy_out.strip():
        try:
            parsed_dep = extract_json_payload(deploy_out)
            if isinstance(parsed_dep, dict):
                for k in parsed_dep.keys():
                    info["deploy_hooks_pending"].append(str(k))
            elif isinstance(parsed_dep, list):
                for item in parsed_dep:
                    if isinstance(item, dict) and item.get("hook"):
                        info["deploy_hooks_pending"].append(str(item["hook"]))
                    elif isinstance(item, str):
                        info["deploy_hooks_pending"].append(item)
        except Exception:
            pass
    info["deploy_hook_pending_count"] = len(info["deploy_hooks_pending"])

    return info


def build_structural_plan(
    repo_root: Path,
    base_sha: str,
    target_sha: str,
    delta: dict[str, Any],
    drush_info: dict[str, Any] | None,
) -> dict[str, Any]:
    """Gera o plano operacional verificavel (F5) sem executar mudancas no Runtime."""
    files = delta.get("files", [])
    composer_changed = any(f["path"] in ("composer.json", "composer.lock", "patches-aculta.json", "patches.lock.json") for f in files)
    install_hooks = [f["path"] for f in files if f["path"].endswith((".install", ".post_update.php", ".deploy.php"))]
    config_files = [f["path"] for f in files if f["path"].startswith("config/sync/")]
    destructive_files = [f["path"] for f in files if f["risk"] == "R4" or f["status"] == "D" and f["path"].startswith("config/sync/")]
    special_runbooks = delta.get("special_runbooks", [])

    has_commerce_cutover = any(r.get("id") == "commerce_shipping_cutover" for r in special_runbooks)

    steps: list[dict[str, Any]] = [
        {
            "step": 1,
            "phase": "freeze_and_preflight",
            "title": "Congelar janela e coordenar agentes/filas",
            "commands": [
                "bash scripts/runtime-sync.sh doctor",
                "bash scripts/runtime-sync.sh plan --refresh",
            ],
            "requires_authorization": False,
            "notes": "Pausar agentes escritores, cron e workers afetados antes do snapshot.",
        },
        {
            "step": 2,
            "phase": "backup_and_restore_proof",
            "title": "Snapshot consistente fora do Git/DocRoot e ensaio de restauracao comprovado",
            "commands": [
                "mysqldump --single-transaction --routines --triggers --hex-blob aculta_runtime > <backup_privado_0600>/runtime.sql",
                "sha256sum <backup_privado_0600>/runtime.sql > <backup_privado_0600>/runtime.sql.sha256",
                "# Restaurar em banco descartavel preprovisionado e autorizado (nunca aculta_simpletest por padrao) e conferir COUNT(*) e drush updatedb:status",
            ],
            "requires_authorization": True,
            "notes": "Snapshot sem restore testado nao autoriza updatedb, config:import nem uninstall.",
        },
    ]

    step_no = 3
    if has_commerce_cutover:
        steps.append(
            {
                "step": step_no,
                "phase": "special_cutover_commerce_shipping",
                "title": "Ordem obrigatoria de cutover do commerce_shipping (vendor antigo preservado)",
                "commands": [
                    "php vendor/drush/drush/drush.php --uri=<URI_TEST> updatedb -y",
                    "# Conferir migracao: blockersForUninstall() vazio, commerce_order.shipments -> aculta_shipment e contagem de pedidos intacta",
                    "php vendor/drush/drush/drush.php --uri=<URI_TEST> pm:uninstall commerce_shipping -y",
                    "composer install --no-interaction --no-progress",
                ],
                "requires_authorization": True,
                "notes": "NUNCA rodar composer install antes do updatedb e do pm:uninstall de commerce_shipping (RUNTIME-UPDATE-WINDOW.md).",
            }
        )
        step_no += 1
    else:
        if composer_changed:
            steps.append(
                {
                    "step": step_no,
                    "phase": "composer_install_locked",
                    "title": "Instalar dependencias travadas pelo composer.lock",
                    "commands": ["composer install --no-interaction --no-progress"],
                    "requires_authorization": True,
                    "notes": "Proibido executar composer update no Runtime.",
                }
            )
            step_no += 1

        if install_hooks or (drush_info and drush_info.get("updatedb_pending_count", 0) > 0):
            steps.append(
                {
                    "step": step_no,
                    "phase": "database_updates",
                    "title": "Aplicar updates de esquema e post-updates aprovados",
                    "commands": ["php vendor/drush/drush/drush.php --uri=<URI_TEST> updatedb -y"],
                    "requires_authorization": True,
                    "notes": "Exige autorizacao nominal para os hooks listados e snapshot validado.",
                }
            )
            step_no += 1

    if config_files or (drush_info and drush_info.get("config_drift_count", 0) > 0):
        steps.append(
            {
                "step": step_no,
                "phase": "config_sync",
                "title": "Revisar drift e importar configuracao autorizada",
                "commands": [
                    "php vendor/drush/drush/drush.php --uri=<URI_TEST> config:status",
                    "php vendor/drush/drush/drush.php --uri=<URI_TEST> config:import -y",
                ],
                "requires_authorization": True,
                "notes": "Exige reconciliacao previa do drift existente no banco para nao sobrescrever estado ativo indevidamente.",
            }
        )
        step_no += 1

    if any(f.endswith(".deploy.php") for f in install_hooks) or (drush_info and drush_info.get("deploy_hook_pending_count", 0) > 0):
        steps.append(
            {
                "step": step_no,
                "phase": "deploy_hooks",
                "title": "Executar deploy hooks do Drush",
                "commands": ["php vendor/drush/drush/drush.php --uri=<URI_TEST> deploy:hook -y"],
                "requires_authorization": True,
                "notes": "Executar apos config:import quando aplicavel.",
            }
        )
        step_no += 1

    steps.append(
        {
            "step": step_no,
            "phase": "cache_and_verification",
            "title": "Reconstruir cache e homologar os site_keys impactados",
            "commands": [
                "php vendor/drush/drush/drush.php --uri=<URI_TEST> cr",
                "bash scripts/runtime-sync.sh verify",
            ],
            "requires_authorization": False,
            "notes": "Conferir HTTP 200/404 nos subdominios afetados e registrar handoff.",
        }
    )

    return {
        "schema_version": SCHEMA_VERSION,
        "generated_at": utc_now(),
        "base_sha": base_sha,
        "target_sha": target_sha,
        "risk": delta.get("risk", "RX"),
        "autonomous_execution": False,
        "summary": {
            "composer_changed": composer_changed,
            "install_or_update_files": install_hooks,
            "config_sync_files_count": len(config_files),
            "config_sync_sample": config_files[:20],
            "destructive_or_r4_files": destructive_files,
            "runtime_updatedb_pending": drush_info.get("updatedb_pending", []) if drush_info else None,
            "runtime_config_drift_count": drush_info.get("config_drift_count") if drush_info else None,
            "runtime_deploy_hooks_pending": drush_info.get("deploy_hooks_pending", []) if drush_info else None,
        },
        "special_runbooks": special_runbooks,
        "ordered_steps": steps,
        "abort_and_rollback_criteria": [
            "Qualquer falha em updatedb, config:import ou pm:uninstall interrompe imediatamente a janela (estado PARTIAL/FAILED).",
            "Proibido usar git reset --hard ou SQL improvisado para tentar reverter schema alterado.",
            "Rollback consiste em restaurar integralmente o snapshot consistente (banco + arquivos persistentes + codigo/lock/vendor correspondente) testado no passo 2.",
        ],
    }


def prepare_runtime_worktreeManifest(repo_root: Path, target_dir: str | None, target_sha: str) -> dict[str, Any]:
    """Inspeciona e planeja (ou prepara sob flag explicita) a separacao entre checkout de trabalho e Runtime (F4)."""
    shared_items = [
        {"path": "vendor", "strategy": "shared_or_locked_install", "exists_in_repo": (repo_root / "vendor").exists()},
        {"path": "web/sites/default/settings.php", "strategy": "preserve_and_mount_0640", "exists_in_repo": (repo_root / "web/sites/default/settings.php").exists()},
        {"path": "web/sites/default/settings.local.php", "strategy": "preserve_and_mount_0640", "exists_in_repo": (repo_root / "web/sites/default/settings.local.php").exists()},
        {"path": "web/sites/default/files", "strategy": "shared_persistent_symlink", "exists_in_repo": (repo_root / "web/sites/default/files").exists()},
        {"path": "private", "strategy": "shared_persistent_symlink", "exists_in_repo": (repo_root / "private").exists()},
        {"path": "secrets", "strategy": "shared_restricted_dir_0750", "exists_in_repo": (repo_root / "secrets").exists()},
        {"path": "var/deployer", "strategy": "shared_state_dir", "exists_in_repo": (repo_root / "var/deployer").exists()},
        {"path": "var/runtime-sync", "strategy": "shared_state_dir_0700", "exists_in_repo": (repo_root / "var/runtime-sync").exists()},
    ]
    return {
        "schema_version": SCHEMA_VERSION,
        "current_isolation_state": "SHARED_CHECKOUT_ACTIVE",
        "warning": "O checkout editado pelos agentes ainda e o mesmo servido pelo Apache (/home/aculta -> checkout). Alteracoes locais ficam visiveis antes do sync-code ate que o cutover F4 seja aprovado e executado.",
        "recommended_strategy": "git worktree add --detach <runtime_checkout_path> <validated_sha>",
        "branch_exclusivity_rule": "A branch 'dev' nunca fica checada em duas worktrees; o Runtime usa HEAD detached no SHA validado.",
        "target_sha": target_sha,
        "target_dir": target_dir,
        "persistent_mounts": shared_items,
        "domains_to_verify": SITE_KEYS_DEV,
        "cutover_executed": False,
        "rollback_procedure": "Restaurar symlink /home/aculta (ou DocumentRoot) para o checkout anterior e reconstruir caches com drush cr.",
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="ACULTA Runtime Sync v2 helper")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_state = sub.add_parser("read-state")
    p_state.add_argument("--repo-root", required=True)
    p_state.add_argument("--state-dir", required=True)

    p_delta = sub.add_parser("classify")
    p_delta.add_argument("--repo-root", required=True)
    p_delta.add_argument("--base-sha", required=True)
    p_delta.add_argument("--target-sha", required=True)
    p_delta.add_argument("--state-status", default="OK")

    p_drush = sub.add_parser("parse-drush")
    p_drush.add_argument("--updb-rc", type=int, required=True)
    p_drush.add_argument("--updb-file", required=True)
    p_drush.add_argument("--cfg-rc", type=int, required=True)
    p_drush.add_argument("--cfg-file", required=True)
    p_drush.add_argument("--deploy-rc", type=int, default=0)
    p_drush.add_argument("--deploy-file", default="")

    p_record = sub.add_parser("record-run")
    p_record.add_argument("--repo-root", required=True)
    p_record.add_argument("--state-dir", required=True)
    p_record.add_argument("--run-json", required=True)
    p_record.add_argument("--update-state", action="store_true")

    p_splan = sub.add_parser("structural-plan")
    p_splan.add_argument("--repo-root", required=True)
    p_splan.add_argument("--base-sha", required=True)
    p_splan.add_argument("--target-sha", required=True)
    p_splan.add_argument("--state-status", default="OK")
    p_splan.add_argument("--drush-json-file", default="")

    p_f4 = sub.add_parser("isolation-plan")
    p_f4.add_argument("--repo-root", required=True)
    p_f4.add_argument("--target-sha", required=True)
    p_f4.add_argument("--target-dir", default="")

    args = parser.parse_args()

    if args.cmd == "read-state":
        repo_root = Path(args.repo_root)
        state_dir = Path(args.state_dir)
        st, status = read_state(state_dir, repo_root)
        print(json.dumps({"state_status": status, "state": st}, ensure_ascii=True))
        return 0

    if args.cmd == "classify":
        repo_root = Path(args.repo_root)
        res = classify_delta(repo_root, args.base_sha, args.target_sha, args.state_status)
        print(json.dumps(sanitize_obj(res), ensure_ascii=True))
        return 0

    if args.cmd == "parse-drush":
        updb_out = Path(args.updb_file).read_text(encoding="utf-8", errors="replace") if os.path.isfile(args.updb_file) else ""
        cfg_out = Path(args.cfg_file).read_text(encoding="utf-8", errors="replace") if os.path.isfile(args.cfg_file) else ""
        dep_out = Path(args.deploy_file).read_text(encoding="utf-8", errors="replace") if args.deploy_file and os.path.isfile(args.deploy_file) else ""
        res = parse_drush_checks(args.updb_rc, updb_out, args.cfg_rc, cfg_out, args.deploy_rc, dep_out)
        print(json.dumps(sanitize_obj(res), ensure_ascii=True))
        return 0

    if args.cmd == "record-run":
        repo_root = Path(args.repo_root)
        state_dir = Path(args.state_dir)
        run_data = json.loads(Path(args.run_json).read_text(encoding="utf-8"))
        run_data["schema_version"] = SCHEMA_VERSION
        run_data["secrets_redacted"] = True

        run_id = str(run_data.get("run_id") or f"{utc_now()}-run")
        safe_run_id = re.sub(r"[^A-Za-z0-9_.:-]", "-", run_id)
        ok, err = ensure_secure_state_dir(state_dir, repo_root)
        if not ok:
            print(json.dumps({"ok": False, "error": err}), file=sys.stderr)
            return 1

        run_file = state_dir / "runs" / f"{safe_run_id}.json"
        ok, err = atomic_write_json(run_file, run_data, repo_root)
        if not ok:
            print(json.dumps({"ok": False, "error": err}), file=sys.stderr)
            return 1

        latest_file = state_dir / "latest.json"
        ok, err = atomic_write_json(latest_file, run_data, repo_root)
        if not ok:
            print(json.dumps({"ok": False, "error": err}), file=sys.stderr)
            return 1

        if args.update_state:
            st, st_status = read_state(state_dir, repo_root)
            if st_status == "CORRUPT":
                print(json.dumps({"ok": False, "error": st.get("state_error", "state.json corrompido")}), file=sys.stderr)
                return 1
            st["updated_at"] = run_data.get("timestamp") or utc_now()
            if is_sha40(run_data.get("remote_sha")):
                st["remote_sha"] = run_data["remote_sha"]
            if is_sha40(run_data.get("checkout_sha")):
                st["checkout_sha"] = run_data["checkout_sha"]
            if is_sha40(run_data.get("new_last_code_sync_sha")):
                st["last_code_sync_sha"] = run_data["new_last_code_sync_sha"]
            if is_sha40(run_data.get("new_last_cache_success_sha")):
                st["last_cache_success_sha"] = run_data["new_last_cache_success_sha"]
            if is_sha40(run_data.get("new_last_validated_sha")):
                st["last_validated_sha"] = run_data["new_last_validated_sha"]
            if "pending_structural_sha" in run_data:
                st["pending_structural_sha"] = run_data["pending_structural_sha"]
            if "pending_structural_risk" in run_data:
                st["pending_structural_risk"] = run_data["pending_structural_risk"]
            if isinstance(run_data.get("drift_summary"), dict):
                st["drift_baseline"] = {
                    "updatedb_pending_count": run_data["drift_summary"].get("updatedb_pending_count"),
                    "deploy_hook_pending_count": run_data["drift_summary"].get("deploy_hook_pending_count"),
                    "config_drift_count": run_data["drift_summary"].get("config_drift_count"),
                    "config_drift_hash": run_data["drift_summary"].get("config_drift_hash"),
                }

            summary_entry = {
                "run_id": run_id,
                "mode": run_data.get("mode"),
                "outcome": run_data.get("outcome"),
                "phase": run_data.get("phase"),
                "timestamp": run_data.get("timestamp") or utc_now(),
                "checkout_sha": run_data.get("checkout_sha"),
                "remote_sha": run_data.get("remote_sha"),
                "last_validated_sha": st.get("last_validated_sha"),
                "risk": run_data.get("risk", "RX"),
                "summary": redact_text(str(run_data.get("summary", ""))),
            }
            st["last_operation"] = summary_entry
            hist = st.get("history") if isinstance(st.get("history"), list) else []
            hist.insert(0, summary_entry)
            st["history"] = hist[:25]
            st.pop("state_error", None)

            ok, err = atomic_write_json(state_dir / "state.json", st, repo_root)
            if not ok:
                print(json.dumps({"ok": False, "error": err}), file=sys.stderr)
                return 1

            # Rotacao simples de arquivos em runs/ (manter no maximo 50)
            runs_list = sorted((state_dir / "runs").glob("*.json"), key=lambda p: p.stat().st_mtime, reverse=True)
            for old_p in runs_list[50:]:
                try:
                    if not old_p.is_symlink():
                        old_p.unlink()
                except OSError:
                    pass

        print(json.dumps({"ok": True, "run_id": run_id}))
        return 0

    if args.cmd == "structural-plan":
        repo_root = Path(args.repo_root)
        delta = classify_delta(repo_root, args.base_sha, args.target_sha, args.state_status)
        drush_info = None
        if args.drush_json_file and os.path.isfile(args.drush_json_file):
            try:
                drush_info = json.loads(Path(args.drush_json_file).read_text(encoding="utf-8"))
            except Exception:
                drush_info = None
        plan = build_structural_plan(repo_root, args.base_sha, args.target_sha, delta, drush_info)
        print(json.dumps(sanitize_obj(plan), indent=2, ensure_ascii=True))
        return 0

    if args.cmd == "isolation-plan":
        repo_root = Path(args.repo_root)
        plan = prepare_runtime_worktreeManifest(repo_root, args.target_dir or None, args.target_sha)
        print(json.dumps(sanitize_obj(plan), indent=2, ensure_ascii=True))
        return 0

    return 2


if __name__ == "__main__":
    sys.exit(main())
