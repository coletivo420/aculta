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

## 0.1.0-dev.2 — Estratégia de migração (evidência)

### Ambiente de teste

- Backup do Runtime: `sql:dump` em `scratchpad` da sessão (fora do repositório, 53 MB, não versionado).
- Cópia do banco do Runtime **não foi criada**: o usuário `aculta_runtime` não tem `CREATE DATABASE` (grants: `aculta_runtime.*` e `aculta_id_cpf_test.*`). Os testes de migração rodam no banco `aculta_simpletest`, previsto em `docs/operations/BANCO-DE-TESTE.md`, com fixtures próprias. Não houve operação no Runtime.

### Mecanismo do Core (confirmado no código instalado, Drupal 11.4.10)

- `EntityDisplayBase::onDependencyRemoval()` (`web/core/lib/Drupal/Core/Entity/EntityDisplayBase.php:456`) remove componentes cujo renderer tem provider na lista de módulos removidos.
- `ConfigManager::uninstall()` (`web/core/lib/Drupal/Core/Config/ConfigManager.php:203`) reprocessa todas as configurações dependentes do módulo.

### Spike (`tests/src/Kernel/DocumentMigrationSpikeTest.php`, fixtures em `tests/modules/`)

Resultado da execução real, 3 testes, 20 asserções, OK:

| Cenário | Resultado observado |
| --- | --- |
| Novo módulo expõe **o mesmo formatter ID** do antigo | O provider **continua sendo o antigo**. Os dois não podem coexistir com o mesmo ID. |
| Desinstalar o antigo **sem migrar** o display | O componente **não some**: volta ao formatter padrão do tipo (`string`). Num display público de PDF, a falha seria **silenciosa**. |
| Trocar o tipo do componente para um ID **novo** antes de desinstalar o antigo | O componente permanece com o ID novo e a dependência deixa de citar o módulo antigo. |

### Consequência para a decisão de IDs

O plano pede que `aculta_pdf_media` e `aculta_pdf_file` sejam preservados. Com base no spike:

- **Preservar os IDs não é seguro** na desinstalação: o módulo antigo precisa sair para o novo assumir o ID, e nesse intervalo o display cai no formatter padrão.
- **Caminho proposto (B):** o novo módulo registra IDs próprios; uma `hook_update_N` troca o tipo dos componentes em `core.entity_view_display.*` (`media.document`, `media.course_document`, `node.document.default` e `node.document.full`) e recalcula as dependências; só depois o módulo antigo é desinstalado. O teste acima comprova o efeito no Core.
- **Alternativa (C):** manter o módulo antigo instalado apenas como provedor dos IDs antigos (estado B prolongado). Preserva os IDs, mas mantém código legado e uma dívida explícita.

**Decisão necessária do responsável antes da 0.1.0-dev.3:** aprovar B (recomendado) ou C. Isso altera o requisito do plano de preservar os IDs de formatter.

### Ordem obrigatória (com B)

1. Backup: `sql:dump` do Runtime, cópia de `config/sync/` e commit de referência.
2. Instalar o novo módulo (código e IDs próprios), sem remover o antigo.
3. `hook_update_N` do novo módulo: trocar `type` dos componentes, reescrever `dependencies.module`, migrar relações Group (`group.relationship_type` e permissões de papel) e atualizar `system.schema`.
4. Verificações anteriores e posteriores: `core.extension`, componentes por display, dependências, relações Group e contagem de Media. Mesmo critério de homologação do plano.
5. Desinstalar o módulo antigo apenas depois de a verificação posterior passar.
6. Rollback: restaurar o código no commit de referência, restaurar o banco do backup no mesmo ponto e `drush cr`.

### Pendências que continuam bloqueadas

- A troca do plugin ID de Group (`aculta_pdf_viewer_group:course_document`) segue a mesma lógica de provider. Não foi testada ainda; deve entrar no spike antes da dev.3.
- O spike cobre o formatter. Não cobre `group.relationship_type`, permissões de papel nem `media.type.course_document`.
- Os 5 erros Kernel da baseline (ver acima) continuam pré-existentes e não afetam o spike, que roda com módulos mínimos.

### Decisão adotada

Adotada a opção **B** (IDs próprios, troca de componentes antes da desinstalação), por ser a única das duas que a evidência do spike sustenta. A decisão deve ser confirmada pelo responsável antes do Runtime ser alterado.

## Roadmap revisado (plano consolidado)

Incorpora planilhas ao escopo, com o nome definitivo `aculta_document_viewer`.

