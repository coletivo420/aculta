# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# ACULTA Maps + OpenStreetMap — Diagnóstico, Visual e Homologação — 2026-11-07

- **Objetivo:** diagnosticar mapas não renderizados, corrigir causa raiz, alinhar apresentação visual no tema V2 e homologar funcional e visualmente.
- **Estado:** causa diagnosticada e documentada (EventLocation::preSave só geocodificava em isNew ou mudança de endereço; local salvo sem coordenadas ficava sem marker); corrigido no owner aculta_events. Ordem mobile-first (mapa -> aviso -> lista), URLs canônicas e categorias aplicadas. Tema V2 atualizado com alturas responsivas (18rem/24rem/30rem), Leaflet attribution legível e foco visível. Ponte SDC map-popup consolidada com story mínima. Homologação Playwright Firefox ESR completa (320, 390, 768, 1024, 1440 px, claro/escuro, zoom 200%, sem JS) com PASS e sem overflow.
- **Decisões:** OSM/Leaflet é o padrão sem chave. OpenStreetMap integration administrativa no MAIN protegida por permission (403 anônimo) e sem escrita externa.
- **Cuidados:** nenhuma escrita no OSM; nenhuma alteração destrutiva ou de banco no Runtime; nenhuma credencial gravada.
- **Commits e arquivos:** 7f05e3b21b, 1ea74f2ddf, c2ca27a068; aculta_maps (docs, status, TODO), aculta_events (Entity, Controller, Kernel tests), aculta420_v2 (SDC stories, styles, docs), capturas (manifest.json).
- **Verificações:** Kernel MapControllerKernelTest 4/4 PASS (79 assertions); Kernel OpenStreetMapOAuthSafetyKernelTest 4/4 PASS; SDC Schemas PASS (52 components, 1126 checks); Mapped Classes PASS; Stylelint PASS; PHPCS PASS; Homologação Playwright Firefox 24 capturas PASS.
- **Pendências e bloqueios:** atualização de coordenadas do local existente no banco aguarda atualização/re-save via interface administrativa pelo responsável (sem UPDATE direto no DB conforme AGENTS.md); minuta de privacidade 0.6 pendente de aprovação antes de publicação.
- **Próximo passo:** aprovação da minuta de privacidade com o responsável e homologação das telas de edição/importação com usuário autenticado.

# V2 — UI Patterns Library — 2026-11-07

- **Objetivo:** organizar visualmente `/painel-administrativo/aparencia/ui/components`, corrigir a barra institucional e definir container próprio centralizado.
- **Estado:** commits `ed04d341e4`, `4987d69102`, `c57fd98d68` e `df5351c35c` publicados; esta rodada adicionou stories ausentes e converteu slots HTML para render arrays Drupal. Stories PHP são resolvidas no preprocess do overview/single; menu usa o provider atual e a barra flui dentro do bloco.
- **Decisões:** stories/pré-visualizações aparecem antes dos metadados recolhidos. CSS contém overflow e elementos posicionados por componente. Nada de colunas artificiais.
- **Cuidados:** preview Chromium com CSS local injetado confirmou 108 blocos e sem overflow em 320/1440. Drupal respondeu 200 após corrigir declaração duplicada que causava 500. Cache foi reconstruído após a implementação; aceite visual autenticado permanece pendente.
- **Commits e arquivos:** `ed04d341e4`, `4987d69102`, `c57fd98d68`, `df5351c35c` e commit desta atualização; tema `aculta420_v2` (hooks, stories, layout, templates, CSS, testes, TODO, CHANGELOG e docs).
- **Verificações:** renderer Drupal 12/12 na rodada atual (Badge, Button, Conexões, Rodapé, Ícone, Wiki, Data Table e Form Field); rodada anterior Dialog/Menu/Barra/Pagination 4/4 e `utility-drawers` confirmado. Stories dinâmicas customizadas e hook testados. Schema SDC 55/764, PHP lint e Stylelint do CSS alterado PASS. `npm run lint:css` encontra violações preexistentes em `src/styles/forum.css`; gate V2 SDC encontra falso positivo `__add-btn` em `studio-calendar.twig`. Token budget e `git diff --check` PASS.
- **Pendências e bloqueios:** cache Drupal reconstruído no Runtime. `updatedb:status` e `config:status` ainda mostram pendências preexistentes, preservadas sem execução/import. Aceite visual autenticado e teste de clique no browser permanecem pendentes.
- **Próximo passo:** confirmar visualmente e clicar nas prévias no overview autenticado; não importar configuração nem executar updates sem escopo explícito.

# V2 — Stylelint CSS — 2026-11-07

- **Objetivo:** corrigir as violações Stylelint no CSS da V2 e fazer a configuração compatível com os contratos locais.
- **Estado:** CSS normalizado pelo preset Drupal; nenhuma mudança intencional de apresentação. Stylelint e gate de fundações PASS.
- **Decisões e alternativas descartadas:** mantidas regras compactas usadas pelo tema; exceções para `dvh`, `@container`, `@starting-style`, `.75rem` e nomes Inter/Oswald, porque o preset legado conflita com recursos e contratos validados da V2.
- **Cuidados:** auto-fix reordena declarações e normaliza valores equivalentes; `color-scheme` permanece no grupo claro. Não substituir o preset sem repetir os gates de fundações.
- **Commits e arquivos:** `ecdb0a84f8`; `.stylelintrc.json`, CSS em `web/themes/custom/aculta420_v2`, TODO, CHANGELOG e roadmap.
- **Verificações:** `npm run lint:css`, `php scripts/validate-aculta420-v2-foundations.php`, `php scripts/validate-agent-token-budget.php` e `git diff --check` PASS. Captura visual não repetida: CSS passou lint e gate de tokens; sem alteração de comportamento pretendida.
- **Pendências e bloqueios:** nenhuma desta entrega; validação visual de fases anteriores mantém seu status próprio.
- **Próximo passo:** seguir com o próximo item do roadmap V2.
