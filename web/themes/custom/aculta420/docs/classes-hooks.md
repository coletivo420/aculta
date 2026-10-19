# Classes e hooks PHP do ACULTA420

Este inventário descreve o código em `src/Hook/` no tema. Os atributos `#[Hook]`
registram os hooks; os métodos listados abaixo são os pontos de entrada atuais.
Para atualizar a lista, confira primeiro as declarações nos arquivos-fonte.

## `ThemeHooks`

Arquivo: [`src/Hook/ThemeHooks.php`](../src/Hook/ThemeHooks.php). Classe de
apresentação com dependências recebidas por construtor. Faz a composição visual
de páginas, adapta contratos neutros recebidos do Portal e trata ajustes
específicos de Views.

| Hook | Método | Responsabilidade |
| --- | --- | --- |
| `preprocess_views_view_vvjb` | `preprocessViewsViewVvjb()` | Desativa controles e autoplay quando o destaque editorial da home tem um único slide. |
| `preprocess_views_view_vvjs_fields` | `deferInactiveAlbumSlideImages()` | Adia o `src` das imagens de slides inativos da View `aculta_album_fotos`, display `block_1`. |
| `preprocess_views_view_vvjs` | `deferInactiveProductSlideImages()` | Adia imagens de slides de produto na View `galeria_produto`. |
| `preprocess_node` | `preprocessNode()` | Constrói o SDC `aculta420:hero` para nós `page` em visualização completa quando há título de hero. |
| `preprocess_field` | `preprocessField()` | Aplica a classe de prosa aos corpos editoriais e envolve o formulário de resposta privada no SDC `aculta420:messenger-compose`. |
| `preprocess_page` | `preprocessPage()` | Compõe a página, adapta marca neutra, move blocos de cabeçalho para variáveis dedicadas e converte ações neutras do Messenger/Avisos em SDCs. |
| `preprocess_private_message_thread` | `preprocessPrivateMessageThread()` | Apresenta o estado nativo de leitura como `aculta420:messenger-read-status`. |
| `preprocess_private_message` | `preprocessPrivateMessage()` | Envolve conteúdo, avatar e direção da mensagem em `aculta420:messenger-item`. |
| `preprocess_html` | `preprocessHtml()` | Aplica o modo de cor resolvido pelo Portal e o título de documento fornecido pelo contrato. |

`presentMessengerPage()`, `presentMessengerTrigger()`, `presentAlertsTrigger()`,
`buildDomainBrandFallback()` e `brandMediaRender()` são auxiliares privados, não
hooks Drupal. `deferProductSlideImage` é callback de renderização confiável,
declarado por `TrustedCallbackInterface::trustedCallbacks()`.

## `PortalUiHooks`

Arquivo: [`src/Hook/PortalUiHooks.php`](../src/Hook/PortalUiHooks.php). Traduz o
atributo neutro `data-portal-ui` em classes de apresentação do tema. O mapa
`TOKENS` é a referência para nomes de tokens e classes resultantes.

| Hook | Método | Responsabilidade |
| --- | --- | --- |
| `preprocess_menu` | `preprocessMenu()` | Converte tokens nos atributos dos links de menu, incluindo itens descendentes. |
| `preprocess_container`, `preprocess_html_tag`, `preprocess_item_list`, `preprocess_input`, `preprocess_form_element`, `preprocess_image`, `preprocess_aculta_portal_element`, `preprocess_aculta_portal_search_trigger`, `preprocess_aculta_portal_cart_status`, `preprocess_aculta_portal_shell`, `preprocess_aculta_portal_account_terms_modal`, `preprocess_aculta_portal_photo_editor` | `preprocess()` | Converte tokens nos atributos e render arrays desses templates. Também troca o ícone de `image_button` do Webform (adicionar e remover) por SVG do tema e aplica a classe de botão; o clique continua `input[type=image]`. |
| `preprocess_details` | `preprocessDetails()` | Aplica o mesmo mapeamento ao elemento `details` depois da preparação do Bootstrap. |

O hook não cria classe para token desconhecido. Novos tokens precisam ser
emitidos pelo Portal como contrato neutro e mapeados em `TOKENS` no tema.

## `PortalBrandHooks`

Arquivo: [`src/Hook/PortalBrandHooks.php`](../src/Hook/PortalBrandHooks.php).
Resolve o asset visual do tema para um template neutro de aviso de pagamento.

| Hook | Método | Responsabilidade |
| --- | --- | --- |
| `preprocess_aculta_portal_payment_notice` | `preprocessPaymentNotice()` | Fornece a URL da marca Mercado Pago ao template do aviso de pagamento. |

## Fronteira de responsabilidade

Os hooks do tema controlam apresentação e não substituem hooks funcionais do
Portal. Para autenticação, Domains, regras de negócio, acesso e dados, consulte
[`architecture.md`](architecture.md) e a
[documentação do Portal](../../../../../docs/portal/README.md). O contrato de
integração entre classes PHP e classes CSS está em
[`design-system.md`](design-system.md) e
[`../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md`](../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md).
