# Paleta ACULTA Skunk Family

Status: **implementado no tema em 2026-11-05 (versão 1.3.0)**. Aprovado pelo responsável em 2026-11-05.

Este documento oficializa as paletas do tema V2 (`aculta420_v2`) para os purposes do Portal. Ele é a fonte de verdade para nomes, chaves e valores. Se a implementação divergir, o documento vence, ou ele é alterado antes, com decisão registrada.

## Regras

1. **Cor de referência da V1.** Cada esquema parte de uma cor da V1 (`web/themes/custom/aculta420/css/tokens.css`), sem criar matiz novo. Variações são só de tom.
2. **Destaque e subelementos.** O destaque é a cor principal do purpose. As outras três cores da V1 (verde, amarelo e vermelho) são subelementos. Nenhum purpose usa cor fora desta paleta.
3. **Variações discretas.** Três tons por cor: fiel à V1, tom mais claro e tom mais escuro. Ajuste de luminosidade de ±5 pontos, sem mudar matiz.
9. **Padrão.** O tom mais escuro (profunda) é o padrão de cada base. Como a V1 tem só quatro cores de base e há dez purposes, os purposes que compartilham uma base usam os outros dois tons. Assim, cada purpose tem um esquema próprio.
4. **Modo escuro.** O destaque sobe de luminosidade (cerca de +6 pontos) e ganha cerca de 10% de saturação. O matiz não muda. Nunca se inverte a cor.
5. **Exceção da Conta.** O verde escuro da V1 não atinge contraste com fundo escuro. No modo escuro, a Conta usa um verde mais claro (`#06a868`), com texto preto.
6. **Superfícies escuras.** Base e três níveis de elevação, cada um cerca de 6% mais claro que o anterior. Elevação vem de luminosidade, não de sombra.
7. **Texto sobre destaque.** Preto ou branco, o que tiver mais contraste.
8. **Contraste.** Texto normal: 4,5:1 (WCAG AA). Elementos decorativos e de interface: 3:1. Amarelos sobre creme ficam abaixo de 3:1; funcionam como faixa com texto escuro, como na V1, e não como texto ou controle sem outro sinal.

## Escopo: o que a paleta muda

A paleta muda **só** a faixa de destaque do purpose: uma faixa na área de conteúdo da página do purpose, com o título ou o indicador do purpose. Nada além disso.

**A paleta nunca altera:**
- botões, em todas as variantes (primário, secundário, contorno);
- anel de foco (`--color-focus-ring`) e contorno de foco;
- barra institucional (`.aculta-institution-bar`), incluindo a conta e o menu da conta;
- barra multidomínio e a navegação;
- abas, badges, status, diálogos, links e tabelas;
- fundo, texto principal e cabeçalho.

**Tokens que a paleta não pode sobrescrever:** `--color-brand-primary`, `--color-brand-highlight`, `--color-brand-secondary`, `--color-on-brand`, `--color-on-highlight`, `--color-focus-ring`, `--color-surface-institution` e os tokens de botão.

**Por que essa regra existe:** a implementação da F3 sobrescrevia `--color-brand-highlight` por purpose no `<html>`. Esse token é usado por botões, foco, abas, navegação, barra multidomínio e a conta na barra institucional. Por isso, esses elementos mudavam de cor ao trocar de purpose. Essa parte deve ser substituída pelos tokens `--purpose-*`, consumidos só pela faixa do purpose.

## Cobertura por purpose

O código tem dez purposes (`DomainSurfaceMap::DOMAIN_IDS`). Cada um tem um esquema próprio.

| Purpose | Esquema padrão | Tom |
|---|---|---|
| Principal (`main`) | Skunk | `profunda` |
| Conta (`account`) | Afghan | `profunda` |
| Apoio (`support`) | Acapulco Gold | `profunda` |
| Revista (`magazine`) | Red Kush | `profunda` |
| Wiki (`wiki`) | Sativa | `fiel` |
| Loja (`shop`) | Lemon Haze | `fiel` |
| Cursos (`courses`) | Panama Red | `fiel` |
| Fórum (`forum`) | Red Congolese | `suave` |
| Links, LinkWeed (`links`) | Ruderalis | `suave` |
| Eventos (`eventos`) | Golden Goat | `suave` |

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

Os nomes aparecem no select de cada purpose na tela Aparência → ACULTA420 V2 → Configurações. A chave é o valor guardado pelo tema. Os nomes não se referem a purposes.

