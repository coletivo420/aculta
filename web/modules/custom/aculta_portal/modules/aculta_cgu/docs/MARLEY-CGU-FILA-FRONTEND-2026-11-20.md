> **SEGUNDA ETAPA DA INTEGRAÇÃO (2026-11-20):** `aculta_forum.marley_topic_actions` conecta o inspetor frontend do Marley a três comandos **reais do Fórum**, reutilizando Form API e owner services: fixar/desafixar, fechar/reabrir e arquivar/desarquivar. As rotas `/marley/forum/topico/{node}/{fixar|fechar|arquivar}` exigem `moderate aculta forum topics`, verificam o nó atual do Forum e acessibilidade no domínio de origem por `DomainSiteManager::runInSite('forum')`. O CGU mantém denúncia/decisão separada da mutação do conteúdo. A fila também verifica topic/target no domínio Fórum e resolve links canônicos para esse host. Nada altera backend/admin, não implementa ações destrutivas, split/merge ou popin HTMX nesta etapa. Testes de contrato publicados; Runtime e Browser ainda DEFERRED.

# Entrega técnica - fila e decisão CGU reais no frontend Marley

**Data:** 2026-11-20. **Branch:** feat/forum-paridade-discourse / PR #394.
**Snapshot inicial:** e30240233f9b97987ea2d1217c5c5821c6f24487.
**Status:** implementação remota em fase de QA. O Runtime e a navegação real não foram homologados.

## O que a implementação conecta

- `MarleyTab/ForumTab.php` requer aculta_forum + aculta_cgu habilitados, permissões por escopo **ModerationScope::allows('forum')**, e aponta para **`aculta_cgu.forum_marley_queue`** no domínio da Conta (nunca para a antiga rota administrativa).
- CGU mantém os serviços reais `CguReportQueue` (lista paginada com reconciliação legado) e `ReportService` (transição, lock e log). Nenhum dado de denúncia foi copiado para tabela do Studio ou do Fórum.
- Novo `CguForumMarleyController` fornece listagem por status, filtro e contagem de **itens visíveis** via SDC `studio-marley-workspace`; consome `filter-chips`, `studio-listing-table`, `surface-panel`, `statistics-moderation-summary` e `moderation-action` existentes. Ícone Phosphor `shield-check`.
- A ação real “Analisar caso” só é renderizada se houver `CguReport` persistido e `reportAccess` permitir. Legados ainda não migrados não podem ser decididos por falso comando.
- O alvo tem de existir, ser de origem **Fórum** (nó aculta_forum_topic, Comment contextual ou aculta_forum_post) e passar por entity `view` no usuário atual. O controller não expõe UID de denunciante nem referências de outro purpose/host.
- `/marley/forum/moderacao/denuncias/{aculta_cgu_report}/decidir` exibe o formulário existente `CguReportDecisionForm`, com CSRF automático da Form API, operação de escrita em `ReportService::decideReport()`, decisão/log CGU e navegação retornando à fila frontend. Não se escreve conteúdo do Fórum ao decidir uma denúncia.
- Rotas com `_aculta_site_key: account` e sem `_admin_route`. Cache máximo zero, contexto por usuário/permissão/domínio. Rota administrativa antiga segue para **administrator**, não é removida nesta fase.
- `ReportService` e `CguReportQueue` ainda têm débitos próprios de performance e de checagens de Domain/Group que precisam da homologação Kernel/browser; não conceder acesso para consertar listas incompletas.

## Limites expressos

1. **Esta entrega não conecta ainda as mutações de conteúdo do `aculta_forum`** (ocultar, remover, editar, fechar, fixar, mover/dividir/mesclar). Muitos comandos atuais ainda usam rotas administrativas ou ACL distintas. O próximo passo é um adapter fino de comandos frontend no módulo Forum, com os mesmos owner services, access checks finos por entidade, ação POST/CSRF, idempotência e auditoria, consumido no inspetor do Marley. Não apontar links para o backend em nome de moderador.
2. A página de decisão usa Form API renderizada no painel lateral, não um popup HTMX de escrita. O popup dos Stories continua visual; migrar para dialog somente após validar comportamento no-JS, POST, foco, mensagens e acessibilidade.
3. A fila limitada a 25 itens por página filtra alvos inacessíveis **depois** do agrupamento CGU. Isso pode resultar em páginas incompletas; revisar paginação de modo access-aware, evitar N+1 de relatórios e não vazar contagem de conteúdo privado.
4. As permissões/role config de moderador e a regra backend admin-only precisam ser auditadas no checkout real com testes de GET/POST/CSRF e papéis compostos. A tela não promove permissões.
5. O arquivo Growroom continua read-only, desvinculado de decisões de conteúdo corrente. Casos já resolvidos não voltam ao aberto por flags legadas.
6. O controller CGU foi introduzido como **view provider** da rota frontend por ser o dono dos relatórios; quando o Marley tiver um presenter universal de owners, mover somente a composição visual para Studio, preservando todo o acesso e escrita no CGU.

## Matriz de aceite frontend (MCGU-01 a 09)

| ID | Cenário | Status inicial |
| --- | --- | --- |
| MCGU-01 | Moderador Fórum vê aba Marley e fila sem backend | Código / Runtime DEFERRED |
| MCGU-02 | Moderador Revista/Wiki isolado não acessa fila Fórum | Código Unit escrito / Runtime DEFERRED |
| MCGU-03 | Denúncias `forum` não misturam `revista`, `wiki`, LMS ou Growroom | Código / Kernel DEFERRED |
| MCGU-04 | Alvo não-visível/inexistente é filtrado sem revelar label/UID | Código / Functional DEFERRED |
| MCGU-05 | Form API real aplica status/parecer, registra log e volta à fila frontend | Código / Functional DEFERRED |
| MCGU-06 | Sem JS, csrf inválido e POST concorrente não mudam conteúdo nem escapam ao backend | Código / Browser DEFERRED |
| MCGU-07 | Decidir denúncia não despublica nó/ForumPost/Comment | Código / Kernel DEFERRED |
| MCGU-08 | 25 itens, estado, navegação, responsivo, dark, acessibilidade e performance | Código / Playwright DEFERRED |
| MCGU-09 | Comandos de conteúdo do Fórum no inspetor, sob ACL e CSRF, com popup | **PENDENTE** |

## Continuação da PR #394

Ler também [Stories Marley](../../aculta_studio/docs/STORIES-MARLEY-DISCOURSE-2026-11-20.md), [Plano Discourse](../../aculta_forum/docs/PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md) e [handoff integral](../../aculta_forum/docs/HANDOFF-AGENTE-CLI-FORUM-COMPLETO-2026-11-20.md). **Este é um marco funcional parcial**, não substitui FDC-R0..R14, T01..70, DV01..19 e SM01..08.

## Proteção contra decisão concorrente

O `ReportService::decideReport()` agora obtém `lock` específico por ID de denúncia, busca a entidade atual por `loadUnchanged()`, revalida o escopo do moderador e a transição antes de salvar; `finally` libera o lock. O mesmo código serve à rota administrativa e ao Marley frontend. Assim, um formulário aberto antes da decisão de outro moderador não pode sobrescrever o status final. O teste estrutural foi atualizado; teste Kernel de dois POSTs sobre o mesmo registro permanece obrigatório na homologação.
