# Funcionalidades

## Disponível antes do submódulo

- Página institucional de links sociais no purpose `links`.
- Botão gerado para a home do purpose `main`.
- Canais oficiais configuráveis (Instagram, YouTube e Facebook) com validação
  HTTPS/host.
- Botões adicionais com URL HTTPS ou caminho interno seguro.
- Projetos renderizados pela View existente.
- Página visual responsiva experimental pelo pattern ACULTA420.

Esses recursos são implementados pelo Portal e pelo tema hoje, não por este
módulo recém-criado. Sua migração para `aculta_links` deverá manter configuração,
conteúdo e URL.

## Fundação 0.2.0 em desenvolvimento

- Entidades `aculta_link`, `aculta_link_page` e `aculta_link_item`, revisionáveis;
  a página é criada em rascunho e os itens ordenam referência tipada ao destino.
- Handler de acesso e permissões explícitas, com propriedade Drupal User e
  teste Unit/Kernel de storage, referências, revisões e isolamento entre contas.
- Dashboard e formulários Core para micropáginas/destinos, com listagem por
  proprietário, revisão e cache de usuário. Rotas administrativas pertencem ao
  MAIN e são condicionais enquanto o módulo estiver desabilitado.
- Ainda não há editor de itens, preview, temas nem rota pública. O submódulo
  continua desabilitado.

## Ainda não disponível

Micropáginas por conta, páginas múltiplas por pessoa, editor, ordenação, preview,
temas selecionáveis, links curtos, QR, analytics, agendamento e componentes
referenciados. LinkStack será analisado como referência funcional; essas funções
não estão implementadas nesta versão.

## Em implementação — 0.2.0-dev.2

- Painel administrativo em `main`, com listas separadas e paginadas para micropáginas e destinos reutilizáveis.
- Formulários de entidade Drupal para CRUD e revisões, protegidos pelos handlers de acesso e permissões do módulo.
- Ainda não existe dashboard público do proprietário nem apresentação pública das novas entidades.
