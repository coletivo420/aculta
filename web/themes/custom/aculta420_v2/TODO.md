# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · ADR-056 aceita; plano de corte legado passa primeiro pela reconciliação da documentação com o checkout.

## Próximo passo

- **P0 — documentação:** concluir o cruzamento de `architecture.md`, `components.md`, `gates.md`, `ui-styles.md`, `layouts-plan.md` e ADR-054/055 com código, Config Sync e Composer. A primeira passada corrigiu contagens, shell, UI Styles e usos obsoletos de Evento/FAQ; a matriz completa permanece pendente.
- **P1 — SDC/Library:** 136 manifests e 216 stories; todos os SDCs têm story. Props de topo têm título e slots têm título/descrição, com gate. Permanecem 440 descrições específicas e auditoria semântica de defaults, variantes, includes e escaping. Duas props de links agora usam o prop type oficial; restam 22 props `unknown` em 13 SDCs, classificadas na ADR-056. Confirmar dados editoriais persistidos no Runtime e seletor de `event-page-layout`.
- **P2/P3 — integrações e corte:** mapear consumidores dos 136 SDCs e tentar remover os 31 templates Twig que existem agora, um por vez, com substituição funcional comprovada. Preservar acesso, cache, traduções, attachments, rotas e Form API.
- **P4 — homologação:** depois dos lotes, validar consumidores reais, estado público/autenticado, cache-hit, claro/escuro, teclado e larguras. Runtime Sync depende de preflight liberado.
- Link do aviso Mercado Pago: validar no CI o contrato Twig de HTTPS/host oficial e `link()`.
- UI Skins e superfícies: aceite visual humano continua pendente apesar dos gates e capturas técnicas já registrados; ver documentos de homologação vinculados no roadmap.

## Marcos e pendências

- UI Patterns Library: 136 manifests/216 stories no checkout; Runtime ainda não revalidado. Todos os SDCs são descobertos pelo provider e têm stories. `aculta_ui_library` será removido por completo após desinstalação estrutural; a configuração Sync já deixou de habilitá-lo.
- Pendem cruzamento completo de consumidores dos SDCs, tentativa de remoção dos templates, cache/configuração ativa e aceite visual/acessível por domínio.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.

- **Correção de cobertura da inversão (2026-11-12):** `layout-section` recebe
  `green-soft` opt-in + UI Styles + story e gates. Pendente escolher as seções
  reais apropriadas no Layout Builder e confirmar se UI Styles aplica a classe
  na instância correta. Não alterar por padrão os 22 displays.
