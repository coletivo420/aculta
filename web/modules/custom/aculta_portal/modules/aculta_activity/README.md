# ACULTA Activity

Módulo mínimo que estabelece `aculta_activity` como proprietário do bundle
Node `activity` e registra seu adapter no ACULTA Content Index. O Index recebe
somente título, identidade, idioma, domínio, publicação e datas; corpo, URLs,
dados do evento e referências a pessoas/arquivos ficam fora da projeção.

A checagem de acesso usa o purpose canônico `main`, mantendo Entity Access do
Drupal para o usuário atual. O módulo depende do `aculta_index`; o Index não
exige que Activity esteja habilitado. Este módulo ainda não assume a edição,
apresentação, workflow nem a migração do conteúdo de atividades.

Roadmap: [`ACULTA-ACTIVITY-ROADMAP.md`](../../../../../../docs/modules/ACULTA-ACTIVITY-ROADMAP.md).
