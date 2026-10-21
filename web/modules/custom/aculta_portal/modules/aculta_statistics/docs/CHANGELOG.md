# Changelog — ACULTA Statistics

Formato: entregas por fase, com commits e evidências. Sem versão de manifesto
até o módulo ser ativado.

## 0.1.0 (fase de fundação, em andamento)

### Documentação
- Auditoria das fontes de métrica e classificação por recurso (`CURRENT-STATE.md`),
  incluindo Fórum, Wiki e LMS, conforme pedido do responsável.
- Matriz de portabilidade com versões, licenças e decisões (`PORTABILITY-MATRIX.md`).
- Arquitetura, catálogo de métricas e integrações (`ARCHITECTURE.md`, `METRIC-CATALOG.md`, `INTEGRATIONS.md`).
- Roteiro e ADRs.

### Código portado (sem ativação)
- `Storage\DailyAggregateStorage` e `AggregateStorageInterface`: adaptado de
  `drupal/statistics` (GPL-2.0-or-later, commit `fe20f9f`).
- `Hook\StatisticsCronHooks`: retenção de 90 dias no cron.
- `Connector\SearchConsole\SearchAnalyticsWindow` e `SearchMetricsDelta`: adaptados
  de `drupal/analyze_search_console` (GPL-2.0-or-later, commit `6f054ac`), sem
  credenciais, sem texto formatado.
- Testes unitários: 9 casos, aprovados com PHPUnit 11.5.57 e PHP 8.4.26 local.

### Verificações
- `php -l` em todos os arquivos PHP novos: sem erro.
- Não validado: `DailyAggregateStorage` (depende de banco; testes Kernel na fase 0.2.0),
  compatibilidade PHP 8.5 (o ambiente local tem 8.4).

### Não feito
- Nenhum `*.info.yml`, schema ou ativação.
- Nenhuma migração de banco.
- Nenhuma instalação de módulo contrib.

### Decisões (sem código)
- Consentimento de conta passa pelo ledger `aculta_user_agreement` do `aculta_user`.
- Contador de leitura da Wiki próprio; QR estático medido só nos novos; convergência com `aculta_links` na 0.7.0; GA4 com banner para anônimos mediante autorização de tema.
- Retenção configurável por métrica no painel administrativo (30, 90, 180, 365 dias ou indefinido), com permissão própria e confirmação antes de reduzir prazo (ADR-0003).

## 0.8.0-dev.1 (painel central de estatísticas, com período e exportação CSV)

### Código
- `StatisticsDashboardController`: página em `/painel-administrativo/aculta/estatisticas` (permissão `administer aculta_statistics`). Uma métrica por vez, período de 7, 14 ou 30 dias (ADR-0005), gráfico SVG, tabela de valores por dia e total do período.
- Exportação CSV em `/painel-administrativo/aculta/estatisticas/exportar`: uma linha por dia e dimensão (`dia,dimensao,total`), no mesmo período e métrica da tela.
- `MetricSeries`: período normalizado (valor fora de 7/14/30 volta a 7) e série diária com zero nos dias sem registro.
- Métricas de sensibilidade R (dado pessoal por aluno) não aparecem no painel nem no CSV.

### Dados pessoais
- Nenhum dado novo. A tela e o CSV mostram só agregados P e I. Sem IP, usuário ou identificador de aluno.

### Testes
- Unit: 81 casos (inclui `MetricSeriesTest`). Kernel `StatisticsDashboardKernelTest`: 2 casos (página com seleção, CSV, métrica restrita fora).

### Pendente (0.8.0)
- Painéis por módulo, filtros por domínio e fonte, comparações entre áreas, e a leitura de `lms.student.status` em painel próprio do docente (não entra no agregado).

## 0.5.0-dev.4 (situação por aluno sob o aceite único; purga na exclusão e na revogação)

