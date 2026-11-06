# Configuração administrativa do tema (V2)

Opções que o administrador vê em **Aparência → ACULTA420 V2 → Configurações**.
Documenta o que existe, o que não é do tema e o que falta.

## 1. Estado

**B1 implementado e renderizado no Runtime.** Existem:

- `theme-settings.php`: formulário com a seção "Superfícies".
- `src/Hook/SurfaceSettingsHooks.php`: única implementação de `preprocess_html`,
  aplica `data-theme` ao `<html>` e as classes neutras ao `<body>` sem lógica
  de domínio.
- `config/schema/aculta420_v2.schema.yml`: schema de `aculta420_v2.settings`.
- `config/install/aculta420_v2.settings.yml`: valores padrão.

As opções são salvas em `aculta420_v2.settings` e ligadas a classes neutras no
`<body>`. A migração dos consumidores CSS e a captura visual continuam
pendentes.

### Uso por administradores

Em **Aparência → ACULTA420 V2 → Configurações → Superfícies**, ajuste os raios,
a sobrescrita de acento e a visibilidade da faixa. Salve o formulário; Drupal
persiste as opções em `aculta420_v2.settings`. Depois da aprovação da mudança,
exporte essa configuração com `drush cex` e revise somente o arquivo
`config/sync/aculta420_v2.settings.yml` antes de commitar.

### Uso por agentes

Leia os valores com
`\Drupal::service(\Drupal\Core\Extension\ThemeSettingsProvider::class)->getSetting($key, 'aculta420_v2')`.
O hook `SurfaceSettingsHooks::preprocessHtml()` aplica as classes no atributo
`attributes` do `<body>`; esse é o nome usado pelo contrato Core
`preprocess_html` e por `templates/layout/html.html.twig`. Se a configuração
ativa ainda não existir (por exemplo, instalação anterior à inclusão do schema),
o hook usa os defaults do tema. Não crie config no Runtime manualmente: salve-a
pelo formulário administrativo e exporte pelo Config Sync.

Para conferir sem alterar configuração, leia o provider com Drush e renderize
uma página; confirme no HTML `data-theme`, `aculta-radius--*` e
`aculta-accent--*`. Verificado no Runtime: HTTP 200, `data-theme="light"`,
cinco classes de superfície no `<body>` e uma implementação ativa de
`preprocess_html`. O provider está disponível, mas `aculta_radius_button` não
tem valor persistido; nesse cenário os defaults documentados são aplicados.
`config:status` está limpo. B2 continua pendente porque não havia configuração
salva para exportar e nenhum `cex` foi executado.

## 2. Opções atuais

| Chave | Tipo | Padrão | Valores | Aplica a |
| --- | --- | --- | --- | --- |
| `aculta_radius_button` | select | `lg` | `base` (8px), `lg` (16px), `full` (pastilha) | botões |
| `aculta_radius_card` | select | `lg` | `base` (8px), `lg` (16px) | cartões e blocos |
| `aculta_radius_field` | select | `base` | `sm` (4px), `base` (8px) | campos de formulário |
| `aculta_accent_override` | select | `mapping` | `mapping` (padrão: pelo tipo de conteúdo), `green`, `yellow`, `red` (forçam uma cor para todos) | cor da faixa de acento |
| `aculta_show_accent_stripe` | boolean | `true` | sim, não | faixa de acento dos cartões de área |

`aculta_accent_override` só fica ativo com a faixa visível. Com `mapping`, o tema
usa o valor `accent` que o Portal envia, por tipo (`docs/superficies.md`, seção 8.3).
Com uma cor fixa, o tema ignora o `accent` do Portal. Essa escolha desfaz a
diferença entre níveis vizinhos e deve ser usada só por pedido do responsável.
Não há opção de "sem faixa" aqui: para isso, use `aculta_show_accent_stripe`.

Os valores seguem `docs/superficies.md`, seção 1.

### 2.1 Cores por purpose (implementado no runtime, F3)

