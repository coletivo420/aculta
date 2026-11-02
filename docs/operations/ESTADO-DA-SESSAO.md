# Estado da sessão

Este é o ponto único de handoff para as próximas sessões e agentes. Atualize este documento antes de trocar de sessão, encerrar uma fase ou transferir o trabalho para outro agente/ferramenta, conforme `AGENTS.md`.

## Handoff mais recente — validação Homelab concluída parcialmente, 2026-11-02

- **Tarefa e contexto:** validar no Homelab as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`; usuário autorizou depois a bomba ZIP real e pediu o relatório completo.
- **Checkout/publicação:** `dev`, limpo e sincronizado com `origin/dev` no momento do handoff. Commits desta validação enviados: `3a1a8569e` (fixture H5P), `9dddc1fff` (fixtures Mailer) e `5e73f2279` (bombas ZIP reais). Commit documental da consolidação final ainda precisa ser criado/enviado.
- **Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. Atualização sem pending updates; import somente de `config/sync`, que removeu a configuração DB-only `aculta_file_uploader.settings`; verificações finais de status limpas.
- **Resultados executados:** Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; Mailer Kernel 7/33 PASS; H5P instalador Kernel 13/310 PASS; casos reais de bomba ZIP 2/78 PASS, rejeitando entrada de 33 MiB e entrada de 2 MiB com razão >100:1 antes da extração. Gates Portal Drupal 11+ 1.159 checks PASS; índice de status 48 componentes PASS. Fórum funcional: ACULTA420 6/33 PASS e Olivero 6/33 PASS.
- **Limite:** não há testes `BrowserTestBase`/`FunctionalJavascriptTestBase` para Portal e Revista. Smoke temporária da Revista falhou antes da execução por dependências de Views (`node.type.revista`, `field.field.node.revista.field_aculta_discussion`) ausentes em instalação limpa sem `config/sync`; logo Portal/Revista não foram validados em nenhum dos dois temas. O achado e motivo estão no relatório abaixo.
- **Documentação a finalizar:** atualizar e publicar `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` com os resultados e este handoff. Não alterar o texto dos achados da revisão.
- **Referências:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

## Handoff mais recente

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** alinhar o checkout Homelab a `dev`, validar as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`, corrigir falhas das suítes e publicar fases concluídas em `origin/dev`.
- **Checkout e publicação:** branch `dev` no checkout servido, sincronizada com `origin/dev`; HEAD verificado `904bdcbc5` após a publicação de duas fases. Commits da tarefa enviados: `3a1a8569e` (`test(aculta_lms_h5p): corrige fixture do instalador`) e `9dddc1fff` (`test(aculta_mailer): registra uploader nos Kernels`).
- **Runtime:** hostname `toca`, Drupal 11.4.10, PHP CLI 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import em `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, 102.039.549 bytes, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. `updatedb`: sem updates; `config:import` apenas de `config/sync` removeu `aculta_file_uploader.settings` DB-only. Verificação final após novas mudanças remotas: `updatedb:status` sem updates e `config:status` sem diferenças.
- **Verificações finais:** gates Portal Drupal 11+ PASS (1.159 checks) e índice PASS (48 componentes). PHPUnit oficial: Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; H5P instalador Kernel 11/232 PASS; Mailer Kernels 7/33 PASS, estes quatro Kernels/Units no banco correto conforme aplicável (`aculta_simpletest` para Kernel). H5P foi corrigido para habilitar `aculta_file_uploader` e instalar seu schema; Mailer para habilitar `file` e `aculta_file_uploader`.
- **Documentação:** `SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` recebeu resultados executados e versões atualizadas, mantendo pendentes o teste real de bomba (aguarda autorização explícita) e os testes Portal/Revista/Fórum com Olivero. `QrCodeBatchController` foi verificado como exportação ZIP de saída; fluxo PPT Opigno permanece fora do escopo conforme plano.
- **Arquivos alterados nesta fase:** commits acima cobrem H5P e Mailer. Permanecem alterações documentais locais neste handoff e no relatório de segurança, ainda não commitadas. Preservada fora do escopo a edição de `web/modules/custom/aculta_portal/modules/aculta_studio/src/Form/StudioUploadEmbedForm.php`.
- **Próximos passos:** confirmar autorização antes da bomba real; decidir/rodar os testes de Portal, Revista e Fórum com Olivero; então finalizar e publicar a fase documental em `dev`, sem incluir a edição alheia de Studio.
- **Leitura para retomar:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** resolver todas as pendências de `php scripts/validate-project-status-index.php` em `dev`, por pedido explícito do responsável.
- **Concluído nesta sessão/fase:** alinhadas as versões dos 12 status files aos manifestos, criado o status e README ausentes do `aculta_file_uploader`, e README raiz regenerado pelo script oficial. Commit `4ac8f1d0a` foi enviado e confirmado em `origin/dev`. Validador final PASS para 48 componentes.
- **Arquivos alterados nesta tarefa:** `README.md` (índice gerado); `docs/operations/status/aculta_{document_viewer,file_uploader,forum,gallery,image,lms,lms_h5p,magazine,portal,qrcode,studio,user,wiki}.yml`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este handoff.
- **Verificações executadas e resultados:** `php scripts/validate-project-status-index.php --write` regenerou o índice; `php scripts/validate-project-status-index.php` PASS (48 componentes, incluindo todos os status e links `next_phase`); parse YAML de todos os status e do manifesto do Uploader PASS; `git diff --check` PASS.
- **Pendências e bloqueios:** nenhum bloqueio no índice. O checkout primário `/home/piradopirata/projetos/aculta` está separado da branch `dev` e pode conter estado de outras frentes; não foi alterado. O trabalho ocorre no checkout existente da branch `dev`.
- **Próximos passos:** nenhum para o índice global; `4ac8f1d0a` está publicado em `origin/dev`, branch limpa, validador PASS. Gates autenticados/externos da tarefa Contributions anterior continuam descritos no handoff anterior e no plano correspondente.
- **Leitura para retomar:** `scripts/validate-project-status-index.php`; `docs/operations/status/`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este arquivo.

### Handoff anterior — execução do plano Contributions, 2026-11-02

Commits publicados em `origin/dev`: `6a0f0d072`, `7495f7a3d` e `2445c89b2`. A tarefa do plano, testes e gates operacionais pendentes estão registrados nas seções acima e em `docs/architecture/PLANO-ACULTA-CONTRIBUTIONS.md`.

Não inclua credenciais, tokens ou outros dados sensíveis. Atualize este handoff ao transferir para um novo ponto de retomada, mantendo referências anteriores apenas se ainda forem necessárias para entender o estado atual.
