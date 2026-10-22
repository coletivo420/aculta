# Pesquisa e evidências — ACULTA Forum

Revisão: 2026-10-22. Base local: `origin/main` `d4a0d66e`. Este documento
distingue documentação upstream, código instalado e decisões alvo. APIs Drupal
e dependências devem ser rechecadas no momento de cada implementação.

Para a ação contextual da View, Drupal Core 11.x documenta `hook_views_pre_render`
como ponto de inserir `attachment_before`/`attachment_after` antes da renderização;
`AccessManagerInterface::checkNamedRoute(..., TRUE)` fornece o resultado de acesso
cacheável. Código instalado verificado em `views.api.php`, `ViewExecutable.php`,
`AccessManagerInterface.php` e `CacheableMetadata.php`. Referências: [Views pre-render](https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/function/hook_views_pre_render/11.x), [AccessManagerInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessManagerInterface.php/interface/AccessManagerInterface/11.x).

O filtro categórico usa o plugin Views `taxonomy_index_tid`; Core 11 suporta
campos `entity_reference` direcionados a `taxonomy_term` e restringe as opções
por vocabulário. A integração e a atualização da tabela `taxonomy_index` foram
verificadas em `TaxonomyViewsHooks.php` e `TaxonomyHooks.php`. Referências:
[plugin TaxonomyIndexTid](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21Plugin%21views%21filter%21TaxonomyIndexTid.php/11.x),
[integração de Views Taxonomy](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21Hook%21TaxonomyViewsHooks.php/class/TaxonomyViewsHooks/11.x).

## Investigação de contagens por categoria (2026-10-22)

A View `aculta_forum_categories` parte de `taxonomy_term_field_data` e hoje não
inclui conteúdo nem contagem. No Core instalado 11.4.10, `NodeViewsData` associa
o tag `node_access` à tabela-base de Node. `Sql::execute()` acrescenta o tag
quando Node é a base da View; além disso,
`RelationshipPluginBase::query()` também acrescenta o tag quando a relação
alcança uma tabela-base que o declara. `TermViewsData` fornece o caminho
`taxonomy_term_field_data` → `taxonomy_index` → `node_field_data` por meio da
relação `taxonomy_index.nid`. Assim, essa relação de Views é uma candidata a
aplicar node grants sem query tag customizado.

`NodeDatabaseHooks::queryNodeAccessAlter()` localiza a tabela Node relacionada,
aplica os grants do usuário e adiciona `user.node_grants:view` ao contexto de
renderização. Uma listagem por query não executa `entity_access()` para cada
Node; por isso, a configuração ainda deve fixar o bundle `aculta_forum_topic`,
publicação e associação ao Domain do purpose `forum`. A antiga hipótese de que
a View teria necessariamente que configurar `node_access` em `query_tags` foi
incorreta e fica substituída por esta leitura do código Core. A presença do tag
ainda não prova que uma contagem final esteja correta: o caminho precisa ser
validado na consulta montada pela View, inclusive quando agregação e pager geram
query de contagem.

O Core também registra em `TaxonomyViewsHooks` a relação `taxonomy_index.nid` →
`node_field_data`; o caminho candidato é termo → `taxonomy_index` → Node, com
filtro fixo para `aculta_forum_topic`, publicado e atribuído à categoria. Domain
Access 3.0.1 implementa grants no mesmo sistema de Node Access por domínio
ativo. A camada de query é uma hipótese compatível com o código instalado, não
prova de que o resultado final respeita todos os acessos e caches do Runtime.

Antes de expor contagens, validar com registros controlados no MariaDB:
publicado e pendente; autorizado e negado por Node grants; Node no propósito
`forum` e fora dele; categoria vazia; e alteração/publicação/despublicação com
invalidação da cache da View. Comparar a consulta/contagem com Entity Access para
cada conta. Não exibir contagem nem ativar agregação enquanto essa matriz e a
revisão de SQL/cache não passarem. Nenhum conteúdo ou configuração Runtime foi
alterado nesta investigação.

