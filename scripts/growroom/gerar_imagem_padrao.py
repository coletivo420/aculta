#!/usr/bin/env python3
"""Gera a imagem padrão para fotos do antigo Growroom que não foram recuperadas.

Texto: "Que pena não conseguimos restaurar essa imagem do antigo growroom".
Paleta: a mesma do acervo (papel quente, grafite, verde-musgo), sem texto de marca de terceiros.

Uso: python3 gerar_imagem_padrao.py SAIDA.png
"""
import sys
from PIL import Image, ImageDraw, ImageFont

FONTE = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
FONTE_NEGRITO = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
TEXTO = "Que pena não conseguimos restaurar essa imagem do antigo growroom"
LARGURA, ALTURA = 960, 640
FUNDO, TINTA, MUSGO, MUTED = (246, 243, 236), (29, 31, 27), (63, 107, 58), (93, 98, 90)


def quebra(draw, texto, fonte, largura_max):
    palavras, linhas, atual = texto.split(), [], ""
    for p in palavras:
        teste = (atual + " " + p).strip()
        if draw.textlength(teste, font=fonte) <= largura_max:
            atual = teste
        else:
            linhas.append(atual)
            atual = p
    linhas.append(atual)
    return linhas


def main(saida):
    img = Image.new("RGB", (LARGURA, ALTURA), FUNDO)
    d = ImageDraw.Draw(img)
    # moldura e ícone de imagem ausente (quadro com montanha e sol)
    d.rounded_rectangle((60, 60, LARGURA - 60, ALTURA - 60), radius=18, outline=MUSGO, width=4)
    cx, cy = LARGURA // 2, 210
    d.rounded_rectangle((cx - 70, cy - 50, cx + 70, cy + 50), radius=10, outline=MUSGO, width=5)
    d.polygon([(cx - 56, cy + 36), (cx - 8, cy - 4), (cx + 26, cy + 22), (cx + 40, cy + 8), (cx + 56, cy + 36)], fill=MUSGO)
    d.ellipse((cx + 22, cy - 34, cx + 44, cy - 12), fill=MUSGO)
    fonte = ImageFont.truetype(FONTE_NEGRITO, 40)
    linhas = quebra(d, TEXTO, fonte, LARGURA - 200)
    y = 320
    for linha in linhas:
        w = d.textlength(linha, font=fonte)
        d.text(((LARGURA - w) / 2, y), linha, font=fonte, fill=TINTA)
        y += 54
    rodape = ImageFont.truetype(FONTE, 22)
    t = "Acervo Growroom · imagem não recuperada"
    w = d.textlength(t, font=rodape)
    d.text(((LARGURA - w) / 2, ALTURA - 120), t, font=rodape, fill=MUTED)
    img.save(saida, "PNG", optimize=True)
    print("gravado:", saida)


if __name__ == "__main__":
    main(sys.argv[1])
