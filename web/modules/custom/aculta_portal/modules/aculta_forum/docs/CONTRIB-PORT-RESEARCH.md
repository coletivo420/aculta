# Pesquisa de módulos contrib para o ACULTA Forum

Pesquisa e inspeção realizadas em 2026-10-20. O objetivo é aproveitar APIs e
comportamentos úteis sem adicionar armazenamento paralelo, dependências sem
necessidade ou código inseguro. Versões instaladas foram conferidas no
`composer.lock` e em `web/modules/contrib`; páginas upstream foram verificadas
na data acima. Antes de copiar código de uma release específica, revisar o
arquivo de licença, avisos de copyright e dependências dessa release, preservar
atribuição e adaptar aos contratos atuais do Portal.

## Matriz

| Projeto / versão | Capacidades observadas | Uso no ACULTA | Decisão e limite |
| --- | --- | --- | --- |
| [Forum contrib](https://www.drupal.org/project/forum) 1.1.3, dependência histórica removida em `0.1.0-dev.50` | Taxonomy, Nodes e Comments organizavam fóruns/tópicos/respostas; validator de uninstall e testes upstream orientaram a retirada | Inspecionar os efeitos da remoção e portar somente capacidades adequadas | Não é o motor-alvo e não será reabilitado. O conteúdo antigo foi excluído do Runtime de teste sob a autorização registrada, o módulo foi desinstalado e o pacote removido. Não portar sua apresentação específica. |
| [CTools](https://www.drupal.org/project/ctools) 4.1.1, instalado | API de plugins/contexts e `ctools_views` para opções de pager e quantidade por posicionamento | Reutilizar APIs instaladas nos blocos Views e integrações contextuais adequadas | Usar o projeto diretamente, não copiar sua infraestrutura. Filtros de acesso, Domain e contexto continuam fixos na View. Ver [`aculta_portal/docs/CTOOLS.md`](../../../docs/CTOOLS.md). |
| [Flag](https://www.drupal.org/project/flag) 5.1.1, instalado | Ações de seguir, salvar e sinalizar entidades, com integração de Views e permissões | Reutilizar flags atuais `seguir_topico` e `salvar_topico`; avaliar denúncia como flag somente com política de moderação e abuso definida | Sem tabela paralela de participação. Notificação após follow fica sob `aculta_messenger`; Flag não autoriza acesso ao alvo nem substitui access da entidade. |
| [Comment Mover](https://www.drupal.org/project/comment_mover) 2.x alpha, não instalado | Mover comentários e seus descendentes entre entidades e posições; documentação descreve clipboard/cut/paste e conversão | Referência comparativa para os formulários e invariantes de movimento próprios | A fila upstream registra pendência de compatibilidade automatizada com Drupal 11; não instalar. O Portal já tem `ForumCommentMover`, com transação, lock, revalidação de host/pai, thread/statistics e testes próprios. Não copiar a estratégia de recriar Comments sem preservar CIDs e contratos do ACULTA. |
| [Quote](https://www.drupal.org/project/quote) 4.1.0, não instalado | Link de citação em Node/Comment que preenche formulário Comment; compatível com Drupal 11 e CKEditor 5 | Padrão de produto portado em `aculta_forum` dev.53, sem trazer dependência ou código executável upstream | A versão ACULTA limita o recurso a Comments publicados, legíveis e respondíveis nos providers existentes; não copia conteúdo inacessível. Citação por seleção e novos contextos precisam de trabalho próprio. |
| [Achievements](https://www.drupal.org/project/achievements) 3.0.3, não instalado | API de marcos e badges baseada em eventos; opt-out e modo não competitivo são módulos opcionais | Referência de desenho para marcos comunitários e concessão não retroativa | Não instalar nem criar segundo catálogo/ledger: `aculta_user` é fonte do catálogo e `aculta_game` avalia eventos aprovados. Reutilizar somente ideias compatíveis com idempotência, privacidade e exclusão de importações históricas. |
| [Gamification](https://www.drupal.org/project/gamification) 2.0.2, não instalado | Configuração de regras de eventos/pontos; integração com User Points/Transaction | Comparar limites de janela, repetição e atribuição ao desenhar eventos do `aculta_game` | Não instalar: cria dependência de sistema de pontos e transações que o ACULTA não usa como fonte canônica. Sem pontos cumulativos/ranking para substituir conquistas existentes. |
| [User Stats](https://www.drupal.org/project/user_stats), não instalado | Estatísticas de perfil, contagens, online/offline e IP; integração com Views | Inventário histórico de itens do Author Pane/Advanced Forum | Não usar nem copiar coleta de IP/online. O projeto informa que não está coberto pela Security Advisory Policy; estatísticas públicas devem vir de serviços ACULTA access-aware e cacheáveis. |
| Advanced Forum 7.x-2.8 e Author Pane históricos | Layouts lateral/empilhado, metadados de autor, ações, estado de leitura e estatísticas | Matriz funcional em [`ADVANCED-FORUM-PORT.md`](ADVANCED-FORUM-PORT.md) e cartões pertencentes a `aculta_user` | Código Drupal 7/PHPTemplate não é executável no Portal. Portar comportamento e critérios, não arquitetura obsoleta, estilos nem decisões de privacidade. |

## Uso de código e proveniência

1. Preferir o código Core/contrib instalado e a sua API pública quando a função
   já existir; CTools e Flag são exemplos atuais.
2. Para projeto não instalado, inspecionar a release exata e os testes antes de
   decidir. Código só pode ser portado depois de validar compatibilidade Drupal
   11.3+, licença, copyright, advisories, manutenção e contratos de acesso/cache.
3. Registrar caminho upstream, versão/tag, arquivo ou algoritmo consultado,
   licença e alterações no commit/documentação da implementação. Nunca copiar
   PHPTemplate, APIs removidas ou código de uma versão não suportada como
   implementação runtime.
4. Reutilizar ideias de UX sem replicar tabelas e serviços já pertencentes a
   Comment, Flag, History, Views, `aculta_user`, `aculta_messenger`,
   `aculta_image` ou `aculta_game`.

## Fontes técnicas consultadas

- [Forum: estrutura com Taxonomy, Node e Comment](https://www.drupal.org/docs/extending-drupal/contributed-modules/forum/overview)
- [CTools 4.x e compatibilidade Drupal 11](https://www.drupal.org/project/ctools)
- [Flag e integração com Views](https://www.drupal.org/project/flag)
- [Comment Mover 2.x: escopo e requisitos](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/comment-mover/comment-mover-2x-user-guide)
- [Pendências de compatibilidade do Comment Mover](https://www.drupal.org/project/issues/comment_mover)
- [Quote 4.x e compatibilidade Drupal 11](https://www.drupal.org/project/quote)
- [Achievements e API de marcos](https://www.drupal.org/project/achievements)
- [Gamification e dependência de User Points](https://www.drupal.org/project/gamification)
- [User Stats: escopo e status da política de segurança](https://www.drupal.org/project/user_stats)