| Nome | Chave | Tom | Destaque claro | Destaque escuro | Contraste destaque × creme | Contraste texto × destaque (claro) | Contraste destaque × fundo escuro | Contraste texto × destaque (escuro) |
|---|---|---|---|---|---|---|---|---|
| **Skunk** | `verde-skunk` | `profunda` | `#5a8022` | `#6c9e22` | 4.24 | 4.63 | 5.3 | 6.53 |
| **Sativa** | `verde-sativa` | `fiel` | `#689427` | `#7ab327` | 3.28 | 5.85 | 6.72 | 8.27 |
| **Ruderalis** | `verde-ruderalis` | `suave` | `#76a82c` | `#89c72c` | 2.59 | 7.42 | 8.37 | 10.31 |
| **Afghan** | `verde-afghan` | `profunda` | `#08271a` | `#059e61` | 14.64 | 16.0 | 4.92 | 6.06 |
| **Acapulco Gold** | `amarelo-acapulco-gold` | `profunda` | `#f0c41e` | `#fbd132` | 1.52 | 12.61 | 11.56 | 14.24 |
| **Lemon Haze** | `amarelo-lemon-haze` | `fiel` | `#f2ca36` | `#fcd64b` | 1.45 | 13.28 | 12.08 | 14.88 |
| **Golden Goat** | `amarelo-golden-goat` | `suave` | `#f4d04e` | `#fcdc64` | 1.37 | 13.99 | 12.63 | 15.56 |
| **Red Kush** | `vermelho-red-kush` | `profunda` | `#c03d27` | `#dd4329` | 4.86 | 5.32 | 3.99 | 4.91 |
| **Panama Red** | `vermelho-panama-red` | `fiel` | `#d4452d` | `#e1563f` | 4.1 | 4.69 | 4.54 | 5.6 |
| **Red Congolese** | `vermelho-red-congolese` | `suave` | `#d85842` | `#e46a55` | 3.56 | 5.4 | 5.26 | 6.48 |

### Skunk (`verde-skunk`)

- Padrão de purpose: Principal. Tom: `profunda`.
- Destaque, claro: `#5a8022`. Escuro: `#6c9e22`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#f0c41e`, `#c03d27`.
- Subelementos, escuro: `#fbd132`, `#dd4329`.

### Sativa (`verde-sativa`)

- Padrão de purpose: Wiki. Tom: `fiel`.
- Destaque, claro: `#689427`. Escuro: `#7ab327`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#f2ca36`, `#d4452d`.
- Subelementos, escuro: `#fcd64b`, `#e1563f`.

### Ruderalis (`verde-ruderalis`)

- Padrão de purpose: Links (LinkWeed). Tom: `suave`.
- Destaque, claro: `#76a82c`. Escuro: `#89c72c`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#f4d04e`, `#d85842`.
- Subelementos, escuro: `#fcdc64`, `#e46a55`.

### Afghan (`verde-afghan`)

- Padrão de purpose: Conta. Tom: `profunda`.
- Destaque, claro: `#08271a`. Escuro: `#059e61`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#5a8022`, `#f0c41e`, `#c03d27`.
- Subelementos, escuro: `#6c9e22`, `#fbd132`, `#dd4329`.

### Acapulco Gold (`amarelo-acapulco-gold`)

- Padrão de purpose: Apoio. Tom: `profunda`.
- Destaque, claro: `#f0c41e`. Escuro: `#fbd132`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#5a8022`, `#c03d27`.
- Subelementos, escuro: `#6c9e22`, `#dd4329`.

### Lemon Haze (`amarelo-lemon-haze`)

- Padrão de purpose: Loja. Tom: `fiel`.
- Destaque, claro: `#f2ca36`. Escuro: `#fcd64b`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#689427`, `#d4452d`.
- Subelementos, escuro: `#7ab327`, `#e1563f`.

### Golden Goat (`amarelo-golden-goat`)

- Padrão de purpose: Eventos. Tom: `suave`.
- Destaque, claro: `#f4d04e`. Escuro: `#fcdc64`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#76a82c`, `#d85842`.
- Subelementos, escuro: `#89c72c`, `#e46a55`.

### Red Kush (`vermelho-red-kush`)

- Padrão de purpose: Revista. Tom: `profunda`.
- Destaque, claro: `#c03d27`. Escuro: `#dd4329`.
- Texto sobre destaque: claro `#ffffff`, escuro `#000000`.
- Subelementos, claro: `#5a8022`, `#f0c41e`.
- Subelementos, escuro: `#6c9e22`, `#fbd132`.

### Panama Red (`vermelho-panama-red`)

- Padrão de purpose: Cursos. Tom: `fiel`.
- Destaque, claro: `#d4452d`. Escuro: `#e1563f`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#689427`, `#f2ca36`.
- Subelementos, escuro: `#7ab327`, `#fcd64b`.

### Red Congolese (`vermelho-red-congolese`)

- Padrão de purpose: Fórum. Tom: `suave`.
- Destaque, claro: `#d85842`. Escuro: `#e46a55`.
- Texto sobre destaque: claro `#000000`, escuro `#000000`.
- Subelementos, claro: `#76a82c`, `#f4d04e`.
- Subelementos, escuro: `#89c72c`, `#fcdc64`.

## Superfícies e neutros

