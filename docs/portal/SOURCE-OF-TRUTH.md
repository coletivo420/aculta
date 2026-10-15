# Fontes de verdade

O Portal agrega experiências; ele não deve se tornar dono acidental dos dados
dos módulos que integra.

## Ownership de autenticação

| Concern | Owner |
| --- | --- |
| Authentication account | Drupal User |
| OAuth provider | Social Auth contrib |
| OAuth integration/policy | `aculta_portal` |
| CAPTCHA engine | CAPTCHA/Turnstile contrib |
| CAPTCHA policy | `aculta_portal` |
| Secrets | Drupal Key + environment/infrastructure |
| Auth visual presentation | ACULTA420 |

| Capacidade | Fonte de verdade | Implementação | Papel do Portal | Purpose | Storage próprio do Portal |
| --- | --- | --- | --- | --- | --- |
| Authentication account | Drupal User | Core User | UX, rotas, sessão, senha e status | ACCOUNT | Não |
| Dados pessoais | Profile | Profile | formulário integrado | ACCOUNT | Não |
| Endereço | Profile customer + Address | Profile + Address | central de dados e UX | ACCOUNT | Não |
| CEP | Address preenchido via ViaCEP | CEP Autocomplete | acessibilidade e integração | ACCOUNT | Não |
| Foto | User/file/image/crop | Core + Crop/Image Widget Crop | editor integrado | ACCOUNT | Não |
| E-mail | Drupal User | Change Mail + Email Confirmer | fluxo integrado | ACCOUNT | Não |
| OAuth provider | vínculos/contas OAuth | Social Auth contrib | API upstream | ACCOUNT | Não |
| OAuth integration/policy | configuração/provider upstream | aculta_portal + Social Auth APIs | disponibilidade, destinos, Domain, conexões e desconexão | ACCOUNT | Não |
| CAPTCHA engine | challenge e validação | CAPTCHA/Turnstile contrib | implementação upstream | formulários públicos | Não |
| CAPTCHA policy | formulários e grupos protegidos | aculta_portal + configuração Drupal | política anônimo/autenticado, exclusões e failure behavior | públicos | Não |
| Secrets | credenciais externas | Drupal Key + environment | fornece referências e Config Overrides | integrações | Não |
| Auth visual presentation | markup/layout neutro | ACULTA420 | aparência de formulários e providers | ACCOUNT | Não |
| Apoio | Commerce Order/Item | Commerce + Donation Flow | resumo e entrada do fluxo | SUPPORT/ACCOUNT | Não |
| Pagamento | Commerce Payment | Commerce + gateway | status/segurança | SUPPORT/ACCOUNT | Não |
| Cursos | Group LMS Course | Drupal LMS | catálogo/resumo | COURSES/ACCOUNT | Não |
| Matrícula | Group membership | Group | leitura/apresentação | COURSES/ACCOUNT | Não |
| Progresso | LMS Course Status | Drupal LMS | leitura/apresentação | COURSES/ACCOUNT | Não |
| Wiki | Node wiki_entry | Core Node | landing/participação | WIKI/ACCOUNT | Não |
| Categorias Wiki | Taxonomy | Core Taxonomy | navegação | WIKI | Não |
| Revisões Wiki | Node revisions/Diff | Core + Diff | atividade/links | WIKI/ACCOUNT | Não |
| Fórum | Forum topic Node | Forum + Node | integração/domain/painel | FORUM/ACCOUNT | Não |
| Respostas Fórum | Comment | Core Comment | participação/moderação | FORUM/ACCOUNT | Não |
| Estrutura Fórum | Taxonomy | Forum + Taxonomy | navegação/domain | FORUM | Não |
| Busca | índice Search API | Search API + Views | composição/domain | WIKI/FORUM | Não |
| Favoritos/seguir | Flag entities | Flag | controles/resumos | ACCOUNT/FORUM | Não |
| Notificação de comentários | Comment Notify | Comment Notify | preferências/links | ACCOUNT/FORUM | Não |
| Revista/editorial | Nodes/Taxonomy/Media | Core + Views | integração de links/SEO | MAGAZINE | Não |
| Loja | Commerce Product/Order/Payment | Commerce | integração/domain/conta | SHOP/ACCOUNT | Não |
| Analytics | Google Analytics | Google Tag/GA4 | eventos aprovados, sem PII | públicos | Não |
| Search ownership | Search Console | Google Search Console | sitemap/verificação | públicos | Não |
| Classroom externo | Google Classroom | Classroom API | adapter futuro; LMS segue canônico | COURSES/ACCOUNT | Não |
| Host/contexto | Domain entities | Domain suite | purpose/URLs/isolation | todos | Não |
| Links sociais e botões públicos | `aculta_portal.social_links` | Config API + formulário administrativo do Portal | Instagram, YouTube e Facebook por URL HTTPS validada; “Visite nosso Site” fixo mais botões adicionais com texto e URL/caminho interno validados | LINKS | Não |
| Projetos na página LINKS | Nodes `project` | View `aculta_projects`, teasers Core e estilo VVJB | projetos publicados com acesso respeitado e URL no purpose MAIN | LINKS | Não |

