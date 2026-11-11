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
| `aculta_shell_variant` | select | `classic` | `classic` (shell atual), `v2` (cinco áreas finais, em homologação) | estrutura da página |

`aculta_accent_override` só fica ativo com a faixa visível. Com `mapping`, o tema
usa o valor `accent` que o Portal envia, por tipo (`docs/superficies.md`, seção 8.3).
Com uma cor fixa, o tema ignora o `accent` do Portal. Essa escolha desfaz a
diferença entre níveis vizinhos e deve ser usada só por pedido do responsável.
Não há opção de "sem faixa" aqui: para isso, use `aculta_show_accent_stripe`.

Os valores seguem `docs/superficies.md`, seção 1.

### 2.0 Shell da página (ADR-054)

A seção **Shell da página** oferece `classic` e `v2`. O padrão é `classic`, que mantém `templates/layout/page.html.twig` e as nove regiões atuais. `v2` ativa `templates/layout/page--shell-v2.html.twig`, que compõe as cinco áreas finais (institution, header, content, sidebar_first, footer) e usa as regiões antigas `navigation`, `highlighted`, `help` e `breadcrumb` como ponte até o corte homologado. A troca é reversível pela mesma opção, sem migração de dados nem alteração do `info.yml` nesta fase.

### 2.1 Skin visual por domínio (UI Skins)

A seleção de aparência não pertence às configurações próprias do tema. `ui_skins` publica as variáveis `--color-accent`, `--color-on-accent` e `--color-accent-sub-1..3` pelos scopes de `aculta420_v2.ui_skins.css_variables.yml`; os valores exportados ficam em `third_party_settings.ui_skins.css_variables` no Config Sync. O Portal resolve `domain_id -> skin_id` e entrega `aculta_skin_id`; o tema aplica `data-skin` e `data-theme` sem conhecer host nem resolver acesso.

O campo `aculta_purpose_palette`, seu formulário e schemas foram removidos, sem leitura/alias de configuração anterior. UI Skins 1.2.1 está ativo em Sync e Runtime; o mapa de skin é resolvido por Domain ID sem mutação de configuração global. Links foi homologado em cinco larguras e dois modos; os demais domínios e cache-hit permanecem pendentes.

### 2.2 Layout de container por purpose - administração descontinuada

Por decisão ADR-054 (2026-11-09), a seção **Layout de container** foi retirada de `theme-settings.php`. Portanto, o administrador não dispõe mais de selects para modo, estilo e largura por purpose.

**Descontinuado no corte P9 (2026-11-09):** a leitura de `aculta_purpose_container`, o schema e a classe `PurposeContainerLayout` foram removidos. Larguras e estilos vêm dos layouts SDC, do Layout Builder e do shell v2, que é o padrão.

A implementação e os testes de 2026-11-06 pertencem ao histórico, em `docs/architecture/PLANO-LAYOUT-CONTAINER-POR-PURPOSE.md`. O plano vigente é `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md` na raiz do repositório.


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
