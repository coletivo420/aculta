# ACULTA Document Viewer — inventário e migração (0.1.0-dev.1)

> Fase 0.1.0-dev.1: auditoria e baseline. Nenhum código, configuração ou dado foi alterado.
> Este documento é o mapa origem/destino da migração de `aculta_pdf_viewer` para `aculta_document_viewer`.

## Decisões do responsável (2026-10-21)

| Tema | Decisão |
| --- | --- |
| Numeração do novo módulo | Recomeça em `0.1.0-dev.1`. O histórico do módulo antigo (`0.1.0-dev.39`) fica preservado no CHANGELOG de origem e é referenciado no novo. |
| Plugin ID do Group (`aculta_pdf_viewer_group:course_document`) | Migra junto na fase `0.1.0-dev.3`, somente após a estratégia de `0.1.0-dev.2` ser comprovada. |
| Tema ACULTA420 | Alterações autorizadas desde o início da frente (ver `docs/portal/MAPA-MODULO-TEMA.md`). |

## Estado inicial do repositório

- Host: `toca` (Homelab). Runtime confirmado: `aculta_runtime` (Drush `status`), URI `http://default`, esperado neste ambiente.
- Branch: `docs/aculta-forum-0.1.0-dev.1`. HEAD antes desta fase: `b995e17c`.
- Worktree com alterações de outras frentes (Fórum, Gallery, configuração de Forum/Gallery e `aculta_pdf_viewer` em `src/Pdf/DocumentFieldMap.php`, `src/Pdf/PdfViewerBuilder.php` e dois testes Unit). Esses arquivos **não fazem parte** desta frente e não são commitados aqui.

## Distinção de camadas

1. **Código:** módulo `aculta_pdf_viewer` (`0.1.0-dev.39`) e submódulo `aculta_pdf_viewer_group` (`0.1.0-dev.4`), ambos em `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/`.
2. **Configuração exportada (`config/sync/`):** 14 arquivos referenciam o módulo ou o submódulo (ver mapa abaixo).
3. **Configuração ativa (banco do Runtime):** `core.extension` mantém `aculta_pdf_viewer: 0` e `aculta_pdf_viewer_group: 0`; `aculta_pdf_viewer.settings` existe; `key_value` (`system.schema`) registra `aculta_pdf_viewer` e `aculta_pdf_viewer_group`.
4. **Dados de conteúdo:** 2 entidades Media do bundle `document` (IDs 234 e 237, nomeadas como fixtures temporárias de homologação), 0 entidades `course_document`, 1 relação de Group do tipo `group_membership` e nenhuma relação `aculta_pdf_viewer_group:course_document`. Nenhum dado foi removido.
5. **Integrações ainda em desenvolvimento:** PV7 (CSP em Report-Only, acessibilidade ampliada) segue em `Dev`, conforme `docs/operations/status/aculta_pdf_viewer.yml`.

## Mapa de consumidores (origem → destino)

| Origem | Destino previsto | Tipo | Fase |
| --- | --- | --- | --- |
| `aculta_pdf_viewer` (módulo) | `aculta_document_viewer` | machine name, namespace `Drupal\aculta_pdf_viewer`, diretório | 0.1.0-dev.3 |
| `aculta_pdf_viewer_group` (submódulo) | `aculta_document_viewer_group` | machine name, namespace, plugin Group | 0.1.0-dev.3 |
| `aculta_pdf_viewer.settings` | `aculta_document_viewer.settings` | config ativa e exportada | 0.1.0-dev.2 (estratégia) / 0.1.0-dev.3 |
| `aculta_pdf_viewer.schema.yml` | `aculta_document_viewer.schema.yml` | schema | 0.1.0-dev.3 |
| `aculta_pdf_viewer.routing.yml` (`/painel-administrativo/aculta/leitor-pdf`) | `/painel-administrativo/aculta/leitor-documentos` | rota admin, com alias temporário | 0.1.0-dev.3 |
| `aculta_pdf_viewer.links.menu.yml` / `links.task.yml` | links do novo módulo | menu administrativo | 0.1.0-dev.3 |
| Formatter `aculta_pdf_media` (`AcultaPdfMediaFormatter`) | mesmo ID, provider novo | plugin Field Formatter | 0.1.0-dev.3 (ID preservado) |
| Formatter `aculta_pdf_file` (`AcultaPdfFileFormatter`) | mesmo ID, provider novo | plugin Field Formatter | 0.1.0-dev.3 (ID preservado) |
| Library `aculta_pdf_viewer/viewer` (`js/pdf-viewer.mjs`) | `aculta_document_viewer/pdf` | library Drupal | 0.1.0-dev.3 |
| Plugin Group `aculta_pdf_viewer_group:course_document` | `aculta_document_viewer_group:course_document` | plugin Group (relação) | 0.1.0-dev.3, após dev.2 |
| Config `group.role.lms_course-member/teacher` | permissões `… aculta_document_viewer_group:course_document …` | permissões de papel | 0.1.0-dev.3 |
| Config `group.relationship_type.lms_course-af8ec2205541224f9d8c4` | `content_plugin` novo | relação Group | 0.1.0-dev.3 |
| Config `media.type.course_document` e campos `field_media_course_document` | dependência de provider atualizada | Media privada de turma | 0.1.0-dev.3 |
| Config `core.entity_view_display.media.document.default` / `.node.document.default` / `.node.document.full` | formatter e dependência | displays públicos | 0.1.0-dev.3 |
| Config `core.entity_view_display.media.course_document.default` | formatter e dependência | display privado | 0.1.0-dev.3 |
| `core.extension` | `aculta_document_viewer: 0` e `aculta_document_viewer_group: 0` | registro de módulo | 0.1.0-dev.2/dev.3 |

