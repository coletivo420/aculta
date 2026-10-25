# Roadmap — ACULTA Statistics

Planejamento. O estado real de cada entrega fica no CHANGELOG e nos commits.
Cada fase termina antes da próxima começar, sem autorização explícita.

## 0.1.0 — Auditoria e fundação (esta fase)

Entregue nesta fase (documentação e código portado sem ativação):

- Auditoria das fontes de métrica: [CURRENT-STATE.md](CURRENT-STATE.md).
- Matriz de portabilidade e licenças: [PORTABILITY-MATRIX.md](PORTABILITY-MATRIX.md).
- Arquitetura: [ARCHITECTURE.md](ARCHITECTURE.md).
- Catálogo inicial: [METRIC-CATALOG.md](METRIC-CATALOG.md).
- Integrações (Commerce, LMS, e-mail, GA4, GSC, fórum, wiki, multidomínio): [INTEGRATIONS.md](INTEGRATIONS.md).
- ADRs em [adr/](adr/).
- Código portado, sem ativação: storage diário, hook de retenção, janela e
  delta do Search Console, com testes unitários (9 casos).

Pendente nesta fase:

- SMTP2GO: "entregues" não existe na API documentada (verificado); a métrica vem do Brevo.
- Commerce Reports: não instalar (sem release estável em D11).
- Decidir sobre gráficos (`charts`). O contador de leitura da Wiki foi decidido e entregue na 0.2.0-dev.3.
- Decidir sobre QR estático.
- Schema da tabela `aculta_statistics_daily`: não instalado (sem migração).

Não iniciado: ativação do módulo, `*.info.yml`, `hook_schema`, painel e conectores.

## 0.10.0 — Wiki (em andamento: ranking e histórico de edição entregues no código)

- Preparar a integração com o submódulo `aculta_wiki`, conforme `docs/modules/WIKI-SUBMODULO.md` (raiz do repositório).
- Feito: `WikiProvider`, `wiki.entries.published`, `wiki.entries.updated` e, na área Wiki do painel, o ranking por verbete do período com exportação CSV.
- Feito: histórico de edição por verbete (`WikiRevisionReport`): revisões, publicadas e rascunhos lidos das tabelas de revisão do Node, com a última edição, pendência primeiro, corte de 50 no painel e CSV completo. Não cria métrica nem muda retenção.
- Testes: unit (`WikiProviderTest`), kernel (`WikiPublicationKernelTest`, `WikiRankingKernelTest`, `WikiRevisionReportKernelTest` e `DailyAggregateStorageKernelTest`). Validação no Runtime pendente.
- Gate: inventário LGPD atualizado antes de criar as métricas.

## 0.4.0 — LMS (em andamento: 0.4.0-dev.1)

- Feito: matrículas por curso, conclusões por curso (uma vez por status) e certificados (total), a partir dos eventos do `aculta_lms`.
- Feito (0.4.0-dev.2): matrículas por curso e turma; supressão de células com menos de 5 alunos para painéis públicos.
- Feito (0.4.0-dev.3): conclusões por turma, pela matrícula da conta no curso (`curso/sem-turma` quando não é única).
- Pendente: taxa de conclusão por curso e turma nos painéis (0.8.0); validação no Runtime (bloqueada pelo container, ver CHANGELOG).

## 0.3.0 — Commerce (concluída no código: 0.3.0-dev.8)

- Feito: pedidos pagos e receita bruta por moeda, a partir do evento `commerce_order.order.paid`.
- Feito (0.3.0-dev.2): reconciliação diária com pedidos oficiais `completed`, por dia UTC e moeda; doações na mesma contagem; qualquer diferença vai ao log.
- Feito (0.3.0-dev.3): reembolsos por incremento em `commerce.refunds.total`; receita líquida na leitura.
- Feito (0.3.0-dev.4): unidades vendidas por variação de produto.
- Feito (0.3.0-dev.5): carrinhos abandonados (24 horas sem alteração), contados no cron.
- Feito (0.3.0-dev.6): vendas por domínio, com o purpose de origem gravado no pedido.
- Feito (0.3.0-dev.7, antecipado da 0.6.0): `drupal/google_tag` habilitado com Consent Mode; falta o ID da propriedade.
- Feito (0.3.0-dev.8): cupons, com pedidos com cupom e desconto por moeda.
- Fase 0.3.0 implementada no código. Pendente: validação com pedidos reais; painel de receita líquida (0.8.0).