### Código
- `lms.student.status` (sensibilidade R, 90 dias): situação de cada aluno por curso e dia, gravada por `LmsStudentStatusSubscriber` a partir do `aculta_lms.student_status_snapshot`. Substitui o dia; idempotente.
- `StudentDataPurgeSubscriber`: na exclusão da conta (`aculta_user.account_deleted`) e na revogação (`aculta_user.consent_revoked`), apaga as linhas do aluno em todos os dias (`purgeStudent`).
- Base: aceite único (`aculta_user_agreement_terms`), com a função descrita na política (ver `docs/compliance/CLAUSULAS-POLITICA-ACEITE-UNICO.md`).

### Testes
- Unit: 78 casos (inclui os dois assinantes). Kernel `DailyAggregateStorageKernelTest`: 8 casos, com a purga por aluno.

### Pendente
- Publicação da política com as cláusulas, antes de a função valer para usuários reais.

## 0.5.0-dev.3 (instantâneo de turma no LMS e regra de período em gráficos)

### Código
- `LmsClassStatusSubscriber` assina `aculta_lms.class_status_snapshot` e substitui o dia de `lms.students.by_status` (curso/turma|situação). Idempotente.
- Métrica `lms.students.by_status` no catálogo e na configuração (retenção de 90 dias).

### Decisões (2026-10-21)
- Todo gráfico do projeto tem seleção de período (ADR-0005). Gráfico de instantâneo sem eixo de tempo fica fora da regra.
- Série diária por aluno não foi criada (revisão LGPD pendente).

### Testes
- Unit: 75 casos (inclui o assinante do LMS).

## 0.5.0-dev.2 (estado de envio, backfill e decisão do painel de e-mail)

### Código
- `email.messages.status` (dimensão `transporte|estado`, estado atual por dia de solicitação): todos os nove estados, o que o painel de envios precisa para `by_status` e `by_transport`.
- `aculta-statistics:backfill-email --days=90` (Drush): reconcilia até 90 dias com o mesmo reconciliador do cron. Substitui valor por dia; rodar de novo não duplica.

### Decisões (2026-10-21)
- O painel de envios do `aculta_mailer` continua ao vivo. A central alimenta as séries. Motivo: o cron roda uma vez por dia e o painel precisa de falhas recentes em tempo real.
- Métrica de estado usa estado atual (não transições), para não contar uma mensagem duas vezes.

### Validação no Runtime de teste (2026-10-21)
- Backfill de 90 dias: 69 envios no log, 69 na fila e 69 no estado. Aceitos 41, entregues 4, devolvidos 1, falhos 26.

### Testes
- Unit: 73 casos. Kernel: conciliação inclui a métrica de estado.

## 0.5.0-dev.1 (e-mail: funil por dia de solicitação, conciliado no cron)

### Código
- `Email\EmailProvider` (interface `EmailProviderInterface`): lê o `aculta_mail_log` por dia UTC de
  solicitação (`created`). Só lê tipo, transporte, código, estado e se o campo `sent` foi preenchido;
  destinatário, correlation ID, assunto e corpo não são lidos.
- `Email\EmailFunnel`: classifica as linhas. Aceito (`sent`) e entregue (estado `delivered`) são
  métricas separadas. Dimensões sem `@`, até 128 caracteres, nunca vazias.
- `Reconciliation\EmailReconciler` e `Hook\EmailReconciliationCronHooks`: no cron, recalcula os últimos
  7 dias e substitui o valor de cada métrica do dia. Idempotente: rodar de novo ou depois de um webhook
  de entrega não conta em dobro.
- `AggregateStorageInterface::replaceDay()`: substitui os totais de um dia na mesma transação. Usado
  só por conciliações; `record()` continua somando.
- Catálogo: `email.messages.queued`, `email.messages.delivered` e `email.messages.bounced` incluídas;
  `accepted` e `failed` passam a ser alimentadas. Retenção de 90 dias na configuração instalada.

### Decisões
- Dia de cada métrica é o da solicitação (`created`), com estado atual. Um envio entregue depois muda o
  contador do próprio dia, enquanto ele estiver na janela de 7 dias. Depois disso o dia fica como foi
  calculado. Alternativa descartada: contar pelo dia do evento, que exigiria gravar cada transição.
