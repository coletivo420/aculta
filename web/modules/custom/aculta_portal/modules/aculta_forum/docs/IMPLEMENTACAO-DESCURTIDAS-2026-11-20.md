# Descurtidas e saldo geral de reações - implementação e gates

**Data:** 2026-11-20. **Branch:** `feat/forum-paridade-discourse`, PR #394. **Patch funcional inicial:** commit `d9f57922a4c81ccee0c515a4dbfff9c50330de11`. **Status:** CODE PARTIAL; sem execução de PHPUnit, PHPCS, PHPStan, Kernel, Browser ou Homelab nesta sessão remota. A confirmação de push é de GitHub, não de deploy.

## Regra de negócio aprovada e semântica adotada

**Todos os emojis/reactions positivos atuais, inclusive IDs desabilitados posteriormente, somam +1 no contador geral de curtidas da publicação**; a reação reservada `dislike` soma -1. Cada pessoa tem **um único voto por publicação**, podendo escolher 1 emoji positivo OU descurtir, trocar e desfazer. A descurtida aparece como **um único botão de reação com emoji Microsoft Fluent `thumbs-down`**, junto ao botão positivo, no **mesmo componente visual `aculta420_v2:reaction-emoji`**. Não há botão nem ação visual separada de "Undo" ou "Desfazer": **clicar novamente na reação que está ativa a remove**. Isso vale para o emoji negativo, a curtida rápida e cada emoji da paleta. Clicar em outro emoji positivo ou na descurtida troca a reação do mesmo UID/alvo. O botão negativo fica fora da paleta de opções positivas, como controle fixo de mesmo estilo. Já existe no banco `aculta_discussion_reaction` a UNIQUE `(entity_type, entity_id, uid)`; reutilizar, não criar tabela/voto paralelo.

```text
positivas = SUM(COUNT rows WHERE reaction != 'dislike')
negativas = COUNT(rows WHERE reaction = 'dislike')
saldo_visivel = MAX(0, positivas - negativas)
```

Exemplos: 8 reações positivas, 2 descurtidas -> saldo 6; 2 positivas, 4 descurtidas -> saldo 0 (votos permanecem no banco para reconciliação). Trocar `broccoli` por `dislike` remove a curtida +1 e registra -1, portanto altera o saldo bruto em -2; clique novamente em `dislike` remove -1. Trocar de `dislike` para `heart` remove -1 e adiciona +1. Nem o botão nem a UI mostram votos negativos individuais ou nomes de quem votou; o saldo líquido não assume valores negativos.

**Distinguir** (a) score por publicação, truncado a zero; (b) saldo de reações recebidas por autor, calculado sobre o agregado positivo menos reações negativas públicas; e (c) métricas públicas agregadas. Somatório de scores por publicação truncados pode divergir de `MAX(0, SUM(positivas)-SUM(negativas))` global; não confundir nem apresentar um como outro. Não destruir registros positivos ou reescrever avaliações antigas.

Reações continuam permitidas somente se o `DiscussionReactionAccess` existente permitir: usuário autenticado, permissão, autor diferente, publicação/host acessíveis, contexto e origem `CURRENT`/`CONTEXTUAL`. Não conceder permissão a moderador, editor ou educador por ter dado/recebido curtidas; LMS/Group e público preservam limites.

### Conquistas Game

O contrato **novo** prevalece sobre os estudos anteriores que tratavam apenas `broccoli` ou algum subconjunto de emojis como Like: **todos os votos positivos entram no contador geral de curtidas e os negativos o reduzem**. Troféus tipo Nice/Good/Great do Discourse devem usar **saldo do item**, e não `reactions_received` de todos os itens do autor. A concessão `aculta_game` ainda está PLANNED: a badge histórica `primeiro_comentario_forum` segue intacta, nenhuma migração de arquivo ou backfill, sem ranking/XP/status automático. Conquistas automáticas do User permanecem após serem concedidas pela regra vigente, mesmo se o saldo baixar; exigir moderação contra abuso. Confirmar critérios e decisão de conteúdo removido antes de ativar gatilhos 10/25/50.

## Implementação existente no patch remoto

