#!/usr/bin/env python3
"""Dump da árvore AT-SPI do Firefox aberto, sem credenciais ou conteúdo sensível."""
import json
import os
import sys

import gi

gi.require_version('Atspi', '2.0')
from gi.repository import Atspi  # noqa: E402

out = os.environ.get('SUITE_OUT')
if not out:
    raise SystemExit('SUITE_OUT é obrigatório')

nodes = []


def walk(node, depth=0):
    if node is None or depth > 12 or len(nodes) >= 400:
        return
    try:
        role = node.get_role_name() or ''
        name = (node.get_name() or '').strip()
        states = node.get_state_set()
        focusable = states.contains(Atspi.StateType.FOCUSABLE)
        showing = states.contains(Atspi.StateType.SHOWING)
    except Exception:
        return
    if name or role in ('document frame', 'frame', 'table', 'heading', 'link', 'push button', 'combo box'):
        nodes.append({
            'role': role,
            'name': name[:180],
            'focusable': bool(focusable),
            'showing': bool(showing),
            'depth': depth,
        })
    try:
        count = node.get_child_count()
    except Exception:
        return
    for index in range(count):
        try:
            walk(node.get_child_at_index(index), depth + 1)
        except Exception:
            continue


desktop = Atspi.get_desktop(0)
firefox = None
for index in range(desktop.get_child_count()):
    try:
        app = desktop.get_child_at_index(index)
        if 'firefox' in (app.get_name() or '').lower():
            firefox = app
            break
    except Exception:
        continue

if firefox is not None:
    for index in range(firefox.get_child_count()):
        walk(firefox.get_child_at_index(index))

payload = {'found': firefox is not None, 'nodes': nodes}
with open(os.path.join(out, 'statistics-atspi.json'), 'w', encoding='utf-8') as handle:
    json.dump(payload, handle, ensure_ascii=False, indent=2)
    handle.write('\n')
if firefox is None:
    sys.exit(2)