### Consumidores de código (dependências `info.yml`)

| Módulo | Dependência atual | Ação |
| --- | --- | --- |
| `aculta_forum` | `aculta_portal:aculta_pdf_viewer` | atualizar para `aculta_document_viewer` (fase dev.3). Arquivo está modificado por outra frente: editar só a linha necessária, em commit separado. |
| `aculta_lms` | `aculta_portal:aculta_pdf_viewer_group` | atualizar (dev.3). |
| `aculta_lms_h5p` | `aculta_pdf_viewer:aculta_pdf_viewer` e `aculta_pdf_viewer_group:aculta_pdf_viewer_group` | atualizar (dev.3). |

### Referências não-código

- `docs/architecture/ROUTES-SITEMAP.md` (rota admin): outra frente tem alteração não commitada nesse arquivo. Atualizar em commit próprio, sem incluir a alteração alheia.
- `docs/portal/PDF-VIEWER-INTEGRATION.md`, `docs/portal/ROADMAP.md`, `docs/portal/IMPLEMENTATIONS.md`, `docs/portal/MAPA-MODULO-TEMA.md`, `docs/modules/LMS-GROUP.md`, `docs/modules/LMS-COMPATIBILITY.md`, `docs/compliance/LGPD-INVENTARIO.md`, `docs/operations/BANCO-DE-TESTE.md`, `docs/portal/SECURITY-ACCESS-FILES-MATRIX.md`.
- Tema: `web/themes/custom/aculta420/css/components/pdf-viewer.css`, `docs/design-system.md` e `CHANGELOG.md` (ACULTA420).
- Status: `docs/operations/status/aculta_pdf_viewer.yml` e `aculta_pdf_viewer_group.yml` (novo componente exige novo arquivo de status).

## Configuração exportada que referencia o módulo

`config/sync/` (14 arquivos citam `aculta_pdf_viewer` ou `aculta_pdf_viewer_group`):

- `core.extension.yml` — registro de módulo.
- `core.entity_view_display.media.document.default.yml`, `core.entity_view_display.node.document.default.yml`, `core.entity_view_display.node.document.full.yml` — displays que usam `aculta_pdf_media` / `aculta_pdf_file`.
- `core.entity_view_display.media.course_document.default.yml` e `core.entity_form_display.media.course_document.default.yml` — displays da Media privada de turma.
- `media.type.course_document.yml`, `field.field.media.course_document.field_media_course_document.yml`, `field.storage.media.field_media_course_document.yml` — dependência de provider.
- `group.role.lms_course-member.yml`, `group.role.lms_course-teacher.yml` — permissões com `aculta_pdf_viewer_group:course_document`.
- `group.relationship_type.lms_course-af8ec2205541224f9d8c4.yml` — `content_plugin: 'aculta_pdf_viewer_group:course_document'`.

Os displays `*.node.document.*` e `media.document` são públicos. O bundle `course_document` é privado de turma. Ambos devem manter a separação já descrita no roadmap PV5.

## Estado do banco do Runtime (somente leitura)

| Item | Valor observado |
| --- | --- |
| `core.extension` | `aculta_pdf_viewer: 0`, `aculta_pdf_viewer_group: 0`, `csp: 0`, `pdfpreview: 0`, `reporting: 0` |
| Config ativa `aculta_pdf_viewer.settings` | presente |
| Config ativa com `aculta_pdf_viewer` | 12 registros (displays, Group, Media, `core.extension`) |
| Config ativa com `aculta_pdf_viewer_group` | incluída nos 12 acima |
| Config ativa com `aculta_pdf_media` / `aculta_pdf_file` | 4 displays (`media.course_document`, `media.document`, `node.document.default`, `node.document.full`) |
| Entidades Media `document` | 2 (IDs 234 e 237, nomes `TEMP HOMOLOGACAO PDF VIEWER 20261017` e `TEMP PV6 PDF aculta-pv6-pv7-20261017`) |
| Entidades Media `course_document` | 0 |
| Relações Group | 1 (`group_membership`); nenhuma `course_document` |
| Tabelas `group*` | presentes (`group_relationship`, `groups`, etc.) |

