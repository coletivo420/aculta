# Auditoria profunda de segurança - Fórum e CGU

**Data:** 21/11/2026.
**Alvo:** `coletivo420/aculta`, PR #394, `feat/forum-paridade-discourse`.
**Snapshot de referência:** `1b21742144f3b92bfba09ec8489d39b35d9b3f13`, com revisão das alterações seguintes até `101397144293fbbac6f2c165bf85e2223db380c8`.
**Branch de auditoria:** `test/forum-marley-boundaries-20261121`, já mesclada em `feat/forum-paridade-discourse` e excluída.
**Método:** inspeção estática de rotas, permission grants, Form API, handlers, acesso a entidade, hooks de Domain/Group, persistência CGU, filas, flags, ForumPost/Comment movers, reação, upload e enquetes. **Nenhum ataque foi executado e nenhum Runtime foi inspecionado**. Todas as severidades são prioridades de triagem, não CVSS ou prova de exploração.

**Atualização de consolidação (21/11/2026):** SFC-01 e SFC-02 receberam correções na PR #395, posteriormente integrada à PR #394. A comprovação por PHPUnit/Kernel/Browser no Homelab segue pendente. A suíte RED inicial foi substituída por testes dedicados; SFC-03 a SFC-09 permanecem em triagem.

## Resumo executivo

Foram identificadas lacunas concretas nas fronteiras de autorização de comentários, leitura contextual em popups e revalidação do destino de movimentação. As defesas existentes não são descartadas: há checks corretos em outras superfícies, como `ForumCommentMover::assertMoveEndpoints()`, `ForumMarleyTopicActions::access()`, `RestrictedForumAccessHooks` e `ReportService::decideReport()`. O problema central é **inconsistência**: uma permissão transversal não deve substituir os direitos sobre a entidade, seu host, seu domínio e seu Group.

### Quadro de achados

| ID | Prioridade | Superfície | Natureza |
|---|---|---|---|
| SFC-01 | CRÍTICA | CommentModerationAccess + Form | Autorização transversal sem host/Domain/Group/Entity Access |
| SFC-02 | ALTA | TopicAdminPopupController | IDOR/leitura de metadados CGU sem view access contextual |
| SFC-03 | ALTA | ForumPostMover::move | Destino não revalidado no serviço; diferença em relação a ForumCommentMover |
| SFC-04 | ALTA | DiscussionCommentDeleteController | Token+UID não revalidam host depois da revogação de Group |
| SFC-05 | MÉDIA | DiscussionReplyEndpointAccess | Entity type arbitrário da URL não é validado antes de getStorage |
| SFC-06 | MÉDIA | CguReportAccessControlHandler | Grant por purpose libera todas as operações sem distinguir view/update/delete |
| SFC-07 | MÉDIA | ForumPostMover | Arquivo/fechamento e descendentes requerem validação de integridade |
| SFC-08 | MÉDIA | CguReportDecisionForm | Mensagem de exceção técnica é refletida ao moderador |
| SFC-09 | OBSERVAÇÃO | CguReportQueue + Marley | Filtro de entidade após paginação; pode esvaziar páginas e exige teste anti-side-channel |

## SFC-01 - Moderação de comentários sem autorização contextual

**Evidências:**
- `aculta_forum.routing.yml`, rota `aculta_forum.comment_moderation`: `/discussoes/{comment}/controles`, ativa em Forum, Magazine, Courses e Wiki, métodos GET/POST.
- `src/Access/CommentModerationAccess.php`: valida cabeçalho `HX-Request` e permissão `administer comments` **OU** `moderate aculta forum comments`, sem consultar a entidade, host, origem, Domain, Group, `view` ou `update`.
- `src/Form/DiscussionCommentModerationForm.php`, `submitForm()`: ações `despublicar`, `republicar`, `remover`, `restaurar` e `excluir`, esta última com remoção física.

