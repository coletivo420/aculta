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


## Painel 0.2.0-dev.2

A consulta administrativa declara `accessCheck(TRUE)`, aplica condição de proprietário para contas comuns e revalida permissão de edição por entidade antes de criar operações. Cache da tabela varia por usuário/permissões e query de paginação, inclui tags de lista e dependências das entidades e resultados de acesso. Itens têm proprietário explícito além de page/link references; o acesso exige coincidência de propriedade nos três registros. Nenhuma rota pública ou redirecionamento curto foi criado.
