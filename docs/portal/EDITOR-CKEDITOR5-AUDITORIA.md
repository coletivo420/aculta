# Auditoria: CKEditor 5 Plugin Pack no ACULTA

Status: **fase 0 — auditoria e decisão de dependência**. Esta fase não cria o submódulo `aculta_editor`, não altera formatos de texto, não habilita nenhum submódulo do Plugin Pack e não altera o tema.

Data: 2026-10-21. Branch: `docs/aculta-forum-0.1.0-dev.1` (PR #297, base `main`).

Pacote avaliado: `drupal/ckeditor5_plugin_pack` (módulo externo, não é fork). Documento de referência do projeto para o editor: este arquivo.

## 1. Ambiente auditado

| Item | Valor | Fonte |
| --- | --- | --- |
| Drupal Core instalado | 11.4.10 | `composer.lock` e `web/core/lib/Drupal.php` (`VERSION`) |
| Requisito do projeto | `drupal/core-recommended ^11.4` | `composer.json` |
| PHP | 8.4.26 (CLI) | `php -v` |
| Composer | 2.8.8 | `composer --version` |
| Ambiente | servidor de testes (`hostname` = `toca`) | `docs/architecture/environments.md` |

O projeto declara PHP 8.5 como alvo. O host de testes roda PHP 8.4 (`DT-P04` em `docs/operations/DEBT-REGISTER.md`). A verificação deste documento foi feita em 8.4.26.

## 2. Estado atual do CKEditor no projeto

**Módulo do Core:** `ckeditor5` está habilitado e é o editor dos formatos abaixo.

**Editores configurados** (`config/sync/editor.editor.*.yml`):

| Formato | Editor | Observação |
| --- | --- | --- |
| `revista_editorial` | ckeditor5 | Toolbar com imagens, galerias (`galeria`, `acultaGalleryCreate`) e Media |
| `usuario_aculta` | ckeditor5 | Toolbar com `drupalMedia`, `acultaImageModel`, `acultaImageLegenda`, `sourceEditing` |
| `wiki` | ckeditor5 | Toolbar com `drupalMedia`, `codeBlock`, `heading`, `sourceEditing` |
| `webform_default` | ckeditor5 | Formato de sistema ("DO NOT EDIT"); não alterar |

**Formatos sem editor:** `restricted_html`, `plain_text`, `email_html`. O `email_html` **não tem filtros exportados** (`filters: {}`), como registrado em `DT-P09`.

**Plugins CKEditor já existentes no código do ACULTA:**

- `aculta_image` (`ImageModel`, `js/ckeditor5/aculta-image-model.js`), em `web/modules/custom/aculta_portal/modules/aculta_image/`.
- `aculta_gallery` (`CreateGallery`, `js/ckeditor5/acultagallery-create.js`), em `web/modules/custom/aculta_portal/modules/aculta_gallery/`.

**Contrib de mídia já no lock:** `drupal/embed` 1.10.0, `drupal/entity_embed` 1.7.0, `drupal/media_gallery`, `drupal/media_duplicates`. Não há contrib de CKEditor 5 além do core antes desta fase.

**Restrições herdadas, que limitam o escopo:**

- Imagem e importação de arquivo passam **obrigatoriamente** por `aculta_image` (AGENTS.md, "Imagens e arquivos de imagem"). Qualquer plugin que importe imagens de URL externa conflita com essa regra.
- Formatos existentes (`revista_editorial`, `usuario_aculta`, `wiki`) estão em uso. Não podem ser sobrescritos.
- Alterações em `web/themes/custom/aculta420/**` exigem autorização explícita do responsável (AGENTS.md). Esta fase não a recebeu.
- E-mail é de responsabilidade do `aculta_mailer`. A auditoria não encontrou um editor de e-mail a integrar nesta fase; o `email_html` continua sem editor.

## 3. Plugin Pack: versão e dependências verificadas

Fontes: repositório Composer oficial do Drupal (`https://packages.drupal.org/8`, metadados p2 e distribuições em `ftp.drupal.org`) e inspeção dos pacotes baixados em diretório temporário, **sem instalação** no projeto.

**Versão escolhida: `drupal/ckeditor5_plugin_pack` 1.5.6** (última estável da série 1.5.x).

| Versão | Requisito Drupal | Requisito premium |
| --- | --- | --- |
| 1.5.1 a 1.5.6 | `drupal/core ^10.3 \|\| ^11.0`, `php ^8.0` | `drupal/ckeditor5_premium_features ^1.6.4 \|\| ^1.7` |
| 1.5.0 | idem | `^1.5` |
| 1.4.0 / 1.4.1 / 1.4.2 | `^9.4 \|\| ^10.0 \|\| ^11.0` | `^1.5` |
| 1.3.2 | `^9.4 \|\| ^10.0 \|\| ^11.0` | `^1.3` |
| 2.0.0-beta1 | não usada | beta; não é candidata |

Conclusão: **não há versão estável da série 1.x que dispense `ckeditor5_premium_features`.** A série 1.5.x foi mantida como recomendado pela missão, e a série 1.3.x/1.4.x foi descartada pelo mesmo motivo e por ser mais antiga.

**Premium Features:** `drupal/ckeditor5_premium_features` 1.8.6 (estável). Requisitos: `drupal/core ^10.3 || ^11.0`, `ext-dom`, `ext-libxml`. Licença do código: GPL-2.0+.

**Premium não é habilitado nem obrigatório no Drupal.** Dentro do Plugin Pack, todos os 24 submódulos dependem apenas de `ckeditor5_plugin_pack:ckeditor5_plugin_pack`, **exceto** `ckeditor5_plugin_pack_free_wproofreader`, que depende de `ckeditor5_premium_features:ckeditor5_premium_features_wproofreader`. A dependência de Composer existe; a dependência de módulo Drupal só recai sobre o WProofreader.

**Licença:** uma pesquisa web indicou que a chave de licença do Premium Features só é exigida para formatos de texto que usam plugins premium, e que formatos só com plugins gratuitos não a consomem. As páginas oficiais em drupal.org retornaram 404 nesta sessão, então **essa afirmação ainda não foi confirmada na documentação oficial** e precisa ser verificada antes de habilitar qualquer plugin do Premium.

**Referências externas encontradas no código (não carregadas por padrão):**

| Origem | Onde | Situação |
| --- | --- | --- |
| `https://cdn.ckeditor.com/ckeditor5/<versão>/dll/` | `ckeditor5_plugin_pack/src/Config/SettingsConfigHandler.php` (`getRemoteDllLocation`) | Usada só se a biblioteca remota for configurada. Por padrão o Plugin Pack usa `/core/assets/vendor/ckeditor5/`. **Manter a opção local.** |
| `svc.webspellchecker.net` (WProofreader) | `ckeditor5_premium_features` | Envia texto a terceiros. Não habilitar (ver seção 5). |
| `cdn.ckeditor.com` (cloud-services), OpenAI, `pdf-converter.cke-cs.com` | `ckeditor5_premium_features` | Só relevantes se um recurso premium for habilitado. Não habilitar. |

## 4. Decisão de dependência Composer

- Adicionado **somente** `"drupal/ckeditor5_plugin_pack": "^1.5"` em `composer.json`.
- `composer.lock`: dois pacotes novos — `drupal/ckeditor5_plugin_pack` 1.5.6 e `drupal/ckeditor5_premium_features` 1.8.6 (dependência obrigatória do Composer). Nenhum outro pacote foi atualizado; a única outra alteração é o `content-hash`. Verificado com `git diff`.
- Motivo: o Plugin Pack entrega 24 plugins de CKEditor 5 gratuitos, que cobrem recursos que o Core não tem (Find and Replace, Word Count, Templates, Paste from Markdown, Link Attributes, Bookmarks, Fullscreen e outros). A alternativa de implementar esses plugins no Aculta não atende à regra de evitar módulos sem necessidade, mas também não justifica código próprio para recursos já maduros.
- Nesta fase **não foi executado `composer install`**. O código não foi copiado para `web/modules/contrib/`, nem para o `vendor/`, e nenhum módulo foi habilitado no Runtime. Isso preserva o estado do Runtime.
- `composer validate`: sem erros novos. Os avisos de versões exatas (`popperjs`, `tippyjs`, `tabby` etc.) já existiam antes da alteração.
- `composer check-platform-reqs`: PHP 8.4.26 e extensões atendem.

**Decisão pendente do responsável:** aceitar `ckeditor5_premium_features` como dependência de código (instalada, não habilitada) no ambiente. Se a resposta for não, o Plugin Pack 1.5.x não pode ser usado, e a alternativa é uma auditoria de implementação própria dos recursos desejados.

## 5. Classificação dos submódulos do Plugin Pack

Classificação de planejamento. **Nenhum submódulo foi habilitado nesta fase.** Os nomes de máquina foram confirmados em `modules/*/` do pacote 1.5.6.

| Submódulo (`ckeditor5_plugin_pack_…`) | Classificação | Motivo |
| --- | --- | --- |
| `fullscreen`, `find_and_replace`, `word_count`, `templates`, `paste_markdown`, `link_attributes`, `bookmark`, `highlight`, `indent_block`, `layout_table`, `select_all`, `page_break`, `text_transformation` | Candidato ao perfil Editorial | Gratuito, depende só do módulo base do Plugin Pack |
| `font` | Candidato, com restrição | Cores e fontes precisam ser limitadas à paleta ACULTA; sem isso, o autor escolhe qualquer cor |
| `emoji`, `todo_document_list`, `empty_block` | Avaliar | Sem uso editorial comprovado; não habilitar sem necessidade |
| `media_embed` | Não habilitar nesta fase | Incorpora conteúdo externo; conflita com a política de mídia (Drupal Media e `aculta_image`) |
| `auto_image` | **Não habilitar** | Importa imagem de URL externa; viola a regra de ingestão do `aculta_image` |
| `html_embed` | **Desabilitado por padrão** | Exige política de segurança própria (missão, seção 12.2) |
| `restricted_editing` | Avaliar para o perfil Institucional | Útil para modelos com trechos bloqueados; depende de configuração de template |
| `free_wproofreader` | **Desabilitado** | Envia texto a serviço de terceiros e depende do Premium Features (seção 3) |

## 6. Conflitos e pontos de integração encontrados

- **Imagens:** `aculta_image` já é dono do pipeline de imagem e de `ImageModel`. Qualquer integração de Media ou de `auto_image` precisa passar por ele. O `aculta_editor` não pode criar upload paralelo.
- **Galeria:** `aculta_gallery` já tem plugin de CKEditor próprio. Não duplicar.
- **Formatos:** os formatos existentes têm toolbars e filtros próprios. A missão pede 4 perfis (Básico, Editorial, Institucional, Email). Nenhum deles existe como configuração. Criar configuração nova é decisão de escopo (seção 8).
- **E-mail:** o `email_html` não tem filtros nem editor. A renderização, a substituição de tokens e o envio pertencem ao `aculta_mailer`. Não há integração a construir nesta fase, porque o editor de e-mail depende de decisão de formato (seção 8).
- **Tema:** estilos do editor (paleta `--aculta-*`) só podem ir ao tema com autorização explícita. Sem ela, qualquer CSS do editor deve ficar na biblioteca do próprio módulo.

## 7. Estado do trabalho desta fase

| Item | Estado |
| --- | --- |
| Dependência Composer (`composer.json` e `composer.lock`) | Implementada e instalada no checkout (`composer install --no-scripts`) |
| Auditoria (este documento) | Implementada |
| Submódulo `aculta_editor` (`0.1.0-dev.1`, estado Dev) | Implementado; habilitado no Runtime de teste |
| Formatos `aculta_basico`, `aculta_editorial`, `aculta_institucional`, `aculta_email` | Implementados (formatos e editores criados, `filter_html` explícito) |
| Templates editoriais (8) | Implementados em `config/install` |
| Submódulos do Plugin Pack habilitados | 15 gratuitos (lista no README do submódulo) |
| Auto Image, HTML Embed, WProofreader, Font, Emoji, Todo, Empty Block, Restricted Editing | Não habilitados |
| Premium Features | Instalado no código, não habilitado |
| Testes Kernel | 6 testes, 93 asserções, OK (`aculta_simpletest`) |
| Interação no navegador (inserir, editar, salvar, reabrir, renderizar) | **Não validada** |
| Integração com Drupal Media (além de `drupalMedia` na toolbar) | **Não implementada** |
| Integração com `aculta_mailer` | **Não implementada** (o formato `aculta_email` existe; renderização e envio seguem no `aculta_mailer`) |
| CSS do editor no tema `aculta420` | **Não implementado** (autorizado; falta fazer e validar visualmente) |

## 8. Decisões do responsável (respondidas em 2026-10-21)

1. **Premium Features:** aceito como dependência de código, instalado e **desabilitado**. Ainda pendente: confirmar a regra da chave de licença na documentação oficial (seção 3).
2. **Tema `aculta420`:** CSS do editor **autorizado**. Ainda não implementado (ver seção 7).
3. **Formatos:** os formatos atuais **não serão modificados**. Os quatro perfis são formatos novos, com permissão mínima.
4. **Recursos:** o responsável escolheu **Media Embed** e **Auto Image**.
   - *Media Embed:* habilitado (submódulo com toolbar `mediaEmbed` em `aculta_editorial`).
   - *Auto Image:* **não habilitado**. O AGENTS.md proíbe importação de origem externa fora da ingestão do `aculta_image`, e o plugin importa de URL externa. Habilitar exige exceção formal registrada pelo responsável; a escolha foi aplicada como exceção pendente, não como habilitação.
   - *Fonte com paleta restrita:* não estava entre as escolhas; foi desabilitada no Runtime.

## 9. Fases propostas (planejamento, não executadas)

1. **F1 — submódulo `aculta_editor`:** criar o submódulo com `package: 'ACULTA Portal'`, `status` em `docs/operations/status/`, README e CHANGELOG. Habilitar apenas os submódulos gratuitos aprovados no Runtime de teste, com verificação de URI e banco antes de cada alteração.
2. **F2 — formatos:** criar os formatos novos, com permissões mínimas. Testes de persistência e de sanitização.
3. **F3 — templates:** modelos do Plugin Pack (`templates`), um por tipo da missão, sem conteúdo fictício.
4. **F4 — Media:** verificar a integração com `aculta_image` e `drupalMedia` nos formatos novos.
5. **F5 — `aculta_mailer`:** apenas a camada de integração e contratos, sem envio no editor.
6. **F6 — estilos:** só após autorização do responsável para o tema.
7. **F7 — testes e documentação final:** testes Kernel no banco `aculta_simpletest`, gates do Portal e validação visual.

Validação exigida por fase: `php scripts/validate-aculta-portal-drupal11.php` e `php scripts/validate-project-status-index.php` quando houver componente novo.

## 10. Verificações desta fase

- `git diff --cached` antes do commit: somente `composer.json` e `composer.lock`.
- Alterações de outros agentes presentes no checkout (`aculta_lms_h5p`, `aculta_forum_lms`, `web/.htaccess`) não foram incluídas em nenhum commit desta fase.
- Nenhuma branch nova, nenhuma PR nova, nenhum merge, nenhum force push.
