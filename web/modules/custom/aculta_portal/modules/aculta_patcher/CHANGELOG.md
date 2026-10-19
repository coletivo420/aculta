# Changelog — aculta_patcher

## 0.1.0-dev.2 — aviso do formulário por token — 2026-10-19

- O aviso de "estado ativo" do formulário de patch usa `data-portal-ui="notice-warning"` no lugar de classes `messages`. Sem mudança de texto nem de comportamento.
- RUNTIME STATUS: DEFERRED.

## Não lançado — 0.1.0-dev.1

- Registro de patches como entidade de configuração com formulário Drupal/Claro,
  permissão administrativa restrita, status para o próximo build e exclusão
  confirmada.
- Comando Drush exporta somente registros ativos depois de validar versão no
  `composer.lock`, encontra o caminho instalado com `Composer\InstalledVersions`
  e valida compatibilidade com `git apply --check` em cópia temporária.
- Mantido um patch Composer para o upload AJAX do editor H5P; o upload do
  widget de campo está desabilitado no fork até integrar imagens com `aculta_image`. O exportador resolve
  pacotes por `Composer\InstalledVersions` e valida o patch em cópia temporária.
- RUNTIME STATUS: `aculta_patcher` habilitado no Runtime de teste após backup privado
  (0600); o registro obsoleto do pacote Drupal H5P foi removido. O patch do editor
  permanece registrado; H5P segue desativado e não foi habilitado no Runtime.
