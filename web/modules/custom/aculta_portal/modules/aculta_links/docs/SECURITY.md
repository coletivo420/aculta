# Segurança e privacidade

Controles a implementar antes de publicar a edição de páginas:

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
