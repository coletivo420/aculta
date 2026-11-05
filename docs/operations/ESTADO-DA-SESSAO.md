# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

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

# Economia de tokens — oito itens aprovados — 2026-11-05

- **Objetivo:** reduzir a leitura e a saída de comandos pelos agentes, conforme aprovação do responsável (itens 1 a 8 de `docs/operations/AUDITORIA-TOKENS-TESTES-2026-11-05.md`, seção 9).
- **Concluído:** itens 1, 2, 3 (Wiki e Studio), 4, 5, 6, 7 e 8. `TESTES-NAVEGADOR.md` caiu de 57 KB para 5,5 KB; as rodadas F a V estão em `docs/history/portal/TESTES-NAVEGADOR-RODADAS.md`. AGENTS da Wiki caiu de 34 KB para 6,7 KB; do Studio, de 13 KB para 7,5 KB. Criado `docs/operations/GATES.md`. Removidos `scripts/validate-institution.php` e `scripts/validate-podplant420-assets.php`.
- **Commits em `origin/dev`:** `b1589e8e` (arquivo e compactação de testes de navegador), `8dbb5066` (AGENTS de módulos), `f9aa6230` (fonte única de regras, gates, não ler, saída obrigatória e remoção de validadores).
- **Verificações:** links relativos dos arquivos alterados conferidos por script; restaram quebras anteriores a esta entrega (`docs/portal/README.md` e `ACULTA-MODULES-CLOSEOUT.md`, para módulos de PDF Viewer que já não estão no caminho indicado). Gates PHP **DEFERRED**: o ambiente não tem `vendor/` nem Drush.
- **Pendências:** `handoff/podplant420` não foi arquivado, por causa de referências vivas em `config/sync/views.view.revista_sessoes.yml` e em scripts de verificação. `aculta_portal/docs/AGENT-POLICIES.md` não foi deduplicado, porque exige conferência item a item com `DRUPAL-11-STANDARDS.md`. Cerca de 30 documentos ativos ainda trazem caminhos de máquina; a regra está em `AGENTS.md`, seção Escopo e segurança, e a correção fica para uma fase própria. Validadores com uma única citação em auditoria ou dívida foram mantidos e precisam de decisão do responsável.
- **Próximo passo:** no servidor de desenvolvimento, rodar `php scripts/validate-aculta-portal-drupal11.php` e os gates do escopo de cada área alterada, colando a saída; depois decidir o arquivamento de `handoff/podplant420`.

