#!/usr/bin/env python3
"""Read-only, local MCP adapter for the ACULTA Drupal development Runtime."""
from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path
from typing import Any

SCRIPT_PROJECT = Path(__file__).resolve().parents[3]


def project_root() -> Path:
    """Use the primary checkout when this server starts from an agent worktree."""
    override = os.environ.get('ACULTA_DRUPAL_PROJECT')
    if override:
        return Path(override).expanduser().resolve()
    completed = subprocess.run(
        ['git', '-C', str(SCRIPT_PROJECT), 'rev-parse', '--path-format=absolute', '--git-common-dir'],
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL,
        text=True,
        timeout=5,
        check=False,
    )
    if completed.returncode == 0:
        common_git_dir = Path(completed.stdout.strip()).resolve()
        if common_git_dir.name == '.git':
            return common_git_dir.parent
    return SCRIPT_PROJECT


PROJECT = project_root()
DRUSH = PROJECT / 'vendor/bin/drush'
URI = 'http://default'
TIMEOUT_SECONDS = 30

TOOLS = [
    {
        'name': 'drupal_site_status',
        'description': 'Read Drupal and PHP version plus safe runtime identifiers. Does not return database credentials or content.',
        'inputSchema': {'type': 'object', 'properties': {}, 'additionalProperties': False},
    },
    {
        'name': 'drupal_extensions',
        'description': 'List enabled and disabled Drupal modules, themes, or profiles. Returns names and versions only.',
        'inputSchema': {
            'type': 'object',
            'properties': {
                'type': {'type': 'string', 'enum': ['module', 'theme', 'profile']},
                'contains': {'type': 'string', 'maxLength': 80, 'description': 'Optional case-insensitive name filter.'},
                'limit': {'type': 'integer', 'minimum': 1, 'maximum': 250},
            },
            'additionalProperties': False,
        },
    },
    {
        'name': 'drupal_config_differences',
        'description': 'Read config/sync differences as config names and states only; values are never returned.',
        'inputSchema': {'type': 'object', 'properties': {'limit': {'type': 'integer', 'minimum': 1, 'maximum': 200}}, 'additionalProperties': False},
    },
    {
        'name': 'drupal_pending_database_updates',
        'description': 'Read pending Drupal database update metadata; does not apply updates.',
        'inputSchema': {'type': 'object', 'properties': {}, 'additionalProperties': False},
    },
]


def run_drush(command: str, *, json_output: bool = False) -> str:
    if command not in {'status', 'pm:list', 'config:status', 'updatedb:status'}:
        raise ValueError('Command is not allowlisted')
    args = ['/bin/bash', str(DRUSH), '--root=' + str(PROJECT / 'web'), '--uri=' + URI, command]
    if json_output:
        args.append('--format=json')
    completed = subprocess.run(
        args,
        cwd=PROJECT,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        timeout=TIMEOUT_SECONDS,
        check=False,
        env=os.environ.copy(),
    )
    if completed.returncode != 0:
        raise RuntimeError(f'Drupal read failed ({command}, exit {completed.returncode})')
    return completed.stdout.strip()


def parse_json_output(raw: str) -> Any:
    # Drush can emit a warning before an otherwise valid JSON document.
    object_start = raw.find('{')
    array_start = raw.find('[') if object_start < 0 else -1
    starts = [p for p in (object_start, array_start) if p >= 0]
    if not starts:
        if not raw:
            return {}
        raise ValueError('Drupal did not return structured output')
    return json.loads(raw[min(starts):])


