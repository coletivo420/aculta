# Botões ACULTA (V2): esquema claro e escuro

Esquema único de botões para todo o site. Não muda por purpose, não muda pela
faixa de destaque e não depende de `--purpose-*`. A barra institucional
(`--color-surface-institution`) não muda de cor.

Visualização aprovada: artefato "Botões ACULTA claro e escuro"
(https://claude.ai/artifact/44m8zh5TzhAHA1N62ecSVv).

## 1. Variantes

| Variante | Uso |
| --- | --- |
| `.button--primary` | ação principal de cada tela; no máximo uma por bloco |
| `.button--secondary` | ação alternativa, com borda visível |
| `.button--outline` | ação de baixa ênfase dentro de cartões e listas |
| `.button--ghost` | ação terciária, sem borda, em barras e menus |

Estados: repouso, `:hover`, `:active`, `:focus-visible` e desativado.
Desativado usa `opacity: .65` e `cursor: not-allowed`; é a única exceção de
opacidade.

## 2. Valores: modo claro

| Token | Valor | Contraste medido |
| --- | --- | --- |
| Primário, fundo (repouso) | `#0c3c29` | texto branco 12,40:1 |
| Primário, fundo (hover) | `#092b1d` | texto branco 15,27:1 |
| Primário, fundo (active) | `#051a12` | texto branco 18,06:1 |
| Primário, texto | `#ffffff` | — |
| Secundário, fundo (repouso) | `#ffffff` | — |
| Secundário, fundo (hover) | `#f2f7f0` | texto 11,42:1 |
| Secundário, fundo (active) | `#e4ece1` | texto 10,26:1 |
| Secundário, borda | `#6b7d70` | 4,38:1 sobre branco |
| Secundário, texto | `#0c3c29` | — |
| Anel de foco | `#0c3c29` | 12,40:1 sobre branco, 2px, offset 2px |

## 3. Valores: modo escuro

| Token | Valor | Contraste medido |
| --- | --- | --- |
| Primário, fundo (repouso) | `#9ac15a` | texto `#171513` 8,80:1 |
| Primário, fundo (hover) | `#a5c86d` | texto 9,61:1 |
| Primário, fundo (active) | `#8cb745` | texto 7,78:1 |
| Primário, texto | `#171513` | — |
| Secundário, fundo (repouso) | `#2b2723` | — |
| Secundário, fundo (hover) | `#3a352f` | — |
| Secundário, fundo (active) | `#322d28` | — |
| Secundário, borda | `#8fa397` | 5,54:1 repouso, 4,54:1 hover, 5,09:1 active |
| Secundário, texto | `#f4efe8` | 12,95:1 |
| Anel de foco | `#f4efe8` | 15,92:1 sobre `#171513` |

## 4. Regras

- O foco é sempre verde-escuro no claro e creme no escuro. Não usar amarelo:
  o amarelo tinha 1,58:1 sobre branco.
- O hover não usa `filter: brightness(...)`. Cada estado tem cor própria.
- Os botões usam só tokens de botão (`--button-*`) e tokens de cor neutros;
  nunca `--purpose-*` nem `--color-brand-highlight`.
- Contraste mínimo: texto 4,5:1; borda de controle e anel de foco 3:1
  (WCAG 1.4.11 e 2.4.13).

## 5. Matriz de contraste nos 10 purposes

Medições obrigatórias dos botões e do anel de foco em relação às superfícies de aplicação e faixas de destaque dos 10 purposes (WCAG 2.1 AA: texto >= 4,5:1; bordas de controle e anéis de foco >= 3:1).

Os botões foram desenhados para repousar sobre as superfícies padrão do tema (`--color-surface`, `--color-canvas`, `--color-surface-raised`), onde o contraste é plenamente atendido em todas as combinações:
- Modo Claro: texto primário 12,40:1 (hover 15,27:1; active 18,06:1); texto secundário 12,40:1 (hover 11,42:1; active 10,26:1); borda secundário 4,38:1 s/ botão e 4,03:1 s/ tela; anel de foco 11,42:1 a 12,40:1 s/ tela.
- Modo Escuro: texto primário 8,80:1 (hover 9,61:1; active 7,78:1); texto secundário 12,95:1 (hover 10,61:1; active 11,91:1); borda secundário 5,54:1 s/ botão e 6,81:1 s/ tela; anel de foco 15,92:1 s/ tela.

### Modo Claro

| Purpose | Esquema | Faixa (accent) | Texto faixa | Primário texto | Secundário texto | Secundário borda | Foco s/ tela | Primário s/ faixa | Secundário s/ faixa |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `main` | Skunk | `#5a8022` | 4,62:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 2,68:1 | 4,62:1 |
| `wiki` | Sativa | `#689427` | 5,85:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 3,46:1 | 3,59:1 |
| `links` | Ruderalis | `#76a82c` | 7,41:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 4,37:1 | 2,83:1 |
| `account` | Afghan | `#08271a` | 15,96:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 1,29:1 | 15,96:1 |
| `support` | Acapulco Gold | `#f0c41e` | 12,62:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 7,45:1 | 1,66:1 |
| `shop` | Lemon Haze | `#f2ca36` | 13,28:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 7,84:1 | 1,58:1 |
| `eventos` | Golden Goat | `#f4d04e` | 13,98:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 8,25:1 | 1,50:1 |
| `magazine` | Red Kush | `#c03d27` | 5,33:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 2,32:1 | 5,33:1 |
| `courses` | Panama Red | `#d4452d` | 4,69:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 2,77:1 | 4,48:1 |
| `forum` | Red Congolese | `#d85842` | 5,39:1 | 12,40:1 | 12,40:1 | 4,38:1 | 11,42:1 | 3,18:1 | 3,89:1 |

### Modo Escuro

| Purpose | Esquema | Faixa (accent) | Texto faixa | Primário texto | Secundário texto | Secundário borda | Foco s/ tela | Primário s/ faixa | Secundário s/ faixa |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `main` | Skunk | `#6c9e22` | 6,55:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,55:1 | 4,62:1 |
| `wiki` | Sativa | `#7ab327` | 8,30:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,22:1 | 5,86:1 |
| `links` | Ruderalis | `#89c72c` | 10,27:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,01:1 | 7,25:1 |
| `account` | Afghan | `#059e61` | 6,07:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,67:1 | 4,28:1 |
| `support` | Acapulco Gold | `#fbd132` | 14,27:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,41:1 | 10,07:1 |
| `shop` | Lemon Haze | `#fcd64b` | 14,86:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,46:1 | 10,49:1 |
| `eventos` | Golden Goat | `#fcdc64` | 15,56:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,53:1 | 10,98:1 |
| `magazine` | Red Kush | `#dd4329` | 4,91:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 2,07:1 | 3,46:1 |
| `courses` | Panama Red | `#e1563f` | 5,60:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,81:1 | 3,95:1 |
| `forum` | Red Congolese | `#e46a55` | 6,49:1 | 8,80:1 | 12,95:1 | 5,54:1 | 15,92:1 | 1,56:1 | 4,58:1 |

## 6. Status e Validação

- **Tokens e componentes:** Migrados integralmente em `src/styles/tokens.css` e `components/button/button.css`, consumindo exclusivamente os tokens semânticos `--button-*`.
- **Invariância por purpose:** Os tokens e o CSS são invariantes por purpose no código; a confirmação visual no Firefox ESR para os 10 purposes nos modos claro e escuro permanece pendente de runtime.
- **Portabilidade mobile:** A regra de 320 CSS px está implementada; a captura visual sem rolagem horizontal permanece pendente de runtime.
- **Contratos e Gates:** Suíte `validate-aculta420-v2-foundations.php` expandida com 73 pares de contraste textuais e estruturais, todos com verificação estrita PASS.
- **Ambiente Homelab:** Captura em runtime real no Homelab permanece pendente (DEFERRED) para sincronização final.

### Atualização de 2026-11-06

- `scripts/validate-aculta420-v2-foundations.php`: PASS; confirmou que `buttons.css` não contém `--color-brand-highlight`, `--purpose-*` nem `filter: brightness(...)`.
- PHPUnit focal do tema: 12 testes e 56 assertions PASS. Não há suíte de botão isolada nos caminhos de teste encontrados.
- Firefox ESR no Runtime: 40 casos nos dez purposes, claro/escuro e 320/1440 px; 358/360 verificações passaram. Botões primário e contorno corresponderam aos tokens `--button-*`, sem filter. Dois casos falharam por overflow geral do Fórum a 320 px. Capturas em `tests/browser/capturas/purpose-buttons/2026-11-06/`; repetir após a correção do overflow.
