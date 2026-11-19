# Registro preservado ao abrir o ciclo Fórum 0.2.0

# V2 - UI Patterns Library - 2026-11-07

- **Objetivo:** organizar visualmente `/painel-administrativo/aparencia/ui/components`, corrigir a barra institucional e definir container próprio centralizado.
- **Estado:** commits `ed04d341e4`, `4987d69102`, `c57fd98d68` e `df5351c35c` publicados; esta rodada adicionou stories ausentes e converteu slots HTML para render arrays Drupal. Stories PHP são resolvidas no preprocess do overview/single; menu usa o provider atual e a barra flui dentro do bloco.
- **Decisões:** stories/pré-visualizações aparecem antes dos metadados recolhidos. CSS contém overflow e elementos posicionados por componente. Nada de colunas artificiais.
- **Cuidados:** preview Chromium com CSS local injetado confirmou 108 blocos e sem overflow em 320/1440. Drupal respondeu 200 após corrigir declaração duplicada que causava 500. Cache foi reconstruído após a implementação; aceite visual autenticado permanece pendente.
- **Commits e arquivos:** `ed04d341e4`, `4987d69102`, `c57fd98d68`, `df5351c35c` e commit desta atualização; tema `aculta420_v2` (hooks, stories, layout, templates, CSS, testes, TODO, CHANGELOG e docs).
- **Verificações:** renderer Drupal 12/12 na rodada atual (Badge, Button, Conexões, Rodapé, Ícone, Wiki, Data Table e Form Field); rodada anterior Dialog/Menu/Barra/Pagination 4/4 e `utility-drawers` confirmado. Stories dinâmicas customizadas e hook testados. Schema SDC 55/764, PHP lint e Stylelint do CSS alterado PASS. `npm run lint:css` encontra violações preexistentes em `src/styles/forum.css`; gate V2 SDC encontra falso positivo `__add-btn` em `studio-calendar.twig`. Token budget e `git diff --check` PASS.
- **Pendências e bloqueios:** cache Drupal reconstruído no Runtime. `updatedb:status` e `config:status` ainda mostram pendências preexistentes, preservadas sem execução/import. Aceite visual autenticado e teste de clique no browser permanecem pendentes.
- **Próximo passo:** confirmar visualmente e clicar nas prévias no overview autenticado; não importar configuração nem executar updates sem escopo explícito.
