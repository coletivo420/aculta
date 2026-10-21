# Integrações — ACULTA Statistics

Desenho de cada integração. Nada aqui foi implementado nesta fase.

## Regra geral de migração

"Migrar para o Statistics" significa **migrar a responsabilidade pelo indicador**:
o catálogo, a agregação, a consulta e o painel passam a ser do Statistics. Os dados
de origem continuam no módulo dono. Não há cópia de registros nem migração de
banco nesta fase (fase 0.1.0 não executa migração).

| Área | Dono dos dados | Dono do indicador (após migração) |
|---|---|---|
| Fórum (tópicos, comentários, participantes) | `aculta_forum` + Core `comment` | Statistics, via provedor `ForumProvider` |
| Wiki (verbetes, revisões) | Conteúdo `wiki_entry` no domínio `wiki420` | Statistics, via provedor `WikiProvider` |
| LMS (matrículas, progresso, conclusões) | `aculta_lms` + `lms` + `group` | Statistics, via provedor `LmsProvider` |
| E-mail | `aculta_mailer` (`aculta_mail_log`) | Statistics, via provedor `EmailProvider` |
| Links | `aculta_links` (`aculta_links_stat_daily`) | Statistics, após convergência |
| Comércio | Commerce | Statistics, via provedor `CommerceProvider` |

Consequência: o módulo que produz o dado não passa a ter painel próprio para
indicador global. Painéis locais continuam para a operação do dono (por exemplo,
o painel de turma do LMS), com as mesmas permissões.

## Fórum (`aculta_forum`)

- **Fonte:** contagem de comentários do Core (`comment_count`), tópicos
  publicados (`aculta_forum_topic` filtrado pelo domínio `forum`) e categorias.
- **Métricas:** `forum.topics.published`, `forum.comments.created`,
  `forum.participants.active`.
- **Provedor:** `ForumProvider` consulta contagens; não lê autoria privada.
- **Regras:** o Fórum não cria builders de perfil; participantes são contados,
  não listados. Comentários de verbete ficam associados à origem.
- **Pendente:** série temporal de comentários. Hoje há só total atual; o
  agregado diário nasce com o evento `forum.comment.created` (fase 0.7.0).

## Wiki (conteúdo `wiki420`)

- **Fonte:** nós `wiki_entry` publicados e revisões do Node.
- **Métricas:** `wiki.entries.published`, `wiki.entries.updated`.
- **Lacuna:** `wiki.entries.read` não tem fonte. O Core não traz contador de
  leitura e o módulo `statistics` não está instalado. Decisão necessária antes de
  criar contador de leitura: custo, privacidade e cache de página.
- **Regra:** a Wiki não é módulo. Não reimplementar a Wiki no Fórum; o Statistics
  só lê o conteúdo.

## LMS (`aculta_lms`, `lms`, `group`)

- **Fonte:** `GroupMembership` com permissão `take course`, `CourseCompletedEvent`,
  `CourseCertificateManager::issue()`, contagens de `LearningDashboardManager::classDashboard()`.
- **Métricas:** `lms.enrollments.created`, `lms.courses.completed`,
  `lms.certificates.issued`, `lms.students.by_status`, `lms.completion.rate`.
- **Restrição:** notas, respostas e progresso individual são **R** (restritos). O
  painel global recebe só contagens por curso e turma, com número mínimo de
  alunos por célula a definir (evita identificar uma pessoa em turma pequena).
- **Pendente:** `AnswerSubmittedEvent` e `AnswerLifecycleEventHooks` estão em
  arquivos **não commitados de outra frente**. Não depender deles até o commit.
  Nenhum arquivo dessa frente foi alterado nesta fase.

## E-mail (`aculta_mailer`, Brevo, SMTP2GO)

- **Fonte primária:** `aculta_mail_log` (estado, transporte, tentativas,
  código). `sent` significa aceito pelo sistema, não entregue.
- **Provedor principal:** Brevo, com webhook (`BrevoWebhook`) e status
  (`BrevoEventStatus`). Entregas confirmadas vêm daqui.
- **Contingência:** SMTP2GO (`TransportFailover`). Não é fonte principal.
- **Conector SMTP2GO (fase 0.5.0):** API de estatísticas documentada
  (bounces em 30 dias, eventos, webhooks). Antes de prometer "entregues",
  confirmar o endpoint na documentação oficial. Não localizado na página consultada.
- **Idempotência:** conciliar por `correlation_id` (`X-Aculta-Mail-ID`) para não
  contar o mesmo evento duas vezes entre webhook, IMAP e API.
- **Não armazenar:** corpo, assunto, destinatário em claro, token ou credencial.

## Comércio (Commerce 3.3.10)

- **Eventos nativos:** `commerce_order.order.paid` (venda concluída),
  `commerce_order.commerce_order.insert` (pedido criado, não é venda),
  `commerce_payment.commerce_payment.insert/update` (pagamento e reembolso).
