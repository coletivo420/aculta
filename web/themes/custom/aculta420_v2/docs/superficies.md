# Superfícies: raios, caixas e blocos de destaque (V2)

Regras para botões, cartões, blocos com fundo e painéis, nos modos claro e
escuro. Vale para o Studio e para as demais telas da V2. Origem: pedido do
responsável em 2026-11-05 (arredondar botões e blocos, padronizar caixas e
blocos de destaque com fundo) e imagem de referência de cartão editorial.

Estado: **documentado. Implementação DEFERRED**: as mudanças de CSS dependem de
captura nos dois modos e de gates, a rodar no Homelab.

## 1. Escala de raios (decidida)

| Uso | Token | Valor | Observação |
| --- | --- | --- | --- |
| Botões | `--radius-lg` | 16px (1rem) | Decidido em 2026-11-05. Antes era `--radius-base` (8px). |
| Cartões e blocos com fundo | `--radius-lg` | 16px | Mesmo raio dos botões, para a família ficar igual. |
| Painéis e popups | `--radius-lg` | 16px | Popup de login e de foto. |
| Campos de formulário | `--radius-base` | 8px | **Padrão**, não pedido. Confirmar. |
| Badges, contadores e avatar | `--radius-full` | 9999px | Pílulas e círculos. |
| Ícones quadrados e miniaturas pequenas | `--radius-sm` | 4px | Uso restrito. |

Regra: o raio da mídia (imagem no topo do cartão) segue o raio do cartão. O
cartão tem `overflow: hidden`, então a imagem é recortada pelo mesmo raio.

## 2. Caixas padronizadas

Três tipos. Cada um tem um papel fixo; não se cria um quarto tipo sem registro.

| Tipo | Uso | Fundo (claro) | Fundo (escuro) | Borda | Raio | Sombra |
| --- | --- | --- | --- | --- | --- | --- |
| **Cartão** | item de lista, cartão de experiência, cartão editorial | `--color-surface-raised` (branco) | `--color-surface-raised` | 1px `--color-border` | `--radius-lg` | `--shadow-raised` |
| **Bloco de destaque** | conteúdo que precisa de atenção, cartão de suíte, bloco de resumo | `--color-surface-highlight` (proposto, seção 3) | idem, escuro | 1px `--color-border-highlight` (proposto) | `--radius-lg` | nenhuma |
| **Painel** | popup, menu hambúrguer, painel de sobreposição | `--color-surface-raised` | `--color-surface-raised` | 1px `--color-border` | `--radius-lg` | `--shadow-raised` (escuro: mais forte, já no token) |

Regras:

- Cartão e bloco de destaque nunca são aninhados um dentro do outro com o mesmo fundo.
- Espaçamento interno padrão: `--spacing-sm` a `--spacing-md`, a confirmar com a escala de espaço.
- O título do cartão fica em Oswald (`--font-family-heading`), e o texto em Inter.
- O rótulo de categoria (kicker) é texto pequeno em caixa alta, na cor de marca `--color-brand-primary` (o token já troca de valor entre claro e escuro).

## 3. Blocos de destaque com fundo: pesquisa de cor

Pedido: os blocos são brancos no modo claro. Queremos fundo colorido para
destaque, nos dois modos. Os valores abaixo foram calculados contra o texto
(`--color-text-primary`: `#0c3c29` no claro; `#f4efe8` no escuro) e contra o
cartão branco e a página do modo escuro (`#211e1b`).

Contraste de texto: todos os candidatos passam WCAG AA com folga (mínimo 4,5:1).
O critério de escolha é, então, diferença visível em relação ao cartão e
identidade com a marca.

### 3.1 Claro

| Candidato | Hex | Texto principal (contraste) | Diferença para o cartão branco | Leitura |
| --- | --- | --- | --- | --- |
| Creme institucional | `#fbf4e8` | 11,3:1 | 1,09 | Cor da barra institucional. Sutil, identidade direta. **Recomendado.** |
| Verde pálido da V1 | `#f2f7f0` | 11,4:1 | 1,09 | Fundo de página da V1. Sutil, mais frio. |
| Verde-folha claro | `#e6f4ea` | 10,9:1 | 1,14 | Mais forte, mas é a cor de status "ok". Não usar para decoração: reservada ao semântico. |
| Amarelo de destaque suave | `#fff8d6` | 11,6:1 | 1,07 | Chama atenção demais para bloco comum; reservado à ação. |

