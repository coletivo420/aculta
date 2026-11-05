# Prompt para o agente: migração ACULTA para Drupal 12

Use este prompt somente depois que a release estável do Drupal 12 estiver publicada e o responsável tiver autorizado a execução. Antes disso, não execute nenhuma etapa que altere o repositório.

Leia antes de começar: `AGENTS.md`, `CLAUDE.md`, `docs/portal/DRUPAL-11-STANDARDS.md`, `docs/operations/AGENT-OPERATIONS.md`, `docs/portal/MIGRACAO-DRUPAL-12-PLANO.md` e `docs/portal/MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md`.

## Objetivo

Migrar o ACULTA de Drupal 11.4 para Drupal 12 estável, em branch de trabalho, sem alterar produção e sem sincronizar o Runtime antes de todos os gates passarem.

## Pré-condições (pare se alguma falhar)

1. Drupal 12.0.0 estável publicado no drupal.org (não beta, não RC). Confirme a release e os change records.
2. PHP 8.5 instalado no host de desenvolvimento. Verifique com `php -v`. Se não estiver instalado, pare e informe o responsável (requer root).
3. Decisão do responsável sobre cada pacote da matriz marcado como bloqueador: `webform`, `commerce`, `group`, `mcp_server`, `mcp_server_oauth`, `mcp_server_tool_bridge`, `mcp_server_ui`, `imagemagick`, `pathauto`, `simple_oauth`, `social_auth`, `private_message`, `csp`, `ctools`, `entity_print`, `flood_control`, `redirect`, `simple_sitemap`, `photoswipe`, `pdfpreview`, `bootstrap`. Para cada um, a decisão é: aguardar release, fork/patch aprovado, ou substituir. Sem decisão registrada, não continue.
4. Working copy limpa: `git status --short --branch` sem alterações de outras pessoas. Não descarte nada que não seja seu.

## Regras de execução

- Trabalhe em worktree isolado fora do repositório (`git worktree add --detach <caminho> origin/dev`), conforme o `AGENTS.md`.
- Não crie branch sem ordem explícita do responsável. Se a ordem existir, use a branch indicada.
- Não faça `git push` até o gate final (fase 6) ser aprovado pelo responsável. A regra padrão do `AGENTS.md` envia commits a `origin/dev`; nesta migração, a exceção deve ser explícita e registrada.
- Não faça force push, reset destrutivo nem reescrita de histórico.
- Mensagens de commit em português, com o trailer `Claude-Session` quando aplicável. Não inclua `Co-Authored-By`.
- Não toque em `secrets/`, `settings.local.php`, `private/`, `var/` nem nos dados do site.
- Não faça deploy nem altere produção.
- Não use caminhos absolutos de máquina em código, scripts ou documentação versionada.

## Sequência

### Fase 0: linha de base

- Registre a versão atual: `composer.lock`, `php -v`, saída de `updatedb:status` e `config:status` (somente leitura).
- Rode os gates: `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-admin-routes-main-domain.php`. Se algum gate falhar antes da migração, registre e pare.

### Fase 1: dívida Drupal 11 (no branch de trabalho, sem subir o core)

- Rode Upgrade Status e Drupal Rector no código customizado (`web/modules/custom/`) e no tema (`web/themes/custom/aculta420/`).
- Corrija as deprecations que serão removidas no 12 (`drupal_static`, `drupal_valid_test_ua`, `drupal_flush_all_caches`, demais APIs apontadas pelos change records).
- Ajuste `core_version_requirement` nos `.info.yml` de `^11` para `^11 || ^12`.
- Rode as suítes Kernel e Unit pelo `aculta_simpletest`, sem criar bancos novos.
- Commit atômico desta fase, separado da mudança de core.

### Fase 2: resolução do Composer

- Atualize `composer.json` para `drupal/core-recommended:^12` e pacotes `core-*` correlatos. Use `composer require ... --dry-run` antes de qualquer alteração.
- Resolva Symfony 8 para `symfony/http-client` e `symfony/brevo-mailer` com `composer why-not` como diagnóstico.
- Atualize `drupal/upgrade_status` para uma versão que aceite `^12`, ou remova-o se não for mais necessário.
- Atualize os contrib de acordo com a matriz: `message_subscribe` 2.1.1, `metatag` 2.2.5, `search_api` 1.43, `vvjb` 2.0.2, `vvjs` 2.0.2, `vvjt` 2.0.0, `vvjf` 2.0.1, `vvja` 2.0.0.
- Aplique ou remova patches de `patches-aculta.json`. Cada patch deve ser verificado contra a nova versão.
- Gate: `composer install` sem erro de resolução, `composer validate` OK.

### Fase 3: instalação e configuração

- `drush cr` (ou `dr`, conforme decisão do responsável).
- `drush updatedb` e `drush config:import --diff`.
- O drift de configuração não pode aumentar. A linha de base é de 284 itens (DT-O10). Registre a diferença antes e depois.
- Gate: nenhum erro de instalação. Drift igual ou menor que a linha de base.

### Fase 4: testes de regressão

- Suítes Kernel e Unit de `aculta_lms`, `aculta_wiki`, `aculta_forum`, `aculta_commerce`, `aculta_user`, `aculta_portal`.
- Smokes de navegador listados em DEBT (uploader, ponto focal, galeria, capa da Revista, reduced-motion, prévia privada da Wiki), no Firefox ESR visível, conforme `docs/operations/NAVEGADOR-TESTES.md`.
- Testes específicos da mudança:
  - login com hashes antigos (recálculo para Argon2id);
  - recuperação de senha e troca de e-mail (`aculta_mail_confirmer`);
  - envio de e-mail via Symfony Mailer / Brevo;
  - carrinho e checkout (Commerce) com pagamento de teste;
  - tema admin (Claro ou Gin, conforme decisão);
  - módulo `history`, com contagem de não lidos do fórum.
- Testes de Portal, Revista e Fórum no tema ACULTA ativo.
- Gate: nenhuma regressão nas suítes e nos smokes. Falha registrada vira pendência, não é aceita em silêncio.

### Fase 5: homologação no Runtime (somente após fase 4 aprovada)

- Snapshot do `aculta_runtime` com hash antes da troca de head.
- `updatedb:status` e `config:status` antes da troca.
- Aplicar `updatedb` e `config:import` do `config/sync`. Se qualquer passo falhar, pare e informe o responsável.
- Sincronize somente o código do Runtime para o commit aprovado.

### Fase 6: gate final e publicação

- Checklist: matriz de contrib fechada, testes verdes, Runtime homologado, plano de rollback documentado (`composer.lock` anterior e snapshot com hash).
- Apresente ao responsável: commits com hash, validações executadas com resultado, estado do Runtime e bloqueios. Não diga que publicou sem confirmação do push.
- Só então, com autorização explícita, faça o push para `origin/dev`.

## Critérios de parada

Pare e informe o responsável, sem tentar contornar, se:

- algum gate falhar sem causa conhecida;
- um pacote bloqueador não tiver decisão registrada;
- o drift de configuração aumentar;
- um teste de regressão falhar em fluxo de dinheiro, acesso ou autenticação;
- aparecer alteração de outra pessoa na working copy;
- a instrução parecer conflitar com `AGENTS.md`, `CLAUDE.md` ou `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`.

Nunca faça mudança "para Drupal 12" por suposição: consulte o change record ou a API instalada.