## 0.2.0 — Núcleo analítico (concluído no código: 0.2.0-dev.4)

- Ativar o módulo: `*.info.yml`, linha no `PROJECT-STATUS-INDEX` do README raiz, schema. **Feito no código**; ativação no Runtime pendente.
- Contratos de evento e de provedor.
- Catálogo de métricas em código (validação de identificador e unidade).
- Publicação de eventos e gravação no storage.
- Cache com tag `aculta_statistics`.
- Testes Kernel com `aculta_simpletest` (nunca `aculta_runtime`).

## 0.3.0 — Commerce

- `CommerceProvider` e coletor de `commerce_order.order.paid`.
- Receita por moeda e domínio; pedidos criados separados de vendas.
- Carrinho abandonado pelo cart.
- Reconciliação com pagamentos.

## 0.4.0 — LMS

- `LmsProvider` sobre `LearningDashboardManager`, `CourseCompletionPublisher` e certificados.
- Matrículas, conclusões e taxa de conclusão por curso e turma.
- Número mínimo por célula para evitar identificação em turma pequena.
- Só depois do commit de `AnswerSubmittedEvent` (outra frente).

## 0.5.0 — E-mail (em andamento: 0.5.0-dev.1)

- Feito (0.5.0-dev.1): `EmailProvider` lê o `aculta_mail_log` por dia de solicitação; `EmailReconciler`
  recalcula os últimos 7 dias no cron e substitui o valor do dia (idempotente). Métricas `email.messages.*`:
  queued, accepted, delivered, bounced e failed. Aceito e entregue ficam separados.
- Feito: conciliação entre Brevo, IMAP e SMTP2GO pelo `correlation_id` já existe no registro único do
  `aculta_mailer`; o Statistics lê esse registro, então cada envio conta uma vez.
- Pendente: devolução hard e soft (`email.bounces.*`), `email.quota.reached`.
- Pendente: conector SMTP2GO. Não há contagem de entregues na API; aguarda decisão do responsável
  sobre o uso (ver INTEGRATIONS).
- Pendente: validação no Runtime de teste com envios reais.
- Não iniciado: painel e resumo semanal com séries de e-mail (0.8.0).

## Produtores de eventos (decisão de 2026-10-21)

Cada submódulo produtor emite um evento; o `aculta_statistics` assina. Produtor não conhece o statistics.

- **Fórum (feito, 0.1.0-dev.127 e dev.159 do aculta_forum):** `aculta_forum.topic_created`, `aculta_forum.comment_created` e `aculta_forum.post_created`
  → `forum.topics.created`, `forum.comments.created` e `forum.posts.created`. Importações sincronizadas não contam como posts novos. Pendente: validação no Runtime.
- **Mailer (pendente):** `aculta_mailer.status_changed` → métrica de estado (`email.messages.status`), estado atual por dia de solicitação (decidido em 2026-10-21). Ver `aculta_mailer/docs/ROADMAP.md`.
- **LMS (feito e validado no Runtime em 2026-10-22):** instantâneo por turma (`lms.students.by_status`), série diária (decidido em 2026-10-21). O `aculta_lms` produz em `ClassStatusSnapshotCronHooks` e o `LmsClassStatusSubscriber` grava por dimensão `curso/turma|situação`. Ver `aculta_lms/docs/ROADMAP.md`.

## Encerramento do módulo (decisão do responsável, 2026-10-21)

