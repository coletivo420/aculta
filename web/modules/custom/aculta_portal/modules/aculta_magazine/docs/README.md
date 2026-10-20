# aculta_magazine (Revista)

> **Transição em andamento:** publicações recebem o campo Comment compartilhado
> `field_aculta_discussion` em Config Sync e no Runtime de teste. A sincronização automática de tópicos foi
> removida; serviços de leitura/conversão ainda sustentam vínculos históricos. A remoção
> final do Drupal Forum e de seus consumidores exige a sequência segura descrita em
> [`MIGRATION-REMOVAL.md`](../../aculta_forum/docs/MIGRATION-REMOVAL.md).

Submódulo do Portal ACULTA (`aculta_portal`). Centraliza tudo que é da Revista: publicações, chamada, resumo,
SEO, caixa de autores e coautoria. Depende de `aculta_user` (perfil, privacidade e redes), de `node_co_authors`
(coautores, contrib), de `metatag` (SEO) e de `pathauto` (URL por sessão).

## Regras (valem para todas as publicações da Revista)

| Regra | Como | Onde |
| --- | --- | --- |
| Publicações da Revista | lista `RevistaPublications::BUNDLES` (hoje `article` e `blog`) | `src/Revista/RevistaPublications.php` |
| Chamada | campo `field_subtitle` (rótulo "Chamada" no blog) | exibição do tipo, acima do corpo |
| Resumo | campo `field_summary` (rótulo "Resumo" no blog); descrição e Open Graph via `[node:editorial-summary]` | metatag `node__<tipo>` |
| SEO | metatag do tipo (`node__article`, `node__blog`) e campo `field_meta_tags` | config do metatag |
| URL por sessão | padrão Pathauto `/[node:session]/[node:title]` (`aculta_article`, inclui o blog) | config do pathauto e token do aculta_portal |
| Listagens por sessão | view `revista_sessoes` (filtro de tipo com todos os tipos da Revista) | config da view |
| Caixa de autores | `node_view`: autor (`node.uid`) e coautores (`co_authors`), foto, nome, bio, ícones das redes | `src/Hook/ArticleAuthorsHooks.php` |
| Coautores | `co_authors` (Node Co-Authors): coautores recebem os direitos de conteúdo do autor | módulo contrib |

## Discussão atual da publicação

`article` e `blog` recebem `field_aculta_discussion`, do tipo Comment `aculta_discussion_post`, diretamente no Node.
O full display usa o formatter Core `comment_default`, e o `aculta_user` constrói o cartão `author_comment`; autoria vinculada ao acervo usa `author_growroom_historico` sem conta ativa. A política
configurável de primeiro comentário do `aculta_user` aplica-se aqui como nos demais contextos públicos; LMS fica
expressamente fora dessa moderação global. A troca article ↔ blog copia o item do campo e re-hospeda as entidades
Comment antes de remover a publicação antiga, preservando autoria, estado e encadeamento.

A configuração está em `config/sync` e `config/install`; as duas instâncias e displays full foram aplicados no
Runtime de teste em importação parcial. Para Nodes de publicação anteriores à instalação do campo,
`PublicationDiscussionHooks` aplica em memória o default
definido na configuração do campo (fallback aberto), sem gravar o Node. Status editorial explícito fechado ou
oculto e estatísticas de comentários carregadas pelo Core permanecem intactos. A visibilidade efetiva e a submissão
de comentários via HTTP/browser continuam pendentes de homologação.

Smoke em memória no Runtime de teste confirmou o field/display e renderizou o
formulário Comment para um participante de teste com `post comments`. Nenhum
formulário foi enviado, e a contagem de Comments permaneceu zero.

## Blog é sinônimo de Editorial

Decisão do responsável (2026-10-14): na Revista, **blog** e **Editorial** são a mesma coisa. Toda publicação do tipo
`blog` é conteúdo editorial da Revista, e o conteúdo editorial é publicado como `blog`. "Editorial" é o nome da
seção que o público vê (sessão em `field_editorial_category`, rota `/editorial/...`); "blog" é o tipo de conteúdo
que a guarda. Não criar um segundo tipo para editorial, nem uma segunda seção para blog.