- O conector SMTP2GO não entrou: a API não tem contagem de entregues (ver INTEGRATIONS), e o uso dele
  depende de decisão do responsável.
- Conciliação entre Brevo, IMAP e SMTP2GO não tem código próprio: o `aculta_mailer` já grava tudo no
  registro único, pelo `correlation_id`. O Statistics lê esse registro.
- Versão do manifesto passa a `0.5.0-dev.1` (nova fase do roteiro).

### Testes
- Unit: 69 casos (inclui funil e reconciliador com dias vazios e substituição).
- Kernel: 20 casos, no banco `aculta_simpletest`. Inclui `replaceDay` e conciliação com registros reais
  do `aculta_mail_log` (dia de fora não entra; reconciliar duas vezes não soma; entrega tardia move
  de aceito para entregue sem duplicar).

### Produtor do Fórum (0.5.0-dev.1)
- `ForumActivitySubscriber` assina `aculta_forum.topic_created` e `aculta_forum.comment_created` e grava `forum.topics.created` (nova métrica, retenção de 90 dias) e `forum.comments.created`. Sem dependência de módulo: os nomes são textos.
- Testes: `ForumActivitySubscriberTest` (Unit). Unit do módulo: 72/72.

### Centralização (ADR-0004, aplicada junto)
- `aculta_links` (0.4.0-dev.31) grava cliques em `links.link.clicks` e lê os totais daqui; a tabela
  `aculta_links_stat_daily`, a fila e o cron próprios foram removidos. Sem migração (banco de teste).
- Validação no Runtime de teste (2026-10-21): `EmailReconciler` na janela de 7 dias gravou 69 envios (igual ao total do `aculta_mail_log`), com aceitos 41, entregues 4, devolvidos 1 e falhos 26. Cron do hook de e-mail ainda não observado no Runtime.

### Não feito
- Devolução `hard`/`soft` e `email.quota.reached`.
- Conector SMTP2GO.
- Validação no Runtime de teste com envios reais (não executada nesta entrega).
- Configuração instalada não reimportada: métricas novas usam o prazo padrão do catálogo (90 dias)
  até a reimportação (`RetentionPolicy::daysFor()`). Nenhuma alteração de schema, então sem `updb`.

## 0.4.0-dev.3 (conclusões por turma, resumo de estatísticas no aculta_mailer)

### Código
- `LmsClassResolver`: resolve a turma da conta no curso (leitura de `lms_classes` e `group_membership`, sem gravar).
  Conclusões passam a usar `curso/turma`; quando a turma não é única, `curso/sem-turma`.
- `StatisticsSummaryLines` implementa `StatisticsLinesProviderInterface` (aculta_mailer): linhas com pedidos,
  receita e reembolsos por moeda (formato brasileiro), cupons, carrinhos, matrículas, conclusões, certificados
  e leituras da Wiki da semana. Só agregados.
- Dependência `aculta_mailer:aculta_mailer`.

### Testes
- Unit: 63 casos (inclui linhas do resumo e conclusão por turma).
- Kernel: 15 casos, aprovados.

### Validação no Runtime de teste
- `aculta_qrcode` habilitado (decisão do responsável), sem atualizações de banco pendentes. O `cr` reconstrói.
- O provedor de linhas e o tipo `aculta_admin_statistics` estão registrados. O texto do resumo sai com os
  indicadores do intervalo (zerados: o ambiente não tem pedidos nem matrículas). Nenhum e-mail foi enviado na verificação.
- Home responde 200.

## 0.4.0-dev.2 (turma nas matrículas e supressão de célula pequena)

### Código
- `lms.enrollments.created` usa a dimensão `curso/turma`, a partir do `classId` do evento de matrícula.
- `CellSuppression::publicCount()`: agregado de turma com menos de 5 alunos não aparece em painel público.
  Regra pronta para os painéis da 0.8.0; relatórios internos seguem sem limite.

