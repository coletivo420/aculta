# ACULTA Activity

**Versão:** `0.1.0-dev.12` — hooks, validação do formulário e relação editorial de mídia cobertos por Kernel.

Módulo mínimo que estabelece `aculta_activity` como proprietário do bundle
Node `activity`, seus hooks de formulário/Metatag, validação condicional e
adapter no ACULTA Content Index. A projeção mantém título, identidade, idioma,
domínio, publicação e datas e registra somente a aresta `editorial_image` para
Media explicitamente referenciada em `field_media_image`; corpo, URLs, dados do
evento, arquivos e metadados privados da mídia ficam fora da projeção.

A checagem de acesso usa o purpose canônico `main`, mantendo Entity Access do
Drupal para o usuário atual. O módulo depende do Kernel `aculta_portal` e de
`schema_metatag`, mas não do submódulo opcional `aculta_index`; sua tag provider
fica inerte quando o Index não está ativo. O Index não exige que Activity esteja
habilitado. O Portal mantém apenas comportamento transversal de canonicalização
e metadados.

Roadmap: [`ACULTA-ACTIVITY-ROADMAP.md`](../../../../../../docs/modules/ACULTA-ACTIVITY-ROADMAP.md).

Em `0.1.0-dev.12`, o Kernel passou 6 testes/123 assertions no MariaDB
`aculta_simpletest`, com 121 depreciações Core/contrib. A validação considera os
valores submetidos, não dados antigos da entidade. Maps Unit + provider Index de
Events passaram 13 testes/186 assertions. O dispatcher real cobre agrupamento,
regras condicionais e metadados de localização, inclusive preservação de
override editorial. O contrato do Index 0.1 não exige eventos
adicionais para grants externos: Activity usa Entity Access padrão sob o
purpose `main`, coberto no Kernel com publicação, rascunho e Domain ausente.
Revisão visual/Runtime ainda depende de servir o candidato; o ciclo 0.1.x será
concluído junto do Kernel `aculta_portal`, sem RC independente.
