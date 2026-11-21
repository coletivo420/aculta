> **SEGUNDA ETAPA DA INTEGRAÇÃO (2026-11-20):** `aculta_forum.marley_topic_actions` conecta o inspetor frontend do Marley a três comandos **reais do Fórum**, reutilizando Form API e owner services: fixar/desafixar, fechar/reabrir e arquivar/desarquivar. As rotas `/marley/forum/topico/{node}/{fixar|fechar|arquivar}` exigem `moderate aculta forum topics`, verificam o nó atual do Forum e acessibilidade no domínio de origem por `DomainSiteManager::runInSite('forum')`. O CGU mantém denúncia/decisão separada da mutação do conteúdo. A fila também verifica topic/target no domínio Fórum e resolve links canônicos para esse host. Nada altera backend/admin, não implementa ações destrutivas, split/merge ou popin HTMX nesta etapa. Testes de contrato publicados; Runtime e Browser ainda DEFERRED.

> **Integração real CGU em PR #394 (2026-11-20):** A aba Fórum agora usa `aculta_cgu.forum_marley_queue` no frontend e o CGU entrega dados reais + formulário de decisão. [Contrato e matriz MCGU-01..09](../../aculta_cgu/docs/MARLEY-CGU-FILA-FRONTEND-2026-11-20.md). Isto substitui o link antigo `aculta_user.denuncias_forum` e não concede backend a moderadores. Os comandos de mutação de conteúdo Fórum ainda exigem adapter frontend próprio; não apresentar Stories como ações executadas.

# Studio Marley - Stories SDC/UI Patterns do frontend e paridade Discourse

**Data:** 2026-11-20. **Branch:** feat/forum-paridade-discourse (PR #394).
**Natureza:** protótipos em Stories, não habilitação do sistema de moderação no Runtime.
**Ownership:** aculta_studio = shell, menus e composição; aculta_forum = tópicos/ForumPost/Comment e autorização por alvo; aculta_cgu = denúncia, decisão e auditoria; aculta_magazine = editorial; aculta_gallery = mídias; aculta420_v2 = SDCs/Stories/ícones. Backend somente administrator.

## Novo SDC de composição

`aculta420_v2:studio-marley-workspace`, grupo `Studio`, cria a **geometria visual** do espaço operacional: cabeçalho, navegação por área, ações, resumo, filtros, conteúdo principal, inspetor lateral e rodapé. Ele consome o `studio-shell` compartilhado e os slots contêm exclusivamente render arrays/SDCs autorizados pelo módulo dono. Não cria outra rota `/studio`, menu duplicado, controller, regras de negócio nem endpoint de mutação. Em mobile as colunas viram uma só; os tokens CSS do tema suportam claro/escuro.

## Stories publicadas na UI Patterns Library

| Componente existente/novo | Story | Paridade/objetivo |
| --- | --- | --- |
| studio-marley-workspace | `forum-fila` | Review Queue: casos, contagens, estados, filtros e inspetor |
| studio-marley-workspace | `forum-caso` | Exame de caso: origem, flags, audit trail e ações separadas |
| studio-marley-workspace | `forum-vazia` | Fila vazia sem inventar dados |
| studio-marley-workspace | `revista-fluxo` | Editorias da Revista, estados e listagem de trabalho |
| studio-marley-workspace | `galerias` | Catálogo e revisão de mídias do Studio Marley |
| popup | `marley-moderar-topico` | Fixar, fechar, arquivar, slow mode, solved, assign, move, split, merge |
| popup | `marley-decidir-denuncia` | Decisão CGU ≠ ação editorial (ocultar/restaurar) |
| popup | `marley-moderar-resposta` | Aprovar, ocultar, restaurar e examinar sinalização |
| filter-chips | `marley-review` | Estados Pendentes/Atribuídos/Resolvidos |
| statistics-moderation-summary | `marley-forum` | Indicadores agregados sem dados de denunciantes |
| studio-listing-table | `marley-queue` | Tabela responsiva de casos CGU e links do owner |

**Componentes consumidos diretamente:** `studio-shell`, `studio-listing-table`, `surface-panel`, `filter-chips`, `statistics-moderation-summary`, `popup`, `moderation-submit`, `badge`, `button`, `icon`. Todas as ações visuais usam ícones do pacote Phosphor existente e estão desativadas nas prévias: **Story não implica endpoint nem permissão funcionando**. Não usar os dados ilustrativos como dados operacionais.

## Regras de integração funcional posteriores

1. **Só `administrator` acessa backend.** A fila moderador pertence à aba Fórum do Marley, servida no frontend. O `ForumTab` ainda depende da rota administrativa `aculta_user.denuncias_forum` e precisa ser corrigido antes do aceite de FDC-R3/T-30/T-41.
2. Render arrays reais vêm de `MarleyPresenter`/providers, com itens que passam na ACL do owner. O SDC não executa consulta nem deduz permissões por `role`/peso.
3. Ao selecionar um caso, UI contextual deve mostrar o alvo autorizado com resumo e eventos sem expor dados privados. Os botões devem virar Form API via POST+CSRF+HTMX, com fallback sem JS, transações, locks, auditabilidade e cache.
4. Resolver/rejeitar denúncia **não** aplica automaticamente ação editorial. Comandos de edição/ocultação separados exigem autorização por entidade, origem, Domain/Group e estado.
5. `aculta_forum_post` é a resposta corrente revisionável. Split/merge precisam ser corrigidos para operar no storage real, preservando revisão, anexos, threads, eventos, Index/Messenger/Statistics. Arquivo histórico Growroom permanece somente leitura.
6. Slow mode, solução aceita, atribuição e fechamento devem ser conectados aos serviços específicos quando aprovados, sem dar poderes do Discourse Admin ao moderador. `aculta_cgu` é owner de casos e decisões, não uma segunda tabela de fórum.
7. UI Patterns Library: validar descoberta das 11 Stories, render Twig, variantes light/dark, width 320/390/768/1024/1440, foco/aria, popup aberto, textos localizados e ícones Phosphor presentes. Não usar mock de dados para alegar PASS em Runtime.

## Aceite específico das Stories (marco visual)

- **SM-01**: cinco páginas completas e seis variações especializadas aparecem no catálogo sem exceções Twig, YAML ou schema.
- **SM-02**: cada página reaproveita os SDCs catalogados; não introduz bloco de UI/API já existente em duplicata.
- **SM-03**: queue mostra estado, motivo, tipo e inspeção, sem nomes reais de denunciantes.
- **SM-04**: popups apresentam com clareza decisão de caso e medida editorial separadas.
- **SM-05**: controles tópicos/respostas exibem ícones Phosphor e rótulos equivalentes a funções Discourse aprovadas; todos desabilitados nos exemplos.
- **SM-06**: navegação da prévia não atravessa backend; frontend runtime usa links do Studio após ACL. Sem access-check no SDC.
- **SM-07**: layout responsivo, claro/escuro, keyboard e contraste aprovados no browser do Homelab.
- **SM-08**: controles operacionais conectados aos owners em tarefa posterior e testados T-29..42, T-51..57. Não considerar SM-08 concluído por mock.

**Status:** Stories e SDC publicados, CSS lint corrigido. Testes do catálogo, inspeção visual e smoke runtime ainda DEFERRED. Manter no escopo integral da PR #394 e [handoff CLI](../../aculta_forum/docs/HANDOFF-AGENTE-CLI-FORUM-COMPLETO-2026-11-20.md).