O `aculta_statistics` está concluído no código (fases 0.1 a 0.9). O responsável encerrou o objetivo com estas pendências explícitas:

1. **Validação real do GA4 e do Search Console (0.6): feita em 2026-10-21.** Consultas reais respondem no servidor de testes: GA4 (propriedade de teste 559962780) e Search Console (`sc-domain:aculta.org`), ambas sem erro. Sem dados nos últimos dias, o que é esperado em propriedade de teste. Credenciais no arquivo de segredos, por `sudo`.
2. **Teste automatizado por host (1.0):** precisa de um site de teste com URL base própria. Verificado manualmente no servidor de testes (ver `docs/AUDITORIA-1.0.md`).

Demais itens (validação visual das áreas, auditoria de segurança e privacidade, medição de carga única) estão em `docs/AUDITORIA-1.0.md`.

## Centralização (decisão de 2026-10-21, ADR-0004)

`aculta_statistics` é a única fonte e central de estatísticas agregadas. Ordem:

1. **Links (concluído em 0.4.0-dev.31 do aculta_links):** cliques em `links.link.clicks`; tabela
   `aculta_links_stat_daily`, fila e cron próprios removidos. Sem migração (banco de teste, sem retenção).
   Aplicado no Runtime de teste em 2026-10-21.
2. **Fórum:** séries temporais em `forum.*`; a contagem atual passa a ler o `aculta_statistics`.
   Bloqueio: produtores `forum.topic.created` e `forum.comment.created` (0.7.0).
3. **E-mail (0.5.0):** métrica `email.messages.status` criada (dimensão `transporte|estado`, estado atual por
   dia de solicitação). Backfill de 90 dias disponível por `drush aculta-statistics:backfill-email`.
   Decidido (2026-10-21): o painel de envios do aculta_mailer **continua ao vivo** (operacional, falhas recentes
   em tempo real). A central alimenta as séries. Resumo semanal segue no roadmap de 0.8.0.
4. **LMS (0.4.0):** contagens da turma em `lms.*`; painel individual fica fora. Produtor de
   `lms.students.by_status` (instantâneo) implementado no `aculta_lms` e validado no Runtime em 2026-10-22.
5. **Gate:** `aculta_statistics` é o único gravador de agregados estatísticos (validação a criar).

Sem remover tabela, dado ou painel antes do passo de migração e da autorização do responsável.

## 0.6.0 — Google Analytics e Search Console (código desligado: 0.9.0-dev.1)

- Feito (código): `Ga4ReportClient` com cliente real (`GoogleGa4ReportClient`, `google/apiclient` 2.20.1, ADR-0006 aceita). Desligado sem propriedade e credencial, que vêm do `aculta_portal.secrets_manager` (`GA4_PROPERTY_ID`, `GA4_SERVICE_ACCOUNT_JSON`, no contrato de segredos).
- Feito: a remoção pendente de `drupal/change_mail_page` foi commitada antes, em commit próprio, porque a outra frente já a tinha aplicado (0.4.0-dev.240 do portal).
- Pendente: provisionar as duas variáveis no servidor de testes e validar uma consulta real. Pendente: domínio verificado no Search Console.

- GA4 Data API (v1beta) em fila, com cache e quota.
- Consentimento (Consent Mode v2) antes de qualquer tag.
- Search Console com OAuth em `aculta_portal.secrets_manager`.
- Propriedades por domínio, sem somar sobreposição.
- Justificativa escrita para `google/apiclient` antes da instalação.

## 0.7.0 — Integrações comunitárias (mídia feita: 0.9.0-dev.1)

- Feito: aberturas de arquivo privado de mídia (`media.files.requests`), sem pedaços e sem arquivo público.
- Decidido: QR estático segue sem medição; mídia medida, coberta pelo consentimento geral do portal.

- Links: convergência concluída antecipadamente (ver Centralização, abaixo).
- QR dinâmico (decisão sobre estático).
- Fórum: série temporal de comentários.
- Wiki: leitura entregue na 0.2.0-dev.3; publicações, atualizações e ranking planejados na 0.10.0.
- Mídia: instrumentar downloads e reproduções.
- Eventos e Messenger, quando existirem como fonte.

