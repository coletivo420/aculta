# Módulos — Editorial, mídia e formulários

Data da revisão: 2026-10-07.

## Editorial

| Módulo | Papel |
| --- | --- |
| node | conteúdo |
| taxonomy | classificação |
| views | listas/consultas |
| ckeditor5 | editor |
| filter | formatos de texto |
| workflows / content_moderation | fluxo editorial |
| content_lock | lock de edição |
| diff | comparação de revisões |
| freelinking | links editoriais, especialmente Wiki |
| token | tokens de integrações |

A Wiki permanece Node/Taxonomy/Views. Freelinking não vira storage de conteúdo.

## Mídia

| Módulo | Papel |
| --- | --- |
| media / media_library | ativos e seleção de mídia |
| image | imagens/estilos |
| crop | configuração/entidades de crop |
| image_widget_crop | integração de crop no widget |

O tema não cria pipeline de imagem paralelo.

## Formulários

`webform` e `webform_ui` atendem formulários estruturados do projeto. Conta pertence a User/Profile/Change Mail; checkout pertence ao Commerce. Para o modelo de criação, permissões, submissões, uploads e publicação, consulte [Plataforma de formulários Webform](WEBFORM-PLATFORM.md).

### Bibliotecas externas do Webform

Webform 6.3.1 é a fonte de verdade dos formulários estruturados e do contrato
das bibliotecas JavaScript que eles podem usar. O ACULTA instala localmente as
bibliotecas ativas do contrato para não depender do CDN como baseline; o CDN
declarado pelo Webform permanece apenas como fallback upstream.

As versões abaixo seguem `webform.libraries.yml` e
`webform/composer.libraries.json` da versão instalada. Não atualizar uma dessas
bibliotecas isoladamente sem confirmar compatibilidade com a versão do Webform.

| Biblioteca | Versão Webform | Pacote Composer | Destino |
| --- | --- | --- | --- |
| CodeMirror | 5.65.12 | `codemirror/codemirror` | `web/libraries/codemirror` |
| jQuery Input Mask | 5.0.9 | `jquery/inputmask` | `web/libraries/jquery.inputmask` |
| International Telephone Input | 17.0.19 | `jquery/intl-tel-input` | `web/libraries/jquery.intl-tel-input` |
| jQuery RateIt | 1.1.5 | `jquery/rateit` | `web/libraries/jquery.rateit` |
| jQuery Select2 | 4.0.13 | `jquery/select2` | `web/libraries/jquery.select2` |
| jQuery Text Counter | 0.9.1 | `jquery/textcounter` | `web/libraries/jquery.textcounter` |
| jQuery Timepicker | 1.14.0 | `jquery/timepicker` | `web/libraries/jquery.timepicker` |
| Popper.js | 2.11.6 | `popperjs/popperjs` | `web/libraries/popperjs` |
| Progress Tracker | 2.0.7 | `progress-tracker/progress-tracker` | `web/libraries/progress-tracker` |
| Signature Pad | 2.3.0 | `signature_pad/signature_pad` | `web/libraries/signature_pad` |
| Tabby | 12.0.3 | `tabby/tabby` | `web/libraries/tabby` |
| Tippy.js | 6.3.7 | `tippyjs/tippyjs` | `web/libraries/tippyjs` |

O Webform 6.3.1 inclui `webform:libraries:composer` para gerar o mapa de
pacotes. Seu comando `webform:composer:update` falha nesta versão ao atribuir
propriedades de objeto à lista `repositories`, que a implementação carrega
como array; por isso, o mapa oficial foi integrado ao `composer.json` raiz com
versões fixadas ao contrato instalado. `composer.lock` registra os artefatos e
`composer install` reconstrói os destinos acima após clone. Não há plugin
Composer extra nem download manual/cópia de arquivos para `vendor` ou
`web/libraries`.

Somente as 12 bibliotecas ativas que o Status Report identificava como
ausentes foram adicionadas. Bibliotecas deprecated/excluídas pelo Webform,
como Algolia Places, `jquery.geocomplete`, `jquery.hotkeys`, `jquery.icheck` e
`jquery.toggles`, não devem ser incluídas sem um elemento/configuração ativa
que as exija.

## Views interativas

VVJ Core fornece a base compartilhada para oito formatos de Views instalados: VVJA Accordion, VVJB Carousel, VVJF FlipBox, VVJH Hero, VVJL Lightbox, VVJR Reveal, VVJS Slideshow e VVJT Tabs. O palco de álbum usa VVJS; carrosséis editoriais permanecem VVJB. VVJT não é engine da Minha Conta; VVJA/FlipBox/Reveal não devem esconder conteúdo essencial. VVJC (3D Carousel) e VVJP (Parallax) não foram instalados. Ver [ACCOUNT-UI.md](ACCOUNT-UI.md) e o plano do [ACULTA420 UI Suite](../../web/themes/custom/aculta420/docs/ui-suite-migration-plan.md).

`galeria_produto` também usa VVJS para percorrer as mídias do produto sem autoplay; mantém o modo `produto_galeria`. No álbum, a View filtra slides pelo acesso `view` de Media e compartilha a ordem com as miniaturas; o palco borbulha cacheabilidade do álbum, Media, File e decisões de acesso.
