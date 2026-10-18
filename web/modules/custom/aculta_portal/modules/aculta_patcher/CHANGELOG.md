# Changelog — aculta_patcher

## Não lançado — 0.1.0-dev.1

- Registro de patches como entidade de configuração com formulário Drupal/Claro,
  permissão administrativa restrita, status para o próximo build e exclusão
  confirmada.
- Comando Drush exporta somente registros ativos depois de validar versão no
  `composer.lock` e compatibilidade com `patch --dry-run`.
- Incluído patch Composer para H5P issue #3548290; Composer repatch e o
  exportador Drush validaram sua aplicação. H5P segue desativado até teste
  funcional das permissões e revisão dos outros gates F10.
- RUNTIME STATUS: `aculta_patcher` habilitado no Runtime de teste após backup privado
  (0600); entidade e rota verificadas por Drush. H5P segue desativado e a
  conferência visual HTTP fica DEFERRED (request 404 no alias MAIN).
