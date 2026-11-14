# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · ADR-056 aceita; reconciliação factual inicial concluída em 2026-11-14; falta cruzar planos por domínio e completar a revisão dos consumers.

## Próximo passo

- **P0 — documentação:** a contagem do checkout é 145 SDCs/229 stories e 17 templates Twig Drupal no tema. Foram migradas para SDC as composições das Views do Fórum, Evento (`event-page-layout` por render array), o envelope visual de e-mail e o wrapper comum de comentários. Wiki full usa ComponentLayout UI Patterns e Revista full compõe `revista-publication` por Layout Builder ou preprocess de compatibilidade. Falta reler os planos por domínio e corrigir status sem evidência em código/Config Sync.
- **P1 — SDC/Library:** os 145 manifests têm story. Props de topo têm título e slots têm título/descrição, com gate. Auditoria reproduzível em 2026-11-14: 289/525 props de topo sem descrição em 59 SDCs; entre os nós de schema tipados sob as props, 396/689 não têm descrição em 63 SDCs. Totais anteriores que não se reproduzem no checkout atual foram substituídos. Este lote documentou `accordion`, `badge`, `breadcrumb`, `empty-state`, `document-card`, `document-download`, `google-signin-button` e `hint`; o lote anterior cobriu cinco elementos básicos e três componentes de estatísticas. Lotes anteriores documentaram a Barra de Reações, a Barra de Reações Recebidas, os três compositores, o emoji e o Fórum. Descrever o restante a partir dos contratos Twig/PHP; confirmar seleções editoriais no Runtime. Duas props de links usam o prop type oficial; validação atual do provider registra 22 props `unknown` em 13 SDCs, incluindo `mail-envelope.colors` (objeto interno de tokens sem Source editorial). Confirmar também o seletor de `event-page-layout`.
- **P2/P3 — integrações e corte:** matriz regenerada em 2026-11-14 (451 linhas: cabeçalho e 450 consumers), com 145 SDCs, 159 displays Views, 52 instâncias de bloco e 22 displays com Layout Builder. A busca encontrou referências literais a 110 SDCs; os outros 35 foram classificados pela intenção declarada nos manifests em `docs/architecture/ACULTA420-V2-AUDITORIA-SDCS-SEM-REFERENCIA-2026-11-14.md`, permanecem publicados e aguardam confirmação de seleções editoriais no Runtime. O Runtime ainda diverge em nove configs. As leituras anteriores de Fórum, cursos, perfis e Revista estão no roadmap; preservar acesso/cache e consumidores Core ao reduzir os 17 templates restantes. O seletor independente foi removido: rota e builder de fragmento saíram, e `DiscussionReactionBuilder` compõe a Barra de Reações com paleta interna; as duas barras mantêm seus contratos. O cache Runtime pré-purga ainda descobria 148 SDCs e precisa de rebuild controlado antes da homologação. `aculta_ui_library` foi removido; resta chave órfã `system.schema`, pendente de backup e prova de restauração.
- **P4 — homologação:** depois dos lotes, validar consumidores reais, estado público/autenticado, cache-hit, claro/escuro, teclado e larguras. Runtime Sync depende de preflight liberado.
- Link do aviso Mercado Pago: validar no CI o contrato Twig de HTTPS/host oficial e `link()`.
- UI Skins e superfícies: aceite visual humano continua pendente apesar dos gates e capturas técnicas já registrados; ver documentos de homologação vinculados no roadmap.

## Marcos e pendências

- UI Patterns Library: 145 manifests/229 stories no checkout; o Runtime descobriu 148 SDCs antes da purga do seletor, portanto o cache precisa de rebuild. `ui_patterns_library` está habilitada e `aculta_ui_library` desabilitada. Todos os SDCs têm stories. `forum-category-page`, `forum-list-page` e `forum-search-page` controlam a estrutura das Views por slots explícitos; `forum-comment` centraliza os presenters de comentários; `mail-envelope` compõe a tabela visual do e-mail. `aculta_ui_library` foi removido do checkout e do Sync/Runtime; resta chave órfã `system.schema`, pendente de limpeza focal após backup e prova de restauração.
- Pendem cruzamento completo de consumidores dos SDCs, remoção dos presenters restantes, homologação de cache/configuração ativa e aceite visual/acessível por domínio.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.

- **Correção de cobertura da inversão (2026-11-12):** `layout-section` recebe
  `green-soft` opt-in + UI Styles + story e gates. Pendente escolher as seções
  reais apropriadas no Layout Builder e confirmar se UI Styles aplica a classe
  na instância correta. Não alterar por padrão os 22 displays.
