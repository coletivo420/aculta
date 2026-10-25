#!/usr/bin/env python3
"""Gera as cópias em Markdown das minutas de Termos de Uso e Política de Privacidade.

A fonte é o HTML em scripts/institution/ (o mesmo que o instalador publica). As cópias existem para leitura e
revisão no GitHub; não editar os .md à mão. Uso: python3 -I scripts/compliance/render-minutas.py
"""
import html
import pathlib
import re

RAIZ = pathlib.Path(__file__).resolve().parents[2]
ORIGEM = RAIZ / 'scripts' / 'institution'
DESTINO = RAIZ / 'docs' / 'compliance' / 'minutas'
DOCUMENTOS = {
    'termos-de-uso.html': ('TERMOS-DE-USO.md', 'Termos de Uso'),
    'politica-de-privacidade.html': ('POLITICA-DE-PRIVACIDADE.md', 'Política de Privacidade'),
}


def inline(texto: str) -> str:
    texto = re.sub(r'<strong>(.*?)</strong>', r'**\1**', texto, flags=re.S)
    texto = re.sub(r'<a href="([^"]+)">(.*?)</a>', r'[\2](\1)', texto, flags=re.S)
    return html.unescape(re.sub(r'<[^>]+>', '', texto)).strip()


def converter(fonte: str) -> str:
    blocos = []
    for tag, miolo in re.findall(r'<(h2|p|ul)>(.*?)</\1>', fonte, flags=re.S):
        if tag == 'h2':
            blocos.append('## ' + inline(miolo))
        elif tag == 'p':
            blocos.append(inline(miolo))
        else:
            blocos.append('\n'.join('- ' + inline(i) for i in re.findall(r'<li>(.*?)</li>', miolo, flags=re.S)))
    return '\n\n'.join(blocos)


def main() -> None:
    DESTINO.mkdir(parents=True, exist_ok=True)
    for origem, (destino, titulo) in DOCUMENTOS.items():
        corpo = converter((ORIGEM / origem).read_text(encoding='utf-8'))
        cabecalho = (
            f'# {titulo} — minuta\n\n'
            f'> **Minuta, não publicada.** Cópia para leitura e revisão, gerada de `scripts/institution/{origem}` '
            f'(fonte única do texto publicado). Não edite este arquivo: altere o HTML e rode '
            f'`python3 -I scripts/compliance/render-minutas.py`. Os trechos entre colchetes são pendências de decisão '
            f'ou de revisão jurídica.\n\n'
        )
        (DESTINO / destino).write_text(cabecalho + corpo + '\n', encoding='utf-8')
        print('gerado', DESTINO / destino)


if __name__ == '__main__':
    main()
