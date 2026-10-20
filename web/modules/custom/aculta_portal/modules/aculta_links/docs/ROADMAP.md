# Roadmap — ACULTA Links

Seguir o ciclo Dev → RC → Alpha e a autorização de escopo do responsável. Cada
fase fecha com commits atômicos, verificações, changelog e índice de estado.

## 0.1.0 — fundação e auditoria

Inventário local, pesquisa de referências e licenças, matriz, integração,
segurança e modelo proposto. Sem remover conteúdo ou alterar Runtime. Esta
entrega conclui documentação/fundação; nenhum código upstream foi copiado.

## 0.2.0 — migração preservadora e entidades base (em andamento)

1. Transferir formulário, configuração, presenters e hooks da landing para
   `aculta_links`, mantendo URL, conteúdo, canais e botões.
2. Atualizar integração neutra Portal–tema e rota administrativa, com paridade e
   rollback demonstrados antes de corte.
3. As três entidades base estão iniciadas (`aculta_link`, `aculta_link_page`,
   `aculta_link_item`) com revisões e acesso privado; completar formulários e
   listagem com filtros de proprietário, publicação e histórico para as três entidades.
4. Fechar a revisão arquivo a arquivo do Linky e a licença por arquivo do
   LinkStack antes de copiar qualquer código/asset.
5. Testar access, revisão, slug, cache, Domain, reversão e configuração; papéis
   não recebem permissões automaticamente.

## 0.3.0 — dashboard e editor

Dashboard, CRUD básico, composição ordenável por tabela Drupal, publicação individual e prévia privada estão implementados. Ainda faltam validação visual em tamanhos variados e navegação fluida entre páginas. Uma conta pode ter várias páginas; sem equipes complexas inicialmente.

## 0.4.0 — interface pública e presets

Rota pública inicial, integração ao SDC existente e prévia privada sem cache compartilhado em `/pagina/{slug}/previa` implementadas em 0.4.0-dev.3; faltam presets governados, SEO, controles de aparência, revisão visual/acessível e migração preservadora da landing antiga antes de RC.

## 0.5.0 — métricas e ferramentas

Desenhar eventos minimizados, filas/agregação, retenção, QR, redirects curtos e
agendamento; revisar LGPD, Flood e open redirect.

## 0.6.0 — integração do ecossistema

Integrações explícitas com `aculta_user`, Domain, conteúdo referenciável, cursos,
eventos, Commerce, campanhas e mídia. Cada conteúdo permanece sob seu módulo.

## 0.7.0 — administração e hardening

Administração global, moderação, limites, observabilidade, cache e proteção contra
abuso, com configuração ambiental no aculta_deployer.

## 1.0.0 — revisão e lançamento

Auditar acesso, testes, Domains, a11y, desempenho, atualizações, licenças e
rollback. Seguir gates Dev → RC → Alpha; sem deploy, tag, PR ou merge automático.