Decisão do responsável em 2026-11-05: o administrador escolhe a cor de destaque
de cada purpose em uma seção nova, **Cores por purpose**, separada de
Superfícies. Estado: **implementado** com a **Paleta ACULTA Skunk Family** (esquemas completos com destaque, texto sobre destaque, subelementos e elevações para modos claro e escuro, detalhados em [paletas-por-purpose.md](paletas-por-purpose.md)). A partir deste commit, `SurfaceSettingsHooks::preprocessHtml` lê a escolha salva, valida a chave em `PurposePaletteSchemes::SCHEMES` e aplica os tokens selecionados nos dois modos.
Escopo: **só destaque** (tokens `--purpose-*` consumidos exclusivamente pela faixa de destaque). Fundo, botões, foco, abas, links e barra institucional **não mudam**. Versão: **MINOR** (`1.3.0`), conforme `docs/versioning.md`. O controle usa a tela nativa do tema (Aparência → ACULTA420 V2 → Configurações) em `theme-settings.php` e salva em `aculta420_v2.settings`; a exportação para `config/sync` segue o passo de `drush cex` descrito na seção 1.

O tema obtém a lista de purposes ativos exclusivamente pelo estado neutro `aculta_portal.active_purposes` da Core State API (`\Drupal::state()->get('aculta_portal.active_purposes', [])`). Não consulta Domain, DomainNegotiator nem serviços do Portal. Se o estado estiver vazio, exibe a mensagem de aviso e não renderiza selects.

| Chave | Tipo | Padrão | Opções | Aplica a |
| --- | --- | --- | --- | --- |
| `aculta_purpose_palette.main` | select | `verde-skunk` | 10 esquemas oficiais | destaque do purpose `main` (Skunk) |
| `aculta_purpose_palette.wiki` | select | `verde-sativa` | 10 esquemas oficiais | destaque do purpose `wiki` (Sativa) |
| `aculta_purpose_palette.links` | select | `verde-ruderalis` | 10 esquemas oficiais | destaque do purpose `links` (Ruderalis) |
| `aculta_purpose_palette.account` | select | `verde-afghan` | 10 esquemas oficiais | destaque do purpose `account` (Afghan) |
| `aculta_purpose_palette.support` | select | `amarelo-acapulco-gold` | 10 esquemas oficiais | destaque do purpose `support` (Acapulco Gold) |
| `aculta_purpose_palette.shop` | select | `amarelo-lemon-haze` | 10 esquemas oficiais | destaque do purpose `shop` (Lemon Haze) |
| `aculta_purpose_palette.eventos` | select | `amarelo-golden-goat` | 10 esquemas oficiais | destaque do purpose `eventos` (Golden Goat) |
| `aculta_purpose_palette.magazine` | select | `vermelho-red-kush` | 10 esquemas oficiais | destaque do purpose `magazine` (Red Kush) |
| `aculta_purpose_palette.courses` | select | `vermelho-panama-red` | 10 esquemas oficiais | destaque do purpose `courses` (Panama Red) |
| `aculta_purpose_palette.forum` | select | `vermelho-red-congolese` | 10 esquemas oficiais | destaque do purpose `forum` (Red Congolese) |

Regras:

- **Só lista aprovada.** O administrador não digita hexadecimal. Cada esquema oficial
  (Skunk, Sativa, Ruderalis, Afghan, Acapulco Gold, Lemon Haze, Golden Goat, Red Kush, Panama Red, Red Congolese)
  já passou no contraste (3:1 para destaque de interface sobre o fundo correspondente, 4,5:1 para texto sobre o destaque).
- **Chave neutra.** O tema aplica `data-purpose` no `<html>` a partir da variável neutra `aculta_purpose` (fornecida pelo Portal em `AppearanceHooks`). Seletores em `tokens.css` ajustam os tokens de destaque `--purpose-*` (`--purpose-accent`, `--purpose-on-accent`, `--purpose-sub-1..3`, `--purpose-surface-1..3`). Não há lógica de domínio no tema.
- Padrão de cada purpose igual ao mapeamento aceito; alterar exige salvar
  pelo formulário e exportar com `drush cex`.

Relação com o `accent` do Portal (seção 3): a cor da faixa **por tipo de
conteúdo** continua vindo do Portal. A cor **por purpose** é escolha do
administrador do tema. As duas coexistem e não se substituem.

### 2.2 Layout de container por purpose (implementado no runtime, F4)

Decisão do responsável em 2026-11-06 conforme `docs/architecture/PLANO-LAYOUT-CONTAINER-POR-PURPOSE.md`: subaba **Layout de container** em Aparência → ACULTA420 V2 → Configurações.