**Contexto comprovado:** `config/sync/user.role.moderador.yml` concede `moderate aculta forum comments`. `LmsCommentModerationAccessHooks` protege outras operações de entidade contextual, **não** compensa a autorização da rota dessa forma de comentário. O cabeçalho HTMX é forjável.

**Cenário a reproduzir em Browser isolado:** conta só com papel moderador Fórum tenta abrir um Comment de host Wiki/Revista/LMS e um Comment de Group restrito do qual não é membro. Esperado: 403/404 para GET e POST, sem alterações em `comment_field_data`, tombstones ou histórico.

**Correção sugerida (owner Forum):** adicionar um checker de acesso por comentário com `DiscussionOriginResolver`, `DiscussionContextResolver` e leitura do host; determinar política específica por purpose e operação; exigir `comment->access('view')` e `host->access('view')` no Domain do owner e membership do Group; validar também em `submitForm()` antes de qualquer alteração. Separar `administer comments` da permissão de moderação contextual. Preservar Form API CSRF, mas não usar CSRF/HTMX como autorização.

## SFC-02 - Popup do tópico sem checagem do alvo

**Evidências:**
- `aculta_forum.routing.yml`, `aculta_forum.topic_admin_popup`: somente `_permission: move aculta forum topics`, sem `_custom_access` ou `_entity_access`.
- `src/Controller/TopicAdminPopupController.php`, `view()`: lê autor, categoria, contadores com `accessCheck(FALSE)`, e carrega `aculta_cgu_report` por `target_type+target_id`, exibindo `decision_notes`.
- Cache do render array utiliza apenas `user.permissions`; não incorpora `user`/Domain/Group, embora haja ações dependentes de grants por entidade.

**Impacto condicional:** contas com a permissão de movimentação podem recuperar dados de tópicos/relatórios inacessíveis por URL direta. O papel `moderador` atual concede `reorganize aculta forum topics`, **não necessariamente** `move aculta forum topics`; verificar quem recebe esta permissão no Runtime antes de classificar explorabilidade real.

**Correção:** exigir tipo `aculta_forum_topic`, purpose de origem Forum e `node->access('view', account, TRUE)` em domínio Forum, com cache metadata de access result; restringir carregamento de relatórios ao purpose e ao escopo CGU, sem exibir pareceres de outros purposes. Isolar o popup a moderadores elegíveis e garantir user/domain cache context ou `max-age=0`.

## SFC-03 - Serviço de movimentação não valida destino

**Evidências:**
- `src/Service/ForumPostMover.php`, `destinations()`: usa node query `accessCheck(TRUE)`, Domain Forum e `view` do destino.
- Na mesma classe, `move()`: só revalida a origem com `access(post, account)` e `isMovableTopic(destination)`; o segundo helper exige apenas bundle e `isPublished()`.
- **Comparação positiva:** `ForumCommentMover::assertMoveEndpoints()` revalida `field_domain_source` e o acesso ao destino dentro do método de mutação.

**Contexto:** o formulário `ForumPostMoveForm` limita as opções e o Drupal Form API normalmente recusa valores fora de `#options`. Isso **reduz o risco do endpoint atual**, mas não protege o serviço contra futuros chamadores, valores antigos, mudança de membership ou corrida entre a apresentação do formulário e a gravação.

**Correção:** revalidar dentro de `move()`, após obter locks, `destination` de Domain Forum, `view` e política de Group; checar status aberto/não arquivado via `TopicAuthorDeletion` ou política transversal do Fórum; validar cada descendente e sua origem; limitar tamanho e detectar ciclo.

## SFC-04 - Exclusão com token antigo após perda de acesso

**Evidência:** `src/Controller/DiscussionCommentDeleteController.php`, `delete()`: valida token CSRF por CID, depois `administer comments` ou `ownerId == accountId` e tombstone; não valida `comment->access('view')`, host, origem, Domain nem Group.

**Cenário:** autor obtém token quando ainda é membro do Group e posteriormente perde associação/membership; tentar POST com token ainda válido. Esperado: negar exclusão após revogação. Moderador fora do Group não deve operar o comentário pelo caminho de autor/admin.