### 3.2 Escuro

| Candidato | Hex | Texto principal (contraste) | Diferença para a página escura | Leitura |
| --- | --- | --- | --- | --- |
| Superfície elevada atual | `#2b2723` | 12,9:1 | 1,12 | Cor que já existe. Diferença pequena. |
| Verde escuro da marca, suave | `#2a3a2f` | 10,5:1 | 1,38 | Tom de verde escondido, ligado à marca. **Recomendado.** |
| Verde escuro mais profundo | `#1f2b24` | 12,8:1 | 1,13 | Diferença pequena demais. |
| Marrom-amarelado | `#3a3115` | 11,2:1 | 1,29 | Lembra o amarelo, mas fica pesado. |

### 3.3 Recomendação

- **Claro:** creme institucional `#fbf4e8`, com borda `--color-border-highlight` para a diferença ficar visível.
- **Escuro:** verde escuro suave `#2a3a2f`, com a mesma borda.
- Os dois valores ficam em tokens semânticos (`--color-surface-highlight`,
  `--color-border-highlight`), definidos em `src/styles/tokens.css` nos dois modos.
  Componente não escolhe cor: só usa o token (regra da V2, AGENTS seção 2).
- Valor final: confirmar com captura nos dois modos. A diferença de 1,09 no claro
  é sutil; se não aparecer na captura, o próximo passo é subir o tom, não colocar
  sombra forte.

## 4. Modo claro e escuro

- Light e dark são o mesmo tema. Muda a luz, não a arquitetura (ADR-046).
- Nenhum componente tem seletor de escuro. Os valores entram em `tokens.css`
  para `:root` e para `[data-theme="dark"]`.
- Sem `prefers-color-scheme`.
- Borda e sombra são checadas nos dois modos. No escuro, a sombra vem do token
  (já mais forte), e a borda ajuda a separar a caixa da página.

## 4.1 Escala de espaçamento

A escala de espaçamento da V2 fica centralizada em `src/styles/tokens.css`.
Componentes devem usar estes tokens, sem criar escalas paralelas:

| Token | Valor | Uso de referência |
| --- | --- | --- |
| `--spacing-xs` | `.25rem` | separação mínima e rótulos |
| `--spacing-sm` | `.5rem` | controles compactos e ícones |
| `--spacing-md` | `.75rem` | grupos de controles |
| `--spacing-base` | `1rem` | preenchimento padrão |
| `--spacing-lg-compact` | `1.25rem` | cartões compactos |
| `--spacing-lg` | `1.5rem` | cartões e painéis |
| `--spacing-xl` | `2rem` | separação entre blocos |
| `--spacing-xxl` | `3rem` | separação editorial |
| `--spacing-section` | `clamp(1.5rem, 3vw, 2.5rem)` | seções responsivas |

## 5. Imagem de referência (cartão editorial)

Referência enviada em 2026-11-05 (cartão de teste com conteúdo sintético). Pontos
que o cartão deve seguir, lidos da imagem:

- Cantos arredondados no cartão inteiro, com raio próximo de 16px.
- Mídia no topo, sem margem interna, recortada pelo mesmo raio.
- Borda fina em tom neutro escuro, por volta de 1px.
- Miolo com espaço interno uniforme, pelo menos 16px.
- Ordem do miolo: rótulo de categoria em verde e caixa alta, título em
  Oswald, subtítulo em semibold, texto corrido em Inter.
- Sem sombra forte: a borda separa o cartão da página.

Nota: o conteúdo da imagem é sintético e não deve ser copiado para fixtures
de produção (ver studio-visual-spec, seção 9, item 13).

## 6. Aplicação

1. Trocar `--radius-base` por `--radius-lg` em `components/button/button.css`
   (e variantes), e nos cartões (`card-cultural`, cartões de experiência, cartões de suíte).