- **Modos suportados:** Boxed (padrão 1200 px com margens), Unboxed (largura total da tela), Contained (1000 px, contido ao centro) e Max full width (1400 px, máxima largura).
- **Largura do container:** faixa numérica de 960 a 1600 px (padrão 1200 px).
- **Largura estreita:** faixa numérica de 600 a 900 px (padrão 750 px), para páginas de leitura (`.layout-page--stack`, `.page__main--stack`).
- **Celular (abaixo de 60 rem):** largura 100% mandatória, ignorando valores numéricos dos sliders e mantendo zero overflow horizontal.
- **Armazenamento e segurança:** salvo sob a chave `aculta_purpose_container` em `aculta420_v2.settings`, sanitizado com lista permitida de modos e faixas numéricas de largura por `PurposeContainerLayout`. Nenhum texto livre é injetado no CSS.
- **Emissão no DOM:** `SurfaceSettingsHooks::preprocessHtml` emite `data-purpose-layout` e as variáveis `--aculta-container-width` e `--aculta-narrow-container-width` no elemento `<html>`.
- **Validação visual:** 40 cenários (10 purposes x 2 modos x 2 larguras 320/1440) verificados em Firefox ESR (:0), 300/300 checks PASS (`tests/browser/capturas/layout-container/2026-11-06/manifest.json`).


## 3. O que não é do tema

Pela regra de fronteiras (AGENTS, seção 2 e 3), estas opções **não ficam no tema**:

- **Modo de cor (claro e escuro):** é preferência do Portal (`aculta_color_mode`,
  tratada em `SurfaceSettingsHooks`). Um padrão do site, se for pedido, vem do Portal.
- **Domínio, purpose e regra de acesso:** pertencem ao Portal.
- **Cor da faixa de acento por área:** o mapeamento purpose → cor (ver
  `docs/superficies.md`, seção 8.3) deve vir do Portal como valor neutro
  (`accent: green|yellow|red`). O tema só traduz esse valor em token.
  Isso ainda não existe no contrato `domain_presentation`.
- **Credenciais e integrações:** nunca em configuração de tema (AGENTS, seção 2).

## 4. Próximos passos

1. Classes neutras `aculta-radius--*` e `aculta-accent--*` são aplicadas no
   `<body>` por `SurfaceSettingsHooks::preprocessHtml`, sem lógica de domínio.
2. Tokens `--radius-control-button`, `--radius-control-card` e
   `--radius-control-field` já estão em `src/styles/tokens.css`.
3. Consumidores devem usar os tokens de raio; revisar as ocorrências legadas
   durante a adoção de componentes.
4. Tokens `--accent-*-stripe` estão em `src/styles/tokens.css` conforme
   `docs/superficies.md`, seção 8.2.
5. Capturas nos dois modos e gates (`docs/gates.md`), no Homelab — os gates
   automatizados passaram; capturas visuais continuam pendentes.
6. Exportar a configuração com `drush cex` para `config/sync` antes do RC. O
   Runtime não tinha diferenças de configuração, então nenhum `cex` foi feito.

## 5. Outras definições que faltam

Além das opções acima, estas definições ainda não existem na estrutura do tema:

- **Tokens de faixa de acento:** implementados em `src/styles/tokens.css`; adoção
  pelos cartões depende do componente e do contrato neutro do Portal.
- **Tokens de bloco de destaque:** implementados nos modos claro e escuro em
  `src/styles/tokens.css`.
- **Contrato `accent`** no `domain_presentation`, a pedir ao Portal.
- **Escala de espaçamento** (`--spacing-*`): definida em `docs/superficies.md`,
  seção 4, e em `src/styles/tokens.css`.
- **Componente de faixa de acento** (propriedade `accent` no SDC do cartão), ainda não criado.
- **Padrão de cartão editorial** (imagem no topo, kicker, título, subtítulo e texto),
  pela referência de 2026-11-05.
- **Página de estilos de referência** (inventário de botões, cartões e blocos nos dois modos),
  útil para captura e revisão.

## 6. Versão

Nenhuma versão foi marcada para este scaffold. A marcação (`info.yml`, `CHANGELOG.md`)
só vem na entrega validada (AGENTS, seção 5).
