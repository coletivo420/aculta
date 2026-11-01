# Responsabilidade pelos dados

| Capacidade | Fonte de verdade | Portal |
| --- | --- | --- |
| Login, senha, e-mail | Drupal User | UX e rotas |
| Perfil | Profile | composição |
| Endereço | Address/Profile customer | integração |
| CEP | CEP Autocomplete + Address | UX/acessibilidade |
| OAuth | Social Auth | conexões |
| Avatar (catálogo, escolha, fallback) | `aculta_user` (`AvatarPool`, `AvatarResolver`) + `aculta_image` (estilos) | apresentação |
| Identidade de autoria e cartões | `aculta_user` (`AuthorIdentityResolver`, `AuthorCardBuilder`) | composição |
| Pedidos/pagamentos | Commerce | apresentação |
| Apoio | Commerce Donation Flow | experiência |
| Loja | Commerce Product/Order/Payment | integração |
| Cursos/progresso | Drupal LMS | apresentação |
| Matrículas | Group | apresentação |
| Wiki | Node/Taxonomy/Revisions/Views | integração |
| Revista/Observatório | Node/Taxonomy/Media/Views | integração |
| Fórum | Forum + Node + Comment + Taxonomy | integração |
| Busca futura | Search API + Views | composição |
| Favoritos/seguir futuros | Flag | controles/resumos |
| Notificação de comentários futura | Comment Notify | preferências/links |
| Domínio | Domain | purpose/URLs/acesso |
| Analytics | Google Analytics | eventos aprovados sem PII |
| Search ownership | Google Search Console | sitemap/verificação |
| Classroom futuro | Google Classroom | adapter; LMS continua canônico |

Antes de criar storage customizado, identificar a API e a fonte de verdade
existente. Se o conceito já pertence a um subsistema adotado, não duplicá-lo.

A matriz detalhada fica em
[`docs/portal/SOURCE-OF-TRUTH.md`](../portal/SOURCE-OF-TRUTH.md).
