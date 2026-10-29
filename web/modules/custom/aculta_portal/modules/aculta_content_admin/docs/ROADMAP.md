# Roadmap — Painel de conteúdo (`aculta_content_admin`)

## ADM5 — Conteúdo moderável

- [x] Descobrir Views de página administrativas sobre entidades de conteúdo e verificar cada rota pelo `access_manager`.
- [x] Incluir filas de controller via allowlist explícita; sem áreas acessíveis, o painel fica negado.
- [x] Kernel cobre coleção escondida/negada e coleção liberada pelo acesso nativo.
- [x] Auditar rotas administrativas servidas por controllers no checkout de 2026-10-28; filas editoriais existentes estão na allowlist e nenhum controller adicional atende ao critério.

### Auditoria de controllers — 2026-10-28

- Incluídas na allowlist: fila de moderação do Fórum, coleções do Fórum LMS, revisão de itens importados do Calendário, avisos, micropáginas/destinos do Links e administração editorial da FAQ. O painel verifica o acesso nativo de cada rota antes de exibi-la.
- Revisadas e excluídas: `aculta_game.achievements` (catálogo/configuração e operações de concessão, não coleção de conteúdo moderável); `aculta_forum.admin` (resumo e métricas do Fórum); `aculta_image.audit` e `aculta_image.audit_report` (relatórios operacionais do acervo, já cobertos pela frente de imagens). `aculta_image.file_usage` é detalhe de um arquivo, não uma lista.
- Views administrativas sobre entidades de conteúdo continuam sendo descobertas automaticamente e preservam o controle de acesso da própria View. Rotas de configuração, relatórios, ações e registros individuais não entram na allowlist.
- Evidência: revisão de `ModeratableCollectionRegistry::CONTROLLER_COLLECTIONS`, das rotas administrativas dos módulos customizados e dos controllers acima. `ModeratableCollectionAccessKernelTest`: 3 testes, 43 assertions, 0 deprecações; cobre negação/liberação por acesso nativo e preservação do acesso da View.

