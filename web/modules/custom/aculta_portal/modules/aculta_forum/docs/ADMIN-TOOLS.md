# Administração do Fórum geral

`/painel-administrativo/forum` é a aba **Início**, intitulada **Painel
Growroom420**, e a entrada canônica do Growroom420. Mostra apenas o resumo do
Fórum público. A navegação para Tópicos, Comentários e
Configurações ocorre pelas abas administrativas do Drupal e pelo menu lateral;
a Visão geral não repete esses links em seu conteúdo.

## Limites dos contextos (documentação; não exibir no painel)

- **Growroom420:** tópicos públicos independentes, organizados por categorias;
  comentários e respostas permanecem no tópico.
- **Revista:** comentários ficam vinculados à publicação e não criam tópicos
  públicos automaticamente.
- **LMS:** discussões de cursos, turmas e atividades permanecem dentro do grupo;
  Educadores são responsáveis pela moderação.
- **Wiki420:** a discussão fica vinculada ao verbete e não aparece como tópico
  público do Growroom420.

## Funções filhas

- `/painel-administrativo/forum/conteudo`: display de página da View
  `aculta_forum_admin_topics`, com ações de conteúdo próprias do Drupal/Views.
  O filtro de tipo e o Domain ativo do Growroom ficam fixos; a View preserva
  `node_access` e a permissão `access content overview`.
- `/painel-administrativo/forum/comentarios`: display geral da View
  `aculta_forum_admin_comments`, limitado ao Comment compartilhado hospedado em
  tópicos `aculta_forum_topic` do Domain ativo e protegido por `administer
  comments`.
- `/painel-administrativo/forum/comentarios/publicados`:
  `/painel-administrativo/forum/comentarios/aguardando-moderacao` e
  `/painel-administrativo/forum/comentarios/rejeitados`: abas de estado dentro
  da área de Comentários. Cada display mantém filtros fixos de estado e marcador
  de rejeição; não mistura Revista, LMS ou Wiki.
- `/painel-administrativo/forum/configuracoes`: `ForumSettingsForm`, protegida
  pela permissão `administer aculta forum settings`. A configuração de mover
  comentários continua desabilitada por padrão e não autoriza conteúdo histórico.

As abas principais do Drupal acompanham as páginas filhas. As subabas de estado
aparecem como segundo nível subordinado à aba Comentários, não na entrada do
Fórum.

## Caminhos antigos e funções compartilhadas

Os antigos caminhos sob `/painel-administrativo/conteudo/forum` continuam sendo
redirects locais para `/painel-administrativo/forum`, inclusive POST; não
executam a operação antiga. As operações de movimento com parâmetros continuam
fechadas por permissão e são redirecionadas quando acessadas diretamente. A
administração genérica de tipos de conteúdo e Taxonomy continua sob as rotas do
Drupal Core.

As políticas compartilhadas de participação continuam no `aculta_user`, sem
uma segunda cópia no Fórum. A moderação de LMS permanece dentro de LMS e pertence
aos Educadores; Revista e Wiki mantêm suas áreas contextuais. A fila de denúncias
continua sob o sistema dono das Flags; ela não é duplicada neste painel.

## Cache e acesso

As contagens do painel são limitadas ao bundle `aculta_forum_topic`, ao Domain
`forum` e às permissões de leitura. EntityQuery declara `accessCheck(TRUE)`;
tags de listas e contextos de usuário, permissão, idioma e Domain variam o
render. As Views administrativas mantêm seus próprios controles de permissão,
Domain, status e `node_access`; nenhum filtro de acesso é configurável por
CTools. CTools Views permanece aplicado apenas onde a configuração por
colocação de blocos é necessária.

## Rotas relacionadas

- `/painel-administrativo/forum`: aba Início com resumo do Fórum público.
- `/painel-administrativo/forum/conteudo`: lista administrativa de tópicos.
- `/painel-administrativo/forum/comentarios`: lista e entrada da moderação.
- `/painel-administrativo/forum/comentarios/publicados`: fila publicada.
- `/painel-administrativo/forum/comentarios/aguardando-moderacao`: fila pendente.
- `/painel-administrativo/forum/comentarios/rejeitados`: fila rejeitada.
- `/painel-administrativo/forum/configuracoes`: configurações específicas.
- `/painel-administrativo/forum/comentarios/{comment}/mover`: formulário
  canônico para mover comentário e respostas descendentes, acessível somente
  com a permissão `move aculta forum comments`; a opção continua desabilitada
  por padrão. O caminho antigo singular `/painel-administrativo/forum/comentario/{comment}/mover`
  redireciona ao painel. Mover tópico permanece indisponível por caminho direto.
- `/painel-administrativo/conteudo/forum` e filhos: redirects antigos para a
  entrada canônica do Fórum.
- `/painel-administrativo/aculta/participacao`: políticas
  compartilhadas do `aculta_user`.