### Limite
- `CourseCompletedEvent` não traz a turma. Conclusões seguem por curso.

### Testes
- Unit: 61 casos (inclui supressão de célula e matrícula por curso/turma).
- Kernel: 15 casos, aprovados.

## 0.4.0-dev.1 (LMS: matrículas, conclusões e certificados, agregados por curso)

### Código
- `LmsEventsSubscriber` escuta os eventos de domínio do `aculta_lms` (contratos de notificação):
  - `CourseEnrollmentEvent` → `lms.enrollments.created`, por curso;
  - `CourseCompletedEvent` → `lms.courses.completed`, por curso, uma vez por status (deduplicado pelo UUID do status);
  - `CertificateIssuedEvent` → `lms.certificates.issued`, total, uma vez por certificado.
- Nomes dos eventos são textos: o módulo não depende de `aculta_lms` e segue funcionando sem ele.
- Deduplicação em `keyvalue.expirable` com validade de 400 dias (acima da retenção de 90 dias).
- Nenhum aluno, nota, resposta ou progresso é gravado: a dimensão é só o curso (ou nada, no certificado).

### Limites
- Certificado não traz o curso no evento: contado no total, sem recorte por curso.
- Recorte por turma não implementado (matrícula registra só o curso).
- Painel pedagógico e números mínimos por célula ficam para a 0.8.0.

### Testes
- Unit: 58 casos (inclui matrícula, conclusão repetida e certificado repetido).
- Kernel: 15 casos, aprovados.
- Runtime de teste: `cr` aplicado; os três eventos têm o assinante registrado. Sem matrículas reais no ambiente, o efeito real não foi exercitado.

## 0.3.0-dev.8 (cupons: pedidos com cupom e desconto por moeda)

### Código
- `CommerceOrderPaidSubscriber` conta `commerce.coupons.orders` (pedido com campo `coupons` preenchido) e
  `commerce.discounts.total` (em centavos, a partir dos ajustes `promotion` do pedido e dos itens). Ajuste em
  outra moeda é ignorado.
- Métricas e retenção (90 dias) no catálogo e na configuração.

### Testes
- Unit: 54 casos (inclui cupom e desconto).
- Kernel: 15 casos, aprovados.

### Estado da fase 0.3.0
- Itens do roteiro implementados no código: pedidos pagos e receita, reembolsos, reconciliação diária,
  unidades por variação, carrinhos abandonados, vendas por domínio (purpose no pedido) e cupons.
- Pendente: validação com pedidos reais no Runtime de teste (não há pedidos no ambiente) e o painel de receita líquida (0.8.0).

## 0.3.0-dev.7 (Google Tag com Consent Mode, antecipado da 0.6.0)

### Dependência
- `drupal/google_tag` 2.0.9 (GPL-2.0). Necessidade: carregar a tag do Google (GA4) pelo mecanismo
  oficial do Drupal, com Consent Mode, em vez de inserir o script no tema. `GOOGLE.md` recomenda
  o Google Tag 2.x. Instalado com `--minimal-changes`: só o pacote e os metadados dos mantenedores entram no lock.

### Código
- `js/consent.js`: quando o Google Tag está em Consent Mode, ele define o padrão negado; o banner só
  envia a atualização da decisão gravada. Sem o Google Tag, o padrão continua sendo definido aqui.

### Estado no Runtime de teste
- `google_tag` habilitado. Nenhum contêiner criado: sem o ID do Google, nenhuma tag é carregada.
- Página responde 200 e não contém script do Google.
- A propriedade (`G-…`) ainda deve ser informada pelo responsável para ativar a tag.

### Pendente
- ID da propriedade GA4 e criação do contêiner.
- Revisão de privacidade com Consent Mode: o `gtag.js` é carregado mesmo com consentimento negado (pings sem cookie).

## 0.3.0-dev.6 (vendas por domínio: purpose de origem no pedido)

### Código
- `CommerceOrderDomainHooks` (`commerce_order_presave`): grava o purpose atual nos dados do pedido novo
  (`getData`, sem alterar o esquema da entidade). Purpose inválido ou ausente não é gravado.
