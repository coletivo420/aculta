#!/usr/bin/env python3
"""Verificador determinístico offline do kit Bloco Sativa 420 (requer Pillow).

Confere o manifesto do kit: presença exata dos arquivos, bytes, SHA-256,
dimensões, formato (PNG/WebP), proporção dos derivados em relação ao original,
opacidade (arte com fundo escuro incorporado) e ausência de duplicatas.
"""
from pathlib import Path
from PIL import Image
import hashlib, json, sys

root=Path(__file__).resolve().parents[1]
kit=root/'web/themes/custom/aculta420/assets/branding/bloco-sativa420'
manifest=json.loads((kit/'manifest.json').read_text())
errors=[]
rel=lambda p: str(p.relative_to(root))
listed={f['path'] for f in manifest['files']}
disk={rel(p) for p in kit.rglob('*') if p.is_file() and p.suffix.lower() in ('.png','.webp')}
if listed!=disk:
    errors.append(f'manifesto e disco divergem: faltando={disk-listed} sobrando={listed-disk}')
seen={}
for f in manifest['files']:
    p=root/f['path']; data=p.read_bytes()
    if len(data)!=f['bytes']: errors.append(f"bytes divergentes: {f['path']}")
    if hashlib.sha256(data).hexdigest()!=f['sha256']: errors.append(f"SHA-256 divergente: {f['path']}")
    if f['sha256'] in seen: errors.append(f"duplicata: {f['path']} == {seen[f['sha256']]}")
    seen[f['sha256']]=f['path']
    with Image.open(p) as im:
        im.verify()
    with Image.open(p) as im:
        if (im.width,im.height)!=(f['width'],f['height']): errors.append(f"dimensões: {f['path']} {im.size}")
        expected={'.png':'PNG','.webp':'WEBP'}[p.suffix.lower()]
        if im.format!=expected: errors.append(f"formato: {f['path']} é {im.format}")
        alpha=im.convert('RGBA').getchannel('A').getextrema()
        if alpha[0]<255: errors.append(f"transparência inesperada em arte com fundo incorporado: {f['path']}")
    if f['type']=='web':
        kind='horizontal' if 'horizontal' in f['path'] else 'square'
        orig=next(g for g in manifest['files'] if g['type']=='original' and kind in g['path'])
        ratio_d=f['width']/f['height']; ratio_o=orig['width']/orig['height']
        if abs(ratio_d-ratio_o)/ratio_o>0.005: errors.append(f"proporção alterada: {f['path']} ({ratio_d:.4f} × {ratio_o:.4f})")
if errors:
    print('Bloco Sativa 420 assets: FAIL'); [print('- '+e) for e in errors]; sys.exit(1)
print(f"PASS: {len(manifest['files'])} arquivos, SHA-256, bytes, dimensões, formato, proporção e opacidade.")