Fontes: Core instalado em `web/core/modules/node/src/NodeViewsData.php`,
`web/core/modules/node/src/Hook/NodeDatabaseHooks.php`,
`web/core/modules/views/src/Plugin/views/query/Sql.php`,
`web/core/modules/views/src/Plugin/views/relationship/RelationshipPluginBase.php`,
`web/core/modules/taxonomy/src/TermViewsData.php` e contrib instalado em
`web/modules/contrib/domain/domain_access/src/Hook/DomainAccessNodeHooks.php`;
[Views relationship access tags](https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21relationship%21RelationshipPluginBase.php/function/RelationshipPluginBase%3A%3Aquery/11.x),
[Views SQL query plugin](https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21query%21Sql.php/class/Sql/11.x),
[Core Node access grants](https://api.drupal.org/api/drupal/core%21modules%21node%21src%21Hook%21NodeDatabaseHooks.php/class/NodeDatabaseHooks/11.x),
[Node access API](https://www.drupal.org/docs/develop/drupal-apis/writing-a-module-that-handles-node-access).

## Estado real do repositório

| Evidência | Constatação | Efeito para o projeto |
| --- | --- | --- |
| Composer/runtime após dev.50 | `drupal/forum` removido de `composer.json`, `composer.lock` e do Runtime de teste; `drupal/history` permanece direto | Não reinstalar o pacote; o propósito público `forum` permanece pelo Domain. |
| Config Sync | Configurações de Node `forum`, taxonomy `forums`, Comment `comment_forum`, campos e View antiga removidos; flags passam às bundles ACULTA | Importação integral espera a revisão do drift existente; aplicar configuração dirigida e verificar flags/contextos. |
| `web/modules/contrib/forum` (inspecionado antes de dev.50) | ForumManager, ForumIndexStorage, controllers, forms, hooks, blocks, routes, validation e config optional | Código removido depois do uninstall oficial; efeitos e validação estão em `MIGRATION-REMOVAL.md`. |
| Código `drupal/forum` 1.1.3 (`ForumUninstallValidator`, `forum.install`, `ForumUninstallTest`) | Uninstall exige zero Nodes `forum` e zero termos no vocabulário configurado; uninstall remove três field storages e chama `FieldPurger` em lote de até 10, mas não exclui Nodes/termos/Comments | Excluir entidades autorizadas explicitamente por Entity API e auditar dependências de configuração antes do uninstall. A auditoria confirmou equivalência de `field_categoria_revista` com categorias editoriais diretas; remover seu field config/avaliar storage antes da configuração do vocabulário. Ver `MIGRATION-REMOVAL.md`. |
| Runtime Magazine `aculta_runtime`, 2026-10-19 (consultas Entity API somente leitura) | 5/8 termos antigos mapeiam `field_categoria_revista` para `editorial_category`; 3 vazios. As 12 publicações vinculadas (8 `article`, 4 `blog`) já têm `field_editorial_category` igual ao destino antigo; são 4 Editorial e 8 Notícias | Não migrar nem substituir campos editoriais. Retirar o mapeamento auxiliar junto com o legado só após validação dos Comments diretos e da limpeza por escopo. Evidência por contagem/comparação em `MIGRATION-REMOVAL.md`. |
| Config Sync | Node `forum`, taxonomy `forums`, Node field `taxonomy_forums`, Comment bundle/field `comment_forum`, displays, flags e View `aculta_forum_topics` | Conhecer dependências e references antes de remover configuração/dados. |
| `aculta_magazine` | `LegacyPublicationTopicLookup`, `PublicationTypeConverter` e `ForumOpeningPost` ainda preservam/leem vínculos; `ForumSectionResolver` e seus updates de criação de pastas foram aposentados em dev.14. `ForumTopicHooks` foi removido em dev.12 após confirmar zero Comments `comment_forum` | F-D25 substitui a discussão por Comment no Node editorial. API de sincronização e backfill removidos em dev.11; lookup restante é somente leitura até remoção segura dos vínculos. |
| `aculta_user` | Cards/autoria, ParticipationRules, proteção de tópicos importados ACULTA e acesso contextual; realms/taxonomia do Forum contrib removidos em dev.49 | Preservar identidade/moderação contextual; discussões LMS dependem de Group, nunca de permissões globais de fórum. |
| `aculta_messenger` | `MessageSubscribeEventHooks` e Aviso/retention atendem eventos de fórum | Reutilizar o único transporte e atualizar produtores/consumidores com idempotência/access. |
| Busca | Search API instalada; config atual não seleciona o Forum nem Comment como fonte pública | Reavaliar índices por contexto, não presumir que a busca cobre tópicos hoje. |
| ACULTA420 | Templates `forum-list`, `node--forum--full`, `comment--comment-forum`; `forum-index.css`, `discussion.css` | Apresentação atual do contrib será substituída pelo tema a seu tempo; identidade é de `aculta_user`. |
| `aculta_user` cards | `PublicProfilePolicy`, `PublicProfileController`, `PublicProfileLink`, `AccountBioCard`, `SocialLinksCard`, `AvatarPool`, `LegacyAuthor*`, amizade/conquistas | Há blocos funcionais reutilizáveis, mas não um AuthorCardBuilder unificado. Ver inventário próprio. |

O código instalado é fonte de verdade para a configuração descrita. A auditoria
somente leitura do Runtime de teste em 2026-10-19 confirmou 8 referências
`field_discussao` em `article` e 4 em `blog`; isso não afirma que a configuração
exportada e o servidor sejam idênticos em todos os itens.

## Upload contextual e integração visual (2026-10-21)

- O Fórum e a Wiki usam o formato `usuario_aculta`, com `drupalInsertImage` e
  `drupalMedia`; o segundo permite somente Media `document` e usa o view mode
  `default`. Esse display público usa o formatter `aculta_pdf_media`, que
  embute PDF.js. Não alterar o display compartilhado para mudar somente o
  Fórum. A permissão de criar mídia e o formato atual também são compartilhados;
  conceder upload nesse formato libera contextos além do comentário público.
  A integração precisa escopar plugin, formato e permissão ao Fórum e preservar
  embeds existentes do bundle `document`.
- Core 11.4.10 fornece o endpoint `ckeditor5.upload_image`; ele usa as
  configurações do formato (`scheme`, diretório e `max_size`) e os validadores
  do plugin Core. `aculta_image.ImageUploadIngestor` já recebe destino
  `public://` ou `private://`, mas a política de extensões/tamanho é fixa em
  `UploadPolicy` (10 MB). As opções futuras do Fórum precisam chegar ao
  validador do servidor e respeitar a política central, inclusive tamanhos
  acima do valor padrão autorizados pelo painel.
- `aculta_gallery` já demonstra o padrão do projeto para um botão CKEditor 5
  customizado que abre o diálogo Core por `Drupal.ckeditor5.openDialog`, recebe
  um Form API/AJAX e insere o resultado via `EditorDialogSave`. Seu formulário
  não abre Media Library aninhada: o Core mantém um único `#drupal-modal` e a
  Library substituiria o primeiro modal. Referências locais:
  `aculta_gallery/src/Plugin/CKEditor5Plugin/CreateGallery.php`,
  `aculta_gallery/src/Form/GalleryEmbedDialogForm.php` e
  `aculta_gallery/docs/ARQUITETURA.md` §4.3.
- A integração com `aculta_gallery` é requisito do Fórum: reutilizar o botão e
  diálogo `CreateGallery`/`GalleryEmbedDialogForm`, a entidade `media_gallery`,
  o contrato Entity Embed e o display PhotoSwipe existente. Não criar álbum,
  formatter ou armazenamento paralelo no `aculta_forum`. O formato de texto do
  Fórum deve habilitar o embed e validar acesso coerente com o comentário. O
  Gallery publica a entidade ao enviar o diálogo, enquanto comentários podem
  aguardar moderação; resolver esse ciclo e a política de arquivo/mídia privada
  com o dono do Gallery antes de habilitar a integração no Fórum, sem mudar os
  usos atuais da Revista. Upload de imagem deve respeitar a ingestão e a política
  de `aculta_image`, sem duplicar processamento ou exclusão.
- O popup padrão exige modal Core; o módulo fornece comportamento e marca
  `data-portal-ui`, enquanto o tema controla largura e apresentação em
  `css/components/dialog.css`. A regra está em
  `docs/portal/POPUP-PADRAO.md`; UI Suite Bootstrap não está instalado. O
  Bootstrap 5 do tema é o primitive visual. UI Patterns/SDC só é adequado ao
  cartão de anexo se houver composição reutilizável. O SDC estável
  `aculta420:card` já fornece slots de mídia, cabeçalho, conteúdo e rodapé; deve
  ser avaliado antes de criar outro cartão, mantendo Portal/Core como donos de
  dados, URL, acesso e cache. UI Styles para CKEditor permanece desativado.
- O formatter compartilhado e `PdfViewerBuilder` do `aculta_pdf_viewer` já
  oferecem abertura do leitor completo por link, com `_blank` padrão, e geração
  de miniatura via `pdfpreview` + ImageMagick + `aculta_image`. O builder atual
  só gera miniatura de fonte `public://`; documentos privados de turma recebem
  link protegido sem preview. O cartão do Fórum precisa mostrar miniatura,
  nome/tamanho e link para o leitor em outra janela por padrão, com opção
  administrativa para abrir na mesma janela, sem renderizar o leitor no
  comentário. Para anexos privados, a miniatura só pode ser publicada depois
  do comentário e precisa acompanhar sua retirada; validar acesso e limpeza
  antes de estender o builder. `private://` sozinho não vincula autorização ao
  comentário: Core verifica acesso ao Media/File, e Media publicado com
  `view media` pode permitir o download anônimo do arquivo privado. O ciclo de
  publicação do Media precisa acompanhar comentário e tópico, e contextos com
  acesso próprio exigem uma checagem explícita da cadeia de entidades; não
  considerar o diretório privado como política de autorização.

Não há renderer Office instalado no Portal. Microsoft Office Online Server
oferece renderização rica, mas requer serviço/farm WOPI; seus online viewers não
abrem arquivos que exijam autenticação. Não serve ao contrato de anexos privados
sem uma infraestrutura WOPI própria. Mammoth.js converte DOCX em HTML no
navegador, sem fidelidade visual completa, e declara que não sanitiza o
conteúdo. SheetJS lê formatos de planilha no navegador, mas é parser/modelo de
dados, não um renderer Office fiel; DOC e apresentações continuam sem solução
local confirmada no Portal. A pré-visualização deve ser local, por formato,
sanitizada e limitada em processamento; não declarar suporte uniforme a
DOC/DOCX/XLS/XLSX/PPT/PPTX sem renderer confirmado para cada extensão. Manter
nome, tamanho e download como fallback, e habilitar preview só para formatos
que passarem revisão de segurança e performance. Referências primárias:
[Mammoth.js — escopo e segurança](https://github.com/mwilliamson/mammoth.js),
[SheetJS Community Edition](https://github.com/SheetJS/sheetjs),
[Microsoft Office Online Server](https://learn.microsoft.com/en-us/officeonlineserver/office-online-server-overview).

### Google Docs/Drive como visualizador Office

As APIs Google Docs/Drive têm quotas de chamadas sem cobrança adicional, mas
não oferecem um endpoint que renderize um arquivo Office arbitrário diretamente
no cartão do Fórum. A API Docs edita documentos Google; para pré-visualizar um
Office seria necessário enviá-lo ao Drive e convertê-lo para Docs, Sheets ou
Slides. As conversões variam por formato, e a importação deve ser verificada
pela lista `about.importFormats` da conta que fará a conversão. Referências
oficiais: [limites e quotas da Docs API](https://developers.google.com/docs/api/limits),
[upload e conversão no Drive API](https://developers.google.com/workspace/drive/api/guides/manage-uploads)
e [formatos de importação](https://developers.google.com/workspace/drive/api/reference/rest/v3/about).

Isso exigiria OAuth e escopos Drive. `drive.file` limita o acesso a arquivos
abertos/criados pelo app e não converte a integração em visualizador público;
escopos mais amplos podem ser sensíveis ou restritos e exigem verificação Google.
Consulte [seleção de escopos Drive](https://developers.google.com/workspace/drive/api/guides/api-specific-auth),
[catálogo de escopos OAuth](https://developers.google.com/identity/protocols/oauth2/scopes)
e [verificação de escopos](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification).

Quando o servidor encaminha anexos de participantes a um Drive da associação,
conteúdo e metadados passam a ser processados e armazenados por terceiro. Além
de quotas de API, há armazenamento, credenciais, controle de acesso, retenção,
exclusão e obrigações de transparência/consentimento. A política de dados das
APIs Google exige divulgação e proteção dos dados obtidos; [Google API User
Data Policy](https://developers.google.com/terms/api-services-user-data-policy)
e [Google APIs Terms](https://developers.google.com/terms) devem ser revisados.
“Sem custo adicional de API” não significa serviço de conversão gratuito,
privado ou sem transferência de dados. A fase de anexos do Fórum não habilita
essa integração sem aprovação explícita do fluxo de dados, da base LGPD, do
escopo OAuth e da política de retenção. Por ora, manter prévia local somente
para formatos cobertos por renderer seguro e usar cartão com metadados/download
nos demais.

### Bibliotecas locais com experiência semelhante a Docs

O PDF.js já embutido no `aculta_pdf_viewer` é um leitor PDF, não importa Office.
Para exibição de Office, as opções se dividem entre conversores locais e uma
suíte web separada:

| Opção | Escopo e fidelidade | Custo operacional/risco |
| --- | --- | --- |
| Mammoth.js | DOCX → HTML semântico; ignora deliberadamente a maior parte do layout, então serve para leitura textual, não para fidelidade de página | JavaScript no navegador; a saída precisa ser sanitizada e documentos não confiáveis podem causar links `javascript:`, acesso a arquivos externos ou consumo excessivo |
| SheetJS Community | Lê XLS/XLSX e pode montar tabelas HTML a partir de planilhas | Não é uma suíte que reproduz a planilha como o Excel; limite de estilos/conteúdo exige testes com arquivos reais e limites de células/tempo |
| `docx-preview` / renderizadores JS de PPTX | Interpretam Office Open XML e montam HTML/CSS/canvas no navegador para formatos específicos | Suporte parcial e fidelidade variam por elementos; dependências, tamanho do pacote, parser e desempenho precisam ser avaliados. A biblioteca `pptx-browser` se declara sem dependências, mas não é renderer oficial do Microsoft Office |
| LibreOffice headless → PDF.js | Converte os formatos que o LibreOffice importa para PDF; o leitor atual exibe o resultado, preservando melhor paginação que conversão semântica | Requer LibreOffice instalado no servidor/worker e conversão isolada de conteúdo não confiável, com limites de CPU, memória, tempo, páginas, rede e diretório temporário |
| ONLYOFFICE Docs Community ou Collabora Online | Suíte web separada com visualização e edição de texto, planilhas e apresentações, integrada por API/WOPI; é a opção mais próxima da experiência Google Docs | Serviço adicional auto-hospedado, integração WOPI no Portal, infraestrutura/monitoramento/atualização e revisão de licença. O documento continua armazenado no ACULTA, mas o servidor Office recebe o arquivo para renderizar |

ONLYOFFICE Docs Community é AGPL-3.0; a FAQ do fornecedor diz que uso em
serviço de rede exige disponibilizar o código de modificações e trabalhos
derivados, manter a marca e não oferece SLA oficial. Collabora Online Development
Edition (CODE) é gratuita para desenvolvimento/pequenas equipes, mas continua
sendo um servidor separado integrado por WOPI. Portanto, nenhuma dessas opções
é uma biblioteca para adicionar ao `aculta_pdf_viewer`, e a licença, escala,
operação e disponibilidade precisam ser aceitas antes de produção. Isso é
distinto das bibliotecas de prévia no cliente (Mammoth, `docx-preview` e
SheetJS), que não fornecem edição colaborativa equivalente ao Google Docs.

Mammoth declara que não busca fidelidade de layout e não sanitiza conteúdo; não
injetar a saída diretamente em página Drupal. SheetJS oferece parsing e
serialização de dados/tabelas HTML, o que não equivale à renderização completa de
uma planilha. LibreOffice documenta conversão headless pela linha de comando.
ONLYOFFICE e Collabora oferecem integração de cliente Office via WOPI; os
exemplos dos fornecedores exigem que o servidor Office consiga chamar o host
WOPI. Referências primárias: [Mammoth — conversão e segurança](https://github.com/mwilliamson/mammoth.js),
[SheetJS — leitura em browser e tabela HTML](https://docs.sheetjs.com/docs/demos/net/network/),
[`docx-preview` (Apache-2.0)](https://github.com/VolodymyrBaydalka/docxjs),
[`pptx-browser`](https://github.com/bowespublishing/pptx-browser),
[LibreOffice — conversão headless](https://help.libreoffice.org/latest/en-GB/text/shared/guide/start_parameters.html),
[ONLYOFFICE — conceitos e auto-hospedagem](https://api.onlyoffice.com/docs/docs-api/get-started/basic-concepts),
[ONLYOFFICE Community — licença e limites](https://helpcenter.onlyoffice.com/docs/faq/docs-community.aspx),
[ONLYOFFICE WOPI](https://api.onlyoffice.com/docs/docs-api/using-wopi/overview),
[Collabora — FAQ sobre CODE e WOPI](https://www.collaboraonline.com/faqs/),
[Collabora SDK/WOPI](https://sdk.collaboraonline.com/CO-SDK-manual.pdf),
[Drupal — acesso a Media privado](https://www.drupal.org/docs/8/core/modules/media/setting-up-private-access-to-media-items),
[Drupal Core — segurança de diretórios](https://www.drupal.org/docs/getting-started/installing-drupal/securing-drupal-file-directories).

Sequência aprovada pelo responsável: primeiro upload seguro de imagens e
integração `aculta_gallery`; depois avaliar preview local de DOCX e planilhas,
com sanitização e limites de processamento. Tipos sem renderer validado mantêm
cartão com metadados/download. Por último, integrar cartão e abertura de PDF ao
ACULTA Document Viewer (`aculta_pdf_viewer`), sem duplicar reader/conversão no
Fórum. Mammoth/SheetJS podem ser previews auxiliares com escopo explícito; uma
suíte WOPI self-hosted oferece maior fidelidade e edição, mas exige decisão de
infraestrutura/licença e não é uma biblioteca PHP/JS que se instala no Drupal.

Referências upstream: [API do plugin CKEditor 5](https://www.drupal.org/docs/drupal-apis/ckeditor-5-api/overview),
[Ajax Dialogs](https://www.drupal.org/docs/develop/drupal-apis/ajax-api/ajax-dialog-boxes),
[Core Media Embed 11.x e view modes](https://api.drupal.org/api/drupal/core%21modules%21media%21src%21Plugin%21Filter%21MediaEmbed.php/class/MediaEmbed/11.x),
[configuração Core da Media Library no CKEditor](https://api.drupal.org/api/drupal/core%21modules%21ckeditor5%21ckeditor5.ckeditor5.yml/main).

## Auditoria de contrib instalado e portabilidade

Inventário conferido em `composer.lock`, `composer show --locked` e no código
presente em `web/modules/contrib` em 2026-10-19. Os módulos abaixo são avaliados
como dependências instaladas; qualquer uso futuro continua sujeito ao código e
testes da versão travada. “Portar” significa adaptar somente comportamento útil
ao contrato ACULTA, com revisão de licença/proveniência, não copiar sistema
inteiro nem criar storage paralelo.

| Módulo/versão | Capacidade e evidência local | Decisão para o Fórum |
| --- | --- | --- |
| `drupal/forum` 1.1.3 | `web/modules/contrib/forum`: `ForumManager`, storage de índice, rotas, formulários, blocos, hooks, validação e configuração optional; Composer depende de `drupal/history` | Legado a retirar na fase autorizada. Inspecionar efeitos de uninstall; nenhum código executável D7 será base do motor novo. O purpose público Growroom420 permanece. |
| [`drupal/flag`](https://www.drupal.org/project/flag) 5.1.1 | Serviço `flag`, entidades de flag, integração Views e configuração `seguir_topico`/`salvar_topico` já consumida por `aculta_forum` | Reutilizar para seguir/salvar/denunciar conforme configuração; não armazenar flags em entidade/tabela própria. Revalidar access/contexto antes de mostrar ou notificar. |
| [`drupal/ctools`](https://www.drupal.org/project/ctools) 4.1.1 | Plugin API e submódulo `ctools_views` em `web/modules/contrib/ctools` | Reutilizar plugins/contextos e blocos Views com opções editoriais controladas. Não expor filtros, ordenação ou campos de access ao configurador. |
| `drupal/history` 1.0.0 | Histórico de leitura do Node e integração de campos/serviços usados pela View de não lidos | Reutilizar para progresso de leitura de tópicos públicos onde a semântica instalada cubra a necessidade. Não presumir suporte a Comments nem cruzar Domain/contextos sem teste. |
| [`drupal/search_api`](https://www.drupal.org/project/search_api) 1.41.0 | Framework de índices/processors; backend e Views integration disponíveis no projeto. Upstream já publicou 1.42 após a versão travada; nenhuma atualização foi solicitada. | Reutilizar índices/Views por contexto. Search API não garante restrições genéricas de acesso; conteúdos LMS/Wiki privados exigem filtros fixos e teste de isolamento. Índice público nunca recebe conteúdo privado. |
| [`drupal/group`](https://www.drupal.org/project/group) 3.3.5 | Membership, roles e acesso de conteúdo por grupo; adapter LMS planejado. A documentação upstream descreve negação fechada para conteúdo agrupado sem permissão/membership. | Integrar pelo adapter opcional e access efetivo; papel global estudante não substitui membership. Nunca publicar Group content no índice público. |
| [`drupal/message`](https://www.drupal.org/project/message) 1.9.0, [`message_notify`](https://www.drupal.org/project/message_notify) 1.5.0, [`message_subscribe`](https://www.drupal.org/project/message_subscribe) 2.1.0 | Message stack de tipos, entrega e assinatura; Message Subscribe usa Flag para subscrição. Integração ativa do Portal é `aculta_messenger`. | Não enviar notificações fora de `aculta_messenger` nem criar fila concorrente. Confirmar consumidores antes de retirada/atualização. |
| `drupal/private_message` 3.0.7 | Mensagem privada independente | Não usar como armazenamento de discussão; não é dependência do motor de Comments nem solução para LMS. |
| [`drupal/comment_mover`](https://www.drupal.org/project/comment_mover) | Não consta no lockfile nem em `web/modules/contrib`. O tarball publicado 2.0.0-alpha1 (SHA-256 `2fa3a4af65b43eb7855b7ae4c78da8368e425bcd4d10477e7f670fdd46510a91`) tem `CommentMover::moveComment()` e testes Kernel que atualizam as entidades existentes; a recursão de thread foi inspecionada diretamente. | Referência funcional para mover Comments/descendentes; não instalar como runtime. A rotina alfa não coordena transação, locks, access, estatísticas dos dois hosts nem cache. Além disso, `recursivelyUpdateCommentThread()` passa a mesma base ao descer níveis, podendo atribuir o mesmo caminho a descendentes profundos; o teste publicado cobre apenas um filho. Esse achado orientou regressão ACULTA com dois ramos e netos, sem copiar a implementação. |
| [`drupal/views_bulk_operations`](https://www.drupal.org/project/views_bulk_operations) 4.4.9 | Adicionado ao `composer.lock` em dev.118; release estável declara Drupal `^10.3 || ^11`. Código instalado confirma Action API, etapa de configuração/confirm, seleção entre páginas e execução Batch API. Consulta upstream e inspeção em 2026-10-20. | Usado apenas na View fixa de tópicos administrativos para seleção/movimento em lote limitado. Action própria revalida permissão, Domain, categoria e histórico em cada Node via `ForumTopicMover`; VBO não substitui access, filtros fixos nem mover Comment transacional. Runtime ainda não habilitado. |
| [`drupal/forum_access`](https://www.drupal.org/project/forum_access) 2.0.2 | Página upstream declara Core 10.3/11/12 e dependência de ACL. Tarball GPL-2.0 (`SHA-256 0d2b83c1b054bac65a85bc5095f0e77b9a21d3de222f9f28efb3e79982f05bc0`) inspecionado; arquivos `src/ForumAccess/Access.php`, info/services, config, formulário e testes. Access é por ACL em termos do vocabulário Forum/Node bundle; a classe examinada carrega include procedural e chama funções globais. | Não instalar nem portar storage/callback: isso cria grants paralelos, cobre o bundle legado e não atende os quatro contextos, Group/LMS ou Wiki. Reaproveitar apenas o cenário de negar acesso antes de responder/formulário nos testes dos providers ACULTA. A licença permite adaptação conforme a política, mas não houve cópia. A referência é para matriz de casos, não substitui Entity Access, Domain ou membership. |
| [`drupal/comment_notify`](https://www.drupal.org/project/comment_notify) 8.x-1.6 upstream | Ausente no lockfile e no diretório contrib; a release estável publicada em ago/2026 declara Drupal 9/10/11. Implementa preferências de acompanhamento, notificação de respostas e cancelamento por item; somente comentários publicados, sem alertar moderadores para fila pendente. | Reutilizar casos de uso e checklist de opt-in/opt-out, reply-only e cancelamento. Não portar entrega de e-mail nem armazenamento/assinaturas: `aculta_messenger` e Flag já são fonte do projeto; validar acesso atual e idempotência em cada envio. |
| [`drupal/achievements`](https://www.drupal.org/project/achievements) 3.0.3 upstream | Ausente no lockfile e no diretório contrib; release declara Drupal 9/10/11/12. Modelo documentado é conquista por marcos, com opção de ocultar placares/ordenação competitiva e opt-out. | Fonte de padrões e cenários para marcos de participação e privacidade, não motor a instalar. O `aculta_game` é dono de eventos e concessões; integrar via eventos aprovados, sem duplicar pontos, armazenamento, placares ou premiar importações. |
| [`drupal/gamification`](https://www.drupal.org/project/gamification) 2.0.2 upstream | Ausente no lockfile e em `web/modules/contrib`; declara Drupal 8–11 e usa plugins/eventos ligados ao ecossistema User Points/Transaction, incluindo limites de atribuição. | Inspecionar como referência para limites/idempotência de gatilhos, mas não incorporar sua dependência de points/transaction: não corresponde à economia e às regras do `aculta_game`. Qualquer port precisa provar que eventos de comentário aprovado/moderado geram uma única concessão e que rollback não premia. |
| [`drupal/ai_comment_moderation`](https://www.drupal.org/project/comment_moderation_ai) 1.0.1 upstream | Ausente no lockfile e no diretório contrib; página consultada declara Drupal 11, dependência de serviço externo/OpenAI e informa que o projeto não está coberto pela política de security advisories. | Não instalar nem portar para a moderação atual. A decisão do Portal requer política explícita por contexto e revisão humana; nova dependência externa sem decisão de produto, privacidade e credenciais. A página serve apenas como referência de casos de classificação/fila. |
| [`drupal/advanced_forum`](https://www.drupal.org/project/advanced_forum) | Não consta no lockfile nem em `web/modules/contrib` | Não instalado. Advanced Forum D7 é catálogo de capacidades; ramo 8.x em desenvolvimento não confirma Drupal 11. Não copiar PHPTemplate, hooks D7 ou APIs obsoletas. |
| [`drupal/author_pane`](https://www.drupal.org/project/author_pane) | Não consta no lockfile nem em `web/modules/contrib`; documentação upstream descreve cartão contextual com foto, nome, data de entrada, presença, contato e dados de Profile, extensões por preprocess e template próprio do Advanced Forum | Referência funcional importante para `aculta_user` e para a matriz `AUTHOR-PANE-PORT.md`. O projeto marca D7 como manutenção mínima e o ramo D8 como inacabado/não utilizável; não copiar preprocess PHPTemplate nem implementar presença/IP sem decisão de privacidade, necessidade e cache. Portar os requisitos úteis via API DI/cacheável e tema ACULTA420. |
| [`drupal/user_stats`](https://www.drupal.org/project/user_stats) | Não consta no lockfile nem em `web/modules/contrib`; upstream descreve dias desde cadastro/login/post, contagem de posts/logins, status online/offline, IP e integração Views/Rules | Referência de inventário para futuras estatísticas de participação, não dependência candidata: somente código Drupal 7, não coberto pela política de avisos de segurança do Drupal.org, com campos sensíveis como IP/último login. Estatísticas do ACULTA precisam de escopo por contexto, consentimento/política de privacidade, access e cache; nunca importar esses campos indiscriminadamente ao cartão. |

### Critérios antes de portar código contrib

1. Confirmar versão no lockfile e inspecionar API instalada, testes, dependências,
   manutenção e licença.
2. Preferir configurar/reutilizar Core e contrib; adaptar código só diante de
   lacuna concreta do contrato.
3. Isolar dependência em adapter e cobrir access, cache, instalação e remoção.
4. Registrar origem, API/arquivo, licença e transformação na documentação da
   feature; apresentação permanece no ACULTA420.
5. Rejeitar padrões incompatíveis com Drupal 11+ ACULTA: service locator, hooks
   migráveis procedurais, acesso sem `accessCheck()`, SQL de domínio e consultas
   sem isolamento de Domain/contexto.

Nenhum código contrib foi copiado nesta auditoria. O código executável do Forum
contrib e os templates do ACULTA420 foram inspecionados para mapear efeitos e
consumidores; alterações de remoção aguardam o procedimento seguro documentado.
Novos candidatos foram conferidos nas páginas/releases oficiais, não adicionados
ao Composer. Forum Access é fonte de cenários de autorização; Comment Notify,
de preferências de acompanhamento; Achievements/Gamification, de marcos e limites.
Os respectivos fontes e testes não foram portados: qualquer adaptação fica
condicionada à inspeção da versão exata e aos contratos `aculta_messenger`,
`aculta_game`, Domain/Group e acesso por contexto. O projeto Comment Moderation AI
foi rejeitado como dependência, inclusive por não estar sob a política de
security advisories e introduzir processamento externo.

## Advanced Forum histórico

Advanced Forum 7.x-2.8 é uma referência Drupal 7 histórica que extendia Core
Forum, Views/CTools, Node/Comment e Author Pane. A página do projeto lista
dependências e alerta que linha moderna está sem stable suportada. A feature
inventory upstream cita: overview com busca, marcar tudo lido, ícones e novos
posts; subfóruns/containers, contagens e última atividade; View de tópicos,
fixados, pager, popularidade/views e legends; thread com autor, número/permalink,
respostas encadeadas, next/previous, preview, citações/ações; Views de ativos,
novos, próprios e sem resposta; estatísticas; seis styles (Naked, Blue Lagoon,
Boxy e versões stacked). A lista completa de portabilidade e destino está em
[`ADVANCED-FORUM-PORT.md`](ADVANCED-FORUM-PORT.md).

O pacote `advanced_forum-7.x-2.8.tar.gz` foi inspecionado sem instalação ou
execução (SHA-256 `e6ab783a0562830363370c269c41c3af81e77daf80fe1cd6533c0f29a9a46edd`).
Os arquivos `styles/naked/advanced-forum.naked.author-pane.tpl.php`,
`styles/naked/advanced-forum.naked.post.tpl.php`, `includes/mark-read.inc`,
`includes/views/advanced_forum_*.view` e `advanced_forum.module` confirmam que o
Author Pane histórico podia exibir nome, avatar, online/last seen, título,
badges, data de entrada, total de posts, pontos de gamificação, contato,
mensagem privada, relações e amizade; o template ainda tinha seção administrativa
com IP e Fasttoggle. O post tratava first-new, reply-to, unpublished, permalink,
edição, assinatura, ações e jump-to-top. A ação de marcar lido escrevia
diretamente na tabela `history` do D7. Esses detalhes informam o inventário e as
exclusões de privacidade; IP/controles administrativos e pontos de terceiros não
são campos de cartão público.

Essas capacidades são analisadas como requisitos, não código fonte para executar.
Não portamos PHPTemplate, procedural hooks, configuração de D7, estilos/imagens
históricas nem APIs obsoletas. Variantes de estilo são recompostas com SDC,
Bootstrap, tokens, light/dark e acessibilidade do projeto.

## Melhores práticas/contratos Drupal atuais consultados

| Fonte primária | Evidência consultada | Consequência |
| --- | --- | --- |
| [Comment: administrar comentários em entidades](https://www.drupal.org/docs/8/core/modules/comment/administering-a-content-types-comment-settings) | Comments podem se vincular a entity types por field; field storage é configurado por entidade hospedeira | O candidato comum usa Nodes em todos os contexts, mas storage/bundles devem passar testes Kernel. |
| [Comment API 11.x](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/interface/CommentInterface/11.x) | Comment é entidade de conteúdo do Core, com owner/translation/revision; respeitar contratos nativos | Usar Comment, não uma resposta custom. |
| [Views / Core](https://www.drupal.org/docs/8/core/modules/views) | Views fornece listas configuráveis com query, filtros, sorts e displays | Usar Views, mantendo access/domain/context filtros imutáveis e revisados. |
| [Rotas Drupal](https://www.drupal.org/docs/drupal-apis/routing-system/structure-of-routes) e [DefaultHtmlRouteProvider 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Routing%21DefaultHtmlRouteProvider.php/function/DefaultHtmlRouteProvider%3A%3AgetAddFormRoute/11) | `_entity_form` usa handler de formulário da entidade e `_entity_create_access` usa formato `entity_type:{bundle_parameter}`; confirmado no Core instalado | `/criar-topico` delega formulário e autorização ao Node/Core; não duplica Form API nem concede acesso por papel. |
| [Cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays) e [cache contexts](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-contexts) | Access e cacheability são parte do resultado renderizado; resultados precisam tags/contexts/max-age corretos | Cartões, comentários, aggregates e contexto privado carregam metadados de cache. |
| [Group docs](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/group) | Group gerencia collections de conteúdo/usuários e acesso com membership/roles de grupo | LMS revalida membership/Group access real, sem role global estudante. |
| [Group project/version matrix](https://www.drupal.org/project/group) | Em 2026 releases 3.x suportam Drupal 11; 4.x alpha exige 11.4+ e muda linha de compatibilidade | Confirmar versão instalada/API; não migrar Group ou habilitar adapter sem decisão. |
| [Drupal 11+ standards local](../../../../../../../docs/portal/DRUPAL-11-STANDARDS.md) | DI, `#[Hook]` quando suportado, EntityQuery access explícito, render/access/cache | Normativo do projeto e aplicável a toda fase funcional. |

Pesquisa obrigatória em Drupal.org e Core instalado será repetida na fase de
implementação. A documentação pública não prova a configuração particular do
ACULTA nem substitui inspeção de `web/core`, contrib e testes.

## Referências Advanced Forum

- [Projeto Advanced Forum](https://www.drupal.org/project/advanced_forum): propósito, linha 7.x e dependências históricas.
- [Feature inventory do projeto](https://www.drupal.org/node/841056): funções de listagens, categorias, thread, views e Author Pane.
- [Styling your forum](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-archive/contrib-modules-for-building-the-site-functionality/social-networking-and-collaboration/community-building-and-social-networking-61): seis combinações de estilo e templates históricos.
- [Releases](https://www.drupal.org/project/advanced_forum/releases): 7.x-2.8 é security update/unsupported; ramo 8.x-1.x é desenvolvimento, não baseline para dependência.
- [Pacote-fonte 7.x-2.8](https://ftp.drupal.org/files/projects/advanced_forum-7.x-2.8.tar.gz): snapshot inspecionado para Author Pane, post template, Views e leitura; SHA-256 registrado acima.

## Decisões ainda sujeitas a prova técnica

- Se um único bundle Comment e `field_aculta_discussion` servem todos os Nodes/contextos sem efeitos colaterais em admin, access, history e moderation.
- Como combinar Group access e node access em Views/Search API, com isolamento observável nos casos A/B.
- Mecanismo de leitura/não lidos: avaliar History e entidade/source local; ainda não há decisão de novo storage.
- Modelo de contagens/visualizações e necessidade de agregação persistida/cache.
- Relevância e risco de assinatura, online, shadow topic, views count e next/previous.
- Dependências exatas de Comment fields e uninstall validators na versão instalada.

Cada item deve ser resolvido com fonte upstream e teste no Core/contrib instalado,
sem afirmar que não confirmado. A fase dev.1 registra pendências em vez de
codificar hipótese.