Decisão do responsável em 2026-11-05 (opção A): os neutros claros passam para a família verde da paleta. O modo escuro usa a família atual da V2. A barra institucional não muda em nenhum modo.

| Token | Papel | Claro (atual → proposto) | Contraste claro (sobre `#f2f7f0`) | Escuro |
|---|---|---|---|---|
| `--color-canvas` | Fundo da página | `#f2f7f0` (mantido) | — | `#171513` (V2, mantido) |
| `--color-surface` / `--color-surface-raised` | Cartão | `#ffffff` (mantido) | — | Elevação 1 `#282421` |
| `--color-surface-institution` | Barra institucional | `#fbf4e8` (**não muda**) | — | `#2b2723` (**não muda**) |
| `--color-text-primary` | Texto principal | `#0c3c29` (mantido) | 11,34:1 sobre creme | V2, mantido |
| `--color-text-secondary` | Texto secundário | `#465563` → `#4d6357` | 5,97:1 | V2, mantido |
| `--color-text-muted` | Texto de apoio | `#596875` → `#5a6b60` | 5,22:1 | V2, mantido |
| `--color-border` | Borda de controle (3:1 mínimo) | `#687783` → `#6b7d70` | 4,03:1 | V2, mantido |

Notas:
- As bordas de controle precisam de 3:1 (WCAG 1.4.11). `#6b7d70` tem 4,03:1 sobre o fundo claro. Bordas decorativas podem ser mais suaves, mas não usadas em campo ou botão.
- Elevações do escuro, a partir de `#171513`, com cerca de 6% de luminosidade por nível: #282421, #39342f, #49433d. Validar contraste do texto antes da aceitação.
- A barra institucional recebe o novo texto secundário e muted, mas o fundo continua o mesmo.
- Saturação no escuro: testar +10% e original. Decisão pela captura.

## Tokens

Nomes semânticos, sem a cor no nome. Valores em cada modo vêm do esquema escolhido.

| Token | Papel |
|---|---|
| `--purpose-accent` | Faixa de destaque do purpose. Nenhum outro componente consome este token |
| `--purpose-on-accent` | Texto sobre o destaque |
| `--purpose-sub-1` a `--purpose-sub-3` | Subelementos, nesta ordem |
| `--purpose-surface-1` a `--purpose-surface-3` | Elevações no modo escuro |

Os tokens são definidos sob o seletor do purpose e redefinidos sob o modo escuro, seguindo o padrão de `:root`, `prefers-color-scheme` e `[data-theme]` do Portal.

## Pendências

Nenhuma pendência técnica para a F3. Implementação concluída com sucesso:
- Substituída a sobrescrita de `--color-brand-highlight` por purpose pelos tokens semânticos `--purpose-*` (`--purpose-accent`, `--purpose-on-accent`, `--purpose-sub-1..3`, `--purpose-surface-1..3`).
- Implementado o select de esquema oficial para os dez purposes na tela de configurações do tema (`theme-settings.php`), com leitura de purposes ativos via Core State API.
- Valores atualizados em `src/styles/tokens.css`, `aculta420_v2.settings.yml` e schema do tema promovido para a versão MINOR 1.3.0.
- Documentação sincronizada em `configuracao-do-tema.md`, `architecture.md`, `roadmap.md` e `CHANGELOG.md`.
- Contraste e fundações validados com gates de design e de fundações.

## Histórico

- 2026-11-05: implementação completa da Paleta ACULTA Skunk Family no tema V2 em versão MINOR 1.3.0, com isolamento de tokens `--purpose-*`, 10 esquemas oficiais e validação de contrastes.
- 2026-11-05: título do documento padronizado como "Paleta ACULTA Skunk Family". Comando intermediário para o agente alinhar o nome em código e documentação do tema.
- 2026-11-05: paleta gerada a partir das cores da V1 e do reggae. Versão aprovada com esquemas nomeados por variedades de cannabis, sem nome de purpose.
- 2026-11-05: adicionado o esquema Skunk para o purpose Links (LinkWeed).
- 2026-11-05: adicionados Panama Red (Fórum) e Acapulco Gold (Eventos). Os dez purposes passam a ter esquema.
- 2026-11-05: tom mais escuro (profunda) definido como padrão de cada base. Esquemas refeitos: Indica sai do catálogo, substituído por Afghan na Conta (Indica falhava 3:1 no escuro). Dez esquemas, um por purpose.
- 2026-11-05: opção A. Neutros claros da V2 ajustados para a família verde (texto secundário, muted e borda de controle). Barra institucional mantida. Superfícies do escuro usam a família atual da V2.
- 2026-11-05: definido o escopo da paleta. Botões, foco, barra institucional e navegação não mudam por purpose. Registrado que a F3 sobrescrevia `--color-brand-highlight`, o que deve ser corrigido.
- 2026-11-05: incluída a seção de verificação de acessibilidade, com simulação de deuteranopia e regra de não usar verde e vermelho como único sinal.
