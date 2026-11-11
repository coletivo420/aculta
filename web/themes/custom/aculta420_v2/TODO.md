# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`. ADR-054/055 seguem abertas.

## Próximo passo

- **US-00–14/RN-00–05:** layouts 22 configs, gate 276 PASS. UI Skins sem carryover A/B/A; contraste e aceite dos dez domínios pendentes. UI Styles Block em Documentos; UI Patterns Views migradas parcialmente (Notícias, Home, galeria, Fórum, Eventos, Transparência, Loja e Wiki), preservando consulta, rows, ACL e render arrays nativos. Wiki 215/215 em 5 larguras/2 modos; Loja 10/10. Cache-hit, zoom 200%, perfis editor/admin, tecnologia assistiva e aceite visual humano pendentes.
- Composer: Skins ^1.2, Icons ^2.1. Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` `BLOCKED_UPSTREAM` (segurança/Core 12). UI Styles CKEditor 5 e Entity Status ativos, validação parcial. UI Patterns CKEditor 5 sem consumidores, `LEGACY_OBSOLETE`; Picker ativo, aceite visual pendente.
- Inventário: atualizar gerador após cada microfase; SDC `layout-grid` inclui `max_columns`. Manter componentes descobertos por UI Patterns Library.

## Marcos e pendências

- SDC/Library: gate atual no inventário versionado; Links A/B/A e capturas em `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json`.
- Pendentes: completar inventário das dez áreas e interfaces administrativas; P8/P9, cache isolation/hit, contraste, zoom, teclado/perfis, rollback e aceite amplo.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.
