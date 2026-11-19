# Política vigente - acervo histórico Growroom separado dos comentários padrão

Data: 2026-11-19. Decisão do responsável: **comentários legados do Growroom permanecem separados dos comentários padrão**, do motor de novas respostas e dos comentários da Revista, Wiki e LMS. O objetivo de clone funcional Drupal do Discourse não elimina esta divisão.

**Estado de execução no Runtime alvo, informado pelo responsável em 2026-11-19:**
a importação histórica ainda não foi feita e não existem autores, tópicos ou
comentários do acervo importados no Runtime. Esta política descreve como esses
dados deverão ser classificados e tratados quando forem importados; não afirma
que já estejam presentes no banco atual. Corpus e manifests de origem ficam
fora do Runtime e não são alvo de limpeza de dados da refatoração.

## Três trilhas não intercambiáveis

| Trilha | Entidades e proveniência | Escrita | Destino |
| --- | --- | --- | --- |
| Acervo histórico Growroom | Tópicos e Comments importados/restaurados com `field_autor_legado` ou `legacy_author`, quando presentes. Marcadores e host precisam ser auditados | **Somente leitura**. Não editar, responder, citar como Comment comum, reagir, mover, mesclar, excluir em lote ou criar aviso/conquista novos. Exceções legais exigem processo explícito | Arquivo com apresentação e busca próprios, URLs, ordem, parent e autoria histórica preservados |
| Growroom novo | `node:aculta_forum_topic` + `aculta_forum_post` revisionável | Respostas novas são ForumPost, nunca Comment legado/padrão | Experiência Discourse via UI Patterns |
| Comentários contextuais padrão | `aculta_discussion_post` sob `wiki_entry`, `revista`, `aculta_lms_topic`; inventariar separadamente `aculta_lms_answer_comment` | Motor universal `aculta_forum` fornece API e UI, sem retirar as permissões de cada host | Thread no próprio conteúdo; não republicar como tópico público |

**A separação física ainda não está comprovada.** O histórico pode coexistir no mesmo storage Core Comment que registros correntes. Nesta primeira fase aplicamos guardas de comportamento, não a promessa de uma entidade física já apartada. O marcador de autor é evidência inicial, não classificação suficiente para dados antigos sem marcador. Casos ambíguos devem falhar fechados após censo.

## Proibições permanentes

- **Não migrar nem converter** comentários históricos do Growroom para `aculta_forum_post`, `aculta_discussion_post` de outros hosts, ou outra entidade de comentários padrão. A eventual reestruturação de armazenamento deve preservar o acervo, sem converter sua natureza ou misturá-lo.
- Não executar indiscriminadamente `migrations/aculta_forum_comment_*.yml` nem seguir planos históricos de conversão Comment->ForumPost. Antes de remover os scripts, localizar todas as dependências e preservar documentação.
- Não misturar contagens de histórico com engajamento novo; não emitir eventos, avisos ou conquistas por importação, rebuild, migração ou restauração.
- Não enviar arquivo histórico à fila CGU corrente; necessidade de medida excepcional precisa de protocolo específico, auditável e autorizado.

## Integração obrigatória com demais módulos

- **Index:** `ForumContentIndexProvider` hoje projeta Comments de tópicos Growroom, sem segmentação completa por proveniência. Planejar classificação explícita em catálogo, índices de arquivo e corrente e controle de acesso, sem retirar a descoberta histórica. Mudar projection version/rebuild somente mediante aprovação.
- **Statistics:** `ForumMetricsProvider` conta Comments Core e ForumPosts. Separar métricas atuais/históricas e impedir que eventos de importação incrementem `forum.comments.created`, `forum.replies.created` e participação. Testar reconciliador e indicadores CGU.
- **CGU:** arquivo fora das filas ordinárias, salvo procedimento excepcional aprovado; `ReportService` resolve purpose pelo host para Comments atuais. LMS exige adapter Group/Educador antes de habilitar denúncias, sem cair em `forum`.
- **Messenger/Game:** arquivos não criam aviso, assinatura, XP, participação ou perfil de usuário atual.
- **UI Suite:** componentes SDC podem ser reutilizados com variante obrigatória read-only/archive, sem ações de interação; dados e classificação preparados pelos serviços, não pelo Twig.
- **Todos os hosts:** Fórum é motor único de comentários, mas Comment Core e ForumPost permanecem entidades distintas conforme o contexto.

## Estado de implementação e limites

Na PR #377, `DiscussionReplyController`, `ForumQuoteHooks` e `DiscussionReplyAccess` ganharam guardas para host, acesso à citação, tombstones e markers históricos; CGU resolveu a classificação de Comments por host e as exclusões em GET passaram a formulário. Falta executar testes Unit/Kernel/Functional e homologar Runtime.

**Lacuna:** `DiscussionReplyFormBuilder` ainda cria Comment para o compositor genérico, inclusive potencialmente no host Growroom. O agente deve fechar esse caminho, encaminhando novos posts Growroom para `ForumPostSubmissionForm`; sem deixar o archive editável. A guarda baseada em `field_autor_legado` deve ser reforçada com classificação por proveniência estável e auditada.

## Etapas para separação estrutural posterior

1. Censo **somente leitura** de `entity_type/bundle/field_name/host/uid legado/marker/status` no Runtime de teste, sem exportar dados pessoais.
2. Mapear `Comment` storage, Views, routes, Search API, Index, Statistics, Flags, Messenger, Game, CGU, permissões, revisões e URLs. Inventário do acervo anterior não comprova o estado atual.
3. Introduzir classificador único testado (`ForumDiscussionOriginPolicy` ou equivalente) para `historic_archive`, `forum_live`, `contextual_comment`, `unknown`; unknown falha fechado.
4. Corrigir todos os writers, filtros e projeções. Provar que 100% do acervo mantém identidade, hierarquia e consulta histórica; migrações de dados seguem proibidas.
5. Se isolamento físico for requerido, apresentar **proposta específica sem conversão de conteúdo**, dry-run, testes de integridade, rollback e autorização operacional distinta.

Consultar [ownership](OWNERSHIP.md), [plano FDC](PLANO-CLONE-DISCOURSE-UI-SUITE-CGU-2026-11-19.md) e [matriz de aceite](MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md).
