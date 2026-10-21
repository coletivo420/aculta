# Catálogo de métricas — proposta inicial

Proposta da fase 0.1.0. Nenhuma métrica destas está implementada. Cada entrada
registra a fonte oficial, para não confundir unidades diferentes.

Campos: **id** (identificador), **fonte** (onde o dado nasce), **unidade**,
**dimensões**, **retenção**, **sensibilidade**.

Sensibilidade: **P** pública (agregado), **I** interna (equipe), **R** restrita
(dado de aluno, pagamento individual). Dados **R** nunca aparecem em painel global.

## Comércio (fonte: Commerce)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `commerce.orders.paid` | evento `commerce_order.order.paid` (uma vez por pedido) | pedidos | moeda | 90 dias | I |
| `commerce.coupons.orders` e `commerce.discounts.total` (implementadas na 0.3.0-dev.8) | evento de pedido pago; campo `coupons`; ajustes `promotion` | pedidos e centavos | moeda | 90 dias | I |
| `commerce.orders.by_domain` e `commerce.revenue.by_domain` (implementadas na 0.3.0-dev.6) | evento de pedido pago; purpose gravado no pedido | pedidos e centavos | `purpose|moeda` (`sem_dominio` se ausente) | 90 dias | I |
| `commerce.carts.abandoned` (implementada na 0.3.0-dev.5) | cron: carrinhos `draft` com itens alterados há 24 h | carrinhos | total do dia | 90 dias | I |
| `commerce.items.sold` (implementada na 0.3.0-dev.4) | evento de pedido pago, itens | unidades | variação de produto | 90 dias | I |
| `commerce.refunds.total` (implementada na 0.3.0-dev.3) | `commerce_payment_update`, incremento do reembolso | centavos da moeda | moeda | 90 dias | I |
| `commerce.revenue.gross` (implementada na 0.3.0-dev.1) | mesmo evento, total do pedido | centavos da moeda | moeda | 90 dias | I |
| `commerce.orders.created` | `commerce_order.commerce_order.insert` | pedidos criados (não são venda) | domínio | 90 dias | I |
| `commerce.revenue.gross` | pagamentos capturados | moeda (centavos) | moeda, domínio | 90 dias | I |
| `commerce.refunds.total` | estado de pagamento reembolsado | moeda | moeda | 90 dias | I |
| `commerce.items.sold` | itens de pedido pago | unidades | produto | 90 dias | I |
| `commerce.carts.abandoned` | carrinho expirado, sem pedido pago | carrinhos | domínio | 90 dias | I |

Regras: pedido criado não é venda; pagamento autorizado não é captura; reembolso
não é venda nova. Cada transição conta uma vez, pelo estado atingido.

## LMS (fonte: `aculta_lms`, `lms`, `group`)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `lms.enrollments.created` | matrícula em turma (`GroupMembership`) | matrículas | curso, turma | 90 dias | I |
| `lms.courses.completed` | `CourseCompletedEvent` (uma vez por status) | conclusões | curso | 90 dias | I |
| `lms.enrollments.created` (implementada na 0.4.0-dev.1; turma na 0.4.0-dev.2) | `CourseEnrollmentEvent` | matrículas | `curso/turma` | 90 dias | I |
| `lms.certificates.issued` (implementada na 0.4.0-dev.1) | `CertificateIssuedEvent` (uma vez por certificado) | certificados | total | 90 dias | I |
| `lms.certificates.issued` | `CourseCertificateManager::issue()` | certificados | curso | 90 dias | I |
| `lms.students.by_status` | painel da turma (contagem) | alunos | curso, turma, status | instantâneo | I |
| `lms.completion.rate` | `completed / enrolled` no mesmo recorte | percentual | curso, turma | derivada | I |

Nota: `lms.completion.rate` é derivada. Numerador e denominador precisam ser do
mesmo recorte; não somar taxas de turmas diferentes.

## E-mail (fonte: `aculta_mail_log`, Brevo, SMTP2GO)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `email.messages.queued` | fila Drupal | mensagens | tipo | 90 dias | I |
| `email.attempts.total` | transporte (`attempts`) | tentativas | transporte, tipo | 90 dias | I |
| `email.messages.accepted` | estado `sent` no log | mensagens aceitas pelo sistema | transporte | 90 dias | I |
| `email.messages.delivered` | webhook/evento do provedor, quando houver | mensagens | transporte | 90 dias | I |
| `email.messages.failed` | estado `failed` | mensagens | transporte, código | 90 dias | I |
| `email.bounces.hard` / `email.bounces.soft` | webhook ou IMAP | mensagens | transporte | 90 dias | I |
| `email.quota.reached` | `TransportQuota` | eventos | transporte | 90 dias | I |

