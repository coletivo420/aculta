# Dependências, segredos e Drupal Security Review

## Dependências

- `composer audit --locked` (não `composer audit` sem `--locked` em worktree sem `vendor/`: o resultado vem vazio, "No packages").
- Para cada advisory registre: pacote, versão travada, advisory, severidade, consumidores e estratégia de correção.
- Não execute `composer update` global, não atualize major e não crie ignore global para vulnerabilidade real. Correção de patch dentro do mesmo minor exige autorização explícita porque altera o lock.
- `npm audit` em `tests/browser` (`@playwright/test` e `@axe-core/playwright`).

## Segredos

- gitleaks está MISSING. Até instalação fixada, registre DEFERRED para o gate de segredos. Não afirme "sem segredos" sem varredura real.
- Nunca imprima o valor encontrado. Relate caminho, linha e tipo.
- Arquivo sensível versionado por engano (chave privada, `.env`, dump) é incidente: não reescreva histórico nem rode rotação sem autorização.

## Drupal Security Review

- `drupal/security_review` está em `composer.json` (`^3.1`) e travado em 3.1.3. O Config Sync habilita o módulo.
- A execução exige Drush e o banco do ambiente. Sem consulta ao Runtime, o resultado é DEFERRED, não PASS.
- A integração deve ser somente leitura. Não corrija configurações, papéis, senhas, `settings.php` ou permissões automaticamente.
