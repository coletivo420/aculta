# Administração do Fórum geral

`/painel-administrativo/forum` é o caminho administrativo padrão e canônico do Growroom420; a própria rota renderiza sua página, sem copiar conteúdo, incorporar telas ou redirecionar para caminhos de outras ferramentas. Todos os caminhos administrativos específicos do Fórum — inclusive conteúdo, filas de comentários, denúncias, configurações e ações de movimentação — redirecionam localmente para este painel central. A direção do redirecionamento é sempre do caminho da ferramenta para `/painel-administrativo/forum`; o painel central não encaminha a pessoa para outra tela.

Os caminhos históricos das ferramentas permanecem reconhecidos como redirects para a entrada central, mas não expõem mais telas próprias nesta seção. A listagem genérica `/painel-administrativo/conteudo` continua sem conteúdo do Fórum. Rotas genéricas do Drupal (como administração de Taxonomy e tipos de conteúdo) e configuração compartilhada de participação permanecem sob seus próprios sistemas e não são absorvidas pela seção.

## Ownership e acesso

- O `aculta_forum` mantém a navegação da seção e configurações específicas do
  Growroom420. Node, Comment, Taxonomy, Views e seus handlers continuam donos do
  armazenamento e do CRUD.
- A permissão específica de cada caminho continua sendo verificada antes do
  redirect. O redirect não executa operações, não altera conteúdo e não amplia
  acesso; a rota genérica de destino também aplica sua própria autorização.
- Configurações compartilhadas de participação pertencem ao `aculta_user` e
  permanecem fora desta seção; não existe cópia nem atalho local dessas regras.
- O LMS não é administrado por esta seção. Educadores moderam discussões dentro
  do contexto de curso/turma; comentários da Wiki e da Revista permanecem em
  suas áreas próprias.

## Opções específicas

O antigo caminho `/painel-administrativo/forum/configuracoes` agora redireciona
para o painel central. A configuração tipada `aculta_forum.settings` continua
existindo; o caminho da ferramenta não é apresentado como tela ativa nesta
seção até uma decisão de produto definir sua nova localização.

Anteriormente, a opção controlava o movimento de um comentário público e sua subárvore entre tópicos:

- `comment_move_enabled` nasce e permanece `false` até uma pessoa autorizada
  habilitá-la conscientemente.
- Habilitar a opção não concede a permissão `move aculta forum comments`; a
  rota e o serviço continuam exigindo essa permissão e revalidando domínio,
  tópico de origem/destino e estado.
- Tópicos e Comments com `field_autor_legado` continuam inelegíveis, mesmo que a
  opção global esteja ativa.
- A opção não altera o mecanismo de moderação nem as discussões de outros
  contextos.

O formulário usa o `#config_target` nativo do Drupal 11.3+ para mapear o controle
à configuração tipada. Não adiciona entidade de configuração própria, tabela,
plugin CTools ou service locator. CTools não substitui formulários de
configuração; nenhum bloco Views desta tela precisa de controle por colocação.

## Rotas relacionadas

- `/painel-administrativo/forum`: página padrão e canônica do Growroom420.
- `/painel-administrativo/forum/conteudo`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/comentarios`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/comentarios/publicados`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/comentarios/rejeitados`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/comentarios/aguardando-moderacao`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/configuracoes`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/denuncias`: caminho legado; redirect local para a página canônica.
- `/painel-administrativo/forum/{node}/mover-categoria` e `/painel-administrativo/forum/comentario/{comment}/mover`: caminhos legados; redirect local para a página canônica.
- `/painel-administrativo/configuracoes/aculta/portal/participacao`: regras
  compartilhadas do `aculta_user`.

Os paths administrativos antigos sob `/painel-administrativo/conteudo/forum`
redirecionam por GET/HEAD/POST para `/painel-administrativo/forum`. Os caminhos
específicos do Fórum sob a seção canônica também redirecionam por GET/HEAD/POST; não
há telas ou ações de escrita ativas nesses caminhos. O painel não é alias das
páginas de conteúdo, configuração ou moderação.
