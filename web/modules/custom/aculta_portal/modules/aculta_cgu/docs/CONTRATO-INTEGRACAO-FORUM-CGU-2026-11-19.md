> **Arquivo histórico Growroom:** comentários importados/restaurados permanecem segregados e read-only, fora da fila comum CGU. [Política](../../aculta_forum/docs/POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md). Medidas legais excepcionais exigem trilha apartada.

# Política ACULTA Forum: clone funcional Drupal do Discourse

Decisão de produto aprovada em 2026-11-19. **Paridade funcional e de experiência com Discourse**, implementação nativa sobre Drupal 11/12, identidade ACULTA e UI Suite/SDC como arquitetura visual obrigatória. O `aculta_forum` é o **motor único de discussões e comentários de todos os módulos do site**. Isso **não** significa transformar qualquer comentário editorial, pedagógico ou restrito em tópico público; cada host preserva entidade, acesso, workflow e propósito. A moderação de denúncias e os casos usam `aculta_cgu` como dono, com adapters de ações por contexto.

## Contrato único de moderação: aculta_cgu + aculta_forum

**Owner:** `aculta_cgu` mantém denúncias, filas, casos, relatórios sigilosos, decisões, notas de equipe e timeline, de forma multicontexto. `aculta_forum` fornece API de alvo e ações de moderação para tópicos, posts revisionáveis e Comments; LMS/Wiki/Revista autorizam operações via Group/Editorias próprios. Não criar nova fila de denúncias no Fórum nem habilitar poder de moderação global por engano.

### Baseline confirmado em código

- `aculta_cgu_report` armazena target, uid, motivo, purpose, status, decision_notes, decided_by, decided_at e legacy_flag_id.
- `ReportService::createReport()` revalida view e usa lock; `CguReportQueue` agrupa denúncias e reconcilia flags legadas; `ModerationScope` restringe por purpose.
- `ReportService::resolvePurpose()` considera `comment` sempre `forum`; **CGU-01 P0:** corrigir usando entidade hospedeira e `aculta_forum.discussion_context_resolver` ou contrato neutro equivalente, sem dependência circular. LMS deve permanecer fail-closed e moderado por Educador, sem adicionar um purpose aberto só para aceitar Comment.
- `ReportService::decideReport()` modifica a denúncia, **não** efetua no método uma ação editorial sobre o conteúdo. **CGU-02 P1:** comando moderativo explícito, ACL, transação e resultado auditável.
- `ForumReportCguBridgeHooks` cria registros espelhados de Flags; `CguReportQueue` também inclui flags antigas. **CGU-03 P1:** ledger e backfill idempotente, sem dupla contagem, sem perder status decidido.
- `ForumModerationMetricsProvider` já registra métricas agregadas pelo Statistics; não criar outro catálogo no Fórum.
- `LmsForumModerationManager` autoriza decisões para Educadores/Group e `EditorialCommentModerationForm` respeita `EditorialCapability`; integrar como comandos adapters e não remover proteção própria.

### Fronteira planejada com `aculta_forum_legacy` — F-D48

Os itens acima descrevem o baseline que ainda está no código desta branch. O
backfill/reconciliação/rollback de Flagging legado e sua chave específica
`legacy_flag_id`/motivo `sinalizacao_legada_forum` não são ownership definitivo
do CGU: pertencem à integração opcional `aculta_forum_legacy`. O CGU continua
dono de `CguReport`, casos, decisões, auditoria, fila genérica e ACL, sem
dependência ou leitura direta de fields/classes do addon.

Para denúncias correntes, o Fórum e os hosts contextuais usam a API genérica do
CGU e fornecem origem/contexto por contrato. `ARCHIVE` fica fora da fila
ordinária; qualquer medida excepcional segue trilha separada e autorizada.
`UNKNOWN` falha fechado; `CURRENT` e `CONTEXTUAL` respeitam ACL e autoridade do
host. Se a idempotência exigir um identificador de entrada, avaliar um vínculo
neutro provider/source ID, sem termos Growroom/Flagging no modelo do CGU. A
transição deve preservar denúncias, decisões e auditoria existentes em clone;
retirar `legacy_flag_id`, mudar storage ou reconciliar registros exige census,
plano reversível e autorização própria. Esta atualização é apenas planejamento:
não executa backfill, rollback, migração nem exclusão.

