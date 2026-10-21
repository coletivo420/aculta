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
  entidade no resultado; composição pública exige propriedade coincidente.
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
- `/redirecionar/{codigo}` resolve apenas um destino publicado, revalida Entity
  Access e executa a constraint de URL antes de emitir `TrustedRedirectResponse`
  302. Códigos têm 12 caracteres aleatórios e não aceitam destino na requisição;
  GETs válidos entram em fila para agregação, HEAD não conta. Monitorar abuso e definir rate limiting se
  tráfego/anomalias indicarem necessidade, sem limitar visitas legítimas por IP.
- A auditoria de 2026-10-20 registrou `GHSA-mg7g-g2wv-6mhr` para
  `UserController::saveLink` do LinkStack até v4.8.6. Não portar esse fluxo ou
  sua autorização; revalidar Entity Access e ownership no Drupal.
- Analytics está desativado por padrão. Após validação legal e ativação
  administrativa em `/painel-administrativo/aculta/links/configuracoes`, registra
  somente GET válido de redirect curto numa fila Drupal; o
  worker soma contadores por destino e data UTC. Agregados expiram após 90 dias
  no cron; fila/backlog permanece sem identificadores de visitantes e eventos
  antigos são descartados quando processados. Não armazenar IP, UID visitante,
  user-agent, referrer, fingerprint ou cookie. Contagens são aproximadas e não
  identificam pessoas únicas. Base legal deve ser validada no inventário LGPD
  antes de habilitar a coleta no Runtime de produção.
- QR Code é calculado localmente como SVG a partir somente da URL canônica
  HTTPS de página publicada. O endpoint não aceita URL da requisição, não busca
  rede, não persiste arquivo e não registra visualizações ou scans.
- O QR de link curto aplica as mesmas garantias ao código do destino: somente
entidades publicadas e válidas geram uma URL canônica de `DomainPurposeManager`;
  arquivo SVG é gerado em memória e não cria telemetria.
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

## Destinos de conteúdo 0.4.0-dev.18

URIs `entity:node/{id}` carregam o conteúdo via Entity Storage. Na página pública
e no redirect, o resolver exige publicação e `view` como anônimo no Domain de
origem; owners autenticados não ampliam o conteúdo exposto. O resultado de
acesso e os dados do nó/Domain entram no cache. Tipos entity diferentes de node
e caminhos `internal:/` ainda não são redirecionados ao purpose de conteúdo.

## Integridade de propriedade 0.4.0-dev.17

Validação de entidade exige que item, micropágina e destino tenham a mesma
conta proprietária. Entity Access aplica a mesma condição antes de permitir
visualização pública; dados inconsistentes existentes não ficam expostos mesmo
que os três registros estejam publicados. A cobertura Kernel verifica os dois
caminhos.


## Painel 0.2.0-dev.2

A consulta administrativa declara `accessCheck(TRUE)`, aplica condição de proprietário para contas comuns e revalida permissão de edição por entidade antes de criar operações. Cache da tabela varia por usuário/permissões e query de paginação, inclui tags de lista e dependências das entidades e resultados de acesso. Itens têm proprietário explícito além de page/link references; o acesso exige coincidência de propriedade nos três registros. Nenhuma rota pública ou redirecionamento curto foi criado.


Editor de composição: a rota e a submissão revalidam access da página; a submissão também confirma que cada item pertence à página editada antes de salvar nova revisão. Query declara `accessCheck(TRUE)`.


## Publicação 0.4.0-dev.1

Slug tem regex ASCII lowercase e `UniqueField`. A página pública exige status publicado e rota marcada com `_aculta_domain_purpose: links`; o Domain subscriber do Portal aplica 404 em purpose incorreto. A consulta usa `accessCheck(TRUE)` e ainda exige entidade publicada. O handler de item só permite view público quando item, página e destino estão publicados. O campo Link tem constraint própria: HTTPS sem credenciais (443 ou porta omitida), URI `internal:/` sem authority/path network-relative, ou `entity:` limitado a entity type/id simples. Links externos abrem com `noopener noreferrer`. Cache inclui contexto de host, idioma, usuário/permissões e tags de listas/entidades/access.