## Regra de gravação

### Projetos aprovados para evolução — 2026-10-15

O [ACULTA Fórum/Growroom420](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md)
integra os mesmos Forum/Node/Comment/Taxonomy. A marca e o prefixo público mudam
para `growroom420` na fase operacional, preservando o purpose `forum`. Autores
históricos mantêm Users individuais bloqueados; o destino público de todos é
o memorial coletivo `/u/lendashistoricas`, sem páginas individuais.

O [ACULTA Game](../../web/modules/custom/aculta_portal/modules/aculta_game/README.md)
tem como fonte inicial de concessões a entidade existente `aculta_badge_award`,
provida pelo `aculta_user`; `BadgeCatalog` e `BadgeService` permanecem nesse módulo.
Os gatilhos `BadgeEvaluationHooks` pertencem ao `aculta_game` desde G1.
Não há um segundo backend de badges. Sua evolução e
eventual mudança de ownership exigem transição própria. A decisão atual é
conquistas públicas por padrão com ocultação e destaques, sem ranking ou
benefícios financeiros; evidências privadas continuam sujeitas a acesso.

Desde G3, escolhas de exibição pertencem ao User Data de `aculta_user`, chave
`game_achievement_display`. O helper `aculta_game.achievement_presentation`
entrega conquistas ao perfil público e à própria conta, com cache e privacidade;
não inclui dados de pedidos, cursos ou avaliadores. Nenhuma concessão é duplicada
para representar destaque ou ocultação. Caixas adicionais seguem o roadmap.

Game G4–G5.2 oferece missões unitárias, administração e avisos de conquista.
Definições/critério:
Config API de `aculta_user.achievement_definitions`; fatos: `aculta_badge_award`,
com motivos administrativos privados, ator e datas. Game controla UX e eventos;
User preserva definições/concessões/privacidade sem dependência inversa. Pedidos
e itens `donation` continuam no Commerce. Motivos não atravessam a projeção pública.

Fórum está em documentação; Game tem verificação funcional de G3–G5.2,
com concorrência e jornadas completas ainda pendentes. A tabela de capacidades existentes
não significa que seus roadmaps estejam implementados ou homologados.

### Princípio

Se uma feature precisa gravar estado, gravar através da API da fonte de verdade.

Exemplos:

- endereço -> Profile/Address;
- tópico -> Forum/Node;
- resposta -> Comment;
- matrícula -> Group/LMS;
- apoio -> Commerce;
- follow/bookmark -> Flag.

Nunca criar uma tabela `aculta_portal_*` apenas para espelhar esses dados.

## Regra de leitura

Preferência:

1. serviço público do módulo;
2. Entity API;
3. Views;
4. plugin/API específica do módulo;
5. Database API apenas quando não existir API pública adequada.

Consultas diretas a tabelas contrib exigem justificativa documental.
