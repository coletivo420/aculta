# Inventário inicial UI Suite e legado visual — US-00

**Data:** 2026-11-10 · **Base documental:** `origin/dev` em `16ac6070974f3e01f16ab3661a7cbe025d80ad32` · **HEAD local:** divergente (`4cc0c0dfe2ea9da8e5d136f5826a039e6656f98e`).

**Estado: PARCIAL / DEFERRED para aceite US-00.** Este registro é uma fotografia inicial auditável, não afirma inventário integral de todas as superfícies visuais. Contagens abaixo foram obtidas no worktree local, cujos documentos e Composer diferem de `origin/dev`; conferir `git status` antes de usá-las como baseline remoto. Nenhuma configuração ou dado foi alterado.

> **Adendo de estado atual — 2026-11-11:** as afirmações abaixo sobre Runtime/Sync, ausência de imports e ausência de integrações são históricas da fotografia inicial. A execução autorizada desde então habilitou UI Skins no Runtime, importou seu mapa/scopes e atualizou Views de forma parcial. O registro corrente é `docs/architecture/inventarios/ui-suite-runtime-2026-11-10.json` e o inventário de consumidores é `ui-suite-consumidores-iniciais.csv` (383 linhas). Este adendo prevalece em caso de divergência.

> **Adendo UI Icons — 2026-11-11:** UI Icons 2.1.0 está habilitado em Sync e Runtime. Os menus principal e de conta usam o adaptador oficial, `icon_picker` e apenas os packs Phosphor Regular/Duotone; não têm atribuições de ícone identificadas. O pack Fluent Emoji permanece propriedade do tema/editorial, usado pela toolbar CKEditor, e não foi incluído nos menus. UI Patterns Icons usa o source nativo. Sync configura `icon_picker`, mas Runtime conserva `icon_popover` (padrão upstream). O snapshot de Runtime/Sync mostrou divergência adicional de submódulos habilitados; o import foi adiado para evitar reconciliação não revisada desses módulos. O inventário classifica isso `MIGRATION_IN_PROGRESS`. Não há consumidor de prop de ícone SDC comprovado nesta fase.

| Superfície atual | Owner / consumidores | Contrato e substituto | Status e evidência |
|---|---|---|---|
| Motor visual de paletas por purpose (`aculta_purpose_palette`, `PurposePaletteSchemes`, `purposePaletteStyle()`, `data-purpose-scheme`, `--aculta-purpose-*`) | tema V2; superfícies em domínios | Removido; UI Skins publica CSS variables por `data-skin`/modo, selecionadas pelo Portal via Domain ID | `LEGACY_OBSOLETE`; busca focal sem consumidores. Não manter alias, fallback nem cópia de configuração antiga, conforme instrução do responsável |
| `DomainPurposeManager` e `DomainSurfaceMap` | Portal; roteamento, URLs, ACL, sessão, conteúdo e cache | Mantidos por funções de domínio; UI Skins não assume essas responsabilidades | `FUNCTIONAL_NATIVE_EXCEPTION`; migração de nomenclatura para `site_key` segue RN-00–05 |
| UI Skins | Portal/tema; dez Domain IDs | 1.2.1 ativo; mapa `links_aculta_org: ruderalis`, demais usam Skunk default; cinco grupos de variables/scopes; A/B/A anônimo sem carryover entre hosts | `MIGRATION_IN_PROGRESS` até contraste e aceite visual nos dez domínios; gate 134 PASS |
| `aculta_albuns:page_1` | `aculta_gallery`; Revista | UI Patterns row `revista-album-card`, style `layout-grid`, display `views-display` | `UI_SUITE_NATIVE`; três resultados, anônimo/HTTP 200 e Firefox 1280/320 claro/escuro. Cache-hit, teclado, zoom e multi-page pendentes |
| `revista_sessoes` páginas editoriais (6 displays) | `aculta_magazine`; Revista | View mantém row nativo `entity:node`/`teaser`; template ACULTA encaminha render arrays de campos para slots do SDC `revista-teaser` | `UI_SUITE_NATIVE` confirmado por Sync + template; `/revista` redireciona para `/`; smoke da home confirma quatro cards SDC, mas sem atribuição ao View. Acesso, pager/cache e aceite do View pendentes |
| `aculta_forum_categories:page_categories` | `aculta_forum`; Fórum | UI Patterns row `forum-category`, style nativo mantém agrupamento, display `views-display` | `UI_SUITE_NATIVE`; 12 resultados, anônimo/HTTP 200; Firefox cinco larguras claras, duas escuras, teclado e alvos ≥44 px |
| `aculta_forum_categories:block_landing` | `aculta_forum`; bloco de entrada | UI Patterns row `forum-category`; style/display nativos | `MIGRATION_IN_PROGRESS`; placement pai está oculto na home, sem aceite de saída visual |
| Displays `default` sem output próprio | Views/Drupal | Base funcional herdada por displays explícitos | `FUNCTIONAL_NATIVE_EXCEPTION`; não depreciar consultas, acesso, filtros ou ordenação nativos |

