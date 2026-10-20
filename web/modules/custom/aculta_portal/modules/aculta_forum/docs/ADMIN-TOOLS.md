# Administração do Fórum geral

`/painel-administrativo/forum` é o caminho administrativo padrão e canônico do Growroom420; a própria rota renderiza sua página, sem copiar conteúdo, incorporar telas ou redirecionar para caminhos de outras ferramentas. Os caminhos administrativos antigos e dispersos que davam acesso ao Fórum, atualmente sob `/painel-administrativo/conteudo/forum`, redirecionam para este painel central. A direção do redirecionamento é sempre do caminho antigo para `/painel-administrativo/forum`.

A listagem dos tópicos e as filas de comentários têm rotas próprias na seção Fórum, ligadas pela navegação administrativa. A lista de tópicos oferece mover em lote por VBO, limitado a 50 itens com confirmação; a Action revalida acesso e imutabilidade histórica para cada Node. Essas telas não são copiadas nem incorporadas à rota central e não aparecem na listagem genérica `/painel-administrativo/conteudo`.

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

- `/painel-administrativo/forum`: página padrão e canônica do Growroom420.
- `/painel-administrativo/forum/conteudo`: listagem dos tópicos públicos; comentários e estados de moderação têm caminhos próprios na seção.
- `/painel-administrativo/forum/comentarios`: consulta agregada.
- `/painel-administrativo/forum/comentarios/publicados`.
- `/painel-administrativo/forum/comentarios/rejeitados`.
- `/painel-administrativo/forum/comentarios/aguardando-moderacao`.
- `/painel-administrativo/forum/configuracoes`: opções próprias.
- `/painel-administrativo/configuracoes/aculta/portal/participacao`: regras
  compartilhadas do `aculta_user`.

Os antigos paths sob `/painel-administrativo/conteudo/forum` redirecionam por
GET/HEAD para `/painel-administrativo/forum`. O painel não é alias das páginas
de conteúdo, configuração ou moderação.