- `PurposeResolverInterface` com adaptador `PortalPurposeResolver`: lê o purpose do `aculta_portal` quando
  ele está instalado. A dependência é opcional, então o módulo segue funcionando sem o portal.
- `CommerceOrderPaidSubscriber` conta `commerce.orders.by_domain` e `commerce.revenue.by_domain` com a
  dimensão `purpose|moeda`. Pedido sem purpose conta como `sem_dominio`. Os totais por moeda não mudam.

### Decisão
- O domínio de origem foi gravado no pedido, por decisão do responsável. A alternativa de loja por domínio
  foi descartada.

### Testes
- Unit: 53 casos (inclui purpose no pedido pago e no presave).
- Kernel: 15 casos, aprovados.

## 0.3.0-dev.5 (carrinhos abandonados, 24 horas)

### Código
- `CartAbandonmentCronHooks` (cron): conta `commerce.carts.abandoned` para carrinhos (`cart`) em estado `draft`
  com itens, alterados na janela `AbandonedCartWindow` (dias D-2 inteiro, para D-1 no cron de D). Cada carrinho
  entra numa única janela diária, desde que o cron rode todo dia. Carrinho vazio não conta.
- Dependência `commerce:commerce_cart`.

### Limites
- Vendas por domínio: não implementado. O pedido não guarda o domínio de origem, e a loja é única (`online`).
  Não há vínculo confiável para a dimensão. Depende de decisão sobre loja por domínio.
- Exclusões de carrinho (além de vazio) não definidas.

### Testes
- Unit: 48 casos (inclui a janela de 24 horas).

## 0.3.0-dev.4 (unidades vendidas por variação de produto)

### Código
- `CommerceOrderPaidSubscriber` soma as unidades de cada item do pedido pago em `commerce.items.sold`,
  com o identificador da variação comprada como dimensão. Item sem entidade ou com quantidade não positiva é ignorado.
- Métrica e retenção (90 dias) no catálogo e na configuração.

### Testes
- Unit: 46 casos (inclui unidades por variação).
- Kernel: 15 casos, aprovados.
- Runtime de teste: `cr` aplicado. Sem pedidos no ambiente, o efeito real não foi exercitado.

## 0.3.0-dev.3 (reembolsos por incremento)

### Código
- `CommercePaymentRefundHooks` (`commerce_payment_update`): conta `commerce.refunds.total` em centavos
  pela diferença entre o reembolso atual e o original (`$payment->original`). Salvar o mesmo reembolso
  de novo não conta. Troca de moeda entre versões não gera incremento.
- Receita líquida fica para a leitura (bruta menos reembolsos): não se grava valor negativo.
- Dependência `commerce:commerce_payment`. Métrica e retenção (90 dias) no catálogo e na configuração.

### Testes
- Unit: 45 casos (inclui 5 do incremento de reembolso).
- Kernel: 15 casos, aprovados após a nova dependência.
- Runtime de teste: `cr` aplicado; o hook `commerce_payment_update` está registrado para o módulo.
  Não há pagamento real no ambiente, então o incremento em dados reais não foi exercitado.

## 0.3.0-dev.2 (reconciliação diária com os pedidos do Commerce)

### Código
- `CommerceReconciler`: totais oficiais por dia UTC e moeda (pedidos `completed` cujo `completed` cai no dia),
  comparados com os contadores do Statistics. Doações entram na mesma contagem.
- `ReconciliationDiff`: comparação pura; qualquer diferença em pedidos ou centavos é reportada.
- `ReconciliationCronHooks` (`#[Hook('cron')]`): reconcilia o dia UTC anterior e registra diferença como
  `warning` no canal `aculta_statistics`. Não altera contadores nem dados do Commerce. Sem `commerce_order`
  ativo, não faz nada.
- `AggregateStorageInterface::dimensions()`: moedas com linha registrada num dia.

