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

## Cobertura por purpose

O código tem dez purposes (`DomainSurfaceMap::DOMAIN_IDS`). A tabela abaixo confirma que cada um tem esquema.

| Purpose | Chave | Esquema |
|---|---|---|
| Principal (`main`) | `main` | Sativa |
| Conta (`account`) | `account` | Indica |
| Apoio (`support`) | `support` | Lemon Haze |
| Revista (`magazine`) | `magazine` | Red Congolese |
| Wiki (`wiki`) | `wiki` | Ruderalis |
| Loja (`shop`) | `shop` | Golden Goat |
| Cursos (`courses`) | `courses` | Red Kush |
| Fórum (`forum`) | `forum` | Panama Red |
| Links, LinkWeed (`links`) | `links` | Skunk |
| Eventos (`eventos`) | `eventos` | Acapulco Gold |

## Verificação de acessibilidade

Base da pesquisa: W3C, Understanding SC 1.4.11 (non-text contrast), e recomendações de paleta para daltonismo (Lyssna, 2026).

**O que a norma exige e como a paleta responde**

- **Texto normal:** 4,5:1. Verificado para o texto sobre o destaque de todos os esquemas, nos dois modos.
- **Componentes de interface e gráficos necessários para entender o conteúdo:** 3:1. Verificado para o destaque sobre o creme (claro) e sobre o fundo escuro (escuro). Os amarelos (Lemon Haze, Golden Goat, Acapulco Gold) ficam abaixo de 3:1 sobre o creme. Por isso, nunca são o único sinal de um controle ou de um estado. Sempre há texto ou ícone junto.
- **Estado só por cor:** a norma aceita só se a diferença de luminância for de pelo menos 3:1 ou se houver outro indicador. Por isso, as cores do tema não carregam estado sozinhas.
- **Elementos desativados e decorativos** ficam fora da exigência de 3:1.

**Daltonismo (simulação de deuteranopia, severidade total, Machado et al. 2009)**

- Verde V1 e vermelho V1 ficam quase iguais: diferença de cor de 3,1 (escala CIELAB, em que abaixo de cerca de 10 já é difícil distinguir). Sem daltonismo, a diferença é 87,3.
- Verde e amarelo, e amarelo e vermelho, continuam distintos: cerca de 38 nos dois casos.
- Consequência: **nenhum estado do sistema (erro, sucesso, aviso) usa só verde e vermelho.** Os estados de interface usam os tokens de status, sempre com texto e ícone.
- Consequência: o nome do purpose é sempre mostrado em texto (rótulo, título ou menu). A cor não é a única forma de identificar um purpose.
- Os pares Sativa e Panama Red (ou Kush) são iguais para quem tem deuteranopia. Por isso, a cor do purpose é sempre acompanhada do nome.

**Modo escuro:** o destaque sobe de luminosidade e não se inverte. A elevação vem de luminosidade. Os contrastes medidos estão nas tabelas acima.

**APCA:** a pesquisa indica que APCA é mais fiel à percepção em fundo escuro, mas ainda não é o padrão do projeto. Os gates usam WCAG 2. Esta paleta usa WCAG 2 como referência. Se o projeto adotar APCA, os valores devem ser reavaliados.

**Limitação:** a simulação é aproximada. Antes da aceitação, testar com pessoas com daltonismo e em dispositivos reais.

## Esquemas

Os nomes aparecem no select de cada purpose na tela Aparência → ACULTA420 V2 → Configurações. A chave é o valor guardado pelo tema. Os nomes não se referem a purposes. O purpose Links (módulo LinkWeed) tem o esquema Skunk.

