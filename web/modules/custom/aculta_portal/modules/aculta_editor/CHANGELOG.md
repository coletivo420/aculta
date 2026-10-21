# Changelog — ACULTA Editor

## 0.1.0-dev.1 — 2026-10-21

- `media_embed` ativado nos quatro formatos (renderização de `<drupal-media>`), com a mesma configuração do `usuario_aculta`.
- Kernel test do ciclo de conteúdo: salvar, recarregar e renderizar um nó com `aculta_editorial`, com sanitização. 8 testes, 140 asserções, OK.
- Contrato com o `aculta_mailer` documentado: o editor de modelos usa `aculta_email`.
- CSS do conteúdo do editor no tema `aculta420` (0.6.0-dev.139).

- Submódulo novo, com formatos `aculta_basico`, `aculta_editorial`, `aculta_institucional` e `aculta_email`, cada um
  com editor CKEditor 5 e `filter_html` explícito.
- Oito templates editoriais em configuração de instalação.
- Permissão `use text format aculta_basico` concedida a `content_editor` no `hook_install`. Os demais formatos não
  são concedidos automaticamente.
- Dependência Composer `drupal/ckeditor5_plugin_pack` 1.5.6 (com `drupal/ckeditor5_premium_features` 1.8.6 instalado e
  desabilitado). Ver `docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md`.
- Kernel test de formatos, permissões, sanitização e templates (6 testes, OK).
- Não alterado: formatos existentes, tema, Runtime fora do submódulo.
