# Roadmap — ACULTA Links

Seguir o ciclo Dev → RC → Alpha e a autorização de escopo do responsável. Cada
fase fecha com commits atômicos, verificações, changelog e índice de estado.

## 0.1.0 — fundação e auditoria

Inventário local, pesquisa de referências e licenças, matriz, integração,
segurança e modelo proposto. Sem remover conteúdo ou alterar Runtime. Esta
entrega conclui documentação/fundação; nenhum código upstream foi copiado.

## 0.2.0 — migração preservadora e entidades base

1. Transferir formulário, configuração, presenters e hooks da landing para
   `aculta_links`, mantendo URL, conteúdo, canais e botões.
2. Atualizar integração neutra Portal–tema e rota administrativa, com paridade e
   rollback demonstrados antes de corte.
3. Fechar análise de arquivos Linky e licença LinkStack. Implementar modelo
   mínimo de link/micropágina/item somente após provar necessidade perante as
   APIs existentes.
4. Testar access, revisão, slug, cache, Domain, reversão e configuração; papéis
   não recebem permissões automaticamente.

## 0.3.0 — dashboard e editor

Portar navegação e fluxo de dashboard, edição, ordenação, preview e fallback
acessível via Drupal Form API. Uma conta pode ter várias páginas; sem equipes
complexas inicialmente.

## 0.4.0 — interface pública e presets

Evoluir SDC, presets governados, SEO, publicação, acessibilidade, modo claro/
escuro e revisão de assets/licenças.

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
