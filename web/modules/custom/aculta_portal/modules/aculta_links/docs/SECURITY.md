# Segurança e privacidade

## Implementado na 0.2.0-dev.1

- `aculta_link` não oferece visualização pública da própria entidade.
- `aculta_link_page` e `aculta_link_item` também permanecem privados; a página
  nasce despublicada.
- Acesso à entidade exige `administer aculta links` ou, para a pessoa
  proprietária, `manage own aculta links`; a criação exige permissão explícita.
- Acesso de item revalida a propriedade tanto da micropágina quanto do destino
  referenciado, adicionando ambos como dependências cacheáveis.
- O handler inclui contextos de cache de usuário/permissões e dependência da
  entidade no resultado.
- O dashboard limita a EntityQuery pela conta quando não é administração,
  declara `accessCheck(TRUE)` e revalida `update` por entidade antes de montar
  cada linha. As listagens variam por usuário/permissões e invalidam pelo tag de
  lista da entidade.

## Controles obrigatórios antes de publicar páginas

- Entity Access por proprietário em rotas, formulários, Views e ações; CSRF e
  Form API para mutações.
- Validar URLs externas com parsing estrito; rejeitar protocolos inseguros,
  credenciais, portas inesperadas e formatos ambíguos. Links com nova aba usam
  `noopener noreferrer`.
- Não buscar URLs remotas para favicon, preview ou metadados agora. Futuro fetch
  requer mitigação SSRF, limite de tamanho/tempo e bloqueio de rede privada,
  inclusive após redirects.
- Escapar texto; conteúdo rico só por formatos/filtros Drupal permitidos. Nunca
  renderizar HTML arbitrário de tema.
- Links curtos resolvem códigos publicados e destinos aprovados, evitando open
  redirect; aplicar Flood/rate limit em criação e cliques.
- Analytics minimiza dados e define retenção/expurgo. Não persistir IP, user
  agent completo, fingerprint ou identificador transversal por padrão; definir
  LGPD e consentimento antes de rastrear.
- Cache público varia/invalida por Domain, idioma, path e entidades; conteúdo
  privado não é cacheado publicamente.
- Imagens passam pelo `aculta_image`, `ImageCatalog` e `UploadPolicy`, sem
  processamento ou limites paralelos.

Esta lista estabelece requisitos futuros, não certifica segurança já testada.

## Prévia privada 0.4.0-dev.3

`/pagina/{slug}/previa` usa o purpose `links`, resolve o slug com
`accessCheck(TRUE)` e exige acesso de atualização da própria página antes de
renderizar rascunhos, links e itens inativos. A resposta declara `max-age: 0`
para não compartilhar renderização privada. O endpoint retorna 404 sem acesso;
o teste Kernel cobre a consulta do proprietário e o limite de cache. Inspeção
visual autenticada no navegador permanece pendente.


## Painel 0.2.0-dev.2

A consulta administrativa declara `accessCheck(TRUE)`, aplica condição de proprietário para contas comuns e revalida permissão de edição por entidade antes de criar operações. Cache da tabela varia por usuário/permissões e query de paginação, inclui tags de lista e dependências das entidades e resultados de acesso. Itens têm proprietário explícito além de page/link references; o acesso exige coincidência de propriedade nos três registros. Nenhuma rota pública ou redirecionamento curto foi criado.


Editor de composição: a rota e a submissão revalidam access da página; a submissão também confirma que cada item pertence à página editada antes de salvar nova revisão. Query declara `accessCheck(TRUE)`.


## Publicação 0.4.0-dev.1

Slug tem regex ASCII lowercase e `UniqueField`. A página pública exige status publicado e rota marcada com `_aculta_domain_purpose: links`; o Domain subscriber do Portal aplica 404 em purpose incorreto. A consulta usa `accessCheck(TRUE)` e ainda exige entidade publicada. O handler de item só permite view público quando item, página e destino estão publicados. O campo Link tem constraint própria: HTTPS sem credenciais (443 ou porta omitida), URI `internal:/` sem authority/path network-relative, ou `entity:` limitado a entity type/id simples. Links externos abrem com `noopener noreferrer`. Cache inclui contexto de host, idioma, usuário/permissões e tags de listas/entidades/access.