UI Patterns Field Group 2.0.x-dev permanece `BLOCKED_UPSTREAM` para novos consumidores: SHA travado sem cobertura Drupal Security Advisory Policy, metadata sem Drupal 12 e compatibilidade com Field Group 4.x ainda não demonstrada. UI Patterns CKEditor 5 segue `LEGACY_OBSOLETE`, sem consumidores no repositório e proibido pela ADR-055; remoção do módulo preexistente do Runtime aguarda operação autorizada.

## Evidências de baseline

| Item | Evidência coletada | Estado / limite |
|---|---|---|
| Repositório | `origin/dev` avançou e divergiu do HEAD local; documentos de planejamento pedidos estão removidos no worktree | Consultados pelo `git show origin/dev:<path>`; não restaurados sobre remoções locais |
| Runtime | Drupal 11.5.0-beta1; conexão Drush disponível | `pm:list`, `cget`, `updatedb:status`, `config:status` somente leitura |
| Drift Runtime/Sync | `config:status` lista diferenças de menus, Views, tema, workflows e outras configurações; warnings de registros `system.schema` de módulos ausentes | **DEFERRED** para sincronização e homologação. Nenhum `cim`, `updatedb`, limpeza de cache ou alteração Runtime executada |
| Composer/lock local | UI Icons 2.1.0, UI Skins 1.2.1, UI Patterns `2.0.x-dev` SHA `a7e9c28c522a5b0c8b71c731cab5482d60164e1e`, UI Patterns Layouts 2.0.23, UI Patterns Field Group 2.0.0-beta2, UI Styles 1.22.0 | Lock local diverge de `origin/dev`; `composer.json` local adiciona Icons/Skins, mas Field Group segue beta2, não a preferência vinculante dev |
| Diretórios contrib locais | `ui_icons`, `ui_skins`, `ui_patterns`, `ui_patterns_field_group`, `ui_styles` presentes | UI Patterns Layouts é dependência do pacote Patterns nesta instalação, sem diretório separado |
| Módulos habilitados no Runtime | Drush lista 27 extensões entre base e submódulos relacionados a Icons, Patterns, Styles e Field Group; inclui `ui_patterns_ckeditor5` | Discrepância operacional com Sync e escopo aprovado. Nenhum módulo foi desabilitado; não ampliar uso de `ui_patterns_ckeditor5` |
| Config Sync no worktree | Entre extensões UI Suite: `field_group`, `ui_patterns`, `ui_patterns_field_group`, `ui_styles`; não constam Icons, Skins, UI Styles Page/CKEditor 5/Entity Status nem a maioria das integrações | Config Sync não prova estado Runtime |
| Tema | 80 diretórios SDC, 80 manifests `.component.yml`, 123 stories; 66 Views em `config/sync`; 216 arquivos de view display/form display/field group/layout/block candidatos no Sync | Contagem bruta, não cobertura funcional ou inventário por consumidor |
| Busca inicial | 44 arquivos correspondem à cadeia antiga de purpose no tema/Portal/Sync; 251 arquivos customizados contêm indicadores de renderização visual | Indicador bruto da busca documentada no plano. Cada ocorrência precisa triagem de uso e consumidor |
| Extração por consumidor | `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`: 159 displays de 66 Views + 22 entity displays com Layout Builder + 80 SDCs com caminhos de referências literais detectadas; plugin ID scan não detectou Views com UI Patterns row/style/display | Candidatos; owners das Views ainda não resolvidos por extensão/implementação. CSV não prova render Runtime, acesso/cache ou adequação da substituição |
| Contratos funcionais RN-00 | `docs/architecture/inventarios/site-key-purpose-contracts.csv`, gerado por `php scripts/inventory-site-key-contracts.php`: 3.059 linhas com path/linha/owner e categoria | Indicador textual; 1.610 ocorrências genéricas de `purpose` precisam triagem semântica. Não equivale a migração RN-00 concluída |