| Arquivo/classe | Modificação |
| --- | --- |
| `Service/ReactionScore.php` (novo) | Arredondamento não negativo do saldo por alvo; todos positivos +1, `dislike` -1 |
| `Service/DiscussionReactionManager.php` | Reservar `dislike`, permitir toggle sem paleta, ler todos positivos inclusive antigos, subtrair descurtidas do total do item, corrigir incremento/decremento de aggregates positivos e eventos positivos; saldo por autor após descurtidas |
| `Controller/DiscussionReactionController.php` | Permitir `reaction=dislike` no POST/HTMX com CSRF e access já existentes |
| `Service/DiscussionReactionBuilder.php` | Encaminhar saldo, positivas e descurtidas ao SDC |
| `aculta420_v2:discussion-reaction-bar` | Botão Fluent `thumbs-down` igual ao curtir positivo, `aria-pressed` para reação ativa, segundo clique remove (não existe Undo separado), HTMX/fallback; contador sempre visível |
| `aculta420_v2:discussion-reactions-received` | Exibir `Curtidas: saldo` com descrição e atributos acessíveis; manter badges por emoji |
| `ForumMetricsProvider.php` | Corrigir `forum.reactions.active` para só positivos e acrescentar snapshots de descurtidas/saldo |
| `aculta_statistics::MetricCatalog` | `forum.reactions.dislikes_current` e `forum.reactions.net_current`, TYPE_SNAPSHOT |
| Unit `ReactionScoreTest.php` + configuração/arquivo | Cenários de saldo e archive, sem execução nessa sessão |

**Reforço de concorrência:** commit subsequente à primeira entrega introduz `LockBackendInterface` no service manager de produção, chave por `entity_type/id/uid`, leitura do voto sob lock e liberação após conclusão do objeto de transação Drupal. Lock ocupado responde conflito HTTP 409 (sem mutação). **Testes concorrentes e idempotência de retry continuam pendentes**, inclusive para o agente local. A unicidade no banco permanece obrigatória.

**Não foi criada migração/tabela nova**. O ID da reação negativa não pertence a `allowed_reactions` e não deve ser adicionado à paleta da configuração.

## Auditoria pendente P0/P1 para o agente local

### P0 - segurança e funcionalidade

1. Validar sintaxe PHP/Twig/schema SDC e fixtures. O PHPStan 10 pode exigir estreitar tipos `array` de `fetchAllKeyed()` na classe `ReactionScore`; ajustar sem suprimir erros indevidamente.
2. **Concorrência:** foi adicionado lock `entity_type + id + uid` com leitura sob lock, transação e resposta 409, mas **ainda faltam testes reais com duas ou mais requisições**, prova de liberação após commit, retries/HTMX e lock que expire em caso de worker morto; corrigir eventuais falhas sem bloquear globalmente.
3. **Recalcular agregados em trocas de polaridade:** positivo -> negativo, negativo -> positivo, desfazer negativo, positivo -> positivo, negativo -> negativo, escopo público -> privado e vice-versa. Verificar que `aculta_discussion_reaction_user_stats` segue contando somente positivos e `getUserStats()` apresenta saldo líquido sem mostrar negativos indevidamente.
4. **Origem/ACL:** negar ARCHIVE, UNKNOWN, tópico despublicado, autoria própria, visitante não autenticado, UID bloqueado, usuário sem email_confirmed conforme regras, revogação de Group e troca de Host entre GET e POST. Proteção ocorre em endpoint, não apenas ocultando botão. Não criar moderação pedagógica LMS no CGU.
5. **Cache e performance:** invalidar item/autores/Views/Stats após troca ou remoção; evitar COUNT de descurtidas com query N+1 para cada autor em lista; índice adequado `(author_uid, is_public_context, reaction)` só após EXPLAIN, schema update + index idempotente autorizado.
6. **Score e exibição:** contador geral sempre visível para visitante e logado, botão negativo exatamente um na barra, sem aparecer na paleta, SVG `thumbs-down.svg` do pacote Microsoft Fluent Emoji (Color, Default, MIT), via `aculta420_v2:reaction-emoji`, modo claro/escuro, 320/390/768/1440, zoom 200/400%, keyboard, aria-pressed, fallback sem JS e atualização HTMX. Revisar se o popup/toolbar do tópico usa todos os displays esperados, inclusive ForumPost, Comment contextual e Node Topic.
7. **Redirect não-JS:** conferir validação da origem de `destination`/`Referer` no controller existente; não introduzir open redirect. POST com CSRF, sem GET mutável.
8. **Contador de autor público:** `getUserStats` é agregado legado que inclui scope `PUBLICATION` (Revista) por definição; decidir se User público mostra geral do Portal ou somente Growroom. Criar API de escopo separada para Game e Statistics, não usar `getUserStats` amplo como likes Growroom. Respeitar privacidade de autor.
9. Não duplicar Comment histórico com `ForumPost`; CURRENT/CONTEXTUAL/ARCHIVE respeitados em tabelas, snapshots e badges.

