# ACULTA Statistics

Central de métricas da plataforma ACULTA: catálogo, agregação, consulta e painel.
Não substitui os dados de origem. Pedidos ficam no Commerce, matrículas e progresso
no LMS, mensagens no sistema de e-mail.

**Estado:** fase 0.1.0, fundação. Módulo não ativado (sem `*.info.yml`).

## Documentação

- [Estado atual](docs/CURRENT-STATE.md): produtores de métrica e classificação, incluindo Fórum, Wiki e LMS.
- [Arquitetura](docs/ARCHITECTURE.md)
- [Catálogo de métricas](docs/METRIC-CATALOG.md)
- [Integrações](docs/INTEGRATIONS.md)
- [Matriz de portabilidade](docs/PORTABILITY-MATRIX.md)
- [Roteiro](docs/ROADMAP.md) e [changelog](docs/CHANGELOG.md)
- [ADRs](docs/adr/)

## Código

- `src/Storage/`: contador diário (adaptado de `drupal/statistics`).
- `src/Hook/StatisticsCronHooks.php`: retenção no cron.
- `src/Connector/SearchConsole/`: janela e variação do Search Console (adaptado de `drupal/analyze_search_console`).
- `tests/src/Unit/`: testes unitários.

Código de terceiros adaptado mantém a licença de origem (GPL-2.0-or-later) e
o commit de referência no cabeçalho do arquivo.
