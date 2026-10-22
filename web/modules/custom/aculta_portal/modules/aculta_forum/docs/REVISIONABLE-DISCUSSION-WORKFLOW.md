# Posts revisionáveis e workflow de moderação do Fórum

Estado: **direção aprovada; implementação em fases, sem deploy**. O responsável
decidiu em 2026-10-21 substituir os Comments do Core nas discussões públicas do
Fórum por uma entidade de post revisionável com workflow completo. Comments
anteriores do Fórum não serão mantidos como acervo legado: o conteúdo e os dados
que fazem parte da migração devem ser copiados e conferidos na nova entidade;
somente então os Comments correspondentes do Fórum serão removidos. Comments da
Revista, Wiki, LMS e outros hosts não pertencem a esta migração.

## Evidência e escolha arquitetural

O Drupal instalado é Core 11.4.10. `Drupal\comment\Entity\Comment` declara
campos translatáveis e estado publicado, mas sua definição não tem chave de
revisão nem tabelas de revisão; portanto, não pode receber diretamente o campo
`moderation_state` e os handlers de Content Moderation.

O Core `content_moderation` integra o handler e as rotas de moderação a entidades
de conteúdo não internas quando `isRevisionable()` é verdadeiro. O plugin
Content Moderation associa workflows a entity type/bundle. Isso permite uma
entidade de conteúdo própria do Fórum sem transformar cada resposta em um Node
genérico. O contrato será validado contra o Core instalado e com Kernel antes
de importar qualquer conteúdo.

Fontes primárias consultadas em 2026-10-21:

