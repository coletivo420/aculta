# Matriz de portabilidade — ACULTA Statistics

Auditoria de 2026-10-20. Os projetos foram clonados em uma pasta temporária do
scratchpad (não instalados no site) e lidos no código-fonte. Versões, licenças e
compatibilidade vêm das páginas do drupal.org consultadas nessa data.

Ações: **PORTAR** (código adaptado com atribuição), **REFERÊNCIA** (estudado, sem
código copiado), **REUTILIZAR** (já existe no ACULTA), **ADIAR** (depende de
decisão), **DESCARTAR** (não compatível ou sem necessidade).

## Código portado nesta fase

| Projeto | Versão / commit | Licença | Drupal | Ação | Destino no ACULTA |
|---|---|---|---|---|---|
| drupal/statistics | 1.0.1 (2026-03-01); commit `fe20f9f` | GPL-2.0-or-later | ^10.3 \|\| ^11 | PORTAR | `src/Storage/DailyAggregateStorage.php` (padrão `merge` por dia; removidos IP/REQUEST_TIME) |
| drupal/analyze_search_console | 1.0.0 (2026-10-01); commit `6f054ac` | GPL-2.0-or-later | ^10.3 \|\| ^11 | PORTAR (só lógica) | `src/Connector/SearchConsole/SearchAnalyticsWindow.php`, `SearchMetricsDelta.php` |

Trechos portados, por arquivo:

- `NodeStatisticsDatabaseStorage::recordView()` → `DailyAggregateStorage::record()`.
  Mudança: chave `(metric, dimension, day)` em vez de `nid`; tempo por `TimeInterface`.
- `NodeStatisticsDatabaseStorage::resetDayCount()` e `maxTotalCount()` → não portados.
  O contador do dia é derivado da data, não zerado por cron. Isso evita reescrever
  todas as linhas diariamente.
- `SearchConsoleClient::buildRequest()` (janela de datas) → `SearchAnalyticsWindow::forDays()`.
  Mudança: `DateTimeImmutable` injetado, limite de 480 dias, sem objeto Google.
- `SearchConsoleClient::calculateChange()` e `formatPercentageChange()` → `SearchMetricsDelta`.
  Mudanças: sem texto formatado (apresentação é do ACULTA420); variação a partir de
  zero retorna `NULL` em vez de "+100%".

Não portados do GSC: `getWebmastersService()`, OAuth, `OAuthCallbackController`
e o cache. Motivo: usam `drupal/key` e token em `state`, que o AGENTS proíbe para
credenciais novas, e exigem `google/apiclient`, que não está no `composer.lock`
(adicionar a dependência exige justificativa à parte). Credenciais futuras vêm de
`aculta_portal.secrets_manager`.

## Referência, sem código copiado

