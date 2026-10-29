# Roadmap — Painel de conteúdo (`aculta_content_admin`)

## ADM5 — Conteúdo moderável

- [x] Descobrir Views de página administrativas sobre entidades de conteúdo e verificar cada rota pelo `access_manager`.
- [x] Incluir filas de controller via allowlist explícita; sem áreas acessíveis, o painel fica negado.
- [x] Kernel cobre coleção escondida/negada e coleção liberada pelo acesso nativo.
- [x] Auditar rotas administrativas servidas por controllers no checkout de 2026-10-28; filas editoriais existentes estão na allowlist e nenhum controller adicional atende ao critério.
- [x] Corrigir colisão de caminho: `/painel-administrativo/conteudo` era compartilhado com a listagem Core `view.content.page_1`; rota customizada agora é `/painel-administrativo/colecoes-moderaveis`, sem alias transitório.
- [ ] Repetir revisão visual/acessível autenticada do candidato servido. A rodada no Runtime (HEAD `355e48a89`) abriu a rota Core antiga e não é evidência do novo caminho.

### Auditoria de controllers — 2026-10-28

- Incluídas na allowlist: fila de moderação do Fórum, coleções do Fórum LMS, revisão de itens importados do Calendário, avisos, micropáginas/destinos do Links e administração editorial da FAQ. O painel verifica o acesso nativo de cada rota antes de exibi-la.
- Revisadas e excluídas: `aculta_game.achievements` (catálogo/configuração e operações de concessão, não coleção de conteúdo moderável); `aculta_forum.admin` (resumo e métricas do Fórum); `aculta_image.audit` e `aculta_image.audit_report` (relatórios operacionais do acervo, já cobertos pela frente de imagens). `aculta_image.file_usage` é detalhe de um arquivo, não uma lista.
- Views administrativas sobre entidades de conteúdo continuam sendo descobertas automaticamente e preservam o controle de acesso da própria View. Rotas de configuração, relatórios, ações e registros individuais não entram na allowlist.
- Evidência: revisão de `ModeratableCollectionRegistry::CONTROLLER_COLLECTIONS`, das rotas administrativas dos módulos customizados e dos controllers acima. `ModeratableCollectionAccessKernelTest`: 3 testes, 43 assertions, 0 deprecações; cobre negação/liberação por acesso nativo e preservação do acesso da View.

### Gates para RC — 2026-10-28

- `ModeratableCollectionAccessKernelTest`: 3 testes / 43 assertions PASS, sem
  depreciações.
- PHP lint: 3 arquivos PASS; PHPCS Drupal no módulo PASS.
- A revisão visual/acessível autenticada ainda não foi executada. A conta
  temporária autorizada nesta sessão é específica ao ADM8 do Calendar; não foi
  reutilizada para o painel de conteúdo. Manter `Dev` até essa revisão.

### Revisão da colisão e gates — 2026-10-29

- Drush read-only no Runtime confirmou `aculta_content_admin.overview` e `view.content.page_1` no mesmo caminho `/painel-administrativo/conteudo`. O navegador autenticado abriu a View Core, não o painel customizado.
- Rota customizada renomeada para `/painel-administrativo/colecoes-moderaveis`; o Kernel confirma esse caminho e sua distinção da rota Core.
- Kernel: 4 testes / 56 assertions PASS. PHP lint: 3 arquivos PASS. `git diff --check`: PASS.
- Revisão visual do novo caminho permanece pendente, pois o Runtime testado ainda estava em `355e48a89`, sem este candidato. Manter `Dev`.
- `validate-module-theme-boundary.php` permanece bloqueado por violações em outros módulos, inclusive rotas de eventos/calendário e teste da Revista.