### Limites conhecidos
- Pedidos pagos antes da flag `paid_event_dispatched` do Commerce podem aparecer como diferença. Isso é esperado.
- A comparação usa o dia do `completed` do pedido; o contador usa o dia do evento. Pedidos pagos num dia
  e concluídos no dia seguinte aparecem como diferença nos dois dias.

### Testes
- Unit: 40 casos (inclui 4 da comparação).
- Kernel: 15 casos (inclui `dimensions()`).
- Runtime de teste: consultas de reconciliação executam sem erro (sem pedidos no ambiente, resultado vazio).
  O cron completo não foi executado de propósito, para não disparar tarefas de outros módulos.

## 0.3.0-dev.1 (Commerce: pedidos pagos e receita bruta)

### Código
- `CommerceOrderPaidSubscriber` escuta `commerce_order.order.paid`, disparado uma única vez por pedido
  na transição para pago (flag `paid_event_dispatched` do Commerce). Grava `commerce.orders.paid` e
  `commerce.revenue.gross` (centavos), com a moeda ISO como dimensão. Pedido gratuito conta o pedido sem receita.
- Falhas de gravação viram aviso/erro no log do canal `aculta_statistics` e não interrompem o pedido.
- Métrica `commerce.revenue.gross` no catálogo e na configuração de retenção (padrão 90 dias).
- Dependência `commerce:commerce_order`.

### Pendente nesta fase (não entregue ainda)
- Reconciliação com os pedidos oficiais do Commerce.
- Reembolsos e cancelamentos (`commerce.refunds.total`).
- Carrinho abandonado, produtos mais vendidos, cupons e vendas por domínio.

### Testes
- Unit: 36 casos (inclui 4 do subscriber de pedido pago).
- Kernel: 14 casos, aprovados.
- Runtime de teste: `cr` aplicado; o subscriber aparece como listener de `commerce_order.order.paid`. Nenhum pedido foi criado na verificação (compras de teste seguem o procedimento de `teste.comum`).

## 0.2.0-dev.4 (controle antiabuso do contador da Wiki)

### Código
- `VisitorKey`: identificador pseudonimo do visitante, HMAC-SHA256 do IP com o `hash_salt` do site e um escopo.
  O IP não é guardado em claro. `VisitorKeyFactory` lê o salt do bootstrap.
- Limites no beacon (`WikiReadController`), usando o serviço `flood` do Core:
  - uma leitura por visitante e verbete a cada 30 min; repetições respondem 204 sem contar;
  - no máximo 60 leituras contadas por visitante a cada hora; depois disso, 429 com `Retry-After`.
- Serviços `aculta_statistics.visitor_key` e `aculta_statistics.wiki_read_controller` com as dependências explícitas.

### Testes
- Unit: 31 casos (controlador com limite por visitante, dedupe, visitantes diferentes e chave pseudonima).
- Kernel: 14 casos, aprovados.
- Runtime de teste: duas leituras do mesmo visitante no mesmo verbete contam uma vez; identificadores do `flood` têm 64 caracteres hexadecimais e nenhum contém o IP. Dados de teste removidos depois.

## 0.2.0-dev.3 (contador de leitura da Wiki)

### Código
- Beacon `POST /estatisticas/wiki/{node}/leitura` (`aculta_statistics.wiki_read_controller`): grava
  `wiki.entries.read` com o nid como dimensão, pelo ponto único `StatisticsRecorder`. Responde 204;
  rascunho, outro tipo de conteúdo ou sem permissão de visualização respondem 404.
- `WikiReadHooks` (`node_view`, visualização completa de `wiki_entry` publicado): anexa a biblioteca
  `aculta_statistics/wiki-read` e a URL do beacon. `js/wiki-read.js` envia uma leitura por página
  com `sendBeacon` (ou `fetch` com `keepalive`), sem cookie.
- Dependência declarada em `drupal:node` (o controlador tipa `NodeInterface`).
- Retenção do contador: a métrica `wiki.entries.read` segue o prazo configurado no painel (padrão 90 dias).

