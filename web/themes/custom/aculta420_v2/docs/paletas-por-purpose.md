# Paletas de cor por purpose

Status: **aprovado pelo responsável em 2026-11-05**. Implementação no tema pendente (ver "Pendências").

Este documento oficializa as paletas do tema V2 (`aculta420_v2`) para os purposes do Portal. Ele é a fonte de verdade para nomes, chaves e valores. Se a implementação divergir, o documento vence, ou ele é alterado antes, com decisão registrada.

## Regras

1. **Cor de referência da V1.** Cada esquema parte de uma cor da V1 (`web/themes/custom/aculta420/css/tokens.css`), sem criar matiz novo. Variações são só de tom.
2. **Destaque e subelementos.** O destaque é a cor principal do purpose. As outras três cores da V1 (verde, amarelo e vermelho) são subelementos. Nenhum purpose usa cor fora desta paleta.
3. **Variações discretas.** Três tons por cor: fiel à V1, tom mais claro e tom mais escuro. Ajuste de luminosidade de ±5 pontos, sem mudar matiz.
4. **Modo escuro.** O destaque sobe de luminosidade (cerca de +6 pontos) e ganha cerca de 10% de saturação. O matiz não muda. Nunca se inverte a cor.
5. **Exceção da Conta.** O verde escuro da V1 não atinge contraste com fundo escuro. No modo escuro, a Conta usa um verde mais claro (`#06a868`), com texto preto.
6. **Superfícies escuras.** Base e três níveis de elevação, cada um cerca de 6% mais claro que o anterior. Elevação vem de luminosidade, não de sombra.
7. **Texto sobre destaque.** Preto ou branco, o que tiver mais contraste.
8. **Contraste.** Texto normal: 4,5:1 (WCAG AA). Elementos decorativos e de interface: 3:1. Amarelos sobre creme ficam abaixo de 3:1; funcionam como faixa com texto escuro, como na V1, e não como texto ou controle sem outro sinal.

## Esquemas

Os nomes aparecem no select de cada purpose na tela Aparência → ACULTA420 V2 → Configurações. A chave é o valor guardado pelo tema. Os nomes não se referem a purposes.

| Nome | Chave | Purpose de origem | Tom | Destaque claro | Destaque escuro | Contraste destaque × creme | Contraste texto × destaque (claro) | Contraste destaque × fundo escuro | Contraste texto × destaque (escuro) |
|---|---|---|---|---|---|---|---|---|---|
| **Sativa** | `verde-sativa` | Principal | `fiel` | `#689427` | `#7ab327` | 3.28 | 5.85 | 6.74 | 8.3 |
| **Ruderalis** | `verde-ruderalis` | Wiki | `suave` | `#76a82c` | `#88c72b` | 2.59 | 7.41 | 8.32 | 10.25 |
| **Indica** | `verde-indica` | Conta | `fiel` | `#0c3c29` | `#06a868` | 11.34 | 12.4 | 5.53 | 6.81 |
| **Lemon Haze** | `amarelo-lemon-haze` | Suporte | `fiel` | `#f2ca36` | `#fcd64b` | 1.45 | 13.28 | 12.06 | 14.86 |
| **Golden Goat** | `amarelo-golden-goat` | Loja | `suave` | `#f4d04e` | `#fddc64` | 1.37 | 13.98 | 12.66 | 15.6 |
| **Red Congolese** | `vermelho-red-congolese` | Revista | `suave` | `#d85842` | `#e46a55` | 3.56 | 5.39 | 5.27 | 6.49 |
| **Red Kush** | `vermelho-red-kush` | Cursos | `profunda` | `#c03d27` | `#dd4228` | 4.88 | 5.33 | 3.97 | 4.88 |

### Sativa (`verde-sativa`)

- Origem: purpose Principal. Tom escolhido: `fiel`.
- Destaque, claro: `#689427`. Escuro: `#7ab327`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#f2ca36`, `#d4452d`.
- Subelementos, escuro: `#fcd64b`, `#e1563f`.
### Ruderalis (`verde-ruderalis`)

- Origem: purpose Wiki. Tom escolhido: `suave`.
- Destaque, claro: `#76a82c`. Escuro: `#88c72b`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#f4d04e`, `#d85842`.
- Subelementos, escuro: `#fddc64`, `#e46a55`.
### Indica (`verde-indica`)

- Origem: purpose Conta. Tom escolhido: `fiel`.
- Destaque, claro: `#0c3c29`. Escuro: `#06a868`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#689427`, `#f2ca36`, `#d4452d`.
- Subelementos, escuro: `#7ab327`, `#fcd64b`, `#e1563f`.
### Lemon Haze (`amarelo-lemon-haze`)

- Origem: purpose Suporte. Tom escolhido: `fiel`.
- Destaque, claro: `#f2ca36`. Escuro: `#fcd64b`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#689427`, `#d4452d`.
- Subelementos, escuro: `#7ab327`, `#e1563f`.
### Golden Goat (`amarelo-golden-goat`)

- Origem: purpose Loja. Tom escolhido: `suave`.
- Destaque, claro: `#f4d04e`. Escuro: `#fddc64`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#76a82c`, `#d85842`.
- Subelementos, escuro: `#88c72b`, `#e46a55`.
### Red Congolese (`vermelho-red-congolese`)

- Origem: purpose Revista. Tom escolhido: `suave`.
- Destaque, claro: `#d85842`. Escuro: `#e46a55`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#76a82c`, `#f4d04e`.
- Subelementos, escuro: `#88c72b`, `#fddc64`.
### Red Kush (`vermelho-red-kush`)

- Origem: purpose Cursos. Tom escolhido: `profunda`.
- Destaque, claro: `#c03d27`. Escuro: `#dd4228`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#5a8022`, `#f0c41e`.
- Subelementos, escuro: `#6c9e23`, `#fbd132`.

## Superfícies

| Papel | Claro | Escuro |
|---|---|---|
| Fundo da página | `#fbf4e8` (creme V1) | `#08201a` |
| Elevação 1 | — | `#0f2b21` |
| Elevação 2 | — | `#15372b` |
| Elevação 3 | — | `#1c4335` |

## Tokens

Nomes semânticos, sem a cor no nome. Valores em cada modo vêm do esquema escolhido.

| Token | Papel |
|---|---|
| `--purpose-accent` | Destaque do purpose (faixa, destaque, realce) |
| `--purpose-on-accent` | Texto sobre o destaque |
| `--purpose-sub-1` a `--purpose-sub-3` | Subelementos, nesta ordem |
| `--purpose-surface-1` a `--purpose-surface-3` | Elevações no modo escuro |

Os tokens são definidos sob o seletor do purpose e redefinidos sob o modo escuro, seguindo o padrão de `:root`, `prefers-color-scheme` e `[data-theme]` do Portal.

## Pendências

- Implementar o select de esquema por purpose na tela de configurações do tema, com os nomes e chaves acima.
- Trocar os valores de `tokens.css` pelos desta paleta, em versão MINOR.
- Atualizar `configuracao-do-tema.md` seção 2.1 e `architecture.md` com o estado real.
- Validar contraste no tema com os gates de design e de fundações.

## Histórico

- 2026-11-05: paleta gerada a partir das cores da V1 e do reggae. Versão aprovada com esquemas nomeados por variedades de cannabis, sem nome de purpose.