**Correção:** autorização por contexto e operação no servidor, verificando host e Comment. Testar revogação em sessão ativa, sem depender de esconder botão.

## SFC-05 - Tipo de entidade da rota de composição sem allowlist

**Evidências:** rota `aculta_forum.discussion_composer` não restringe `entity_type` na expressão do caminho. `DiscussionReplyEndpointAccess::access()` e `DiscussionReplyController::composer()` chamam `getStorage($entity_type)` antes de validar se o tipo é um host permitido.

**Risco:** tipos inexistentes ou não suportados podem causar exceções/HTTP 500 e carga de erro/log. A política contextual de resposta é adequada para hosts válidos e falha fechada; o problema é a validação inicial do parâmetro.

**Correção:** lista explícita de entity types suportados no checker e no controller, sem permitir caracteres ou tipos arbitrários; responder 404 de forma estável.

## SFC-06 - CGU concede operações de entidade sem distinção

**Evidência:** `src/Entity/CguReportAccessControlHandler.php`, `checkAccess()`, retorna allowed quando `ModerationScope::allows(account, purpose)` sem condicionar `operation`. Isso inclui potenciais operações delete/update, além de view.

**Exposição:** a entidade CGU é `internal` e as rotas administrativas estão sujeitas a `AdminRouteAccessCheck`, reduzindo a exposição HTTP atual. Ainda assim, consumidores futuros que confiem em `entity->access('delete')` poderiam herdar privilégio excessivo.

**Correção:** matriz por operação; negar delete ou exigir permissão exclusiva e confirmação administrativa; manter a decisão oficial exclusivamente em `ReportService`. Relatórios do próprio denunciante só view, nunca update/delete.

## SFC-07 - Fechamento e integridade de subárvore no mover revisionável

**Evidências:** `ForumPostMover::isMovableTopic()` não consulta `TopicAuthorDeletion::isClosed()/isArchived()`. `loadPostSubtree()` percorre parents com query `accessCheck(FALSE)`, sem limite explícito, verificação de ciclo e checagem de que cada descendente pertence ao tópico fonte. Há revalidação de `view` antes de salvar, o que é positivo, mas não substitui validação de integridade de árvore.

**Correção:** adotar limites e verificações semelhantes a `ForumCommentMover::loadSubtree()`; revalidar após locks; não permitir destino fechado/arquivado e avaliar origem do root/descendentes.

## SFC-08 - Erro interno em mensagem de formulário CGU

**Evidência:** `CguReportDecisionForm::submitForm()` captura `\\Throwable` e mostra `$e->getMessage()` em `messenger()->addError()`. Dependendo do erro, mensagem técnica de backend pode vazar detalhes para moderadores.

**Correção:** mensagem pública genérica com referência de incidente, detalhes apenas em logger auditável e sem PII.

## SFC-09 - Paginação e visibilidade

`CguReportQueue::getQueueItems()` agrupa e pagina a partir de dados de denúncia; o controller `CguForumMarleyController::queue()` aplica `targetViewAccess()` nos itens da página. A implementação protege labels e casos efetivamente exibidos, mas pode gerar páginas vazias enquanto existem itens em páginas seguintes e deve ser testada contra contagem indireta de alvos restritos. Avaliar paginação com acesso antes do cálculo de totais e cursor estável.

## Controles positivos verificados

