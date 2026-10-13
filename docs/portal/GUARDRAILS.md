# Guardrails do Portal

Regras que bloqueiam regressão no `aculta_portal`. São normativas: quem altera o Portal deve
cumpri-las, e o gate ou a revisão deve checá-las. Não são roadmap nem relatório.

Documentos normativos relacionados: [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md),
[SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md), [ARCHITECTURE.md](ARCHITECTURE.md),
[ADMIN-DOMAIN-POLICY.md](ADMIN-DOMAIN-POLICY.md),
[CROSS-DOMAIN-REQUEST-POLICY.md](CROSS-DOMAIN-REQUEST-POLICY.md),
[PAYMENT-DOMAIN-POLICY.md](PAYMENT-DOMAIN-POLICY.md),
[DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md),
[AJAX.md](AJAX.md), [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md),
[AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) e
[FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md).

## Fronteiras gerais

- O Portal integra capacidades existentes. Não substitui storage, regra de negócio ou
  decisão de access de Core ou contrib.
- Não criar storage paralelo quando o Core ou o contrib já tiver a capacidade.
- Não usar service locator (`\Drupal::`) em código novo de `src/`.
- Não hardcodar hostname ou domínio em controllers, rotas, templates ou config de tema.
- Decisão por purpose vem de `DomainPurposeManager` e do Domain, nunca de hostname.
- O tema recebe contexto neutro de apresentação. Não recebe entidade `Domain`, storage nem
  decisão de access.
- Não criar CMS, catálogo, carrinho, pedido, pagamento ou LMS paralelo a Core ou contrib.

## Fórum (`forum`)

- Não criar entidade Topic, Reply, tabela de fórum ou tabela de comentários. Usar Forum,
  Node e Comment nativos.
- Não criar `ForumController` só para reconstruir a listagem nativa. Mapear rotas nativas antes
  de escrever subscriber.
- Acesso direto a um tópico em host incorreto é bloqueado pela política multidomínio.
- Acompanhar tópico e favoritos usam Flag, não storage custom.
- Moderação reutiliza permissões, Comment, Node, Views e ferramentas administrativas. O Portal
  Admin pode ser hub de acesso e resumo, mas não um segundo CRUD de moderação.
- Conta agrega participação sem duplicar comentários.

## Revista (`magazine`)

- O Portal não cria CMS editorial paralelo. Não recriar formulários de Node ou Taxonomy.
- Conteúdo editorial preserva revisões, workflow e propriedades do Node.
- Plugins de schema custom do Portal são dívida auditável: só ficam quando o upstream instalado
  não cobre a propriedade com a mesma semântica.
- Analytics e Search Console são integrações de plataforma, não lógica editorial. Search Console
  observa URLs canônicas e sitemap reais.

## Loja (`shop`)

- O Portal não cria catálogo, carrinho, pedido ou pagamento paralelo.
- Não transformar Donation Order Items em produtos de loja só para reaproveitar UI.
- Não transformar compra de produto em "apoio" para contornar a modelagem Commerce.
- Não hardcodar `loja.aculta.org` em controllers. Usar `DomainPurposeManager`.
- Não adicionar eventos de mensuração (`purchase`, `add_to_cart`) antes de existir o fluxo Commerce
  correspondente e um plano de mensuração revisado.
- Antes de ativar catálogo: finalidade, política comercial, Product Types, checkout, gateway,
  LGPD, testes em MariaDB, isolamento de Domain e exportação de configuração. Nenhuma dessas
  decisões é tomada sem especificação.

## Wiki (`wiki`)

- O Portal não cria entidade, tabela ou índice próprio para espelhar verbetes.
- Dashboards e listagens consultam entidades e revisões reais. Não criar contador persistido no
  Portal só para dashboard.
- Conteúdo unpublished ou restrito não vaza metadata em cards, busca ou listagem.
- A Wiki não recria CRUD editorial.

## Minha Conta

- Semântica de apresentação: entity metadata → componente → decisão de access. Nunca o caminho
  inverso, e nenhum componente decide access.
- Conteúdo não publicado (curso, verbete, pedido Commerce) não aparece por apresentação, mesmo
  quando o componente só mostra metadata.
- Extração de componentes não altera OAuth, não substitui Form API, não duplica Social Auth,
  Email Confirmer, LMS ou Commerce, e não cria dependência obrigatória de SDC ainda inexistente.
- Nenhuma regra funcional migra para o tema.
- Substituição de fluxo acontece por fluxo, nunca com remoção total antecipada.

## AJAX e formulários

- O Portal não mantém um segundo framework de AJAX. Usa Drupal AJAX, Views AJAX ou Form API.
- Toda interação assíncrona tem fallback de página inteira.
- Um SDC pode apresentar a moldura visual de uma seção, mas não recria Form API nem decide
  submissão. Upload e crop continuam no widget contrib.

## Como verificar

- Gate Drupal 11+: `php scripts/validate-aculta-portal-drupal11.php`.
- Revisão de PR: checar cada regra que o diff toca. Regra violada é finding, não nota.
- Regressão: comportamentos já aceitos são invariantes em `docs/portal/ANTI-REGRESSAO.md`; diff que toca um deles precisa do caso executado.