## 0.8.0 — Dashboards

- Regra (ADR-0005, 2026-10-21): todo gráfico com eixo de tempo tem seletor de período (7, 14 e 30 dias), no mesmo commit do gráfico.

- Feito (0.8.0-dev.1): painel central (uma métrica por vez), período de 7, 14 e 30 dias, gráfico, tabela e exportação CSV. Métricas R fora do painel.
- Feito (0.9.0-dev.2): painéis por área (vendas, e-mail, cursos, links, fórum, mídia, wiki) no design system do ACULTA420 (tokens `statistics-*`, autorizado em 2026-10-21). Pendente: validação visual no navegador.
- Pendente: filtros por domínio e fonte.
- Feito (0.9.0-dev.1): comparação com o período anterior de mesmo tamanho, com variação percentual e aviso de que não é causa. Comparação entre áreas (métricas lado a lado) fica para o próximo corte.
- Feito (0.9.0-dev.1): filtro por domínio em pedidos.
- Verificado visualmente no servidor de testes (2026-10-21): áreas renderizadas; dados reais pendentes.

## 0.9.0 — Segurança e otimização (feito no código: 0.9.0-dev.1)

- Feito: auditoria das alterações de prazo (canal `aculta_statistics`); índices já existentes conferidos.
- Pendente: revisão do LGPD-INVENTARIO com a métrica de mídia (feita abaixo).

- Retenção, privacidade e revisão do LGPD-INVENTARIO.
- Índices, filas, cache e reconciliação.
- Revisão de permissões por relatório.

## 1.0.0 — Auditoria final (aberta: ver docs/AUDITORIA-1.0.md)

- Feito: auditoria de código, segurança e privacidade, com o que foi e não foi verificado (`docs/AUDITORIA-1.0.md`).
- Decidido (2026-10-21): painéis de estatísticas ficam no tema administrativo Claro. Ajusta-se só o conteúdo. O CSS do ACULTA420 para estatísticas não se aplica a essas telas.
- Pendente: testes funcionais e multidomínio; teste de carga; cliente GA4 real (bloqueado pelo composer); revisão visual de todos os painéis por área.

- Testes unitários, Kernel, funcionais e multidomínio.
- Auditoria de segurança, licenças e dados.
- Testes de desempenho.
- Revisão completa da documentação.

## Regras de ciclo

- Dev → RC → validação Alpha → produção, conforme `docs/operations/RELEASE-LIFECYCLE.md`.
- Não criar `preRC`.
- Não abrir PR nem fazer merge sem pedido explícito do responsável.

## Decisões incorporadas (2026-10-20)

- 0.6.0: GA4 condicionado a consentimento de conta pelo ledger `aculta_user_agreement`; banner de cookies (Consent Mode v2) para anônimos, sujeito a autorização para o tema ACULTA420. Somente `google_tag`.
- 0.7.0: convergência do agregado de `aculta_links` no `aculta_statistics`; QR estático medido só nos QRs novos.
- Antecipado para a 0.2.0-dev.3 por decisão do responsável: contador de leitura da Wiki (`wiki.entries.read`), com beacon e retenção configurável.
- 0.2.0: configuração de retenção por métrica (Configuration API) e permissão própria; 0.9.0: auditoria das alterações de prazo.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-08: padronizar providers para Wiki, Fórum, LMS, Commerce, User e Messenger; evitar dashboards/contadores paralelos, remover PII e aplicar retenção. Métricas de Group de associados são agregadas e não expõem membership individual.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B: métricas e dashboards seguem Statistics com providers por produto; eventos Messenger não devem gerar PII ou contagem duplicada; a futura membership Group de associados exige agregação sem identificação individual. Reaproveitar os contratos de eventos publicados apenas com semântica compatível.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**
