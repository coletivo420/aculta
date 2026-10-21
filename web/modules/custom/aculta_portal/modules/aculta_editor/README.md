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
`email_html` **não foram alterados**. Os novos formatos são oferecidos nos campos `body` de Artigo, Página básica e
 Documento (o campo do Artigo não restringe a lista; Página e Documento foram ampliados em 0.1.0-dev.2).

Todos os formatos usam `filter_html` com lista explícita de elementos e atributos. O editor visual não substitui
essa sanitização. O formato `aculta_email` não aceita `style`.

## Submódulos do Plugin Pack habilitados

`ckeditor5_plugin_pack_bookmark`, `_find_and_replace`, `_fullscreen`, `_highlight`, `_indent_block`,
`_layout_table`, `_link_attributes`, `_media_embed`, `_page_break`, `_paste_markdown`, `_select_all`,
`_templates`, `_text_transformation` e `_word_count`.

## Desabilitados por decisão

- **Auto Image:** habilitado por exceção formal do responsável (2026-10-22), registrada na auditoria. O plugin só age
  com o plugin de imagem do Core (`ckeditor5_image`) no formato, que os formatos do `aculta_editor` não têm. Por isso
  fica inerte: adicionar o upload do Core violaria a regra do `aculta_image`.
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

- Kernel: `tests/src/Kernel/AcultaEditorFormatsKernelTest.php` (formatos, editores, permissões, `media_embed`, ciclo de conteúdo, sanitização de script,
  `onclick`, `javascript:` e `iframe`, tabelas e âncoras por perfil, layout de e-mail e templates).
  Resultado em 2026-10-21 no banco `aculta_simpletest`: 8 testes, 140 asserções, OK. Três avisos de deprecação vêm
  de `ckeditor5_plugin_pack_templates` (contrib).

## Integração com aculta_mailer

- O editor visual de modelos (`MailTemplateForm`, `aculta_mailer` 0.1.0-dev.61) usa o formato `aculta_email`.
- O corpo HTML é escapado nas variáveis e filtrado pelo formato no render; a parte HTML do envio usa esse corpo sem escapar de novo.
- Sem o formato `aculta_email` (este submódulo desabilitado), o corpo HTML sai escapado, sem marcação (falha fechada).
- Tokens, cabeçalho e rodapé, envio, logs e relatórios continuam no `aculta_mailer`. A proposta e o desenho estão em [`docs/PROPOSTA-MAILER.md`](docs/PROPOSTA-MAILER.md).

## Validação no navegador

Em 2026-10-22, no servidor de testes, com Chromium headless e login pelo link de teste da conta uid 1, o ciclo foi executado no nó de teste 500 (artigo não publicado, criado por Drush e removido ao final):

- **Editar:** troca de formato para `aculta_editorial`, com confirmação do Core. A toolbar carregada tem os itens do formato (modelos, tabela, quebra de página, destaque, localizar e substituir, tela cheia, Media Embed).
- **Inserir:** modelo "Notícia" pelo botão "Inserir modelo", tabela 2×2 e texto digitado.
- **Salvar:** clique real no botão Salvar; o banco confirmou formato, tabela, modelo e texto.
- **Reabrir:** formato, tabela, modelo e texto persistidos.
- **Renderizar:** tabela, modelo e texto na página, sem `<script>` no corpo.

Observações (decisão pendente, não defeito do ciclo):

- Os tipos **Página básica** e **Documento** não oferecem os formatos novos no campo `body`; só o **Artigo** oferece. A diferença está na configuração de cada campo.
- O **Artigo** exige "Imagem editorial" (mídia). O widget da biblioteca de mídia não aceitou a seleção preenchida por script; por isso o teste usou o nó criado por Drush.

## Limitações conhecidas

- **Auto Image:** não habilitado até haver exceção formal do responsável (ver a auditoria, seção 8).
- **Deprecações:** três avisos vêm de `ckeditor5_plugin_pack_templates` (contrib).
- **PasteFromMarkdown:** habilitado; o comportamento de colagem não foi verificado no navegador.
- **Pré-visualização do modelo de e-mail:** usa o mesmo escape do envio; a tela não foi validada no navegador.
- **Formulário de modelos do mailer:** coberto por testes de unidade e Kernel; não foi validado no navegador.
