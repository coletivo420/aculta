# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status: `docs/operations/status/aculta420_v2.yml`. ADR-054/055 seguem abertas.

## Próximo passo

- **Superfícies 2026-11-12:** canvas branco e tom green-soft por padrão nos três SDCs; raised neutro. Rodar gates, UI Library, dez domínios, acessibilidade e aceite visual real antes de declarar homologado. Ver docs/architecture/ACULTA420-V2-HOMOLOGACAO-SUPERFICIES-2026-11-12.md.

- **US-00–14/RN-00–05:** layouts 22 configs, gate 276 PASS. UI Skins 300/300 nos dez domínios em cinco larguras/modos; contraste ≥7,16:1, sem overflow e landmarks únicos. Cache-hit, zoom 200%, teclado, perfis e aceite humano pendentes. UI Styles Block em Documentos; UI Patterns Views parciais (Revista, Home, galeria, Fórum, Eventos, Transparência, Loja, Wiki), preservando consulta, ACL e render arrays. Wiki 247/247 em cinco larguras e dois modos; Loja 10/10. Cache-hit, zoom, perfis, tecnologia assistiva e aceite humano pendentes.
- Composer: Skins ^1.2, Icons ^2.1. Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1` `BLOCKED_UPSTREAM` (segurança/Core 12). UI Styles CKEditor 5 e Entity Status ativos, validação parcial. UI Patterns CKEditor 5 sem consumidores, `LEGACY_OBSOLETE`; Picker ativo, aceite visual pendente.
- Inventário: atualizar gerador após cada microfase; SDC `layout-grid` inclui `max_columns`. Manter componentes descobertos por UI Patterns Library.
- CSP (2026-11-11): zero violações `style-src` nas rotas públicas válidas testadas; nonce UI Skins, estilos HTMX externos e modal sem atributo inline. Autenticado DEFERRED: UID 205 ausente no Runtime.

## Marcos e pendências

- SDC/Library: gate atual no inventário; UI Skins dez domínios 300/300, contraste ≥7,16:1; manifest `tests/browser/capturas/ui-skins-ten-domains/2026-11-11/manifest.json`. Catálogo admin 84 SDCs. Drawer Claro fora da viewport a 320; zoom/teclado pendentes.
- Pendentes: completar inventário das dez áreas e interfaces administrativas; P8/P9, cache isolation/hit, contraste, zoom, teclado/perfis, rollback e aceite amplo.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.