### Modelo proposto: denúncia != caso != ação

1. `CguReport`: entrada individual sigilosa por usuário/alvo/motivo, preserva tombstone, link com flagging migrado e timestamp.
2. `ModerationCase` (entidade revisionável do CGU ou modelagem equivalente): agrega denúncias por alvo + contexto + janela sem expor identidades, registra escalonamento, owner, atribuição, notas privadas e timeline imutável.
3. `ModerationDecision`: procedente/improcedente/em análise com justificativa; pode concluir caso sem mudar conteúdo.
4. `ModerationAction`: comando tipado explícito, autorizado novamente no submit, aplicado pelo owner do alvo (ForumPost workflow, Comment status, Node topic, LMS/Educador, Revista/Editor, Wiki/Editor) e registrado com resultado, erro/retry, compensação e auditoria.
5. Mensagens a usuários via Messenger/Mailer conforme preferência e privacidade, sem nome do denunciante em aviso ao denunciado.

### Política de escopo e autorização

- Não autorizar por bundle `comment` somente. Resolver origem `entity_type + bundle + field_name + host entity + purpose + Group/membership + ACL do alvo`. Host desconhecido = negar e registrar diagnóstico interno.
- `forum` público e grupos restritos, `revista`, `wiki` e `lms` precisam políticas distintas. Revisar cuidadosamente `ModerationScope::PURPOSES` antes de incluir LMS: acesso pedagógico não pode ser concedido à equipe do Fórum/CGU sem autorização escolar adequada.
- A fila global deve obedecer `ModerationScope` E Entity Access do alvo; em conteúdo não publicado oferecer visão de revisão autorizada via adapter específico, nunca bypass irrestrito por `accessCheck(FALSE)` isolado.
- Denunciante visualiza somente comprovante/estado próprio, sem notas internas, pares denunciantes ou identidade do moderador quando sensível. Moderação tem histórico seguro e retenção configurável.

### Interoperabilidade com Discourse/CGU/UI Patterns

Fila em MAIN: `/painel-administrativo/moderacao/denuncias` (recorte `purpose=forum` por link do painel Fórum). Casos oferecem filtros, prioridade, atribuição, agrupamento, contexto de conteúdo/autor permitido, notas de equipe, ações de confirmação, histórico e recurso. Render em SDCs/UI Patterns; o CGU fornece dados neutros e não produz CSS próprio. Receber relatório sem fechar tópico automaticamente: o mesmo princípio de revisão humana do Discourse.

### Sequência de implementação e aceite

1. Corrigir `resolvePurpose` via provider/host; Unit+Kernel de Comment em revista/wiki/lms, ForumPost, tópico, publicação sem host e Group privado.
2. Inventariar dados e migração Flagging → CGU. Separar a compatibilidade histórica para `aculta_forum_legacy`; dry-run de contagens/chaves, matriz de origem e backfill/rollback idempotentes somente no escopo autorizado, sem apagar tabelas ou denúncias sem plano próprio.
3. Extrair registry de `ModerationTargetAdapterInterface` para integrar regras Forum/LMS/Revista/Wiki. Serviços opcionais não criam dependência circular.
4. Modelar caso/notes/timeline; acesso de campo e cache privado.
5. Vincular decisão à ação explícita; simular rollback de falha, concorrência e repetição. Proibir mutações GET/HEAD.
6. Conectar métricas `aculta_statistics` ao caso e ação, mantendo as contagens existentes por denúncia e sem identificador pessoal; confrontar snapshots com baseline.
7. Compor UI Patterns no painel CGU e Test Matrix: anônimo, autor, denunciante, moderador Fórum, revisor Revista, editor Wiki, Educador LMS, admin MAIN, matrícula revogada, flagging repetido, action partial failure.

**Nenhuma implementação funcional ou migração foi efetuada nesta entrega documental.**
