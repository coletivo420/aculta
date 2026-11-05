# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Resolução do CLAUDE.md (aculta, branch dev) — 2026-11-05

- **Objetivo:** reduzir o CLAUDE.md a ponteiros e mover cada regra para o arquivo dono, conforme pedido do responsável.
- **Concluído:** `753ef5f1a3` em `origin/dev`. CLAUDE.md com 1494 bytes; `AGENTS.md` novos em `aculta_portal_cep` e `aculta_pagamentos_mercadopago`; linha de independência do tema em `aculta_portal/AGENTS.md`. No rebase, a regra de CEP já estava na raiz do `AGENTS.md` do remoto, então não a dupliquei no módulo do portal. `AGENTS.md` raiz não alterado (4984 bytes).
- **Verificações:** `validate-agent-token-budget.php` PASS (CLAUDE 1494/3000, handoff 3/3); `validate-module-theme-boundary.php` PASS (4248); `validate-root-policy.php` PASS (13 checks); `validate-admin-routes-main-domain.php` PASS (169 rotas).
- **Riscos:** nenhum registrado.
- **Pendências:** nenhuma desta entrega. O repositório é `aculta`.
- **Próximo passo:** nenhum.


# Migração data-portal-ui, mapa do V2 e upload centralizado — 2026-11-05

- **Objetivo:** migrar as classes de design de `aculta_lms`, `aculta_lms_resources` e `aculta_paths` para `data-portal-ui` e mapear os tokens no tema V2; centralizar o validador de upload da resposta do LMS.
- **Concluído:** `8dfa7ddb` (migração de 22 pontos, hook `preprocess` genérico no V2, gate de tokens que varre submódulos), `7c223cf6` (`ConsumerUploadPolicy::extensionValidators()`), `091cbf81` (39 tokens mapeados no V2 sem classe, atributo `data-portal-ui` mantido no DOM, regex do gate aceita `__`) e `044b079d` (remoção de `UserUploadPolicy` e do teste Kernel dela). Todos em `origin/dev`.
- **Verificações:** `php -l` PASS nos PHP alterados. `validate-module-theme-boundary.php` PASS (4248 verificações). `validate-aculta-image.php` PASS. `validate-agent-token-budget.php` PASS. Captura visual, PHPUnit e Kernel: DEFERRED (sem Runtime e sem `vendor/`).
- **Riscos:** o hook `preprocess` do V2 não foi executado no Runtime. Sem o mapa, token não mapeado não gera classe e o elemento perde estilo; a regressão visual só se confirma com captura.
- **Pendências:** (1) portar a cobertura do teste Kernel removido (formulário de abas, diálogo, inspeção de bytes) para `ConsumerUploadPolicy`, DEFERRED; (2) decidir `config/sync/aculta_portal.user_upload.yml` e o schema, que continuam sem uso (exigem pedido explícito); (3) captura visual de aulas, eventos e Studio no V2, porque os 39 tokens dependem de seletor de atributo.
- **Próximo passo:** o responsável decidir o item (2) e autorizar a porte do teste (1); depois, captura visual no Runtime.

# Padrão Drupal 11+ revisado para Drupal 12 ready — 2026-11-05

- **Objetivo:** atualizar `docs/portal/DRUPAL-11-STANDARDS.md` para o alvo Drupal 12 ready e para as regras de htmx, e remover decisões históricas e legadas.
- **Concluído:** documento normativo reescrito (linha de versão, seção Drupal 12, seção htmx com regras e diferenças do htmx 4, fases P1 e P4 e contagens de dívida removidas; os tetos ficam no gate). Texto integral anterior arquivado em `docs/history/portal/DRUPAL-11-STANDARDS-ARQUIVO-2026-11-05.md`.
- **Commit:** `c2cc2aa8` (documento). Esta entrada foi registrada em commit separado, por falha de script na mesma sessão.
- **Fontes verificadas em 2026-11-05:** change record de htmx 4 no Core 12 (publicado em 2026-09-03, presente em 12.0.0-beta1) e change record de htmx no Core 11.3 (`https://www.drupal.org/node/3539472`). Notícia de terceiros sobre remoções de Search, Claro e Olivero do Core, ainda não confirmada em drupal.org.
- **Verificações:** o gate `validate-aculta-portal-drupal11.php` confere apenas que os documentos citam o padrão, e a referência continua válida. Gate não executado aqui (sem `vendor/` e sem Drush): DEFERRED.
- **Pendências:** confirmar em drupal.org a lista de remoções do Core 12 e a data do release; decidir o tema administrativo (Claro ou Gin); inventariar os pontos do Portal que usam `Htmx` ou `#ajax`.
- **Próximo passo:** rodar o gate do Portal no servidor de desenvolvimento e, em seguida, testar um Core 12 beta em ambiente de teste para medir o impacto do htmx 4.