- Ao importar conteúdo editorial, usar o tipo `blog` e a sessão Editorial, salvo decisão expressa em contrário.
- Notícias seguem o tipo `article` com a sessão Notícias; Notícia não é sinônimo de editorial.
- O modelo de publicação (`aculta420:revista-publication`) vale para os dois tipos.

## Trocar o tipo de uma publicação (Notícia ↔ blog)

O Drupal não converte o tipo de um nó no lugar. O serviço `aculta_magazine.publication_type_converter` faz a troca
(é um serviço PHP: pode ser chamado de qualquer código; não há comando Drush nem tela registrados).

- `plan($post, $target)`: não grava. Mostra o tópico vinculado, os campos que serão copiados e os que se perdem
  (campos que só existem no tipo de origem).
- `convert($post, $target)`: grava. Ordem: (1) cria a publicação no tipo de destino com os campos copiados, o mesmo
  autor, a mesma data de criação e o mesmo estado; (2) preserva o vínculo histórico `field_discussao` quando existe
  e re-hospeda na publicação nova os Comments diretos de `field_aculta_discussion`; (3) remove o alias da antiga e a
  nova recebe o mesmo endereço pelo Pathauto; (4) a antiga perde o vínculo e o alias e é excluída. Tudo em transação.
- A antiga é sempre excluída. A referência histórica sai da antiga antes da exclusão; Comments diretos ficam na
  publicação nova e não são criados nem movidos para Nodes do Forum contrib.

### O que permanece na troca

- **Tópico do Forum antigo**: a entidade referenciada não é alterada; o vínculo `field_discussao` pode permanecer
  temporariamente como referência histórica. Nenhum tópico novo é sincronizado.
- **Comments diretos**: ficam associados à publicação convertida (mesmos CIDs, autoria, estado e hierarquia); o
  formatter Core de `field_aculta_discussion` os exibe na publicação.
- **Endereço**: o mesmo, pelo padrão da sessão. O endereço antigo responde 404 (sem redirecionamento).
- **Autoria**: o autor (`uid`), os coautores (`co_authors`) e o autor editorial (`field_editorial_author`) são copiados.
  A caixa de autoria e o autor do tópico seguem o autor copiado. Verificado no runtime em 2026-10-14: publicações
  PodPlant420 #01 e Cuidar não é crime, de `coletivo420` (uid 15), continuam de `coletivo420`; o Bloco Sativa segue
  do uid 1. Os tópicos têm o mesmo dono de suas publicações.
- **Autor dos comentários**: cada Comment mantém o próprio autor (`uid`). A conversão re-hospeda os Comments diretos
  preservando CID, autor, estado, parent/thread e conteúdo. O teste legado em tópico no Runtime de 2026-10-14 não
  homologa a discussão direta atual.
- **Campos**: copiados quando existem no destino. Blog e Notícia têm os mesmos campos da Revista, então nada se perde.

### Verificação

- Homologação pendente: criar Comment diretamente na publicação, converter article ↔ blog, comprovar CID, estado,
  hierarquia, exibição no novo host e moderação; depois remover apenas o fixture de teste.

## Privacidade

A caixa de autores usa somente `PublicProfilePolicy` (aculta_user): link para o perfil só com perfil público,
foto e bio só quando exibidas, ícones só das redes preenchidas e exibidas. E-mail não entra na caixa.

## Como adicionar um tipo da Revista (vídeo, galeria, outro)

1. Criar o tipo de conteúdo com os campos `field_subtitle` (rótulo "Chamada"), `field_summary` (rótulo
   "Resumo"), `field_editorial_category`, `field_media_image` e `field_meta_tags`.
2. Adicionar o machine name em `RevistaPublications::BUNDLES`.
3. Criar o metatag `node__<tipo>` copiando o de `node__article` (o resumo entra por `[node:editorial-summary]`).
4. Incluir o tipo no padrão Pathauto `aculta_article` (critério de bundle) e no filtro de tipo da view `revista_sessoes`.
5. Exibir chamada e resumo na exibição `node.<tipo>.default` (view display).
6. Testar: página na Revista com chamada, descrição de SEO, caixa de autores e coautoria.

