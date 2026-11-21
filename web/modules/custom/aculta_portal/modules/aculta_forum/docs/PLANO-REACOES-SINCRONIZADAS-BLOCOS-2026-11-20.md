# FDC-R14 - Contadores de reações sincronizados nos blocos do Fórum

**Data:** 2026-11-20. **Branch:** feat/forum-paridade-discourse, PR #394.
**Estado:** código inicial publicado em 8c27f24d, e7505f8c, 7221b839, 92672260; QA/Runtime e Browser NÃO homologados.
**Escopo:** abertura do tópico, respostas ForumPost, Comment corrente, cartões normal/espelhado, barras de recebidas, autoria, perfil e consumidores LMS/Wiki/Revista autorizados. Esta frente COMPLEMENTA o Fórum integral FDC-R0..R13; não substitui outras entregas.

## Diagnóstico e solução

O POST atualizava a linha de voto e substituía só a barra HTML da mensagem via HTMX; tags de cache invalidados não modificam os outros cards já abertos. Comentários da Revista ordenavam pela soma bruta de emojis, ignorando a descurtida. O cabeçalho de Comment não recebia total_count e tinha ID duplicado com a barra do rodapé.

**Solução server-driven:** DiscussionReactionManager permanece fonte de verdade do banco; DiscussionReactionBuilder prepara total_count (max(0,positivos-descurtidas)), contagem recebida do autor e positivos dados pelo clicador. O SDC discussion-reaction-bar entrega esses números em atributos data-* do fragmento HTML retornado após POST. Drupal behavior `aculta_forum/reaction-counter-sync` observa o evento `htmx:afterSwap` e sincroniza:
1. Saldo e breakdown de emojis da **mensagem**, inclusive todas as suas aparições na página, cabeçalho separado e barra de rodapé, com novos IDs de popover únicos quando clonados.
2. Curtidas recebidas **pelo autor** nos cartões de abertura, respostas normais/espelhadas e perfil quando presentes.
3. Curtidas positivas **dadas pelo eleitor**, onde visíveis; nunca incluir dislikes nesse agregado positivo.
4. Estado ativo, aria-pressed e botão Undo em cópias da mesma barra.

O HTML servidor também é válido sem JS: POST+PRG 303 local recarrega os números. IDs de mensagem são filtrados por tipo/id; IDs de usuários por uid; não compartilhar contadores privados cross-Domain/Group. Cache Drupal: manter context user/permissões e tags de entidade e `aculta_user_reactions:UID`; memo de `getUserStats` só por request e invalidado no toggle. Não usar cálculo otimista nem segundo fetch. O SDC forum-post-card exibe o total do autor com emoji brócolis.

**Contrato imutável:** padrão 🥦 broccoli; botão negativo isolado 👎🏽 thumbs-down-medium; popin SOMENTE positivos + ação Undo (nunca descurtida). Um voto por usuário e alvo; positivo troca por positivo sem alterar totais, apenas distribuição por emoji; negativo troca o sinal da contribuição, com clamp 0; Undo remove qualquer voto e não vira dado persistido. Nenhum evento negativo concede conquistas.

## Matriz de aceite FDC-R14: T-62 a T-70

| ID | Experimento | Aceite |
| --- | --- | --- |
| T-62 | Curtir abertura | Mensagem +1, autor recebido +1, eleitor dado +1, sem refresh |
| T-63 | Curtir reply normal e espelhado | Mesmo comportamento com autor e message id corretos, inclusive quando outros cards do mesmo autor estão na tela |
| T-64 | Descurtir / Undo / segundo clique | Contadores recalculados no servidor, nunca negativos, botão mão parda fora do popin |
| T-65 | Troca positiva / positiva-negativa | Mesmo registro UID/entidade, detalhamento por emoji e pressed/Undo corretos |
| T-66 | Mensagem duplicada e comentário com cabeçalho | Todas as barras recebidas e breakdown sincronizados, sem duplicar popover IDs |
| T-67 | Perfil / zero-to-one-to-zero | Dados em usuário/perfil atualizados; sem stale cache; nunca substituir contador de outra pessoa |
| T-68 | Wiki, Revista, LMS, Group e arquivo Growroom | ACL por contexto, arquivo read-only, ordenação Revista usa saldo líquido |
| T-69 | Sem JavaScript, erros/concorrência/CSRF | PRG 303 seguro, não muda DOM em 403/409, rejeita duplo voto e não infere valores otimistas |
| T-70 | Performance/a11y/CI | Kernel+Functional+Browser+Playwright, 320/390/768/1440, keyboard/aria-live/dark, PHPStan10, PHPCS, Stylelint/ESLint; N+1 e benchmarks 1k/10k |

**Status:** testes de contrato apenas escritos, CI/Runtime/browser DEFERRED até evidências. Importante: atualização é instantânea apenas **na página que recebeu o HTMX**, não é push entre abas/sessões; outros clientes atualizam na próxima requisição. Não criar SSE/polling geral sem justificativa de produto.

## Trabalho restante para agente CLI

- Rodar testes reais T-62..70 e DV-01..19 em worktree isolada; ajustar origem/ACL e UI Patterns reais do node de abertura, ForumPost e Comment (não só stories).
- Testar escopo restrito LMS/Group, HTML no-JS, caches, aria-live, validação HTMX, duplo clique rápido/409, trocas de emojis, descurtida/Undo e foco após swap.
- Homologar dados iniciais dos cartões com render do servidor, sem flash de zero incorreto; revisar AuthorCardBuilder/SDC e perfil; garantir disabled/retired emoji e item oculto.
- Medir N+1 com 100/1k/10k cards, preload batch se necessário, e reconciliação de `aculta_statistics` e `aculta_game`. Discutir semanticamente clamp por item versus clamp agregado antes de alterar métricas.
- Integrar esta frente ao plano MAIOR de moderação, Studio, CGU, enquetes, Game, Index, Messenger e paridade Discourse. Não declarar PR completa por ter concluído apenas contadores.

[Roadmap Fórum](ROADMAP.md) | [Handoff CLI integral](HANDOFF-AGENTE-CLI-FORUM-COMPLETO-2026-11-20.md) | [Matriz principal](MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md)
