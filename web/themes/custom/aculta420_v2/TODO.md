# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · ADR-056 aceita; reconciliação factual inicial concluída em 2026-11-14; falta cruzar planos por domínio e completar a revisão dos consumers.

## Próximo passo

- **P0 — documentação:** a contagem do checkout é 147 SDCs/232 stories e 19 templates Twig Drupal no tema após migrar a tabela visual do e-mail para `mail-envelope` e acrescentar estados vazios para as composições do Fórum. Wiki full usa ComponentLayout UI Patterns e Revista full compõe `revista-publication` por Layout Builder ou preprocess de compatibilidade. Falta reler os planos por domínio e corrigir status sem evidência em código/Config Sync.
- **P1 — SDC/Library:** os 147 manifests têm story. Props de topo têm título e slots têm título/descrição, com gate. A auditoria de schema registra 371 props de topo sem descrição em 81 SDCs e 492 nós de schema de props sem descrição em 81 SDCs (inclui campos aninhados). Permanecem a auditoria semântica de nomes, tipos, defaults, variantes, includes e escaping, além de confirmar seleções editoriais no Runtime. Duas props de links usam o prop type oficial; restam 21 props `unknown` em 12 SDCs, classificadas na ADR-056. Confirmar também o seletor de `event-page-layout`.
- **P2/P3 — integrações e corte:** matriz estática atualizada em 2026-11-14 (456 linhas: cabeçalho e 455 consumers), com 147 SDCs, 159 displays Views, 52 instâncias de bloco e 22 displays com Layout Builder. Há referências literais a 113 SDCs; os 34 restantes continuam publicados na UI Patterns Library e não são candidatos a exclusão automática. Leituras Drupal somente no Runtime confirmaram: `aculta_forum_categories.page_categories` usa row UI Patterns + `forum-category`, mas estilo padrão ainda agrupa por categoria-pai; removi só o fallback fields e mantive o template de agrupamento. `aculta_forum_public_topics.default` mantém row `fields`, então o fallback SDC continua necessário. `revista_sessoes` usa estilo padrão no Runtime, embora o Sync configure UI Patterns `revista-listing`. Configurei `courses` e `courses_catalog` para usar UI Patterns Views + `layout-grid`, preservando a row `entity:group`, filtros, acesso, pager e cache; a leitura Runtime anterior à mudança encontrou `grid_responsive` em `courses` e estilo padrão em `courses_catalog`. Perfis ainda usa row `fields`. Registrar e homologar divergências do Runtime antes de remover adapters restantes. Teasers da Revista, Wiki e Documentos, catálogo da Loja, apresentação full da Revista e FAQ compõem SDCs por render arrays ou Layout Builder; `mail-envelope` agora compõe a tabela visual do e-mail; a Library single page voltou ao template upstream e o loader em lote usa o container oficial de stories. O override de ícone dos mapas foi removido: o tema fornece o SDC `icon` ao template owner do módulo Maps. Seguir pelos 19 templates restantes, preservando fields, traduções, attachments, rotas e Form API enquanto a composição UI Suite não cobrir o contrato.
- **P4 — homologação:** depois dos lotes, validar consumidores reais, estado público/autenticado, cache-hit, claro/escuro, teclado e larguras. Runtime Sync depende de preflight liberado.
- Link do aviso Mercado Pago: validar no CI o contrato Twig de HTTPS/host oficial e `link()`.
- UI Skins e superfícies: aceite visual humano continua pendente apesar dos gates e capturas técnicas já registrados; ver documentos de homologação vinculados no roadmap.

## Marcos e pendências

- UI Patterns Library: 147 manifests/232 stories no checkout; Runtime ainda não revalidado após os cortes recentes. Todos os SDCs têm stories. `forum-category-page`, `forum-list-page` e `forum-search-page` controlam a estrutura das Views por slots explícitos; as stories cobrem lista e estado vazio. `mail-envelope` agora expõe a tabela visual do e-mail. `aculta_ui_library` será removido por completo após desinstalação estrutural; a configuração Sync já deixou de habilitá-lo.
- Pendem cruzamento completo de consumidores dos SDCs, remoção dos presenters restantes, homologação de cache/configuração ativa e aceite visual/acessível por domínio.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; atualize este TODO/status no mesmo commit, sem histórico.

- **Correção de cobertura da inversão (2026-11-12):** `layout-section` recebe
  `green-soft` opt-in + UI Styles + story e gates. Pendente escolher as seções
  reais apropriadas no Layout Builder e confirmar se UI Styles aplica a classe
  na instância correta. Não alterar por padrão os 22 displays.