| Versão | Fase | Entrega | Estado |
| --- | --- | --- | --- |
| 0.1.0-dev.1 | Auditoria | Inventário e contratos | Concluída (`25ed3109`) |
| 0.1.0-dev.2 | Migração | Estratégia segura e spike | Concluída como evidência (`c23bc37d`, `7f031487`) |
| 0.1.0-dev.3 | Identidade | Módulo instalado e compatível com PDF | Executada em estado B: código (`4b170d06`), configuração (`740a0c32`) e Runtime; módulo antigo permanece |
| 0.1.0-dev.4 | DOCX | docx-preview local | Vendoring concluído (`2b7f6a90`); renderização em navegador pendente |
| 0.1.0-dev.5 | Integração DOCX | Media/File e visualização autorizada | Validação estrutural concluída (`3649a742`); renderização aguarda spike de isolamento |
| 0.1.0-dev.6 | Tabulator | Tabulator + SheetJS locais | Vendoring concluído (`42042558`, `71545352`); leitor não iniciado |
| 0.1.0-dev.7 | Planilhas | XLSX, XLS, ODS, CSV e múltiplas abas | Não iniciada |
| 0.1.0-dev.8 | Leitor unificado | Toolbar e controles ACULTA420 | Não iniciada |
| 0.1.0-dev.9 | Integração transversal | Wiki, Fórum, LMS, Revista | Não iniciada |
| 0.1.0-dev.10 | Segurança | Arquivos maliciosos, privados, limites e isolamento | Não iniciada |
| 0.1.0-rc.1 | Homologação | Migração, regressão, acessibilidade | Não iniciada |
| 0.1.0 | Estável | Implantação após aprovação | — |

O PDF.js, suas configurações, o visualizador completo, as miniaturas PDFPreview e os contratos Group existentes são preservados.

### Execução da 0.1.0-dev.3 (estado B)

Resolvido o bloqueio anterior: o módulo novo foi criado a partir do conteúdo commitado (`HEAD`), sem mover o diretório do antigo. Os quatro arquivos da outra frente não foram tocados.

- Runtime: backups antes (`sql:dump` em scratchpad, dois arquivos). `pm:install` de `aculta_document_viewer` e `aculta_document_viewer_group`.
- Displays `media.document`, `media.course_document`, `node.document` (default/full) migrados para os formatters novos. Verificado: nenhum display com formatter antigo.
- Relação `lms_course`, permissões de `lms_course-member`/`teacher`, dependência imposta da Media `course_document` apontam para o submódulo novo. Verificado após a gravação.
- `config/sync` espelha o estado ativo (sem divergência nos objetos migrados).
- Módulo antigo permanece instalado. Desinstalá-lo exige antes ajustar `aculta_forum`, `aculta_lms` e `aculta_lms_h5p` (dependências `info.yml`); `aculta_forum.info.yml` tem alterações de outra frente.

Riscos abertos da estado B:
1. Hooks e serviços duplicados enquanto os dois módulos coexistem: `entity_presave` do submódulo Group e o guard de CSP. Ambos são idempotentes.
2. Falha de outra frente no Runtime: `ForumAttachmentPublicationHooks::__construct()` recebe `Hook\ForumGalleryPublication`, mas o serviço instancia `Attachment\ForumGalleryPublication`. Qualquer operação de entidade que dispare esse hook quebra. Não é desta frente; está registrada aqui para o dono.
3. Smoke de render: o campo da Media de teste 234 é montado pelo formatter novo (`data-aculta-pdf-viewer`). O render de entidade completo volta vazio porque o site não tem a view mode `media.default`; isso é pré-existente.

### Bloqueio anterior (resolvido)

1. **Arquivos de outra frente:** `aculta_pdf_viewer/src/Pdf/DocumentFieldMap.php`, `src/Pdf/PdfViewerBuilder.php` e dois testes Unit têm alterações não commitadas. Renomear o diretório moveria esse trabalho. Pela regra do projeto, isso não é feito sem o dono.
2. **Runtime:** o Runtime lê o próprio checkout. Renomear o diretório sem executar a migração deixa `core.extension` apontando para um módulo ausente. A migração no Runtime é operação persistente e exige autorização explícita.

### Verificação de Tabulator e SheetJS (registro npm, 2026-10-21)

**Tabulator** (`tabulator-tables`, licença MIT):
- Último da linha 6.5: **6.5.3** (`sha512-nJ2QaqZzQvd9B4rf4LBnRxfb6sSqA/BX2OsXnSJ2d8k21oXDSTtFtOUrDap04a/6BDGivC/MByCxy43ugadJIg==`).
- Última versão geral no npm: 6.7.0. Usar 6.5.3 respeita o plano, que fixa a linha 6.5.

**SheetJS** (pacote npm `xlsx`, licença Apache-2.0):
- A última versão publicada no npm é **0.18.5** (`sha512-dmg3LCjBPHZnQp5/F/+nnTa+miPJxUXB6vtk42YjBBKayDNagxGEeIdWApkYPOf3Z3pm3k62Knjzp7lMeTEtFQ==`).
- As versões mais novas do SheetJS CE não estão no npm; são distribuídas pelo site da SheetJS. Não confirmei aqui o conteúdo nem os avisos de segurança dessas versões.
- Não confirmei nesta sessão os avisos de segurança da linha 0.18.x. Antes de interpretar arquivos de usuário, é preciso verificar os advisories e escolher a versão. Isso é uma decisão pendente.

## Próximas etapas

- **0.1.0-dev.3:** renomeação, após (a) o dono da frente de `aculta_pdf_viewer` commitar ou separar os arquivos alterados e (b) autorização para migração no Runtime.
- **0.1.0-dev.4 (fechamento):** renderizar um DOCX público de teste no navegador, com `renderAltChunks: false`, e registrar o resultado.
- **0.1.0-dev.6 (leitor):** integrar Tabulator Spreadsheet e SheetJS (ver manifestos em `web/libraries/tabulator` e `web/libraries/sheetjs`).
