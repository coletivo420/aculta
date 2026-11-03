# Plano de migração ACULTA: Drupal 11.4 → Drupal 12

Base: branch `dev`, commit `82db095`. Análise estática do repositório e de fontes públicas (verificadas em 2026-11-03). Este plano não executa nenhuma alteração.

## 1. Situação atual

| Item | Estado | Observação |
|---|---|---|
| Core | `drupal/core-recommended` 11.4.10 | Pré-requisito do Drupal 12 é 11.3+: atendido |
| PHP exigido | `>=8.3` em `composer.json` | Drupal 12 exige PHP 8.5: precisa subir |
| PHP no host | CLI 8.3.6 agora; `docs/` cita 8.4.26 | Divergência a conferir; PHP 8.5 não instalado (DT-P04, exige root) |
| Symfony | `symfony/http-client` e `symfony/brevo-mailer` `^7.4` | Drupal 12 usa Symfony 8 |
| Drush | `drush/drush` 13.8.0 | Drupal 12 recomenda o CLI nativo `dr` (Drupal 11.4+); compatibilidade do Drush não confirmada |
| Tema | `aculta420` com `core_version_requirement: ^11`, base `bootstrap` 5.0.2 | Precisa `^11 || ^12` |
| Admin | `claro` habilitado | Gin deve virar tema admin estável no D12; decidir |
| Módulos core que saem do core | `history` habilitado (contrib `drupal/history` 1.0.0 já no composer); `contact`, `ban`, `field_layout`, `migrate_drupal`, `migrate_drupal_ui` | `contact` e `ban` não estão habilitados; `history` precisa validação |
| Senhas | hash padrão passa a Argon2id | Hashes antigos continuam válidos e são recalculados no login; testar |
| Config | 284 diferenças de drift conhecidas (DT-O10) | Não deve crescer na migração |
| Código customizado | `aculta_mcp`, `aculta_mcp_client`, `aculta_portal` (+ submódulos) | Usa atributos, sem `@Plugin`; resta procedural (`drupal_static`, `drupal_valid_test_ua`, `drupal_flush_all_caches` em scripts/testes) |
| Contrib beta/alpha | `mcp_server` 2.0.0-beta7, `mcp_server_oauth` 1.0.0-alpha2, `mcp_server_tool_bridge` 1.0.0-beta5, `mcp_server_ui` 1.0.0-beta2, `ui_patterns_field_group` 2.0.0-beta2, `tool` 1.0.0-beta12 | Gargalo principal |
| Patches | `patches-aculta.json`, `patches.lock.json`, `composer-patches` | Cada patch precisa aplicar na versão nova |
| Pins | `drupal/pathauto` 1.15 exato; libs JS (photoswipe, pdf.js, codemirror, select2, intl-tel-input...) | Revisar pins |

## 2. Calendário e risco de prazo

- Drupal 12.0.0: fontes divergem. Um artigo indica semana de **7 de dezembro de 2026** (janela de release). Um post oficial de drupal.org cita meta anterior de 10 de agosto de 2026 e janelas em junho e dezembro. Estado atual: alpha1 e beta1 já publicados para teste de contrib e upgrade.
- Drupal 11 segue com suporte até meados/fim de 2028. Não há urgência de EOL que force a migração antes do lançamento do projeto.

**Recomendação:** não bloquear o lançamento do ACULTA pelo Drupal 12. Tratar a migração como fase paralela, com gate de go/no-go. Se o release cair em dezembro e a matriz de contrib não fechar, o projeto lança no D11.4 e migra depois.

## 3. Decisões que dependem do responsável

1. Lançar em D11.4 com migração paralela, ou exigir D12 no lançamento.
2. Ambiente com PHP 8.5: precisa de root no host (DT-P04). Sem isso não há validação real.
3. Usar branch dedicada ou manter em `dev` via worktree. `AGENTS.md` proíbe criar branch sem ordem explícita.
4. Drush vs `dr`: manter Drush 13 até D12 confirmar compatibilidade, ou migrar agora.
5. Admin theme: manter Claro ou migrar para Gin.
6. Janela de Runtime para a sincronização (D-70).

## 4. Fases

### Fase 0 — Decisões e pré-requisitos (1 semana)
- Responder as decisões da seção 3.
- Confirmar no drupal.org a data da release 12.0.0 e os change records do Core 12.
- Instalar PHP 8.5 no host (requer root) ou definir alternativa de container.
- Registrar baseline: `updatedb:status`, `config:status`, saída dos gates (`validate-aculta-portal-drupal11.php`, `validate-final-drupal.php`) com `vendor/` presente.

### Fase 1 — Dívida Drupal 11 (eliminar antes de subir o core)
- Rodar Upgrade Status e Drupal Rector no código customizado e nos temas, em worktree isolado.
- Corrigir deprecations de Core 11.x que já são removidas no 12 (ex.: `drupal_static`, `drupal_valid_test_ua`, `drupal_flush_all_caches`, chamadas `drupal_*` e APIs marcadas em change records).
- Ajustar `core_version_requirement` para `^11 || ^12` nos `info.yml` (tema e módulos).
- Atualizar `composer.json` para `php >=8.5` só na fase 4, não antes.
- Gate: Kernel `aculta_simpletest`, suítes Unit dos módulos e o gate de deprecações sem regressão.