2. Criar `--color-surface-highlight` e `--color-border-highlight` em `src/styles/tokens.css`,
   nos dois modos, com os valores da seção 3.3.
3. Aplicar o bloco de destaque nos pontos definidos pelo responsável (lista na
   especificação do Studio, seção 4).
4. Capturas: claro e escuro, 320, 390, 1440 px, com zoom de 200%, em `$SUITE_OUT`.
5. Gates do tema no Homelab (`docs/gates.md`).

## 7. Pendências

- Confirmar o valor dos campos de formulário (8px padrão).
- Confirmar em captura o contraste visível do creme no claro.
- Definir quais blocos viram destaque com fundo (a lista fica na especificação do Studio).
- Atualizar `docs/components.md` quando os componentes forem alterados (AGENTS seção 6).

## 8. Faixa de acento por domínio

Alguns cartões ganham uma faixa colorida na borda inicial (lado esquerdo em
texto LTR), para identificar a área do site. A cor vem do **domínio ou
subdomínio (purpose)** onde o cartão aparece, nunca de dado do conteúdo.

### 8.1 Escala de cada cor do tema

Três cores de base, com variações. Valores calculados nesta sessão, com
contraste contra o cartão branco (modo claro) e contra a superfície escura
`#211e1b` (modo escuro).

| Cor | Passo | Hex | Contra branco | Contra escuro | Uso |
| --- | --- | --- | --- | --- | --- |
| **Verde** (`--palette-green`, `#689427`) | 50 | `#f3f6ee` | 1,09 | 15,22 | fundo suave |
| | 100 | `#e7eedc` | 1,19 | 13,94 | fundo de destaque |
| | 200 | `#cfddba` | 1,43 | 11,59 | fundo de destaque forte |
| | **500** | **`#689427`** | **3,59** | **4,62** | **faixa no claro e no escuro** |
| | 700 | `#49681b` | 6,44 | 2,58 | texto sobre claro |
| | 900 | `#2f4312` | 10,93 | 1,52 | texto sobre claro, uso raro |
| **Amarelo** (`--color-brand-highlight`, `#f2ca36`) | 50 | `#fefbef` | 1,04 | 15,97 | fundo suave |
| | 100 | `#fdf7df` | 1,08 | 15,39 | fundo de destaque |
| | 200 | `#fbeebf` | 1,16 | 14,28 | fundo de destaque forte |
| | 500 | `#f2ca36` | 1,58 | 10,49 | **faixa no escuro** |
| | **700** | **`#a98d26`** | **3,20** | **5,18** | **faixa no claro** |
| | 900 | `#6d5b18` | 6,66 | 2,49 | texto sobre claro, uso raro |
| **Vermelho** (`--color-link-editorial`, `#b52f22`) | 50 | `#f9eeed` | 1,13 | 14,63 | fundo suave |
| | 100 | `#f3dedc` | 1,29 | 12,85 | fundo de destaque |
| | 200 | `#e7bcb8` | 1,70 | 9,74 | **faixa no escuro** |
| | **500** | **`#b52f22`** | **6,19** | **2,68** | **faixa no claro** |
| | 700 | `#7f2118` | 9,87 | 1,68 | texto sobre claro |
| | 900 | `#51150f` | 14,29 | 1,16 | texto sobre claro, uso raro |

### 8.2 Regra de contraste da faixa

A faixa é elemento gráfico, não texto. Pelo critério de contraste de elementos
gráficos (WCAG 1.4.11), precisa de 3:1 contra o fundo do cartão. Por isso:

- **Claro:** verde 500 (3,59), amarelo 700 (3,20) e vermelho 500 (6,19) passam.
  O amarelo 500 (1,58) não passa: não usar como faixa no claro.
- **Escuro:** verde 500 (4,62), amarelo 500 (10,49) e vermelho 200 (9,74) passam.
  O vermelho 500 (2,68) não passa: não usar como faixa no escuro.

Os tokens de faixa ficam por modo, em `src/styles/tokens.css`:

- `--accent-green-stripe`, `--accent-yellow-stripe`, `--accent-red-stripe`
  (claro: 500, 700 e 500; escuro: 500, 500 e 200).

### 8.3 Regra de prioridade

A cor da faixa vem do **tipo de conteúdo**, não só do domínio. Nível de
conteúdo vence o domínio. Dentro de um mesmo domínio, dois níveis vizinhos
(por exemplo, evento e mapa, ou curso e aula) nunca usam a mesma cor.

Ordem de decisão:

1. Tipo de conteúdo com faixa definida (tabela 8.3.1).
2. Domínio (tabela 8.3.2), quando o tipo não tem regra própria.
3. Sem faixa.

O tema recebe a cor já resolvida como valor neutro (`accent: green | yellow | red`),
enviado pelo Portal em `domain_presentation`. O tema não decide tipo nem domínio
(ver `configuracao-do-tema.md`).

### 8.3.1 Mapeamento por tipo de conteúdo (decidido em 2026-11-05)

| Domínio | Tipo | Faixa | Token (claro / escuro) |
| --- | --- | --- | --- |
| Eventos (`events`) | evento | verde | `--accent-green-stripe` |
| Eventos (`events`) | mapa | amarelo | `--accent-yellow-stripe` |
| Eventos (`events`) | local | vermelho | `--accent-red-stripe` |
| Cursos (`courses`) | curso | verde | `--accent-green-stripe` |
| Cursos (`courses`) | aula | amarelo | `--accent-yellow-stripe` |
| Fórum (`forum`) | tópico | verde | `--accent-green-stripe` |
| Fórum (`forum`) | subtópico | vermelho | `--accent-red-stripe` |
| Wiki (`wiki`) | verbete (equivale a tópico) | verde | `--accent-green-stripe` |
| Wiki (`wiki`) | seção (equivale a subtópico) | vermelho | `--accent-red-stripe` |

A correspondência de verbete e seção com tópico e subtópico foi aplicada como
leitura da resposta "Fórum e Wiki". **Confirmar com o responsável** antes da
implementação.

Blocos decorativos de outros tipos seguem a mesma regra: a cor é atribuída
pelo nível de conteúdo, e níveis vizinhos recebem cores diferentes.

### 8.3.2 Mapeamento por domínio (fallback)

Vale quando o tipo não está na tabela 8.3.1.

| Purpose | Faixa | Token (claro / escuro) |
| --- | --- | --- |
| `shop` (Loja) | verde | `--accent-green-stripe` |
| `courses` (Cursos) | amarelo | `--accent-yellow-stripe` |
| `account` (Conta) | amarelo | `--accent-yellow-stripe` |
| `magazine` (Revista) | vermelho | `--accent-red-stripe` |
| `events` (Eventos) | vermelho | `--accent-red-stripe` |
| `forum` (Fórum) | verde | `--accent-green-stripe` |
| `wiki` (Wiki) | verde | `--accent-green-stripe` |
| `main` (principal) | sem faixa | — |
| `support` (Apoio) | sem faixa | — (padrão, a confirmar) |
| `links` (Links) | sem faixa | — (padrão, a confirmar) |

As linhas de Eventos e Cursos deste fallback são substituídas pela tabela
8.3.1 nos tipos que ela cobre (evento, mapa, local, curso e aula).

### 8.4 Onde a faixa aparece

- Categorias e tópicos do fórum, pela tabela 8.3.1 (tópico e subtópico).
- Verbetes e seções da wiki, pela tabela 8.3.1.
- Cartões de evento, mapa e local, por Eventos.
- Cartões de curso e aula, por Cursos.
- Cartões de revista, pelo purpose `magazine`.
- Demais cartões, quando o responsável indicar.

Regra: a faixa é decorativa. A informação (nome da área, tipo, categoria) fica
no texto do cartão, não só na cor.

### 8.5 Estado

- Documentação: entregue neste documento (2026-11-05).
- Implementação: **DEFERRED** no Homelab. Depende de tokens de faixa em
  `tokens.css`, da classe por tipo no componente de cartão e da chave `accent`
  no contrato do Portal.
- Captura de contraste por modo: pendente.