| Projeto | Versão / data | Licença | Drupal | Ação | O que aprendemos |
|---|---|---|---|---|---|
| drupal/google_analytics_reports | 4.0.1 (2025-12-28) | GPL-2.0-or-later | ^10 \|\| ^11 | REFERÊNCIA | Consulta GA4 via `runReport` da Data API **v1beta** (confirmado em `GoogleAnalyticsQuery.php`), com Views como interface. Não portar o plugin Views agora: é pesado e depende de biblioteca Google não instalada. O cliente GA4 deve nascer do zero sobre a Data API, na fase 0.6.0. A página do projeto não confirma suporte à Data API no texto consultado. |
| drupal/analyze | 1.4.1 (2026-10-20) | GPL-2.0 | ^10.3 \|\| ^11 | REFERÊNCIA | Aba de análise por entidade e analisadores plugáveis (statistics, GA, GSC, SEO). Confirma o caminho de relatório contextual por entidade (fase 0.8.0). Analisadores de IA ficam fora de escopo. |
| drupal/charts | 5.2.4 (2026-09-14) | GPL-2.0-or-later | ^10.3 \|\| ^11 \|\| ^12 | ADIAR | Ativo e com API de plugins por biblioteca (Chart.js, Highcharts, etc.). Não adotar antes da decisão sobre gráficos. Não copiar bibliotecas JS. |
| drupal/dashboard | 2.2.1 (2026-08-17) | GPL-2.0 | ^11.2 | REFERÊNCIA | Só funciona em Drupal 11.2+. Painel do ACULTA deve seguir o design system do ACULTA420, não este módulo. |
| drupal/google_tag | 2.0.9 (2025-08-25) | GPL-2.0 | ^9.5 \|\| ^10 \|\| ^11 | ADIAR | Suporta GTM e gtag.js. A página consultada não cita Google Consent Mode. Não instalar sem decisão de consentimento (`GOOGLE.md`). |
| drupal/klaro | 3.1.2 (2026-10-07) | GPL-2.0 | ^10.2 \|\| ^11 | ADIAR | Gerencia consentimento e suporta Consent Mode v2. Candidato para o gate de GA4, fase 0.6.0. Registra IP apenas se a opção "Log unknown resources" for ligada: deve ficar desligada. |
| drupal/eca_commerce | 2.1.1 (2025-08-15) | GPL-2.0 | ^10.4 \|\| ^11.2 | REFERÊNCIA | Expõe os eventos do Commerce Core ao ECA. Não instalar: os mesmos eventos estão disponíveis pela API nativa do Commerce 3.3.10 (ver CURRENT-STATE.md). |
| drupal/commerce_reports ("Commerce Reporting") | 1.0-rc4 (2025-02-04); sem release estável | GPL-2.0 | ^9 \|\| ^10 \|\| ^11; Commerce 3 | ADIAR | Nome correto do projeto: `commerce_reports` (o nome `commerce_reporting` deu 404). Compatível com D11, mas só tem release candidata; o próprio projeto declara "no supported stable releases". Consultas diretas ao banco de pedidos, sem estado de reembolso refletido no histórico (segundo a documentação da versão 7.x). Não instalar: o adaptador próprio sobre eventos nativos do Commerce cobre o escopo. |
| drupal/symfony_mailer_log (Mailer Plus Log) | 1.2.7 (2026-09-02) | GPL-2.0 | ^10.3 \|\| ^11 \|\| ^12 | REFERÊNCIA | Registra mensagens do Mailer Plus como entidades. O ACULTA já tem `aculta_mail_log`; não duplicar. Ciclo de vida, status e retenção não estão descritos na página. |
| drupal/trace_mail_log | 1.1.2 (2026-01-26) | GPL-2.0 | ^10.3 \|\| ^11 | REFERÊNCIA | Captura transcrição SMTP e status (queued, sending, sent, failed), com opção de não guardar o corpo. Útil como checklist de diagnóstico. Guarda destinatários e transcrição completa: não serve como armazenamento do Statistics. |
| SMTP2GO API (developers.smtp2go.com) | — | — | — | REFERÊNCIA | Verificado em 2026-10-20: `/stats/email_cycle` (enviados no ciclo), `/stats/email_history` (agregado com `bounces` e `rejects` por grupo, sem status por mensagem) e webhooks. **Não há métrica de entregues** em nenhum desses endpoints. SMTP2GO é só contingência no ACULTA. |

## Reutilização interna (já existe no ACULTA)

| Recurso | Módulo | Decisão |
|---|---|---|
| Agregado diário de cliques, retenção de 90 dias, cron com `#[Hook]` | `aculta_links` (`aculta_links_stat_daily`, `LinkAnalytics`) | REUTILIZAR o padrão. Convergência futura: o Statistics deve assumir o agregado de links. Não fazer agora: mudaria o módulo de Links, que tem alterações de outra frente. |
| Log de envio e painel de contagens | `aculta_mailer` (`aculta_mail_log`, `MailDashboard`) | REUTILIZAR como fonte. |
| Contagens de comentários | Core `comment` via `comment_count` nas Views de `aculta_forum` | REUTILIZAR como fonte. |
| Painel de progresso e de turma | `aculta_lms` (`LearningDashboardManager`) | REUTILIZAR como fonte. |

## Licenças e obrigações

- Todos os projetos clonados são GPL-2.0 ou GPL-2.0-or-later. O código portado é compatível com o restante do projeto (GPL-2.0-or-later).
- Cada arquivo portado traz cabeçalho com o projeto, a licença e o commit de origem.
- Nenhum código dos projetos foi copiado para fora do módulo. O diretório temporário do scratchpad não entra no Git.
- Nenhum módulo contrib foi instalado para este estudo.