### P1 - integração Analytics/Game

1. Confirmar `MetricCatalog` no Runtime e `MetricProviderInterface::provides()`; snapshots `forum.reactions.active` (= positivos), `forum.reactions.dislikes_current`, `forum.reactions.net_current` (= max 0 do agregado de CURRENT público). O snapshot global **não** é soma de scores individuais truncados; documentar a semântica e não converter em contador histórico cumulativo.
2. `ForumReactionEvent::ADDED` continua evento de **nova reação positiva**: negativo não deve emitir. Reação de `dislike` para positiva pode emitir `ADDED`; negativo -> positivo precisa teste idempotente e reconciliação de métricas; removida não elimina histórico de eventos.
3. Criar contrato de resultados por UID `ForumMemberActivitySummaryInterface` no Fórum/Stats; Game usa esse contrato para thresholds de score por item e apresentação permitida no Studio/User. O `aculta_game` permanece opcional; sem dependência do Fórum para Game.
4. Configuração de thresholds no `aculta_game` (critério auditável, versionado) e concessões no `aculta_user.badge_service` existentes, nunca storage de pontos. Prêmios do Discourse Nice/Good/Great só se score de UM tópico/resposta superar limiares com pessoas elegíveis, sem backfill do arquivo.
5. `aculta_index` e Messenger recebem somente fatos públicos permitidos; não indexar identidades de descurtidas ou popularidade de cursos privados; avisos não geram punição ou privilégios.
6. Capturar snapshots e reprocessamento de eventos sem duplicação em `aculta_statistics`; reconciliação `dry-run` e testes MariaDB.

## Testes de aceite propostos

| ID | Cenário | Esperado |
| --- | --- | --- |
| DV-01 | 8 reações positivas antigas distintas por ID, 2 negativas | Score 6, sem alterar rows antigas |
| DV-02 | 1 positivo -> dislike | Score bruto -2 na transição, registro único, autor positivas -1 e dislikes +1 |
| DV-03 | dislike -> positivo | Score sobe 2; aggregate positivo e evento atual reabilitados uma única vez |
| DV-04 | dislike -> clique dislike | Remove voto negativo, score sobe 1 até clamp; não altera aggregate de reações positivas |
| DV-05 | mesmo voto em duas requisições concorrentes | Um único row e resposta não-500; invariantes e eventos coerentes |
| DV-06 | 0 positivas, 5 descurtidas | Score público 0; negativos não desaparecem ao receber nova curtida |
| DV-07 | emoji desativado na configuração mas ainda no banco | Continua valendo +1 no score geral, sem aparecer como opção ativa na paleta |
| DV-08 | troca por outra reação positiva | Score não muda; reaction stats positiva não duplica |
| DV-09 | estado publicado->despublicado / CURRENT->ARCHIVE | GET/POST/Stats/Game negam ou reclassificam conforme contrato, sem prêmio novo |
| DV-10 | Viewer anônimo e usuário de LMS/Group diferente | Sem reação ativa ou vazamento de score de conteúdo privado |
| DV-11 | UI teclado/HTMX/sem JS/tema claro-escuro | Botão único, aria-pressed, saldo atualizado, sem redirect externo |
| DV-12 | Snapshot vs evento | negativos fora de `forum.reactions.added`, 2 novos snapshots coerentes após reconciliação |
| DV-13 | 10/25/50 por score por item | Game nunca premia usando reação bruta do autor ou contagem de alvo diverso |
| DV-14 | 1k/10k/100k posts, cache quente | Sem N+1, limites definidos, EXPLAIN e benchmarks |
| DV-15 | Game desativado e arquivo histórico coexistente | Fórum/Stats funcional, 0 prêmios históricos, arquivo imutável |

**Status DV-01..15:** PLANNED. Unit inicial de cálculo escrito no Git, ainda não executado. Não chamar entrega completa até testes e Runtime.

## Fontes de verdade e continuidade

