# Contrato da API de Author Cards

API evoluída em `0.4.0-dev.27`, após avaliar `PublicProfileController`,
`PublicProfilePolicy`, `PublicProfileLink`, `LegacyAuthorManager`,
`AccountBioCard`, `SocialLinksCard` e os contratos neutros do Portal. O builder
prepara autoria e render array cacheável; cada ação continua dependendo de
policy e consumidor próprios.

## Entrada

- sujeito tipado: `UserInterface` ou `LegacyAuthorInterface`/referência legada;
- chave de variante User aceita por `AuthorIdentityResolver::USER_VARIANTS`;
- usuário Drupal com perfil principal; variantes comuns usam o apelido, enquanto
  `author_editorial` pode usar o nome de exibição da conta para manter atribuição;
- a variante identifica o consumidor, mas não amplia os dados retornados.

Não aceitar arrays arbitrários de dados privados fornecidos pelo chamador. Um
contexto identifica apresentação e policy; não é concessão de acesso.

## Saída

- `AuthorIdentity`, um view-model neutro que implementa `CacheableDependencyInterface`;
- nome público de exibição e URL canônica opcional em string, gerada por `PublicProfileLink`;
- `member_since`, apenas como ano da conta quando o perfil inteiro é público;
- somente em `author_editorial`, bio e redes sociais filtradas pelo opt-in do
  perfil público; endereço social só é entregue para host e esquema HTTP(S) reconhecidos;
- tags e contextos herdados da conta e do perfil principal.

O resultado não contém e-mail privado, endereço, documentos, relações LMS,
campos ocultos, ID interno, entidade Drupal ou serviço. Pode conter um render
array de avatar emitido por `aculta_image`. A visibilidade da foto passa por
`PublicProfilePolicy`: opt-in em perfil público; foto e apelido no perfil
privado conforme a política existente. Cache tags e contexts são aplicados ao
render array. Dados dependentes de access devem acrescentar a metadata real do
resultado de access antes de serem usados.

## Serviços conceituais

- `AuthorIdentityResolver` (`aculta_user.author_identity_resolver`): resolve User,
  perfil principal, apelido e URL pública opcional.
- `PublicMemberIdentityInterface` (`Drupal\aculta_user\Identity\PublicMemberIdentityInterface`): expõe só o apelido permitido pela política pública, ou `NULL` sem identidade. Consumidores não devem usar o nome interno da conta como fallback público.
- `AuthorIdentity`: contrato escalar cacheável de saída, sem regra de negócio.
- `AuthorCardBuilder` (`aculta_user.author_card_builder`): cria o render array
  neutro `aculta_user_author_card` para o contrato de apresentação do tema.
- `AuthorCardVariantResolver` (`aculta_user.author_card_variant_resolver`):
  escolhe a variante contextual para Comment a partir do bundle da entidade
  hospedeira; recusa contextos desconhecidos e não decide access.
- `PublicProfileLinkInterface`: permite compor e testar o link canônico sem
  substituir `PublicProfileLink` como fonte de verdade.
- `PublicProfilePolicy`: continua responsável pela política pública existente.
- `SocialNetworksInterface`: entrega links por rede; `SocialNetworks` preserva
  a constante pública `NETWORKS` e valida host e esquema HTTP(S) também ao ler
  valores já armazenados.

`member_since` não inclui a data ou hora exatas e fica ausente quando o perfil é
privado; o tema só o apresenta em `author_forum`/`author_forum_stacked`. Bio e
redes da variante editorial não são concedidas por `designation`; o tema recebe
somente valores autorizados pelo perfil e a designação fornecida pelo Magazine. A
entidade Account é cache dependency e a formatação anual usa UTC, sem variação
por fuso horário.

`author_growroom_historico` não passa por este resolver: `LegacyAuthor` não é
User e não ganha login, perfil, data de conta ou ações. `AuthorCardBuilder::buildLegacy()`
é o consumidor dedicado, apresenta o nome e a origem do acervo e mantém cache da
entidade histórica.
Nenhum service locator novo foi introduzido. Referências Drupal 11+: [DI](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal), [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays) e [CacheableDependencyInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/11.x).