### Matriz de integrações observadas

| Integração | Composer/FS | Config Sync local | Runtime (Drush) | Classificação atual |
|---|---|---|---|---|
| UI Patterns base | presente | habilitado | habilitado | `UI_SUITE_NATIVE` |
| UI Patterns Blocks / Field / Field Formatters / Layouts / Views / Library | módulos instalados | conferir configurações consumidoras individualmente | habilitados | `UI_SUITE_NATIVE` nos consumidores já ligados; restante **não classificado** |
| UI Patterns Field Group | beta2 no lock local | habilitado | habilitado | `BLOCKED_UPSTREAM` para nova adoção até avaliação de segurança, Field Group 4.x e Drupal 12; uso Runtime existente precisa inventário |
| UI Patterns CKEditor 5 | módulo aparece no filesystem | ausente | habilitado segundo Drush | `BLOCKED_UPSTREAM`/fora do escopo aprovado; discrepância precisa reconciliação operacional. Não criar integrações novas nem alterar Runtime sem procedimento autorizado |
| UI Styles base e submódulos | 1.22.0 presente | base e Block/Layout Builder/Library/UI Patterns/Views habilitados | habilitados | `UI_SUITE_NATIVE` onde configurado |
| UI Styles Page / Entity Status / CKEditor 5 | pacotes/submódulos disponíveis a confirmar por módulo | ausentes | Page/Entity Status/CKEditor 5 não confirmados habilitados | `MIGRATION_IN_PROGRESS` (planejamento); sem consumidor validado |
| UI Icons e Menu/Picker/Popover/Patterns | 2.1.0 e módulos presentes no filesystem local | ausentes | base e submódulos correspondentes listados como habilitados | `UI_SUITE_NATIVE` apenas nos consumidores verificados; configuração efetiva e mapeamento de ícones pendentes |
| UI Skins | 1.2.1 presente no filesystem local | ausente | disponível no filesystem, mas não listado como habilitado | `MIGRATION_IN_PROGRESS`; sem ponte Domain ID → skin |

### Restrição da API UI Skins instalada

Na versão local 1.2.1, `UiSkinsInterface::CSS_VARIABLES_THEME_SETTING_KEY` aponta para `third_party_settings.ui_skins.css_variables`. O hook `Drupal\ui_skins\Hook\PageTop` lê esses valores com `ThemeSettingsProvider` e os converte em CSS inline. O plugin manager descobre definições YAML do módulo/tema, mas o caminho padrão de render lê os valores salvos nas configurações do tema. Portanto, a integração padrão não resolve Domain ID → skin por request. A futura ponte deve consumir definições permitidas e anexar valores no escopo da resposta com cache por domínio/usuário, sem alterar theme settings durante a requisição. `ui_skins` não está ativo no Runtime observado, então esta leitura de API é do código instalado localmente, não homologação de comportamento Runtime.

## Registro inicial de superfícies rastreadas

O plano vinculante exige uma linha para cada superfície ativa com owner, rota/consumidores, dados, substituto, acesso/cache, teste antes/depois, commit e rollback. A tabela abaixo cobre somente os elementos identificados na primeira busca da cadeia de paletas; não representa cobertura de Views, formulários, blocos e páginas site-wide.

