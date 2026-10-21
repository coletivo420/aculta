# Estado atual — produtores e consumidores de métricas

Auditoria de 2026-10-20 no checkout do Runtime de teste. Classificação conforme
o roteiro da fase 0.1.0. Nada aqui foi alterado.

Legenda: **EXISTENTE E FUNCIONAL**, **EXISTENTE MAS INCOMPLETO**, **AUSENTE**,
**DUPLICADO**, **DEVE SER REUTILIZADO**, **DEVE SER PORTADO**, **INTEGRAÇÃO FUTURA**.

## Resumo

- Não existe `aculta_statistics` nem qualquer painel central de métricas.
- Há quatro produtores com dados reais: e-mail, links, LMS e fórum.
- Não há código de Google Analytics, Google Tag ou Search Console no site.
- Não há módulo `statistics` do Core instalado.
- Commerce e pagamento têm eventos nativos, mas nenhuma contagem analítica.
- Mídia (revista, galeria, PDF) e QR Code não medem uso.

## Fórum (`aculta_forum`, Growroom420)

| Recurso | Onde está | Classificação |
|---|---|---|
| Contagem de comentários por tópico | Campo `comment_count` das Views `aculta_forum_public_topics` e `aculta_forum_unread_topics` (estatística de comentários do Core) | DEVE SER REUTILIZADO |
| Contagem de tópicos publicados e categorias | `ForumAdminOverviewController::overview()`, consultas de entidade filtradas pelo domínio `forum` | EXISTENTE MAS INCOMPLETO: só totais atuais, sem série temporal |
| Respostas e participantes por período | Não existem | AUSENTE |
| Leitura de tópico (marcador de lido) | `ForumReadMarker` guarda a última leitura por usuário | DEVE SER PORTADO só como agregado; o registro por usuário é privado |
| Movimentação de tópicos e comentários | `ForumTopicMover`, `ForumCommentMover` | Ação administrativa, não métrica |

Regra do AGENTS: o Fórum não cria builders de perfil. O Statistics só lê
contagens, nunca a autoria privada.

## Wiki (conteúdo no domínio `wiki420`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Verbetes | Tipo de conteúdo `wiki_entry`, fluxo `wiki_editorial`, campo `field_domain_source` | EXISTENTE FUNCIONAL |
| Lista pública | View `wiki_entries` (`config/sync/views.view.wiki_entries.yml`), sem campo de contagem | EXISTENTE MAS INCOMPLETO |
| Visualizações e edições por verbete | Não existem: o módulo `statistics` do Core não está instalado | AUSENTE |
| Histórico de revisões | Revisões nativas do Node (`content_moderation` / revisões do Core) | DEVE SER REUTILIZADO como fonte de "atualizações" |
| Comentários em verbete | Comentários associados ao verbete de origem (AGENTS) | DEVE SER REUTILIZADO |

Regra do AGENTS: a Wiki não é módulo, é conteúdo. Não reimplementar a Wiki no
Fórum. Contagem de leituras exige decisão, porque o Core não traz esse contador.

## LMS (`aculta_lms`, `drupal/lms` 1.2.4, `group` 3.3.5)

| Recurso | Onde está | Classificação |
|---|---|---|
| Painel do aluno (progresso e certificados) | `LearningDashboardManager::studentDashboard()` | EXISTENTE FUNCIONAL, individual e privado |
| Painel da turma (contagens) | `LearningDashboardManager::classDashboard()`: alunos, não iniciados, em andamento, aguardando avaliação, aprovados, reprovados | DEVE SER REUTILIZADO como fonte agregada |
| Matrícula | `GroupMembership` com permissão `take course` | DEVE SER REUTILIZADO |
| Conclusão de curso | `CourseCompletedEvent`, `CourseCompletionPublisher` | DEVE SER REUTILIZADO como evento |
| Certificados | `CourseCertificateManager` | DEVE SER REUTILIZADO |
| Resposta enviada (avaliação) | `AnswerSubmittedEvent` e `AnswerLifecycleEventHooks`: arquivos **não commitados de outra frente** | INTEGRAÇÃO FUTURA. Não depender até o commit |
| Descarte de tentativas | `AttemptArchiveManager` | Retenção de dados educacionais, não métrica |

Regra: o LMS é a fonte de matrícula e progresso. O Statistics recebe contagens
por turma e curso, nunca notas ou respostas individuais.

