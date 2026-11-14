# author-card

Cartão visual para a identidade já autorizada pelo `aculta_user`.
`PagePresentationHooks` encaminha o contrato neutro do módulo diretamente ao
SDC e conserva os render arrays de avatar e ações nos slots. O mesmo hook
adapta `profile-identity` do Studio à variante `profile_full`.
O componente não carrega usuário, Profile, foto nem decide visibilidade; nunca
gera iniciais.

Stories: `author-card.default.story.yml` e `author-card.forum-reactions.story.yml`.
Nas variantes de fórum e perfil completo, as contagens positivas de reações
recebidas e dadas aparecem em uma lista acessível com rótulos para leitores de
tela.
