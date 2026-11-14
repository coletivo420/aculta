# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · ADR-056 aceita; reconciliação factual inicial concluída em 2026-11-14; falta cruzar planos por domínio e completar matriz de consumers.

## Próximo passo

- **P0 — documentação:** primeira correção factual feita a partir do checkout: 141 SDCs/223 stories, 29 templates Drupal após migrar Wiki teaser para render array SDC, Wiki full em UI Patterns e estado atual do Runtime. Falta reler os planos por domínio e corrigir status que ainda não tenham evidência em código/Config Sync.
- **P1 — SDC/Library:** 141 manifests e 223 stories; todos os SDCs têm story. Props de topo têm título e slots têm título/descrição, com gate. Permanecem 440 descrições específicas e auditoria semântica de defaults, variantes, includes e escaping. Duas props de links agora usam o prop type oficial; restam 22 props `unknown` em 13 SDCs, classificadas na ADR-056. Confirmar dados editoriais persistidos no Runtime e seletor de `event-page-layout`.
- **P2/P3 — integrações e corte:** matriz dos 141 SDCs regenerada neste lote (461 linhas). `revista_sessoes` aponta os seis displays para UI Patterns Views style + `revista-listing`; saída aguarda homologação no Runtime. Teasers da Revista e Wiki agora compõem SDCs por render arrays no preprocess; o template Twig do teaser Wiki foi removido. O full da Revista usa ComponentLayout `revista-publication` no Sync e encaminha `post_meta`/`article_authors` aos slots; o Runtime ainda diverge e o adapter de transição permanece. Seguir pelos demais templates com substituição comprovada, preservando acesso, cache, traduções, attachments, rotas e Form API.
- **P4 — homologação:** depois dos lotes, validar consumidores reais, estado público/autenticado, cache-hit, claro/escuro, teclado e larguras. Runtime Sync depende de preflight liberado.
- Link do aviso Mercado Pago: validar no CI o contrato Twig de HTTPS/host oficial e `link()`.
- UI Skins e superfícies: aceite visual humano continua pendente apesar dos gates e capturas técnicas já registrados; ver documentos de homologação vinculados no roadmap.

## Marcos e pendências

- UI Patterns Library: 141 manifests/223 stories no checkout; Runtime ainda não revalidado no commit atual. Todos os SDCs têm stories. `aculta_ui_library` será removido por completo após desinstalação estrutural; a configuração Sync já deixou de habilitá-lo.
- Pendem cruzamento completo de consumidores dos SDCs, remoção dos presenters restantes, homologação de cache/configuração ativa e aceite visual/acessível por domínio.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.

- **Correção de cobertura da inversão (2026-11-12):** `layout-section` recebe
  `green-soft` opt-in + UI Styles + story e gates. Pendente escolher as seções
  reais apropriadas no Layout Builder e confirmar se UI Styles aplica a classe
  na instância correta. Não alterar por padrão os 22 displays.
