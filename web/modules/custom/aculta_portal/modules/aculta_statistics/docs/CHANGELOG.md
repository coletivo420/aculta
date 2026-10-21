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
- Consentimento reutiliza o `agreement` do `aculta_portal` (não há módulo de consentimento no `aculta_user`).
- Contador de leitura da Wiki próprio; QR estático medido só nos novos; convergência com `aculta_links` na 0.7.0; GA4 com banner para anônimos mediante autorização de tema.
- Retenção configurável por métrica no painel administrativo (30, 90, 180, 365 dias ou indefinido), com permissão própria e confirmação antes de reduzir prazo (ADR-0003).
