# Planejamento das ferramentas de movimento

Estado: **planejado; nenhuma ação de mover tópico ou comentário está
implementada**. Este contrato detalha os itens de organização e moderação do
[`ROADMAP.md`](ROADMAP.md), incorporando padrões observados no Discourse sem
importar seus papéis, storage ou regras de acesso.

## Referência Discourse e princípios aproveitáveis

O Discourse separa a mudança de categoria de um tópico do movimento de posts
selecionados. A interface de seleção permite mover posts para tópico existente
ou criar tópico novo (split); o fluxo também oferece opções de ordem cronológica
e registra aviso no tópico de origem. O merge movimenta o conjunto de posts e
tem tratamento especial para a origem. No código upstream, endpoints verificam
`guardian.ensure_can_move_posts!`, e o modelo encaminha a operação a `PostMover`
em vez de alterar relações na UI diretamente. Essa separação é a referência de
produto, não uma prescrição para Drupal.

O Discourse também apresenta uma fila de revisão filtrável por tipo/estado,
flags configuráveis, ordenação/priorização e ferramentas colaborativas de notas
e timeline. A ACULTA aproveitará descoberta e rastreabilidade, mas manterá filas
administrativas por contexto e não adotará trust levels, pontuação opaca ou
ocultação automática até decisão específica.

Referências upstream consultadas em 2026-10-19:

- [Guia Discourse: mover posts para tópico novo/existente](https://meta.discourse.org/t/moving-posts-to-a-new-or-existing-topic/136733)
- [Guia Discourse: gerenciamento de conteúdo, divisão, categoria e moderação](https://meta.discourse.org/t/discourse-moderation-guide-part-3-managing-content/406266)
- [Implementação Discourse: controller de tópicos](https://github.com/discourse/discourse/blob/main/app/controllers/topics_controller.rb)
- [Implementação Discourse: modelo de tópico e `move_posts`](https://github.com/discourse/discourse/blob/main/app/models/topic.rb)

## Operações distintas

### A. Reorganizar categoria/subcategoria

Alterar pai e peso de termo Taxonomy reorganiza a árvore. É uma operação sobre
Taxonomy, não um movimento de conteúdo. Views, breadcrumbs e filtros devem
refletir a árvore nova; validar ciclos, pai permitido e quantidade de tópicos
afetada antes de confirmar. Somente termos de `aculta_forum_categories` entram
no fluxo.

### B. Mover tópico para outra categoria

Atualizar a referência de categoria do mesmo Node por Entity API, mantendo NID,
UUID, revisão, autor, estado, alias, Comments, anexos, flags e acompanhamento.
Permitir somente entre termos do vocabulário público do Growroom e dentro do
mesmo purpose/domínio. Revalidar acesso de origem e destino na submissão; nome
de papel não concede autorização. Operação em lote precisa mostrar seleção,
origem/destino e número de tópicos, com confirmação e resultado individual.

Não criar Node-sombra por padrão, não substituir alias automaticamente e não
transformar mudança de categoria em alteração de conteúdo que exponha tópico
privado. Se links históricos exigirem redirecionamento, decidir canonical,
permalinks e política de URL antes da implementação.

### C. Mover comentário/resposta

Primeira versão planejada: mover um Comment e sua subárvore de respostas para
outro tópico `aculta_forum_topic`, somente dentro do Fórum público e do mesmo
purpose. Não transferir para entidades Revista, LMS ou Wiki, nem entre tópicos
com políticas distintas de acesso/moderação. O destino precisa ter o campo de
discussão habilitado e aberto, e acesso de leitura/escrita/moderação compatível.

Preservar CID/UUID, autor, conteúdo, timestamps, estado publicado/rejeitado/
pendente, idioma, referência de autoria histórica quando aplicável, anexos e
file usage, flags e permalink quando tecnicamente possível. A operação nunca
aprova, rejeita, reabre ou publica um comentário como efeito colateral. Se
estados de origem e destino não forem compatíveis, negar e explicar sem alterar
dados.

Selecionar comentário pai exige mover todos os descendentes como uma unidade
atômica; se a API/armazenamento não puder garantir isso, bloquear movimento do
pai com respostas. Não permitir órfãos. A interface deve resumir comentário,
destino e quantidade de respostas afetadas e oferecer confirmação explícita.

O fluxo deve atualizar corretamente thread/parent/permalink, estatísticas dos
dois Nodes, cache tags, History/estado de leitura, Search API, flags,
notificações e filas. Falha em qualquer etapa deve preservar a árvore original;
notificações só saem após sucesso e revalidação de acesso. Aviso neutro de
movimentação pode ser avaliado para origem e destino, mas não deve ser um
Comment de usuário fabricado nem alterar o histórico autoral.

### D. Dividir ou mesclar tópicos

Split cria um Node tópico novo e move a seleção/subárvore para ele. Merge move a
conversa de origem para tópico existente e decide explicitamente o destino do
tópico de origem. São ações compostas, mais arriscadas que mover um Comment, e
ficam depois da operação C. Antes de implementar, decidir preservação de URLs,
mensagem de contexto/aviso, ordem cronológica, Flags/History, comportamento de
tópico vazio e recuperação/undo. Não apagar o Node de origem como efeito
implícito.

## Limites permanentes de contexto e imutabilidade

- **Fórum público:** só operações entre categorias/tópicos públicos compatíveis.
- **Revista:** comentários permanecem na publicação; sem movimentação para
  tópicos públicos. Um fluxo editorial futuro exigiria contrato próprio.
- **LMS:** sem ferramentas globais de movimento. Qualquer operação futura exige
  adapter, acesso atual ao mesmo curso/turma/atividade e autorização dos
  Educadores; nenhuma conversa sai do contexto privado.
- **Wiki:** discussão permanece vinculada ao mesmo verbete; não mover para o
  Growroom nem confundir com revisão editorial.
- **Growroom histórico importado:** imutável para todos os papéis, inclusive
  administrador; não mover tópico, comentário ou categoria do acervo.

## APIs, auditabilidade e portas de implementação

Usar Entity API, Form API, Access API, Comment/Node/Taxonomy e as APIs instaladas
de moderação. Não gravar tabelas diretamente, não criar storage paralelo e não
usar CTools como mecanismo de formulário ou autorização. CTools pode compor
blocos Views configuráveis por colocação; filtros de contexto e access ficam
fixos na View.

Antes do código, inspecionar as versões instaladas de Drupal Core e contrib e
confirmar APIs de re-hospedagem do Comment, integridade de `pid`/`thread`,
revisões, file/entity usage, transações, cache e integração Search API. O Core
não oferece no contrato da interface Comment um workflow de alto nível para
mover comentários; não assumir que mudar `entity_id`/`field_name` é suficiente.
Se a consistência de todos os efeitos derivados não puder ser provada, a
operação deve falhar fechada.

Auditar autor, origem, destino, IDs afetados, horário e resultado usando
infraestrutura de log/auditoria existente; nunca registrar corpo de comentário,
dados pessoais extras ou conteúdo privado em logs. A tela de confirmação e o
resultado devem indicar sucesso/falha sem revelar destino inacessível.

## Testes exigidos antes de ativar

- Permissão e Entity Access nas duas pontas; submissão com ID adulterado;
  CSRF, acesso direto e revalidação no momento da ação.
- Bloqueio de mudança entre purpose/domínios, Fórum/Revista/LMS/Wiki e acesso
  privado; bloqueio do acervo histórico para qualquer conta.
- Tópico aberto/fechado, comentários publicados/pendentes/rejeitados, autor
  legado, pai com múltiplas respostas, threads e destinos incompatíveis.
- Preservação de CID/UUID, autoria, timestamps, revisões, arquivos/usage,
  flags, links/âncoras, estatísticas, cache e indexação.
- Atomicidade e recuperação após falha entre persistência e atualização de
  efeitos; idempotência/double submit; nenhuma notificação antes do commit.
- Modo lote: prévia do conjunto afetado, seleção parcial, limites de volume e
  relatório por item sem sucesso parcial silencioso.
- Functional/browser: teclado, confirmação legível, retorno contextual,
  mensagens acessíveis e ausência de vazamento de título/conteúdo do destino.

As ferramentas entram em dev.9 (categorias e mover tópicos) e dev.12 (mover
comentários; split/merge somente após decisão de produto e prova técnica). Até
testes e documentação de operação/rollback estarem completos, os controles não
devem aparecer no Runtime.
