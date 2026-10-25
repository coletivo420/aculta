# ACULTA Editor

Submódulo opcional do `aculta_portal`. Reúne os modelos editoriais (templates do CKEditor 5 Plugin Pack) e a
configuração de formatos de texto do ACULTA. Desabilitado, o Portal não depende dele.

Versão: `0.1.0-dev.4` (estado Dev). Auditoria e decisões: [`docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md`](../../../../../docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md).

## Formatos de texto (unificados)

Os formatos antigos recebem as funções do ACULTA; os excedentes foram removidos.

| Formato (máquina) | Função | Editor |
| --- | --- | --- |
| `plain_text` | **Aculta Básico (texto puro)** | sem editor (texto) |
| `usuario_aculta` | **Usuário ACULTA (HTML restrito)** | CKEditor 5 |
| `revista_editorial` | **Aculta Editorial** (HTML com filtro de edição) | CKEditor 5 |
| `email_html` | **Aculta Email (e-mails)**, usado pelo `aculta_mailer` | CKEditor 5 |
| `wiki` | **Aculta Wiki (somente Wiki)** | CKEditor 5 |
| `webform_default` | formato do sistema do Webform (não alterar) | CKEditor 5 |

Removidos: `aculta_basico`, `aculta_editorial`, `aculta_institucional`, `aculta_email` (sem conteúdo) e
`restricted_html` (o único conteúdo, a página 372, foi migrado para `usuario_aculta` sem perda: só `<p>` e `<a>`).

## HTML completo (tipo restrito)

- **Tipo `aculta_adm` (ACULTA administração):** corpo em `full_html` (HTML completo do Core), sem restrição de tags. É o
  único lugar com HTML sem filtro. Fica restrito à administração pela permissão de criação do tipo.
- **Editorial** (`revista_editorial`) passou a ter filtro de HTML (lista de tags de edição, a mesma validada no
  navegador), por decisão do responsável.

## Modelos (templates)

Oito modelos em `config/install/ckeditor5_plugin_pack_templates.ckeditor5_template.*.yml`: artigo institucional,
notícia, comunicado oficial, nota pública, manifesto, artigo jurídico, documento explicativo e conteúdo de campanha.
Disponíveis no formato `revista_editorial` (Editorial). Os textos são marcadores entre colchetes, não dados fictícios.

## Submódulos do Plugin Pack

Habilitados: `ckeditor5_plugin_pack_auto_image` (exceção do responsável; inerte sem `ckeditor5_image`),
`_bookmark`, `_find_and_replace`, `_fullscreen`, `_highlight`, `_indent_block`, `_layout_table`,
`_link_attributes`, `_media_embed`, `_page_break`, `_paste_markdown`, `_select_all`, `_templates`,
`_text_transformation` e `_word_count`.

Desabilitados: `html_embed`, `free_wproofreader`, `font`, `emoji`, `todo_document_list`, `empty_block`,
`restricted_editing`. Premium Features está instalado no código, mas não habilitado.

## Contrato com o aculta_mailer

- O editor de modelos de e-mail usa `email_html` (Aculta Email).
- O corpo HTML é escapado nas variáveis e filtrado pelo formato no render; a parte HTML do envio usa esse corpo sem
  escapar de novo.
- Tokens, cabeçalho e rodapé, envio, logs e relatórios continuam no `aculta_mailer`.

## Validação

- Kernel: `tests/src/Kernel/AcultaEditorFormatsKernelTest.php` (7 testes, 87 asserções, OK em 2026-10-22). Três avisos
  de deprecação vêm de `ckeditor5_plugin_pack_templates` (contrib).
- Navegador (2026-10-22, servidor de testes): ciclo editar, inserir modelo e tabela, salvar, reabrir e renderizar
  com o formato Editorial; colagem Markdown no Editorial.
- Revisão visual do e-mail: aprovada pelo responsável.

## Rollback

Desabilitar o submódulo não apaga conteúdo. Os formatos são de configuração do site, não deste módulo.
