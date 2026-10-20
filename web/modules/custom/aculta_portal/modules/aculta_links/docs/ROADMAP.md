# Roadmap — ACULTA Links

Seguir o ciclo Dev → RC → Alpha e a autorização de escopo do responsável. Cada
fase fecha com commits atômicos, verificações, changelog e índice de estado.

## 0.1.0 — fundação e auditoria

Inventário local, pesquisa de referências e licenças, matriz, integração,
segurança e modelo proposto. Sem remover conteúdo ou alterar Runtime. Esta
entrega conclui documentação/fundação; nenhum código upstream foi copiado.

## 0.2.0 — migração preservadora e entidades base (em andamento)

1. Landing, formulário, configuração, presenter e hooks específicos pertencem
   ao submódulo; o update `10002` migra sem remover o original e o formulário
   dual-write mantém rollback. A integração usa os contratos atuais de Domain e
   tema.
2. Atualizar integração neutra Portal–tema e rota administrativa, com paridade e
   rollback demonstrados antes de corte.
3. As três entidades base estão iniciadas (`aculta_link`, `aculta_link_page`,
   `aculta_link_item`) com revisões e acesso privado; completar formulários e
   listagem com filtros de proprietário, publicação e histórico para as três entidades.
4. Fechar a revisão arquivo a arquivo do Linky e a licença por arquivo do
   LinkStack antes de copiar qualquer código/asset.
5. Testar access, revisão, slug, cache, Domain, reversão e configuração; papéis
   não recebem permissões automaticamente.

## 0.3.0 — dashboard e editor (em andamento)

Listas paginadas, CRUD Core, operações por entidade, acesso a página pública/preview
e composição ordenável por tabela Drupal estão implementados. Ainda faltam
validação visual completa, navegação fluida entre páginas e editor de identidade
em uma experiência coesa inspirada no LinkStack. Uma conta pode ter várias
páginas; equipes complexas não são prioridade.

## 0.4.0 — interface pública e presets

Rota pública, prévia privada e presets governados `classic`, `soft` e `outline` implementados em 0.4.0-dev.4; em 0.4.0-dev.5 a landing raiz foi restaurada com preservação do Node editorial e fallback dos dados atuais. Faltam SEO, controles adicionais de aparência e revisão visual/acessível antes de RC.

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
