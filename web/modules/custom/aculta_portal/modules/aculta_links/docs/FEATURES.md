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
  MAIN e estão registradas no Runtime de teste; acesso segue as permissões e o Entity Access.
- Editor de itens com ordenação e publicação individual, além da rota pública
  `/pagina/{slug}` no purpose `links`; o módulo foi habilitado no Runtime de
  teste em 2026-10-20. Preview no editor e migração da landing institucional
  seguem pendentes.

## Ainda não disponível

Micropáginas por conta, páginas múltiplas por pessoa, editor, ordenação, preview,
temas selecionáveis, links curtos, QR, analytics, agendamento e componentes
referenciados. LinkStack será analisado como referência funcional; essas funções
não estão implementadas nesta versão.

## Em implementação — 0.2.0-dev.2

- Painel administrativo em `main`, com listas separadas e paginadas para micropáginas, destinos reutilizáveis e itens de página.
- Formulários de entidade Drupal para CRUD e revisões, protegidos pelos handlers de acesso e permissões do módulo.
- Ainda não existe dashboard público do proprietário, preview ao vivo ou apresentação de identidade herdada de `aculta_user`.

## Editor de composição — 0.3.0-dev.1

A rota administrativa `/painel-administrativo/links/pagina/{aculta_link_page}/editar-itens` lista itens da página, permite alterar ordem por tabela arrastável com campo numérico como fallback e ativar/desativar individualmente. Cada item salvo cria revisão. A edição do texto e destino permanece no formulário individual da entidade.

## Micropágina pública — 0.4.0-dev.1

- `/pagina/{slug}` renderiza somente micropágina publicada no purpose `links`.
- Itens publicados aparecem em ordem por peso e ID; item, página e destino precisam estar publicados e acessíveis.
- Destinos permitem URIs Drupal internas (`internal:`/`entity:`) ou HTTPS sem credenciais e sem portas não padrão.
- Contrato de render é neutro no módulo; ACULTA420 reutiliza o SDC `social-links-page`.
