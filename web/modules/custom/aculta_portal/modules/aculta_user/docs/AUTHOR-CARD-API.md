# Contrato da API de Author Cards

API proposta, sem classes runtime nesta fase. Nomes finais devem ser escolhidos
após avaliar `PublicProfileController`, `PublicProfilePolicy`, `PublicProfileLink`,
`LegacyAuthorManager`, `AccountBioCard`, `SocialLinksCard` e os contratos neutros
atuais do Portal.

## Entrada

- sujeito tipado: `UserInterface` ou `LegacyAuthorInterface`/referência legada;
- chave de variante entre a lista documentada;
- contexto funcional opaco e validado (`forum`, `publication`, `lms`, `wiki`);
- operação/ações candidatas; as permissões são revalidadas pelo dono da ação.

Não aceitar arrays arbitrários de dados privados fornecidos pelo chamador. Um
contexto identifica apresentação e policy; não é concessão de acesso.

## Saída

- view-model escalar e render arrays seguros para template;
- URL como objeto/link gerado pelo Portal quando existir;
- avatar como render array produzido por `aculta_image`;
- ações disponíveis já filtradas por acesso, com cache metadata;
- `CacheableMetadata` com dependências User/Profile/LegacyAuthor/File/Policy,
  permissões, amizade, conquistas visíveis e contexto necessário.

O resultado não deve conter email privado, endereço, documentos, relações LMS,
campos ocultos, ID interno quando não necessário, entidade Drupal ou serviço.
Aplicar `CacheableMetadata::createFromObject()` a resultados cacheáveis e incluir
cache contexts para as variações reais. Resultados de access sem metadata
adequada não são seguros para cache público.

## Serviços conceituais

- `AuthorIdentityResolver`: normaliza User versus autoria legada e resolve a URL pública.
- `AuthorCardAccessPolicy`: delega a `PublicProfilePolicy` e access APIs sem duplicar regra.
- `AuthorCardVariantRegistry`: lista/valida variantes suportadas e seus dados.
- `AuthorCardBuilder`: prepara render array/view-model com dependências explícitas.
- `AuthorCardViewModel`: contrato de saída imutável, sem regra de negócio.

Esses nomes são responsabilidades, não obrigação de cinco classes. Evitar
fragmentação; implementar apenas os serviços cujo contrato comum tenha
consumidores reais. Nenhum service locator novo. Referência: padrões Drupal 11+
de [DI](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal) e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).
