# Permissões e ownership

- Drupal User é a identidade única. Não portar cadastro, login, recuperação,
  sessões, perfis ou administração de usuários LinkStack.
- Cada micropágina tem proprietário Drupal. Rotas, formulários, Views e ações
  verificam Entity Access e a permissão geral necessária.
- Decisões de acesso retornam resultados cacheáveis e incorporam dependências
  de entidade/usuário/permissão adequadas, conforme a API Drupal 11.
- Proprietários gerenciam apenas suas páginas; administração global é política
  separada e auditável.
- Publicar/despublicar é explícito. Revisões não implicam aprovação editorial.
- `manage own aculta links` permite criar e gerenciar páginas/destinos próprios;
  `administer aculta links` permite gestão global e é restrita. A administração
  só lista outros proprietários com permissão global.
- `aculta_user` fornece identidade, foto, bio e redes somente quando a pessoa
  autoriza e cada campo está público segundo a política de perfil. A página não
  herda informação privada automaticamente.
- Nenhum papel recebe essas permissões automaticamente nesta fase.

## Rotas de administração 0.2.0-dev.2

O painel exige `manage own aculta links` ou `administer aculta links`. Operações de entidade revalidam proprietário e permissão em cada leitura/escrita; as rotas administrativas geradas pelo Core continuam declaradas, porém inativas até habilitar o módulo. Criar entidades usa uma das duas permissões acima.