### Fase 2 — Matriz de compatibilidade contrib
- Para cada pacote de `composer.lock` (263 pacotes), verificar release com suporte a core `^12` e Symfony 8.
- Pacotes de atenção:
  - Beta/alpha (lista acima): verificar se há release estável ou se é preciso aguardar.
  - `drupal/history`: confirmar que o nome do módulo habilitado continua válido.
  - `drupal/commerce` 3.3.10 e `commerce_stock` (DT-COM-16): verificar suporte a D12.
  - `drupal/webform` 6.3.1, `drupal/group` 3.3.5, `drupal/geofield` 1.67.0, `drupal/simple_oauth` 6.1.3 + `e0ipso/simple_oauth_21`, `drupal/social_auth*`, `drupal/search_api` 1.41.0, `drupal/csp` 2.2.4, `drupal/imagemagick` 4.0.2, `drupal/freelinking` 4.0.3, `drupal/bootstrap` 5.0.2.
  - H5P (`h5p-core` 1.28, `h5p-editor` 1.25) com integração customizada (DT-H5P02 a 04).
  - Patches em `patches-aculta.json`: verificar cada um contra a release nova.
- Saída: tabela com cada pacote, release alvo, status (estável, beta, sem release) e ação.
- Gate: nenhum pacote sem release estável sem decisão registrada (substituir, fork ou aguardar).

### Fase 3 — Upgrade em worktree isolado
- Em worktree fora do repositório (`git worktree add --detach`), conforme `AGENTS.md`:
  - Subir `drupal/core-recommended` para `^12` e `symfony/*` para `^8`.
  - Subir `php` para 8.5 e ajustar `composer.json`.
  - Resolver conflitos de versão contrib e aplicar patches.
- Rodar `composer install`, `drush cr` (ou `dr`), `updatedb`, `config:status` e `config:import --diff`.
- Gate: sem erros de instalação; drift de config não aumenta em relação aos 284 conhecidos.

### Fase 4 — Testes de regressão
- Suítes Kernel e Unit por módulo, incluindo `aculta_lms`, `aculta_wiki`, `aculta_forum`, `aculta_commerce`.
- Smokes de navegador já listados em DEBT (uploader, ponto focal, galeria, capa da Revista, reduced-motion, prévia privada da Wiki).
- Testes específicos da mudança:
  - Login com hashes antigos e recálculo para Argon2id.
  - Redefinição de senha e troca de e-mail (`aculta_mail_confirmer`).
  - Tema admin (Claro ou Gin) e o bug de Navigation (DT-P24).
  - Módulo `history` e a contagem de não lidos do fórum.
  - Commerce: carrinho, checkout, pagamento de teste (Mercado Pago em modo teste).
- Gate: nenhuma regressão funcional nas suítes e nos smokes listados.

### Fase 5 — Homologação no Runtime (D-70)
- Snapshot do `aculta_runtime` com hash antes da troca.
- `updatedb:status` e `config:status` antes da troca de head.
- Aplicar `updatedb` e `config:import` do `config/sync`; parar e avisar o responsável se qualquer passo falhar.
- Não tocar em `secrets/`, `settings.local.php`, `private/`, `var/` nem nos dados do site.
- Não há sincronização com produção sem autorização explícita.

### Fase 6 — Go/no-go e release
- Checklist: matriz de contrib fechada, testes verdes, Runtime homologado, plano de rollback.
- Rollback: `composer.lock` anterior, snapshot do banco com hash, e revert do commit de migração.
- Produção só com autorização explícita do responsável.

## 5. Riscos

| Risco | Impacto | Mitigação |
|---|---|---|
| Contrib beta sem release estável no prazo | Alto | Matriz na fase 2; decidir substituir ou aguardar |
| PHP 8.5 indisponível no host | Alto | Instalar com root ou usar container |
| Symfony 8 quebra mailer e http-client | Médio | Testar `aculta_mail_confirmer` e envio via Brevo |
| Patches de contrib não aplicam | Médio | Verificar cada patch na fase 2 |
| Drift de config cresce | Médio | Comparar contra o baseline de 284 |
| Argon2id exige extensão no PHP | Baixo a médio | Verificar `password` extension no PHP 8.5 |
| Lançamento antes de fechar a migração | Alto | Lançar em D11.4 se o gate da fase 2 não fechar |

## 6. Próximo passo

1. Responsável decide as seções 2 e 3.
2. Executar Fase 0 e Fase 2 (matriz de contrib) em paralelo, que são leitura e não alteram código.
3. Só então abrir a Fase 1 em worktree.

Fontes:
- https://www.drupal.org/about/core/blog/help-us-reach-drupal-12s-second-release-window-in-august
- https://www.sparkfabrik.com/en/blog/drupal-12-features-requirements-migration/
- https://www.thedroptimes.com/node/72619 (beta1, não acessível no momento)
