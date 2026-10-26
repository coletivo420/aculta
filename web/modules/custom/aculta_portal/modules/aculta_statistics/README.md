# ACULTA Statistics

**Versão:** `0.9.0-dev.9` — concessão da permissão granular ao papel content_editor.

Central de métricas da plataforma ACULTA: catálogo, agregados diários, retenção
configurável por métrica e consulta. Não substitui os dados de origem. Pedidos
ficam no Commerce, matrículas e progresso no LMS, mensagens no sistema de e-mail.

**Estado:** ativo no Runtime MAIN, confirmado por Drush em 2026-10-26. Consultas GA4/Search Console com credenciais e homologação visual permanecem pendentes.

## O que existe

- Catálogo de métricas em código (`src/Catalog/MetricCatalog.php`): identificador,
  rótulo, unidade, sensibilidade (P, I, R) e prazo padrão.
- Gravação pelo ponto único `aculta_statistics.recorder`: recusa métricas fora do catálogo.
- Armazenamento diário `aculta_statistics_daily` (`aculta_statistics.install`),
  sem IP, usuário, referrer ou user-agent.
- A consulta `AggregateStorageInterface::dimensionTotalsForDimensions()` soma
  até 100 dimensões selecionadas em uma única consulta agrupada; consumidores
  como o Content Index podem correlacionar uma página já autorizada sem ler
  todas as dimensões globais. A autorização do conteúdo continua no módulo dono.
- `AuthorizedEntityMetricAggregator` implementa o contrato opcional do Portal;
  exige `view content-level statistics` para métricas públicas da allowlist e
  `administer aculta_statistics` para métricas internas. Métricas restritas
  permanecem negadas. Reutiliza a agregação SQL existente, sem dependência
  obrigatória ou junção entre esquemas.
- Retenção por métrica (`RetentionPolicy`): 30, 90, 180, 365 dias ou indefinido. Padrão 90.
- Cron (`StatisticsCronHooks`): aplica o prazo de cada métrica.
- Funil de e-mails por dia de solicitação, a partir do `aculta_mail_log` (aceito, entregue, devolvido, falho), recalculado no cron (`EmailReconciler`).
- Pedidos pagos e receita bruta por moeda, a partir de `commerce_order.order.paid` (fase 0.3.0, em andamento).
- Painel de retenção em `/painel-administrativo/aculta/estatisticas/retencao`,
  com confirmação antes de reduzir prazo (permissão `administer aculta_statistics`).
- Contador de leitura da Wiki: beacon `POST /estatisticas/wiki/{node}/leitura` em verbetes publicados
  (`wiki.entries.read`, dimensão = nid, retenção configurável). Antiabuso: uma leitura por visitante
  e verbete a cada 30 min, até 60 por visitante por hora, com identificador pseudonimo do visitante.
- Banner de consentimento para visitante anônimo (`consent_banner.enabled`, desligado por padrão):
  biblioteca `aculta_statistics/consent`; o marcado visual é do tema (`consent-banner`).

## Documentação

- [Estado atual](docs/CURRENT-STATE.md) e [arquitetura](docs/ARCHITECTURE.md)
- [Catálogo de métricas](docs/METRIC-CATALOG.md) e [integrações](docs/INTEGRATIONS.md)
- [Matriz de portabilidade](docs/PORTABILITY-MATRIX.md)
- [Roteiro](docs/ROADMAP.md) e [changelog](docs/CHANGELOG.md)
- [ADRs](docs/adr/)

## Código portado

- `src/Storage/DailyAggregateStorage.php`: adaptado de `drupal/statistics` (GPL-2.0-or-later, commit `fe20f9f`).
- `src/Connector/SearchConsole/`: adaptado de `drupal/analyze_search_console` (GPL-2.0-or-later, commit `6f054ac`).

## Testes

- Unit: `tests/src/Unit/` (catálogo, retenção, gravador, janela e variação do GSC, controlador da Wiki, pedido pago). 108 testes / 438 assertions PASS em 2026-10-26.
- Kernel: `tests/src/Kernel/` (storage, cron, formulário de retenção, banner, beacon da Wiki). 47 testes / 319 assertions PASS em 2026-10-26, executados contra `aculta_simpletest`.
- `StatisticsDashboardFunctionalTest`: o alias do mapper foi corrigido pelo owner Melhor Envio em `0.1.0-dev.2`, mas a nova tentativa ainda não produziu resultado funcional: com Core duplicado no docroot, o processo servidor tentou declarar `system_hook_info()` a partir de dois caminhos; com o Core compartilhado read-only, o runner ficou sem avançar além do cabeçalho do PHPUnit e foi encerrado. A classe permanece DEFERRED (nenhuma assertion após a correção do wiring); não atribuir esta falha de harness ao painel. Sem escrita no Runtime; revisão visual pendente.