1. **Backend admin-only:** `aculta_portal/src/Access/AdminRouteAccessCheck.php` restringe `_admin_route`, `/painel-administrativo` e `/admin` ao papel `administrator` ou UID 1; o último conjunto de roles removeu acesso backend de papéis operacionais.
2. **Domain/Group do Fórum restrito:** `RestrictedForumAccessHooks` usa grants e `entity_access` fail-closed.
3. **Movimento de Comment:** `ForumCommentMover` revalida ambos os lados do movimento, origem, subtree e acesso por entidade sob locks.
4. **CGU e concorrência:** `ReportService::decideReport()` obtém lock por relatório, recarrega `loadUnchanged()`, valida scope e transição e libera lock no finally.
5. **Marley/CGU:** `CguForumMarleyController::reportAccess()` combina propósito Forum, host real e leitura de entidade no Domain Forum antes de apresentar relatório.
6. **Reações:** `DiscussionReactionController` exige POST e valida token CSRF vinculado a tipo/ID; `DiscussionReactionAccess` exige leitura do contexto e separa arquivo Growroom.
7. **Denúncias por Flag:** `ForumFlagAccessHooks` valida a origem e target, e a ponte CGU `ForumReportCguBridgeHooks` revalida leitura ao criar report.

## Testes de regressão e plano de aceite

**Histórico dos testes RED:** `ForumCguAuthorizationRedTest.php` foi retirado da execução normal após a integração, porque o construtor de `CommentModerationAccess` mudou. A cobertura atual de SFC-01 e SFC-02 está nos arquivos `CommentModerationBoundaryTest.php`, `TopicAdminPopupBoundaryTest.php` e `CommentModerationFormBoundaryTest.php`. SFC-04 segue pendente e **não** deve ser contado como corrigido.

**Kernel/Browser obrigatórios após remediação** (sem usar Runtime compartilhado):
- SECURITY-K01: moderador Forum + Wiki/Revista, dois grupos, revogação ativa e comentários de hosts distintos; `view`/moderação separados.
- SECURITY-K02: excluir com CSRF emitido antes da revogação de Group retorna 403 e não muda tombstone.
- SECURITY-K03: mover post para destino fora do Domain ou inacessível, fechado ou arquivado; `move()` deve rejeitar mesmo quando chamado diretamente.
- SECURITY-K04: ciclo em `parent`, descendente de outro tópico e > limite de subárvore não alteram dados.
- SECURITY-B01: GET/POST de `/discussoes/{comment}/controles` com HX e perfil só Forum para comentário Wiki/LMS/Group alheio retorna 403/404.
- SECURITY-B02: `/forum/topicos/{node}/controles` não revela conteúdo ou parecer CGU quando não há view sobre o tópico.
- SECURITY-B03: GET com `entity_type` inválido em compositor responde 404, nunca 500.
- SECURITY-B04: FORM CGU com csrf inválido e status final antigo não altera parecer ou visibilidade do alvo.
- SECURITY-B05: permissões de backend e UI Marley em papéis combinados continuam isoladas por owner.

## Plano de correção sem conflito com o agente

- **P0 implementado, homologação pendente:** SFC-01 e SFC-02. Checkers e Form API foram integrados à branch do Fórum; executar `scripts/tests/verify-forum-cgu-sfc01-sfc02.sh` na worktree existente com PHPUnit, Kernel e Browser isolados.
- **P1:** SFC-03/04/07. Centralizar checagens dentro dos serviços de movimento e exclusão; aplicar validações de Group e Domain sob locks, com limite de subárvore.
- **P2:** SFC-05/06/08/09. Endurecer allowlists, operações de entidade CGU, mensagens e paginação.
- **Aceite:** nenhum bypass de Domain/Group em GET/POST direto, nenhum efeito colateral de decisão CGU em publicação, nenhum vazamento de identidades e nenhum endpoint de moderação no backend para papéis operacionais.
- **Execução atual:** matriz Unit -> Kernel com banco `simpletest` -> Browser com fixture descartável -> revisão e registro de PASS/FAIL/SKIP. Trabalhar diretamente em `feat/forum-paridade-discourse`, sem novas branches e sem alterar checkout servido.

## Limitações da auditoria

A inspeção atual não é SAST completo nem pentest autenticado. Não foram executados PHPUnit, navegador, scans PHPStan ou tráfego em ambiente real. A classificação de severidade não garante exploração via HTTP; gates adicionais de permissões, acessos em outras extensões e configuração efetiva do servidor podem reduzir a exposição. Reavaliar frente a novos commits do agente antes de aplicar patches.
