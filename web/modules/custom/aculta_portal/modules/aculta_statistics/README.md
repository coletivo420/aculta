# ACULTA Statistics

Central de métricas da plataforma ACULTA: catálogo, agregados diários, retenção
configurável por métrica e consulta. Não substitui os dados de origem. Pedidos
ficam no Commerce, matrículas e progresso no LMS, mensagens no sistema de e-mail.

**Estado:** módulo ativo no código (fase 0.2.0). Ativação no Runtime de teste pendente.

## O que existe

- Catálogo de métricas em código (`src/Catalog/MetricCatalog.php`): identificador,
  rótulo, unidade, sensibilidade (P, I, R) e prazo padrão.
- Gravação pelo ponto único `aculta_statistics.recorder`: recusa métricas fora do catálogo.
- Armazenamento diário `aculta_statistics_daily` (`aculta_statistics.install`),
  sem IP, usuário, referrer ou user-agent.
- Retenção por métrica (`RetentionPolicy`): 30, 90, 180, 365 dias ou indefinido. Padrão 90.
- Cron (`StatisticsCronHooks`): aplica o prazo de cada métrica.
- Painel de retenção em `/painel-administrativo/aculta/estatisticas/retencao`,
  com confirmação antes de reduzir prazo (permissão `administer aculta_statistics`).

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

- Unit: `tests/src/Unit/` (catálogo, retenção, gravador). 21 casos.
- Kernel: `tests/src/Kernel/` (storage e cron de retenção). 6 casos, executados
  contra `aculta_simpletest`.
