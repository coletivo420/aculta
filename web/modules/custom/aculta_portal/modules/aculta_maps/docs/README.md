# ACULTA Maps (`aculta_maps`)

Submódulo de mapas do ACULTA, reutilizável por qualquer parte do site (eventos e locais, revista, wiki, apoio e outros).
Decisão: D-19 em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`. Provedores: ADR-030.

| Arquivo | Conteúdo |
| --- | --- |
| [PLANO-IMPLANTACAO.md](PLANO-IMPLANTACAO.md) | Escopo, API pública, provedores, privacidade, migração do código de `aculta_events` e riscos |
| [ROADMAP.md](ROADMAP.md) | Roadmap único do submódulo, com fases, critérios e status |

Regra de uso: nenhum módulo chama o Google, o OpenStreetMap ou o Nominatim diretamente. Todo mapa e toda
geocodificação passam pelo `aculta_maps`.
