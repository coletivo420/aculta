# author-card

Cartão visual para a identidade já autorizada pelo `aculta_user`.
`PagePresentationHooks` encaminha o contrato neutro do módulo diretamente ao
SDC e conserva os render arrays de avatar e ações nos slots. O mesmo hook
adapta `profile-identity` do Studio à variante `profile_full`.
O componente não carrega usuário, Profile, foto nem decide visibilidade; nunca
gera iniciais.

Story: `author-card.default.story.yml`.