- [Drupal 11.x: `ContentModeration::entityTypeAlter()`](https://api.drupal.org/api/drupal/core%21modules%21content_moderation%21src%21EntityTypeInfo.php/function/EntityTypeInfo%3A%3AentityTypeAlter/11.x)
- [Drupal 11.x: plugin Content Moderation e associação entity type/bundle](https://api.drupal.org/api/drupal/core%21modules%21content_moderation%21src%21Plugin%21WorkflowType%21ContentModeration.php/class/ContentModeration/11.x)
- [Drupal 11.x: API de Content Moderation](https://api.drupal.org/api/drupal/core%21modules%21content_moderation%21content_moderation.api.php/11.x)
- Código instalado: `web/core/modules/comment/src/Entity/Comment.php`,
  `web/core/modules/content_moderation/src/EntityTypeInfo.php` e
  `web/core/modules/content_moderation/src/Plugin/WorkflowType/ContentModeration.php`.

## Contrato da entidade

Machine name: `aculta_forum_post` (bundle lógico único com o mesmo ID; ambos
abaixo do limite de 32 caracteres). A entidade é dona somente de posts e
respostas das discussões públicas `aculta_forum_topic`.

- Content entity revisionável, com revisão publicada/default independente da
  revisão de trabalho, autor de revisão, instante e mensagem de revisão.
- Campos revisionáveis: tópico hospedeiro, resposta pai, posição de thread,
  assunto, corpo/format, autor e instante de criação. Estado publicado e
  `moderation_state` seguem o workflow; não duplicar estado em campo paralelo.
- Identidade de post estável entre revisões. Respostas mantêm relação pai/filho
  por entity reference. A mudança de tópico preserva IDs e gera revisão nova;
  thread e índices são recalculados sob lock e transação.
- Entity Access revalida tópico público, Domain purpose `forum`, status da
  revisão e acesso do destinatário. Rascunho, revisão não publicada e denúncia
  nunca são expostos pela página pública ou por cache compartilhado.
- Apresentação ocorre dentro da página canônica do tópico; links de post apontam
  para o slug português do tópico com fragmento estável do post. Não criar
  slug público baseado em ID numérico.
- Anexos, Media Gallery, flags, Search API, progresso de leitura e avisos são
  associados ao novo post pelo ID da entidade. Consumidores atuais que dependem
  de Comment devem migrar antes de remover os Comments do Fórum.

`aculta_forum` permanece dono do escopo e do adapter. Content Moderation/Workflows
Core são donos de revisão, estado e transições; `aculta_user` continua dono de
identidade e da política compartilhada de primeira participação. A decisão do
responsável é aplicar essa política em todo o site, exceto no Fórum interno do
LMS. O novo post público deve consultar `ParticipationRules` de `aculta_user`
para escolher seu estado inicial, sem duplicar configuração; o adaptador LMS
permanece fora do workflow público. A política compartilhada foi desacoplada do
tipo Comment por `requiresFirstDiscussionModeration()` para permitir esse uso.

## Workflow proposto para decisão funcional

IDs técnicos sugeridos (rótulos traduzidos para português):

| Estado | Publicado | Revisão default | Uso |
| --- | --- | --- | --- |
| `draft` | Não | Não | Rascunho do autor/moderador |
| `review` | Não | Não | Aguardando avaliação |
| `published` | Sim | Sim | Visível na discussão pública |
| `rejected` | Não | Sim | Recusado, preservado para auditoria do autor/moderador |

Transições propostas: criar rascunho; enviar para revisão; retirar da fila;
aprovar/publicar; rejeitar; corrigir e reenviar; despublicar para revisão. O
workflow precisa definir permissões por transição para participante autenticado,
moderador e administrador. Nenhuma transição será inferida apenas pelo nome do
papel; a autorização precisa estar coberta por teste de acesso.

Edição do próprio post publicado é permitida dentro de uma janela configurável
por consumidor, padrão de 10 minutos desde a primeira publicação. Salvar edição
não reinicia o prazo. Valor `0` desativa edição pelo autor; permissões de
moderadores e administradores continuam governadas por seus handlers e
transições. Dentro do prazo, a revisão publicada pode ser atualizada sem voltar
à fila; após o prazo, só operações autorizadas de moderação permanecem. O Core
handler continua sendo a fonte de acesso: a janela apenas restringe, nunca
concede permissão de edição.

As opções pertencem aos painéis próprios: Fórum público/Growroom420, Fórum do
LMS, Wiki e Comments compartilhados. A política consome o timestamp imutável de
primeira publicação e tolera o período anterior à migração sem bloquear o acesso
base dos Comments existentes.

Os IDs/nomes finais e permissões ainda serão fechados na implementação e nos
testes de workflow; a configuração da política inicial continua sendo a de
`aculta_user`, sem criar uma opção duplicada no Fórum.

## Plano de entrega

1. **Contrato e prova Kernel:** entity type revisionável, esquema SQL de base e
   revisões, fields revisionáveis, access handler fail-closed, criação de duas
   revisões e leitura explícita da revisão default/latest. Provar integração
   automática do Core Content Moderation com o workflow de fixture antes de
   instalar config permanente.
2. **Workflow e UX:** estados/transições aprovados, roles/permissões por
   transição, Form API/ações de envio e revisão, histórico legível e View de
   fila. Visual mobile, teclado, leitor de tela e isolamento de cache. A dev.143
   inicia a escrita pública contextual via form mode Core; a dev.144 acrescenta
   respostas a posts publicados no mesmo tópico e a dev.145 identifica o pai na
   leitura e no composer sem quebrar a ordem cronológica. A dev.146 compara
   assunto/corpo de revisões adjacentes usando Diff contrib dentro da rota
   administrativa contextual; a dev.147 também permite escolher quaisquer duas
   revisões mostradas na página atual do histórico. A dev.151 passa a aceitar
   IDs do histórico em páginas diferentes e valida no servidor que pertencem ao
   mesmo post; a rota de comparação revalida o acesso. Hierarquia visual e
   homologação visual continuam pendentes. A dev.148 liga a
   edição pública dentro da janela do autor, salvando revisão nova sem expor
   campos de moderação ou contexto. A dev.149 conecta os diálogos existentes de
   anexo/galeria ao post e mantém a publicação vinculada ao estado público do
   post e do tópico; a retenção inclui todas as revisões. A dev.150 torna
   navegável o contexto do pai fora da página corrente, calculando sua página
   com a mesma ordenação e query de acesso da thread e preservando os pagers
   existentes.
3. **Migração não destrutiva em código:** mapear Comments apenas quando
   `comment_type=aculta_discussion_post`, host Node de bundle
   `aculta_forum_topic` e `field_name=field_aculta_discussion`. Migrar a árvore
   pai/filho, autoria, timestamps, conteúdo/format, tradução, estado publicado,
   anexos/embeds, uso dos arquivos, denúncias/flags, busca e avisos de seguidores.
   Validar contagens, UUIDs de mídia, árvore, estado e destinatários. O script
   deve suportar dry-run, lotes limitados, transação por árvore e idempotência.
   A dev.152 acrescenta `drush aculta-forum:audit-comment-migration`, um
   inventário somente leitura e sem IDs/PII na saída. Ele identifica o escopo
   elegível, formatos, idiomas/traduções, estados, autoria histórica,
   timestamps, embeds e referências pai ausentes/externas/cruzadas. Ainda não
   cria posts nem usa o mapa Migrate. A dev.154 também conta Flagging entities
   ligadas aos Comments elegíveis por configuração de flag, sem expor uid ou
   sessão. O contrib Flag impede transferir uma Flagging existente para outra
   entidade; a migração precisará recriar a associação no destino pela API e
   validar quantidade, dono/global e timestamp. A dev.155 inventaria os avisos
   `forum_resposta` do Messenger por tópico elegível e classifica o marcador
   `ultimo_comentario` para guiar a conversão pelo mapa source/destination.
4. **Corte e remoção autorizada:** somente depois dos gates de equivalência e
   backup recuperável, ativar a nova leitura/escrita e remover os Comments do
   Fórum via Entity API. Não excluir Comments de outros hosts nem remover o
   field type compartilhado enquanto Revista/Wiki/LMS o usarem.
5. **Integração completa e RC:** mover post/subárvore, publicação dos anexos,
   `aculta_image`, Gallery, flags/denúncias, Search API, avisos Messenger,
   permalinks, contadores e concorrência HTTP; atualizar mapa de rotas, roadmap,
   changelog, matriz de testes, manifests/status e visual X11. RC só após gates,
   revisão de código e validação visual. Deploy/Alpha ficam depois da autorização.

## Critérios de aceite

- Nova entidade aparece como revisionável no Core e como entidade moderável no
  workflow fixture; revisão publicável não vaza antes da transição permitida.
- Participante não publica por conta própria quando a política exige revisão;
  moderador não pode transicionar conteúdo fora do Fórum.
- Histórico mostra revisão atual/anterior, autor, instante e estado sem revelar
  rascunhos a visitante.
- Migração preserva árvore e conteúdo migrável, mas remove os Comment Entities
  do Fórum após validação; nenhuma entidade de Revista/Wiki/LMS é tocada.
- Anexos e avisos continuam acessíveis ao mesmo destinatário conforme as
  políticas atuais; referências privadas não ficam públicas por erro de migração.
- Testes Kernel em MariaDB, Functional/API de rotas e permissões, testes de
  integração dos módulos e inspeção visual X11 passam antes de RC.
