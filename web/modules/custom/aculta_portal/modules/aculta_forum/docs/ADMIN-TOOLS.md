# Administração do Fórum geral

O Growroom420 tem seção própria em `/painel-administrativo/forum`. A listagem
de tópicos é a página inicial da seção; comentários e ações administrativas do
Fórum ficam sob a mesma raiz. A listagem genérica de Conteúdo não inclui Nodes
`aculta_forum_topic` nem filas de Comments do Fórum.

## Ownership e acesso

- O `aculta_forum` mantém a navegação da seção e configurações específicas do
  Growroom420. Node, Comment, Taxonomy, Views e seus handlers continuam donos do
  armazenamento e do CRUD.
- A permissão restrita `administer aculta forum settings` protege a página de
  configurações. Acesso às categorias, ao bundle, às denúncias, à moderação e
  às ferramentas de movimento continua sendo avaliado pelo sistema dono.
- Configurações compartilhadas de participação pertencem ao `aculta_user`; a
  seção oferece um link para a origem canônica. Não existe cópia local dessas
  regras.
- O LMS não é administrado por esta seção. Educadores moderam discussões dentro
  do contexto de curso/turma; comentários da Wiki e da Revista permanecem em
  suas áreas próprias.

## Opções específicas

`/painel-administrativo/forum/configuracoes` edita `aculta_forum.settings` pelo
`ConfigFormBase` do Core. A opção disponível controla o movimento de um
comentário público e sua subárvore entre tópicos:

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

- `/painel-administrativo/forum`: tópicos públicos e entrada da seção.
- `/painel-administrativo/forum/comentarios`: consulta agregada.
- `/painel-administrativo/forum/comentarios/publicados`.
- `/painel-administrativo/forum/comentarios/rejeitados`.
- `/painel-administrativo/forum/comentarios/aguardando-moderacao`.
- `/painel-administrativo/forum/configuracoes`: opções próprias.
- `/painel-administrativo/configuracoes/aculta/portal/participacao`: regras
  compartilhadas do `aculta_user`.

Os antigos paths em `/painel-administrativo/conteudo/forum` foram removidos e
respondem 404, sem redirecionamento, conforme a política de slugs do projeto.
