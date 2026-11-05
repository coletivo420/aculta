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

## 5. Pendências

- Os botões ainda não são consumidos pelos componentes: `components/button/*.css`
  usa `--color-brand-highlight` e `filter: brightness(.9)` no hover. A migração
  para os tokens `--button-*` é tarefa de implementação, ainda não feita.
- Matriz de contraste sobre as dez faixas de purpose, nos dois modos: ainda
  não medida. Um par que falhar é corrigido no botão ou na faixa, nunca no
  purpose.
- Captura visual nos dois modos, no Homelab, depois da migração.