### Decisão
- A leitura da Wiki foi antecipada do roteiro da 0.7.0 por decisão do responsável (contador próprio,
  sem consentimento, com retenção por métrica). Base legal confirmada em 2026-10-20 e registrada em `LGPD-INVENTARIO.md`.

### Testes
- Unit: 25 casos (incluindo 4 do controlador da Wiki).
- Kernel: 14 casos (incluindo 3 do beacon). Cada requisição HTTP fica em teste próprio: o Kernel de teste
  mantém o estado da requisição anterior e uma segunda chamada na mesma execução responde 405 por engano.

### Validação no Runtime de teste
- `wiki420.aculta.toca.net.br`: beacon em verbete publicado responde 204 e a leitura é contada (teste de
  fumaça; a linha de teste foi removida depois). Verbete inexistente responde 404.
- `conta.aculta.toca.net.br` responde 404 para verbete da Wiki, pela política de purpose: verbete não se serve fora do domínio `wiki420`.
- Página do verbete responde 200 e anexa o `drupalSettings.acultaStatistics.wikiRead`.

## 0.2.0-dev.2 (banner de consentimento e testes de formulário e cron)

### Código
- Opção `consent_banner.enabled` (padrão desligada) em configuração, com schema.
- `ConsentBannerHooks` (`page_attachments`): anexa a biblioteca `aculta_statistics/consent`
  e a configuração de cookie e Consent Mode só quando a opção está ligada. A tag
  `config:aculta_statistics.settings` invalida o cache ao alternar a opção.
- `js/consent.js`: Consent Mode v2 com padrão negado, cookie `aculta_consent_v1`
  (180 dias, SameSite=Lax) e atualização ao aceitar ou recusar. Sem banner de terceiros.
- Aliases `services.yml` por classe, para autowiring do hook.

### Testes
- Kernel: 11 casos (storage, cron por métrica, formulário de retenção, hook do banner), aprovados contra `aculta_simpletest`.
- Unit: 21 casos, aprovados.

### Validação
- Runtime de teste: `drush cr` aplicado; página de `conta.aculta.toca.net.br` responde 200; banner presente com `hidden`; biblioteca não anexada (opção desligada).

## 0.2.0-dev.1 (núcleo analítico, em andamento)

### Código
- `aculta_statistics.info.yml` com versão `0.2.0-dev.1`, core `^11.3`, sem dependência de outros módulos.
- Schema `aculta_statistics_daily` em `aculta_statistics.install` (chave `(metric, dimension, day)`, índice `(metric, day)`).
- Catálogo de métricas (`MetricCatalog`, `MetricDefinition`) com sete métricas iniciais.
- Gravador único `StatisticsRecorder`: recusa identificador fora do catálogo.
- `DailyAggregateStorage` ganhou `countBefore` e `purgeMetricBefore`.
- Retenção por métrica (`RetentionPolicy`) em configuração: lista `retention` com `metric` e `days`
  (dias; 0 = indefinido). Chaves de configuração não aceitam ponto, por isso a lista.
- Cron aplica o prazo de cada métrica (`StatisticsCronHooks`).
- Formulário de retenção com confirmação quando a redução apaga linhas (`RetentionSettingsForm`).
- Rota `/painel-administrativo/aculta/estatisticas/retencao`, permissão `administer aculta_statistics`.
- Aliases por classe e interface no `services.yml` para autowiring.

### Verificação
- Unit: 21 testes, aprovados (PHPUnit 11.5.57, PHP 8.4.26 local).
- Kernel: 6 testes, aprovados contra `aculta_simpletest` (procedimento de `docs/operations/BANCO-DE-TESTE.md`).
- Aviso conhecido: `sites/simpletest/browser_output` não gravável (não afeta Kernel).

### Não feito nesta versão
- Ativação no Runtime de teste (`updb`) e schema instalado lá.
- Banner de consentimento para visitante anônimo (tema ACULTA420), autorizado mas não iniciado.
- Painel de consulta de métricas (dashboards são 0.8.0).