def invoke(name: str, args: dict[str, Any]) -> Any:
    if not isinstance(args, dict):
        raise ValueError('Tool arguments must be an object')
    if name == 'drupal_site_status':
        args = ['/bin/bash', str(DRUSH), '--root=' + str(PROJECT / 'web'), '--uri=' + URI, 'status', '--format=json', '--fields=drupal-version,php-version,db-driver,uri']
        completed = subprocess.run(args, cwd=PROJECT, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=TIMEOUT_SECONDS, check=False, env=os.environ.copy())
        if completed.returncode != 0:
            raise RuntimeError(f'Drupal read failed (status, exit {completed.returncode})')
        data = parse_json_output(completed.stdout.strip())
        keys = ('drupal-version', 'php-version', 'db-driver', 'site', 'uri')
        return {key: data[key] for key in keys if key in data}
    if name == 'drupal_extensions':
        extension_type = args.get('type', 'module')
        if extension_type not in {'module', 'theme', 'profile'}:
            raise ValueError('Unsupported extension type')
        search = args.get('contains', '').casefold()
        if not isinstance(search, str) or len(search) > 80:
            raise ValueError('Invalid name filter')
        limit = args.get('limit', 100)
        if not isinstance(limit, int) or isinstance(limit, bool) or not 1 <= limit <= 250:
            raise ValueError('Limit must be between 1 and 250')
        data = parse_json_output(run_drush('pm:list', json_output=True))
        rows = []
        values = data.values() if isinstance(data, dict) else data
        for item in values:
            if not isinstance(item, dict) or item.get('type') != extension_type:
                continue
            name_value = str(item.get('name', ''))
            display = str(item.get('display_name', ''))
            if search and search not in (name_value + ' ' + display).casefold():
                continue
            rows.append({'name': name_value, 'label': display, 'status': item.get('status'), 'version': item.get('version')})
        rows.sort(key=lambda row: row['name'])
        return {'type': extension_type, 'count': len(rows), 'items': rows[:limit], 'truncated': len(rows) > limit}
    if name == 'drupal_config_differences':
        limit = args.get('limit', 100)
        if not isinstance(limit, int) or isinstance(limit, bool) or not 1 <= limit <= 200:
            raise ValueError('Limit must be between 1 and 200')
        data = parse_json_output(run_drush('config:status', json_output=True))
        values = data.values() if isinstance(data, dict) else data
        rows = []
        for item in values:
            if isinstance(item, dict):
                rows.append({'name': item.get('name'), 'state': item.get('state')})
        rows.sort(key=lambda row: str(row.get('name', '')))
        return {'count': len(rows), 'items': rows[:limit], 'truncated': len(rows) > limit}
    if name == 'drupal_pending_database_updates':
        data = parse_json_output(run_drush('updatedb:status', json_output=True))
        values = data.values() if isinstance(data, dict) else data
        rows = []
        for item in values:
            if isinstance(item, dict):
                rows.append({key: item.get(key) for key in ('module', 'update_id', 'description', 'type') if key in item})
        return {'count': len(rows), 'updates': rows}
    raise ValueError('Unknown tool')


def send(message: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(message, ensure_ascii=False, separators=(',', ':')) + '\n')
    sys.stdout.flush()


def handle(message: dict[str, Any]) -> None:
    method = message.get('method')
    request_id = message.get('id')
    if method == 'notifications/initialized' or method == 'notifications/cancelled':
        return
    if method == 'initialize':
        params = message.get('params') or {}
        version = params.get('protocolVersion', '2024-11-05')
        send({'jsonrpc': '2.0', 'id': request_id, 'result': {
            'protocolVersion': version,
            'capabilities': {'tools': {}},
            'serverInfo': {'name': 'aculta-drupal-readonly', 'version': '1.0.0'},
            'instructions': 'Read-only access to ACULTA local Drupal Runtime metadata. No writes, arbitrary commands, SQL, PHP, configuration values, or entity content are exposed.'
        }})
    elif method == 'ping':
        send({'jsonrpc': '2.0', 'id': request_id, 'result': {}})
    elif method == 'tools/list':
        send({'jsonrpc': '2.0', 'id': request_id, 'result': {'tools': TOOLS}})
    elif method == 'tools/call':
        params = message.get('params') or {}
        try:
            result = invoke(params.get('name', ''), params.get('arguments') or {})
            send({'jsonrpc': '2.0', 'id': request_id, 'result': {'content': [{'type': 'text', 'text': json.dumps(result, ensure_ascii=False, indent=2)}]}})
        except Exception as exc:
            send({'jsonrpc': '2.0', 'id': request_id, 'result': {'isError': True, 'content': [{'type': 'text', 'text': str(exc)}]}})
    elif request_id is not None:
        send({'jsonrpc': '2.0', 'id': request_id, 'error': {'code': -32601, 'message': 'Method not found'}})


def main() -> None:
    if not DRUSH.is_file():
        raise SystemExit('Configured Drush executable is unavailable')
    for line in sys.stdin:
        try:
            message = json.loads(line)
            if isinstance(message, dict):
                handle(message)
        except Exception:
            # Keep protocol stdout clean; clients receive no environment details.
            continue


if __name__ == '__main__':
    main()
