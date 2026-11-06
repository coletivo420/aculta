# author-card

Cartão visual para a identidade já autorizada pelo `aculta_user`.
`templates/aculta-user-author-card.html.twig` adapta os consumidores de autoria;
`PagePresentationHooks` adapta `profile-identity` do Studio à variante
`profile_full`. Ambos conservam os render arrays de avatar e ações nos slots.
O componente não carrega usuário, Profile, foto nem decide visibilidade; nunca
gera iniciais.

Story: `author-card.default.story.yml`.
