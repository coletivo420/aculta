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
- `aculta_user` fornece identidade, foto, bio e redes somente quando a pessoa
  autoriza e cada campo está público segundo a política de perfil. A página não
  herda informação privada automaticamente.
- Esta fase não declara permissões e não concede capacidades a papéis.
