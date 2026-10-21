# Modelo de entidades

Implementadas como entidades de conteúdo revisionáveis, as três responsabilidades
ficam separadas. `aculta_link` usa o campo Link do Core para o destino, a
propriedade padrão de User, publicação e metadados de revisão. `aculta_link_page`
guarda título, slug, descrição, proprietário, aparência governada por preset e
estado de publicação (rascunhos começam despublicados). `aculta_link_item` guarda página/destino referenciados,
rótulo, descrição, ordem e estado.

Há formulários Core e rotas administrativas declaradas para as três entidades, além de um dashboard com listas paginadas. O handler de itens exige que o próprio item, a página e o destino referenciado pertençam à conta que os gerencia. O slug tem gramática ASCII minúscula e unicidade validada pelo Core. A renderização pública já está declarada em `/pagina/{slug}`, condicionada ao purpose `links` e ao estado publicado; a camada visual reaproveita o SDC existente. O handler de página/destino
mantém acesso privado até a integração de publicação/domínio.

| Conceito | Responsabilidade proposta | Relações |
| --- | --- | --- |
| Link reutilizável | Destino do campo Link do Core, título e publicação; `aculta_link` já cobre o armazenamento revisionável e propriedade. | Autor Drupal; destino pode ser URI interna ou HTTPS externa; Entity Access privado. |
| Micropágina | `aculta_link_page`: título, slug, descrição, preset visual, publicação e opção revisionável `show_owner_identity` (desligada por padrão). | `uid` proprietário; identidade pública mínima via `aculta_user`, Domain/purpose resolvido em runtime. |
| Item de página | `aculta_link_item`: composição ordenada que aponta a um destino reutilizável. | Referências tipadas à página e ao link; peso e ativo. |
| Evento estatístico | Contagem agregada diária dos GETs aceitos por redirecionamento curto; nunca visitante único nem page view. | ID de destino e data UTC; fila contém só ID/timestamp, sem IP, UID de visitante, user-agent, referrer ou cookie. Agregado retido por 90 dias. |

Uma conta poderá ter várias páginas. Colaboração não entra na primeira entrega.
Unicidade/reservas de slug devem seguir as normas de URL pública. O Core `link`
continua adequado para campos editoriais comuns e não será substituído
globalmente.

O submódulo não depende de Dynamic Entity Reference: relações entre entidades
Drupal serão definidas por tipo conhecido ou por adapters/plugins explícitos.
Assim a referência a destinos não instala dependência adicional nem imita uma
entidade universal de URL.