## Gates e verificação

- `php scripts/validate-aculta-portal-drupal11.php`, `validate-module-theme-boundary.php`,
  `validate-public-slugs.php`, `validate-environment-addresses.php`.
- Navegador: publicação de teste (blog) com chamada, resumo, corpo e coautora; caixa de autores; descrição de SEO.

## Pendências

- Vídeo e galeria: tipos ainda não criados; seguem o passo a passo acima.
- Inspeção visual humana.

## Função x tema (separação)

| Parte | Dono | Onde |
| --- | --- | --- |
| Tipos, campos, SEO, URL por sessão, listagens e caixa de autores (dados e marcação com tokens) | `aculta_magazine` (função) | `src/`, `config/sync` (displays, metatag, pathauto, view) |
| Aparência da postagem (largura de leitura, imagem, chamada, resumo, vídeo 16:9, caixa de autores) | ACULTA420 (tema) | `css/components/revista-post.css`, `portal-account.css` |

O submódulo não tem CSS nem classes de design: todo elemento sai com `data-portal-ui` e o tema traduz.
Os displays (`core.entity_view_display.node.*.full`) definem quais campos aparecem e em que ordem; a aparência é do tema.
Referência de leitura usada: largura de linha entre 60 e 75 caracteres (Piccalilli, "Limit line lengths to increase readability").

## Cabeçalho da postagem e compartilhamento (função)

- `src/Presentation/PostPresentation.php` (serviço, chamado pelo hook `node_view` de `ArticleAuthorsHooks`):
  faixa com autor, data e hora (fuso do site) e os botões de compartilhamento (WhatsApp, Telegram, Facebook,
  Reddit e e-mail), com links montados a partir da URL canônica da publicação.
- Só um hook `node_view` por módulo: novas funções de exibição entram como serviço chamado por esse hook.
- Aparência (círculos, cores por rede, título e faixa) é do ACULTA420 (`revista-post.css`), com tokens semânticos.
- Data: `field_published_at` é guardado em UTC; ao preencher manualmente, use o horário UTC (ex.: 19:32 de Brasília = 22:32 UTC).

## Integração antiga com Drupal Forum (em remoção)

Registro histórico: a versão anterior criava um tópico Drupal Forum por publicação. Essa decisão foi substituída: cada publicação mantém a discussão dentro da própria entidade via Comment compartilhado; novos tópicos públicos automáticos estão proibidos.

- Pastas históricas: pasta-mãe "Revista" e pastas por categoria foram usadas somente pelos tópicos antigos.
  `ForumSectionResolver` não participa da criação ou atualização de publicações.
- Vínculo histórico: `field_discussao` permanece temporariamente para leitura de tópicos antigos e conversão de tipo.
  Nenhum hook de ciclo de vida de publicação cria, atualiza ou despublica tópicos.
- Abertura histórica: `ForumOpeningPost` lê resumo e imagem da publicação vinculada. Nenhum texto ou arquivo é
  copiado; mudanças editoriais não alteram o Node tópico legado.
- Estado de migração: `field_aculta_discussion` está instalado em `article` e `blog` no Runtime de teste. O hook
  `ForumTopicHooks` foi removido; o Runtime tinha zero Comments `comment_forum`. `LegacyPublicationTopicLookup` só lê os
  vínculos históricos para apresentação/conversão e não pode criar nem alterar tópicos. A limpeza de conteúdo legado e remoção do módulo contrib
  continuam pendentes e protegidas pelo plano de recuperação.
- URL do tópico: `/topico/<título>` (padrão `aculta_forum_topic`).
- Moderação atual: comentários diretos seguem a política configurável do primeiro comentário público, fora do LMS.
  A regra específica do LMS permanece sob Educadores com acesso contextual.
- Novo tipo de publicação da Revista: entra em `RevistaPublications::BUNDLES` e recebe configuração de Comment direto.
- Categoria nova: não cria pasta no vocabulário antigo do Forum.
- Não há criação manual de tópico para publicação. Tópicos de outros tipos (verbete, atividade) seguem o plano E3.