- **Carrinho abandonado:** `CartManager` e a expiração do módulo cart.
- **Decisão:** não instalar ECA Commerce nem Commerce Reporting. ECA Commerce
  apenas expõe os eventos nativos; Commerce Reporting não foi localizado no
  drupal.org na consulta de 2026-10-20.
- **Regra:** cada transição conta uma vez, pelo estado atingido. Reembolso não
  é venda nova. Receita preserva moeda.
- **Mercado Pago:** é o processador. O Statistics recebe o resultado, não
  armazena cartão nem dado de pagamento individual.

## GA4 (fase 0.6.0)

- **Coleta:** separada da consulta. Coleta só com consentimento
  (Google Consent Mode v2) e pela camada de tag, não pelo Twig do tema.
- **Consulta:** GA4 Data API (`runReport`, v1beta, como usa `google_analytics_reports`),
  por job em fila, com cache e quota.
- **Candidatos:** `drupal/google_tag` 2.x (GTM ou gtag) e `drupal/klaro` para
  consentimento. Decisão pendente.
- **Sem PII:** nada de e-mail, nome, IP ou identificador de usuário enviado ao Google.
- **Relatório:** "usuários ativos" e "sessões" são métricas do GA4; não somar com
  cliques ou visualizações internas.

## Search Console (fase 0.6.0)

- **Consulta:** Search Analytics, com `dataState: all` (inclui dados parciais;
  marcar a proveniência).
- **Janela:** `SearchAnalyticsWindow::forDays()`, limite de 480 dias.
- **Comparação:** `SearchMetricsDelta::between()`. CTR em pontos percentuais;
  posição vem agregada pela API e não é somada.
- **Autenticação:** OAuth em `aculta_portal.secrets_manager`, **não** pelo módulo
  Key nem pelo `state`. Exige justificativa e `google/apiclient`.
- **Propriedade:** domínio (DNS) preferido, conforme `GOOGLE.md`. Propriedades
  sobrepostas não são somadas.
- **Mapeamento:** URL para entidade Drupal só com correspondência confiável.

## Links e QR Codes

- **Links:** reutilizar `aculta_links_stat_daily`; retenção de 90 dias.
- **QR:** só o dinâmico pode ser medido. O estático não tem registro por
  design (`aculta_qrcode/README.md`). Mudar isso exige decisão do responsável.

## Mídia (a instrumentar)

- Reproduções e downloads não têm fonte hoje. Exige evento novo, dimensão por
  arquivo ou episódio e decisão de consentimento.

## Multidomínio

- Dimensão `domain` com identificador estável (`main.aculta`, `loja.aculta`,
  `wiki420.aculta`, `forum.aculta`). Nunca hostname de Homelab ou produção.
- Hosts e purposes vêm de `docs/architecture/multidomain.md` e do `aculta_deployer`.
- Painel administrativo sempre no purpose `main`.

## Decisões do responsável (2026-10-20)

- **Consentimento (GA4 e rastreamento):** usa o mecanismo existente, não um novo.
  O aceite é o módulo contrib `agreement`, orquestrado por
  `aculta_portal/src/Account/` (`MailTrackingConsent`, `AccountTermsPolicy`), com os
  acordos `aculta_account_terms` (termos, obrigatório) e `aculta_rastreamento`
  (opcional). O `aculta_user` não tem módulo de consentimento próprio.
- **GA4 para contas:** acordo do `agreement`, como `aculta_rastreamento`.
- **GA4 para anônimos:** banner de cookies com Google Consent Mode v2. Exige
  **autorização explícita** para alterar `web/themes/custom/aculta420/` (AGENTS).
  Até lá, nenhuma tag de visitante anônimo é carregada.
- **Tag:** somente `drupal/google_tag`, sem Klaro (consentimento próprio).
- **QR estático:** só QRs novos passam por rota medida. QRs já impressos seguem
  sem medição, apontando ao destino direto. A medição nova depende de a rota de
  redirecionamento do QR estático ser criada, o que altera a geração do QR (fase 0.7.0).
- **Links:** `aculta_statistics` assume o agregado de `aculta_links` na fase 0.7.0; `aculta_links` vira provedor.
- **Wiki (leitura):** contador próprio no `aculta_statistics`, métrica
  `wiki.entries.read`, dimensão = identificador do verbete, agregado diário, sem
  IP nem usuário, retenção de 90 dias. Viabilidade verificada: visitas servidas do
  cache só são contadas com beacon JS; e o contador precisa entrar no
  `LGPD-INVENTARIO.md` antes de ser criado. Alternativa descartada: `drupal/statistics`
  (contrib), por não ter recorte por domínio e registrar IP no access log se ligado.
  Como o dado é agregado e sem identificação, a base legal fica a confirmar com o
  responsável no inventário.
