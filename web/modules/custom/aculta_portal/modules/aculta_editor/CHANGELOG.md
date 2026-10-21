# Changelog — ACULTA Editor

## 0.1.0-dev.1 — 2026-10-21

- Submódulo novo, com formatos `aculta_basico`, `aculta_editorial`, `aculta_institucional` e `aculta_email`, cada um
  com editor CKEditor 5 e `filter_html` explícito.
- Oito templates editoriais em configuração de instalação.
- Permissão `use text format aculta_basico` concedida a `content_editor` no `hook_install`. Os demais formatos não
  são concedidos automaticamente.
- Dependência Composer `drupal/ckeditor5_plugin_pack` 1.5.6 (com `drupal/ckeditor5_premium_features` 1.8.6 instalado e
  desabilitado). Ver `docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md`.
- Kernel test de formatos, permissões, sanitização e templates (6 testes, OK).
- Não alterado: formatos existentes, tema, Runtime fora do submódulo.
