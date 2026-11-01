#!/usr/bin/env python3
"""Verificador determinístico offline do kit Baque Sativa (requer Pillow).

Confere o manifesto `manifest.json` do kit: presença exata dos arquivos, bytes,
SHA-256, dimensões, formato (PNG/WebP), transparência e ausência de duplicatas.
"""
from pathlib import Path
from PIL import Image
import hashlib, json, sys

root=Path(__file__).resolve().parents[1]
kit=root/'web/themes/custom/aculta420/assets/branding/baque-sativa'
manifest=json.loads((kit/'manifest.json').read_text())
errors=[]
listed=[v['path'] for v in manifest['variants']]
disk=sorted(str(p.relative_to(kit)) for p in list((kit/'source').rglob('*'))+list((kit/'web').rglob('*')) if p.is_file())
if sorted(listed)!=disk:
    errors.append(f'manifesto e disco divergem: faltando={set(disk)-set(listed)} sobrando={set(listed)-set(disk)}')
seen={}
for v in manifest['variants']:
    p=kit/v['path']; data=p.read_bytes()
    if len(data)!=v['bytes']: errors.append(f"bytes divergentes: {v['path']}")
    if hashlib.sha256(data).hexdigest()!=v['sha256']: errors.append(f"SHA-256 divergente: {v['path']}")
    if v['sha256'] in seen: errors.append(f"duplicata: {v['path']} == {seen[v['sha256']]}")
    seen[v['sha256']]=v['path']
    with Image.open(p) as im:
        im.verify()
    with Image.open(p) as im:
        if (im.width,im.height)!=(v['width'],v['height']): errors.append(f"dimensões: {v['path']} {im.size}")
        if 'A' not in im.getbands() or im.getchannel('A').getextrema()[0]>=255: errors.append(f"sem transparência: {v['path']}")
        expected={'.png':'PNG','.webp':'WEBP'}[p.suffix.lower()]
        if im.format!=expected: errors.append(f"formato: {v['path']} é {im.format}")
if errors:
    print('Baque Sativa assets: FAIL'); [print('- '+e) for e in errors]; sys.exit(1)
print(f"PASS: {len(manifest['variants'])} arquivos ({len(disk)} no disco), SHA-256, bytes, dimensões, formato e transparência.")