Distinção obrigatória: **aceita** (o sistema de e-mail recebeu) não é **entregue**.
"Entregue" só aparece quando houver confirmação do provedor.

## Links e QR (fonte: `aculta_links`, `aculta_qrcode`)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `links.link.clicks` | `LinkAnalytics::recordClick()` | cliques | destino | 90 dias | P |
| `qrcode.redirect.requests` | redirecionamento dinâmico | acessos | QR, destino | 90 dias | P |

`qrcode.redirect.requests` depende de decisão: o QR estático não é medido por
design. Só o dinâmico pode ser medido.

## Busca (fonte: Search Console, fase 0.6.0)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `seo.search.clicks` | Search Analytics | cliques | propriedade, data | conforme API (~16 meses) | P |
| `seo.search.impressions` | Search Analytics | impressões | propriedade, data | idem | P |
| `seo.search.ctr` | derivada: cliques ÷ impressões | fração | propriedade | derivada | P |
| `seo.search.position` | Search Analytics | posição média ponderada pela API | propriedade | não somar: vem agregada | P |

Não somar CTR nem posição entre dias ou propriedades. Recalcular a partir de cliques e impressões quando for preciso agregar.

## Analytics (fonte: GA4 Data API, fase 0.6.0)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `analytics.active_users` | GA4 `activeUsers` | usuários | propriedade, data | conforme GA4 | P |
| `analytics.sessions` | GA4 `sessions` | sessões | propriedade, canal | conforme GA4 | P |

Não confundir `analytics.sessions` com visitas do Drupal: são fontes diferentes.
Não somar sessões do GA4 com cliques de links ou visualizações internas.

## Comunidade (fontes: `aculta_forum`, Wiki/Node, comentários)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `forum.topics.published` | nós `aculta_forum_topic` publicados | tópicos | categoria, domínio | instantâneo | P |
| `forum.comments.created` | comentários novos (Core `comment`) | comentários | categoria | 90 dias | P |
| `forum.participants.active` | usuários distintos que postaram no período | pessoas | — | 90 dias | I |
| `wiki.entries.published` | nós `wiki_entry` publicados | verbetes | domínio | instantâneo | P |
| `wiki.entries.updated` | nova revisão de `wiki_entry` | revisões | domínio | 90 dias | P |
| `wiki.entries.read` | **não existe fonte** (Core sem contador) | — | — | — | — |

Usuários distintos (`forum.participants.active`) são pessoas: guardar só a
contagem, nunca a lista. Cálculo por hash com sal do ambiente, se preciso.

## Mídia (fonte: a instrumentar)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `media.downloads` | download de PDF/arquivo (a criar) | downloads | arquivo | 90 dias | P |
| `media.plays` | reprodução de áudio/podcast (a criar) | reproduções | episódio | 90 dias | P |

Sem fonte hoje: exige instrumentação, decisão sobre consentimento e dimensão por arquivo.

## Operação (fontes: filas, cron, logs do Drupal)

| id | fonte | unidade | dimensões | retenção | sens. |
|---|---|---|---|---|---|
| `ops.queue.items` | tamanho das filas (Queue API) | itens | fila | instantâneo | I |
| `ops.cron.last_run` | estado do cron | timestamp | — | instantâneo | I |
| `ops.mail.failures` | `email.messages.failed` | falhas | transporte | 90 dias | I |

## Regras gerais

- Taxas são derivadas de numerador e denominador do mesmo recorte e da mesma fonte.
- Receita preserva moeda; nunca soma moedas diferentes.
- Comparação entre fontes não implica causalidade. Sem identificador de campanha ou
  de origem confiável, mostrar só a comparação agregada.
- Métrica sem fonte confirmada não entra no catálogo como disponível.

## Retenção por métrica

A coluna "retenção" acima é o padrão inicial. O administrador ajusta cada métrica
no painel (30, 90, 180, 365 dias ou indefinido), conforme ADR-0003. Métricas de
visita podem ser configuradas como indefinido, por decisão do responsável; o risco
está registrado na ADR.