| ID / caminho | Owner e consumidores conhecidos | Origem / risco | Alvo UI Suite | Status e retirada |
|---|---|---|---|---|
| `theme-settings.php` — controles `aculta_cores_purpose` / `aculta_purpose_palette` | tema V2; administradores que configuram identidade por domínio | Config de tema global; risco de configuração compartilhada entre domínios | UI Skins + mapeamento de skin no Portal | `LEGACY_DEPRECATED`; substituir após piloto Links, paridade e rollback; remover quando busca de consumidores der zero |
| `src/Palette/PurposePaletteSchemes.php` | tema V2; resolução de cores por purpose | Dados de paleta estáticos consumidos por hook/testes/CSS | definições UI Skins de temas e CSS variables | `LEGACY_DEPRECATED`; retirar após zero chamadas e equivalência light/dark/contraste |
| `src/Hook/SurfaceSettingsHooks.php::purposePaletteStyle()` | tema V2; preprocess HTML | Emite `data-purpose-scheme`, `--aculta-purpose-*` e variáveis Studio inline | UI Skins com atributos/variables escopados por resposta | `LEGACY_DEPRECATED`; remover após piloto, cache A/B e todos os consumidores migrados |
| `src/styles/tokens.css` seletores `data-purpose-scheme` / `--aculta-purpose-*` | tema V2; estilos dependentes de esquema por domínio | Cascata global condicionada por atributo; risco de regressão ampla | Tokens semânticos alimentados por UI Skins | `LEGACY_DEPRECATED`; remover seletor/aliases quando `rg` comprovar zero consumidores |
| `aculta420_v2.ui_styles.yml::purpose_palette` | tema V2; UI Styles em displays/config | Opções de esquema baseadas no motor antigo | UI Skins (identidade) + UI Styles (variações estruturais finitas) | `LEGACY_DEPRECATED`; retirar depois de migrar configurações consumidoras |
| `AppearanceHooks` + `ColorModePreference` | Portal; páginas públicas e autenticadas em todos os domínios | preferência do usuário > default do site; cache `user`, `domain`, config e usuário | manter preferência no Portal; delegar a apresentação light/dark ao UI Skins | `MIGRATION_IN_PROGRESS`; não remover preferência nem domínio; exige teste de cache e modos |
| `DomainPurposeManager` / `DomainSurfaceMap` | Portal; rotas, URLs, conta, sessão, ACL, conteúdo e cache | Contrato funcional transversal, não apenas visual | preservar; novo serviço separado de resolução Domain ID → skin | `FUNCTIONAL_NATIVE_EXCEPTION`; não depreciar nem substituir pelo UI Skins |
| Views e displays do site | 66 Views e 159 displays listados em `inventarios/ui-suite-consumidores-iniciais.csv`; scan do Sync encontrou zero plugin IDs UI Patterns row/style/display; owners ainda não resolvidos | entidades e consultas Drupal; filtros, pager, ordenação e acesso precisam inspeção por display; Runtime não confrontado por causa do drift | UI Patterns Views row/style/display, Layouts e SDCs | `MIGRATION_IN_PROGRESS` como fila de inspeção; cada display segue sem classificação final até validar owner, uso, saída, acesso e cache |
| Formulários, blocos, páginas administrativas e templates Portal | módulos customizados e tema; 251 candidatos brutos de render | Form API/Render API, acesso, cache e HTMX são riscos de preservação | adaptadores SDC/UI Styles/Patterns mantendo plugin/API original | **não classificado por consumidor**; exige varredura arquivo/rota e testes direcionados |

## Próximas evidências necessárias

1. Reconciliar o HEAD local divergente e as remoções locais com o responsável antes de editar arquivos já alterados por terceiros.
2. Rodar inventário completo por módulo/rota/configuração, comparar Runtime e Sync em estado sem drift e registrar cada consumidor com os campos do plano vinculante.
3. Resolver Field Group `2.0.x-dev@dev` com SHA travado e avaliação documentada; até lá, não ampliar sua ativação.
4. Registrar autorização operacional para qualquer sincronização, atualização de dependência com efeito no Runtime, cache rebuild ou migração de configuração. O estado atual não é seguro para escrita.
5. Capturar baseline e homologar Links (claro/escuro, CSS computed, contraste, cache e rollback) antes de implantar a ponte UI Skins.

## Resultado US-00

**Parcial; critério bloqueante ainda DEFERRED.** Pacotes, versões locais, extensões Sync/Runtime, 159 displays de Views, 22 entity displays com Layout Builder, 80 definições SDC e referências literais iniciais foram confrontados. Regenerar com `php scripts/inventory-ui-suite-consumers.php`. Owners e saídas das Views, validade semântica de cada referência SDC, inventário integral por cada uma das dez frentes, interfaces administrativas e todos os modelos visuais ainda não estão concluídos. Não declarar ADR-054/055 concluídas com este artefato.

O inventário funcional separado RN-00 pode ser regenerado com `php scripts/inventory-site-key-contracts.php`. O CSV agrega ocorrências por linha e categoria; menções genéricas requerem revisão semântica e os nomes propostos devem seguir o plano `ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.