- [Roadmap Fórum, FDC-R0..13](PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md) e [estudo de Game/Discourse](ESTUDO-DISCOURSE-GAME-METRICAS-INTEGRACOES-2026-11-20.md).
- [Roadmap de Game](../../aculta_game/docs/ROADMAP.md), [FST Statistics](../../aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md) e [Studio operacional](../../aculta_studio/docs/PLANO-FRONTEND-OPERACIONAL-2026-11-20.md).
- [Prompt CLI de continuação integral](HANDOFF-CLI-DESCURTIDAS-2026-11-20.md).

### Ajuste visual solicitado - 2026-11-20

Foi substituído o ícone Phosphor negativo pelo SVG original Microsoft Fluent Emoji, na mesma biblioteca de reações já usada pelo ACULTA. Fonte: `microsoft/fluentui-emoji/assets/Thumbs down/Default/Color/thumbs_down_color_default.svg` (licença MIT). Botões positivos, negativos e paleta usam `aria-pressed` e enviam o ID atual ao `toggleReaction()`: segundo clique na seleção ativa **remove o voto**, sem outro botão. O emoji `thumbs-down` é um asset visual de reação; o ID de armazenamento negativo continua `dislike`, reservado fora de `allowed_reactions`.

### Ajuste vinculante: mão parda, sem duplicação no popin (2026-11-20)

O ícone do botão negativo é `thumbs-down-medium.svg`, **Microsoft Fluent Emoji Medium** 👎🏽, registrado nos packs existentes do tema (`aculta_reacoes` e `aculta_fluent_emoji`) e no catálogo `aculta_editor`. O ID persistido continua `dislike`. O botão negativo permanece **fora da paleta de emojis positivos**, e `DiscussionReactionManager::getAllowedReactions()` exclui `dislike`, `thumbs-down`, `thumbs-down-medium` e aliases, inclusive por ícone. O template da paleta também aplica guarda defensiva. Clicar novamente em qualquer reação ativa a remove, inclusive descurtir, sem ação Undo adicional. Este requisito prevalece sobre os anteriores que mencionavam mão genérica.

Validar com teste de configuração deliberadamente malformada incluindo IDs/ícones negativos na paleta e com navegador/HTMX. Até então, apenas código publicado; aceite Runtime DEFERRED.

### Regra final do popin - positiva + Undo (2026-11-20)

**Decisão mais recente e prevalecente:** o popin mostra os emojis positivos configurados e **uma opção Desfazer/Undo**, não mostra a descurtida nem o emoji de mão parda. A mão parda 👎🏽 aparece **exclusivamente no botão próprio** ao lado da curtida rápida por brócolis 🥦. O popin possui o formulário POST `reaction=undo`: remove a reação ativa, seja positiva ou negativa, sem converter Undo em um voto, emoji, trigger de Game ou métrica. Sem reação ativa, o controle fica desabilitado e um POST manual de Undo não grava linha. Continuam válidos o segundo clique no botão de brócolis/descurtida/emoji ativo e a troca entre emojis positivos; um único voto por UID/alvo.

**Segurança:** `DiscussionReactionManager::UNDO_ACTION` é comando transitório validado pelo controller e pelo próprio manager, separado de `isAllowedReaction()`; `getAllowedReactions()` e o Form admin rejeitam ID `undo` e IDs/ícones negativos, inclusive se Config Sync estiver incorreto. `undo` nunca integra `allowed_reactions`, o banco de votos nem o popin de emojis. Backend administrativo apenas para `administrator`.

**Novos testes de aceite DV-16..19 (PLANNED, código inicial escrito):**
- DV-16 - usuário com `broccoli` selecionado: popin mostra positivos + Undo, **não** mostra descurtida; Undo remove 1 voto e decrementa contador recebido.
- DV-17 - usuário com `dislike` selecionado: popin mostra Undo, mas **não** oferece opção de descurtir; Undo remove o negativo e aumenta saldo do alvo em 1 até o clamp.
- DV-18 - usuário sem reação: Undo desabilitado; POST `reaction=undo` autenticado é no-op sem inserção na tabela.
- DV-19 - configuração adulterada com `undo`, `dislike` ou `thumbs-down-medium` no catálogo positivo não cria opção positiva; remapeamento/dados legados mantêm brócolis default e emoji pardo no botão exclusivo.

As fases de Theme/Browser, Kernel/concorrência e CI devem ser verificadas no Homelab antes de promover os cenários a PASS.
