# Roadmap — Painel de conteúdo (`aculta_content_admin`)

## ADM5 — Conteúdo moderável

- [x] Descobrir Views de página administrativas sobre entidades de conteúdo e verificar cada rota pelo `access_manager`.
- [x] Incluir filas de controller via allowlist explícita; sem áreas acessíveis, o painel fica negado.
- [x] Kernel cobre coleção escondida/negada e coleção liberada pelo acesso nativo.
- [x] Auditar rotas administrativas servidas por controllers no checkout de 2026-10-28; filas editoriais existentes estão na allowlist e nenhum controller adicional atende ao critério.
- [x] Corrigir colisão de caminho: `/painel-administrativo/conteudo` era compartilhado com a listagem Core `view.content.page_1`; rota customizada agora é `/painel-administrativo/colecoes-moderaveis`, sem alias transitório.
- [x] Repetir revisão visual/acessível autenticada do candidato servido. Evidência atualizada na seção RC abaixo.

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
- Na rodada inicial, a revisão visual ainda estava pendente porque o Runtime permanecia em `355e48a89`. A fronteira global também acusava violações externas; ambas foram revalidadas na seção RC abaixo.

### Candidato RC — 2026-10-29

- Firefox ESR visível no Runtime de desenvolvimento, candidato `bad8ef5fe`:
  painel autenticado abriu em 200 e mostrou somente seis coleções de Fórum
  acessíveis à conta de teste UID 5; os seis links tiveram foco visível.
- Reflow sem overflow em 1280, 390 e 320 CSS px; 7/7 checks visuais PASS.
  Sem criação/edição de conteúdo. Evidências em
  `/home/piradopirata/aculta-suite-out/aculta-content-admin`.
- Kernel 4 testes/56 assertions PASS, zero depreciações; PHP lint 3/3 e PHPCS
  Drupal/DrupalPractice PASS. Gates globais de rotas, Drupal 11+ e fronteira
  módulo/tema também passaram (3366 verificações nesta última).
- ADM5 recebe `0.1.0-rc.1`. Não há configuração própria a importar. Alpha
  deve repetir a verificação de acesso no host definitivo após o fluxo de
  release autorizado.

