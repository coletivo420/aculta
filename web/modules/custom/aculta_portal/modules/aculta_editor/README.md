# ACULTA Editor

Submódulo opcional do `aculta_portal`. Monta os formatos editoriais do ACULTA sobre o CKEditor 5 (Core) e o
**CKEditor 5 Plugin Pack** (dependência Composer `drupal/ckeditor5_plugin_pack` 1.5.6). Desabilitado, o Portal não
depende dele.

Versão: `0.1.0-dev.1` (estado Dev). Auditoria e decisões: [`docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md`](../../../../../docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md).

## Formatos

| Formato | Público | Editor | Permissão |
| --- | --- | --- | --- |
| `aculta_basico` | colaborador com permissão reduzida | parágrafos, negrito, itálico, títulos 2–3, listas, links, citação, mídia | `content_editor` (concedida no `hook_install`) |
| `aculta_editorial` | redatores e editores | + tabelas, âncoras, destaque, quebra de página, templates, localizar e substituir, tela cheia, Media Embed, código-fonte | concedida pelo responsável |
| `aculta_institucional` | gestores de documentos institucionais | + listas de definição, títulos 2–5, templates, quebra de página | concedida pelo responsável |
| `aculta_email` | modelos de e-mail | negrito, itálico, sublinhado, links, listas, tabelas de layout, mídia | concedida pelo responsável |

Os formatos `revista_editorial`, `usuario_aculta`, `wiki`, `webform_default`, `restricted_html`, `plain_text` e
`email_html` **não foram alterados**.

Todos os formatos usam `filter_html` com lista explícita de elementos e atributos. O editor visual não substitui
essa sanitização. O formato `aculta_email` não aceita `style`.

## Submódulos do Plugin Pack habilitados

`ckeditor5_plugin_pack_bookmark`, `_find_and_replace`, `_fullscreen`, `_highlight`, `_indent_block`,
`_layout_table`, `_link_attributes`, `_media_embed`, `_page_break`, `_paste_markdown`, `_select_all`,
`_templates`, `_text_transformation` e `_word_count`.

## Desabilitados por decisão

- **Auto Image:** importa imagem de URL externa, o que exige a ingestão do `aculta_image`. Não habilitado até
  haver exceção registrada.
- **HTML Embed:** exige política de segurança própria (desabilitado por padrão).
- **WProofreader:** envia texto a terceiros e depende do Premium Features.
- **Fonte e cor (`_font`):** não escolhido pelo responsável nesta fase. Não habilitado no código.
- **Emoji, lista de tarefas, bloco vazio, edição restrita:** sem uso aprovado.

## Premium Features

`drupal/ckeditor5_premium_features` está instalado no código (dependência Composer obrigatória do Plugin Pack 1.5.x),
**mas não está habilitado**. Nenhum recurso premium é usado.

## Templates

Oito modelos em `config/install/ckeditor5_plugin_pack_templates.ckeditor5_template.*.yml`: artigo institucional,
notícia, comunicado oficial, nota pública, manifesto, artigo jurídico, documento explicativo e conteúdo de campanha.
Disponíveis nos formatos `aculta_editorial` e `aculta_institucional`. Os textos são marcadores entre colchetes (ex.:
`[Título da notícia]`), não dados fictícios, e devem ser substituídos pelo autor.

## Instalação e atualização

- Instalação limpa: `drush en aculta_editor` cria formatos, editores e templates, e concede `aculta_basico` a
  `content_editor` pelo `hook_install`.
- Se a instalação for interrompida (como ocorreu no Runtime de teste em 2026-10-21, com `PreExistingConfigException`),
  o hook não roda. Nesse caso conceda a permissão manualmente ou chame `aculta_editor_install()`.
- Alterações futuras dos formatos devem ocorrer por configuração exportada, nunca por hook de atualização que sobrescreva
  formatos já em uso.

## Rollback sem perda de conteúdo

Desabilitar o submódulo não apaga conteúdo. Os textos salvos com esses formatos continuam no banco; só o editor deixa
de existir. Não desinstalar o módulo enquanto houver conteúdo com esses formatos (ver a consulta de uso na auditoria).

## Testes

- Kernel: `tests/src/Kernel/AcultaEditorFormatsKernelTest.php` (formatos, editores, permissões, sanitização de script,
  `onclick`, `javascript:` e `iframe`, tabelas e âncoras por perfil, layout de e-mail e templates).
  Resultado em 2026-10-21 no banco `aculta_simpletest`: 6 testes, 93 asserções, OK. Três avisos de deprecação vêm
  de `ckeditor5_plugin_pack_templates` (contrib).

## Limitações conhecidas

- Interação no navegador (inserir, editar, salvar, reabrir e renderizar com os plugins) **não foi validada** nesta fase.
- Integração com Drupal Media e com `aculta_mailer` **não implementada** além dos itens de toolbar. O `aculta_mailer`
  continua dono da renderização, dos tokens e do envio.
- CSS do editor no tema `aculta420` **não implementado**, embora a autorização tenha sido dada, por exigir validação
  visual e atualização do mapa módulo–tema.
- O plugin de `pasteFromMarkdown` está habilitado; o comportamento no editor ainda não foi verificado no navegador.
