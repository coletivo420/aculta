# Inventário inicial UI Suite e legado visual — US-00

**Data:** 2026-11-10 · **Base documental:** `origin/dev` em `16ac6070974f3e01f16ab3661a7cbe025d80ad32` · **HEAD local:** divergente (`4cc0c0dfe2ea9da8e5d136f5826a039e6656f98e`).

**Estado: PARCIAL / DEFERRED para aceite US-00.** Este registro é uma fotografia inicial auditável, não afirma inventário integral de todas as superfícies visuais. Contagens abaixo foram obtidas no worktree local, cujos documentos e Composer diferem de `origin/dev`; conferir `git status` antes de usá-las como baseline remoto. Nenhuma configuração ou dado foi alterado.

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
