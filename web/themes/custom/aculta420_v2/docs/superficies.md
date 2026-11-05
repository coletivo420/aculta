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