| Nome | Chave | Purpose de origem | Tom | Destaque claro | Destaque escuro | Contraste destaque × creme | Contraste texto × destaque (claro) | Contraste destaque × fundo escuro | Contraste texto × destaque (escuro) |
|---|---|---|---|---|---|---|---|---|---|
| **Sativa** | `verde-sativa` | Principal | `fiel` | `#689427` | `#7ab327` | 3.28 | 5.85 | 6.74 | 8.3 |
| **Ruderalis** | `verde-ruderalis` | Wiki | `suave` | `#76a82c` | `#88c72b` | 2.59 | 7.41 | 8.32 | 10.25 |
| **Indica** | `verde-indica` | Conta | `fiel` | `#0c3c29` | `#06a868` | 11.34 | 12.4 | 5.53 | 6.81 |
| **Lemon Haze** | `amarelo-lemon-haze` | Suporte | `fiel` | `#f2ca36` | `#fcd64b` | 1.45 | 13.28 | 12.06 | 14.86 |
| **Golden Goat** | `amarelo-golden-goat` | Loja | `suave` | `#f4d04e` | `#fddc64` | 1.37 | 13.98 | 12.66 | 15.6 |
| **Red Congolese** | `vermelho-red-congolese` | Revista | `suave` | `#d85842` | `#e46a55` | 3.56 | 5.39 | 5.27 | 6.49 |
| **Red Kush** | `vermelho-red-kush` | Cursos | `profunda` | `#c03d27` | `#dd4228` | 4.88 | 5.33 | 3.97 | 4.88 |
| **Skunk** | `verde-skunk` | Links (LinkWeed) | `profunda` | `#5a8022` | `#6c9e22` | 4.24 | 4.63 | 5.30 | 6.53 |
| **Panama Red** | `vermelho-panama-red` | Fórum | `fiel` | `#d4452d` | `#e1563f` | 4.1 | 4.69 | 4.54 | 5.6 |
| **Acapulco Gold** | `amarelo-acapulco-gold` | Eventos | `profunda` | `#f0c41e` | `#fbd132` | 1.52 | 12.62 | 11.57 | 14.25 |

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

### Skunk (`verde-skunk`)

- Origem: purpose Links (LinkWeed, rota `/redes-sociais`). Tom escolhido: `profunda`.
- Destaque, claro: `#5a8022`. Escuro: `#6c9e22`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#08271a`, `#f0c41e`, `#c03d27`.
- Subelementos, escuro: `#0a432c`, `#fbd132`, `#dd4329`.
- Observação: o tom escuro da Skunk é o verde V1 mais profundo. Ele fica em contraste com o fundo escuro (5,30:1) e com o creme (4,24:1), por isso serve para as páginas de links, que são de leitura e de ação.

### Panama Red (`vermelho-panama-red`)

- Origem: purpose Fórum (`forum`). Tom escolhido: `fiel`.
- Destaque, claro: `#d4452d`. Escuro: `#e1563f`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#689427`, `#f2ca36`.
- Subelementos, escuro: `#7ab327`, `#fcd64b`.

### Acapulco Gold (`amarelo-acapulco-gold`)

- Origem: purpose Eventos (`eventos`). Tom escolhido: `profunda`.
- Destaque, claro: `#f0c41e`. Escuro: `#fbd132`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#5a8022`, `#c03d27`.
- Subelementos, escuro: `#6c9e23`, `#dd4228`.

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

- Implementar o select de esquema por purpose (dez purposes) na tela de configurações do tema, com os nomes e chaves acima.
- Trocar os valores de `tokens.css` pelos desta paleta, em versão MINOR.
- Atualizar `configuracao-do-tema.md` seção 2.1 e `architecture.md` com o estado real.
- Validar contraste no tema com os gates de design e de fundações.

## Histórico

- 2026-11-05: paleta gerada a partir das cores da V1 e do reggae. Versão aprovada com esquemas nomeados por variedades de cannabis, sem nome de purpose.
- 2026-11-05: adicionado o esquema Skunk para o purpose Links (LinkWeed).
- 2026-11-05: adicionados Panama Red (Fórum) e Acapulco Gold (Eventos). Os dez purposes passam a ter esquema.
- 2026-11-05: incluída a seção de verificação de acessibilidade, com simulação de deuteranopia e regra de não usar verde e vermelho como único sinal.
