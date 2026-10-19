# Contrato da API de Author Cards

API base implementada em `0.4.0-dev.26`, após avaliar `PublicProfileController`,
`PublicProfilePolicy`, `PublicProfileLink`, `LegacyAuthorManager`,
`AccountBioCard`, `SocialLinksCard` e os contratos neutros do Portal. A API
resolve apenas identidade mínima; cada capacidade adicional ainda precisa de
policy, consumidor e teste próprios.

## Entrada

- sujeito tipado: `UserInterface` ou `LegacyAuthorInterface`/referência legada;
- chave de variante User aceita por `AuthorIdentityResolver::USER_VARIANTS`;
- usuário Drupal com perfil principal e apelido; sem apelido não há identidade;
- a variante identifica o consumidor, mas não amplia os dados retornados.

Não aceitar arrays arbitrários de dados privados fornecidos pelo chamador. Um
contexto identifica apresentação e policy; não é concessão de acesso.

## Saída

- `AuthorIdentity`, um view-model escalar que implementa
  `CacheableDependencyInterface`;
- apelido de exibição e URL canônica em string, gerada por `PublicProfileLink`;
- tags e contextos herdados da conta e do perfil principal.

O resultado não contém e-mail privado, endereço, documentos, relações LMS,
campos ocultos, ID interno, entidade Drupal ou serviço. O `AuthorIdentity`
implementa a interface de cache do Core, permitindo que o consumidor incorpore
a metadata ao render array. Dados dependentes de access devem acrescentar a
metadata do resultado de access e seus contexts reais antes de serem usados.

## Serviços conceituais

- `AuthorIdentityResolver` (`aculta_user.author_identity_resolver`): resolve User,
  perfil principal, apelido e URL pública opcional.
- `AuthorIdentity`: contrato escalar cacheável de saída, sem regra de negócio.
- `PublicProfileLinkInterface`: permite compor e testar o link canônico sem
  substituir `PublicProfileLink` como fonte de verdade.
- `PublicProfilePolicy`: continua responsável pela política pública existente.

`author_legacy` não passa por este resolver: `LegacyAuthor` não é User e não
ganha login, perfil ou ações de conta. O `AuthorIdentity` não implementa ainda
cartões renderizados, avatar, ações ou variante editorial completa. Nenhum
service locator novo foi introduzido. Referências Drupal 11+: [DI](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal), [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays) e [CacheableDependencyInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/11.x).