## E-mail (`aculta_mailer`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Log por mensagem (`aculta_mail_log`) | Entidade com `correlation_id`, tipo, estado, transporte, tentativas, código SMTP e destinatário em HMAC | DEVE SER REUTILIZADO como fonte |
| Painel de contagens por período | `MailDashboard::summary()` (total, estado, tipo, transporte, código de erro) | EXISTENTE FUNCIONAL, sem gráfico |
| Webhook Brevo e status de entrega | `BrevoWebhook`, `BrevoEventStatus` | DEVE SER REUTILIZADO. Provedor principal |
| Transporte de contingência SMTP2GO | `TransportFailover('smtp', 'smtp2go')`, segredos `SMTP2GO_*` | EXISTENTE. SMTP2GO não é a fonte principal |
| Monitor IMAP e DMARC | `MailMonitor`, resumos DMARC sem IP | DEVE SER REUTILIZADO |
| Cota por transporte | `TransportQuota`, `QuotaSignatures` | DEVE SER REUTILIZADO |
| Aberturas e cliques | Fase F11, somente com consentimento | EXISTENTE, condicionado |

Distinção que o Statistics deve manter: `sent` no log significa aceito pelo
sistema de e-mail, não entregue.

## Links (`aculta_links`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Cliques por destino e dia | `LinkAnalytics::recordClick()` → fila → `aculta_links_stat_daily` | DEVE SER REUTILIZADO. Modelo de privacidade de referência |
| Retenção de 90 dias | `LinkAnalytics::purgeExpired()`, cron com `#[Hook]` | DEVE SER REUTILIZADO |
| Cliques por destino para tela | `clickCounts()` | DEVE SER REUTILIZADO |

## QR Code (`aculta_qrcode`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Redirecionamento | `QrCodeRedirectController`, sem registro de leitura | EXISTENTE FUNCIONAL |
| Estatística | Documentado: "Estatísticas de QR estático não existem por design" | AUSENTE por decisão. Mudar exige decisão do responsável |

## Usuários (`aculta_user`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Abas de atividade do próprio usuário | `ActivityController` (wiki, fórum, revista, comentários, conquistas) | Privado. Não é fonte global |
| Perfil público | `aculta_user` é o único dono de view-models de identidade (AGENTS) | DEVE SER REUTILIZADO só para contagens agregadas |

## Comércio e pagamento (`commerce` 3.3.10, `aculta_donation`, `aculta_pagamentos_mercadopago`)

| Recurso | Onde está | Classificação |
|---|---|---|
| Eventos de pedido | `commerce_order.order.paid`, `commerce_order.commerce_order.insert/update`, itens | DEVE SER REUTILIZADO (API nativa) |
| Eventos de pagamento | `commerce_payment.commerce_payment.insert/update` | DEVE SER REUTILIZADO |
| Carrinho e expiração | `CartManager`, `Cron.php` do módulo cart | DEVE SER REUTILIZADO para carrinho abandonado |
| Fluxo de apoio | `aculta_donation` (checkout e recorrência) | DEVE SER REUTILIZADO |
| Painel de receita | Não existe | AUSENTE |

## Mídia (revista, galeria, PDF)

| Recurso | Onde está | Classificação |
|---|---|---|
| Reproduções e downloads | Não existem contadores | AUSENTE |
| Visualizador de PDF | `aculta_pdf_viewer`, sem evento de download próprio | AUSENTE: exige instrumentação nova |

## Operação e filas

| Recurso | Onde está | Classificação |
|---|---|---|
| Fila de cliques de links | `LinkAnalyticsQueueWorker` | DEVE SER REUTILIZADO como indicador de fila |
| Diagnósticos de deploy | `aculta_deployer` | Sem painel de métricas |

## Google (GA4, Search Console, Tag)

| Recurso | Onde está | Classificação |
|---|---|---|
| GA4 / Google Tag | Não há código no site. `GOOGLE.md` marca GA4 como "LAUNCH-MINIMAL, sujeito a consentimento" | AUSENTE. INTEGRAÇÃO FUTURA (0.6.0) |
| Search Console | Não há código. `GOOGLE.md` prefere verificação por DNS | AUSENTE. INTEGRAÇÃO FUTURA (0.6.0) |

## Duplicações a evitar

- Contador de cliques: `aculta_links` já tem agregado. O Statistics deve convergir para ele, não criar um segundo.
- Log de e-mail: `aculta_mail_log` é o registro. Não instalar Mailer Plus Log nem Trace Mail Log para isso.
- Contagem de comentários: já existe no Core via `comment_count`.
- Eventos de Commerce: não instalar ECA Commerce; a API nativa basta.
