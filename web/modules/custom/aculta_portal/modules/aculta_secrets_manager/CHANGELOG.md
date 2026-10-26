# Changelog

## 0.1.0-dev.3 — fallback CSS no tema administrativo Claro — 2026-10-26

- Seletores semânticos das abas e da rolagem de tabela preservam o estilo quando o ACULTA420 não está ativo; valores visuais não mudam.
- Tokens preexistentes `secrets-section` e `secret-value*` continuam sem classes no Claro; estilos correspondentes permanecem como dívida para uma etapa própria.
- Gate de fronteira e testes Unit/Kernel existentes registrados após execução nesta revisão. O render do painel com Claro no Kernel foi tentado, mas o harness para no theme registry por falta do plugin de campo `state`; mantida a checagem CSS estática e o render foi marcado DEFERRED.


## 0.1.0-dev.2 — tokens de apresentação das abas — 2026-10-26

- Classes visuais das abas e da rolagem de tabela passam pelo contrato Portal–tema, preservando as mesmas classes no ACULTA420.
- Dívida: mover `css/secrets-tabs.css` do submódulo para o tema em fase posterior.
- Gate de fronteira, schema SDC, foundations, PHP lint, PHPCS e testes Unit combinados (20/106) e Kernel combinados (8/231): PASS. Firefox ESR: DEFERRED (`DISPLAY` indisponível); validação de instalação via Drush: DEFERRED (docroot isolado sem `settings.php`, Runtime fora do escopo).


## 0.1.0-dev.1

- Extraída a UI administrativa de `aculta_portal` para este submódulo: controller, formulários de
  alteração e remoção, rotas, permissão `administer aculta secrets`, link de menu e de aba, biblioteca
  das abas (JS e CSS).
- Mantidos os nomes das rotas (`aculta_portal.secrets_*`), do link de menu e da biblioteca do painel
  `aculta_portal/requirements-report`, porque o Portal gera links para eles (`DeployerStatusController`,
  `PortalRequirementsController`).
- Abas Produção e Testes (ARIA, teclado, mobile com rolagem horizontal).
- Escopo por ambiente no backend: `value()`, `save()` e `clear()` recusam credencial fora do ambiente
  ativo (no núcleo, em `aculta_portal`). Controller nega mostrar, editar e apagar fora do escopo (403).
- `Cache-Control: no-store, private` e `Pragma: no-cache` nas rotas do painel.
- Token CSRF (`_csrf_token`) nas rotas de revelar, alterar e apagar.
- Testes unitários: abas, escopo, cabeçalhos.


## Integração na PR #298 — 2026-10-26

- Reexecutados na branch: Unit Portal + Secrets 384 testes / 1.008 assertions PASS; suítes Unit dos consumidores Mailer, Statistics, CPF, Mercado Pago e Melhor Envio 403 / 1.100 PASS; Kernel Routes 1 / 4 PASS em MariaDB dedicado.
- PHPCS Drupal/DrupalPractice e PHP lint passaram em todo `src/` e `tests/` após correções de documentação e formatação sem mudança funcional.
- `validate-secrets-contract.php`, aliases de rota e gate administrativo por Drush passaram; o gate administrativo examinou o Runtime MAIN somente em leitura e não valida código ainda não implantado.
- Runtime MAIN informou `aculta_secrets_manager` Enabled, versão `0.1.0-dev.1`; nenhuma gravação foi feita.
- Functional não foi reexecutado na PR. Firefox ESR e HTTP com sessão real permanecem evidência registrada na branch de origem, sem revalidação nesta integração. Estado mantido em Dev; não elegível a RC enquanto Functional e testes de instalação/rollback não forem concluídos.
