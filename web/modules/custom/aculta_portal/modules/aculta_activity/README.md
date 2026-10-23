# ACULTA Activity

Módulo mínimo que estabelece `aculta_activity` como proprietário do bundle
Node `activity`, seus hooks de formulário/Metatag, validação condicional e
adapter no ACULTA Content Index. O Index recebe somente título, identidade,
idioma, domínio, publicação e datas; corpo, URLs, dados do evento e referências
a pessoas/arquivos ficam fora da projeção.

A checagem de acesso usa o purpose canônico `main`, mantendo Entity Access do
Drupal para o usuário atual. O módulo depende do `aculta_index` e
`schema_metatag`; o Index não exige que Activity esteja habilitado. O Portal
mantém apenas comportamento transversal de canonicalização e metadados.

Roadmap: [`ACULTA-ACTIVITY-ROADMAP.md`](../../../../../../docs/modules/ACULTA-ACTIVITY-ROADMAP.md).
