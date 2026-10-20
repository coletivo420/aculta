# Modelo de entidades proposto

Ainda não implementado. Confirmar requisitos, referência Linky e APIs Drupal
antes de escolher storage. Entidades usarão Entity Access, cacheability,
propriedade Drupal e schemas/updates reversíveis.

| Conceito | Responsabilidade proposta | Relações |
| --- | --- | --- |
| Link reutilizável | Destino externo validado ou referência interna; rótulo e estado. | Autor Drupal; alvo interno quando disponível; revisão se necessária. |
| Micropágina | Página de conta com slug, identidade pública opcional, aparência e publicação. | `uid` proprietário; Domain/purpose resolvido em runtime. |
| Item de página | Composição ordenada que aponta a link ou tipo permitido de conteúdo. | Página + link/referência; peso, ativo e janela opcional. |
| Evento estatístico | Visita/clique mínimo, retenção curta e agregação. | IDs opacos de página/item e data; sem IP ou user-agent persistente por padrão. |

Uma conta poderá ter várias páginas. Colaboração não entra na primeira entrega.
Unicidade/reservas de slug devem seguir as normas de URL pública. O Core `link`
continua adequado para campos editoriais comuns e não será substituído
globalmente.