**Atenção:** os dois Media de homologação devem ser preservados. Não apagar nem alterar sem autorização explícita.

**Divergência de configuração (`drush config:status`):** há diferenças em `core.entity_view_mode.node.pdf`, `group.*` (`lms_course` displays e campos de Group) e `system.action.entity_print_pdf_download_action`. Não pertencem diretamente ao viewer e não são tratadas aqui. Devem ser registradas como dívida antes de `config:import`.

## Baseline de testes (Dev, sem alterações)

Árvore de trabalho com alterações alheias já presentes; os testes refletem esse estado.

| Suite | Resultado | Observação |
| --- | --- | --- |
| PHPUnit Unit (`aculta_pdf_viewer/tests/src/Unit`) | **28 testes, 217 asserções, OK** | Aviso de diretório não gravável para HTML output (`sites/simpletest/browser_output`), sem falha. |
| PHPUnit Kernel (`aculta_pdf_viewer/tests/src/Kernel`) com `aculta_simpletest` | **5 testes, 5 erros, 12 deprecações** | Ver causas abaixo. |

Causas dos erros Kernel, pré-existentes e não relacionadas à migração:

1. `AcultaPdfMediaEmbedTest` (4 testes): `aculta_portal.aculta_user_agreement_registration` depende do serviço `aculta_user.aculta_user_agreement_ledger`, que não existe no container de teste. Indica dependência entre módulos não habilitada nesse teste ou serviço ausente no estado atual da árvore.
2. `AcultaPdfViewerInstallTest` (1 teste): `aculta_lms` tem configuração com dependência não satisfeita (`views.view.courses_admin` → `system.menu.admin`) durante a instalação de teste.

Esses erros são dívida já existente. Não serão corrigidos nesta fase para produzir PASS, conforme a regra do plano.

## Procedimento de rollback (estrutural)

Esta fase não altera o Runtime. Para as fases seguintes, o rollback é:

1. Backup do banco do Runtime antes de qualquer `drush updb` ou `config:import` (`drush sql:dump` fora do repositório, em local sem Git).
2. Backup de `config/sync/` e do diretório do módulo (commit anterior à fase).
3. Reversão por restauração coordenada: código no commit anterior, banco restaurado no mesmo ponto, `drush cr`.
4. Validar `core.extension` e a presença de `aculta_pdf_viewer` antes de reabilitar qualquer tráfego.

A estratégia específica de migração de identidade (`dev.2`) detalha o procedimento e precisa ser testada em cópia do banco antes de aplicar no Runtime.

## Riscos identificados

1. **Plugin ID do Group** aparece em permissões de papel e em `group.relationship_type`. Alterá-lo sem migração das relações pode quebrar acesso de turmas (fase dev.3).
2. **`core.extension` com o módulo antigo instalado:** renomear a pasta sem compatibilidade provoca módulo ausente. A camada de compatibilidade precisa existir até a conclusão da migração (fase dev.2).
3. **Dependências de outros módulos** (`aculta_forum`, `aculta_lms`, `aculta_lms_h5p`) referenciam os nomes antigos. Precisam ser atualizadas na mesma fase que a renomeação, sem sobrescrever alterações alheias.
4. **Configuração divergente** no Runtime (ver seção anterior) pode impedir `config:import` limpo.
5. **Testes Kernel já falham** (ver baseline). Qualquer validação de migração depende de corrigir ou isolar essas causas antes.
6. **Worktree compartilhado**: alterações de outras frentes em `aculta_pdf_viewer/src/Pdf/` e em arquivos de `docs/architecture/ROUTES-SITEMAP.md` e `config/sync/` precisam ficar fora dos commits desta frente.
7. **Dados de homologação** (Media 234 e 237): devem ser preservados nas migrações e usados como caso de teste.

## Próximas etapas

- **0.1.0-dev.2 — Estratégia segura de migração:** definir e testar em cópia do banco a estratégia para registro de extensões, schemas, configuração ativa e exportada, providers, serviços, plugins, relações Group e histórico de atualizações.
- Antes disso, resolver ou isolar as causas dos erros Kernel da baseline, se bloquearem a validação da estratégia.
