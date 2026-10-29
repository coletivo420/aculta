# ACULTA Activity

**Versão:** `0.1.0-dev.11` — hooks de formulário e Metatag validados no dispatcher do Drupal e no formulário real de nó.

Módulo mínimo que estabelece `aculta_activity` como proprietário do bundle
Node `activity`, seus hooks de formulário/Metatag, validação condicional e
adapter no ACULTA Content Index. O Index recebe somente título, identidade,
idioma, domínio, publicação e datas; corpo, URLs, dados do evento e referências
a pessoas/arquivos ficam fora da projeção.

A checagem de acesso usa o purpose canônico `main`, mantendo Entity Access do
Drupal para o usuário atual. O módulo depende do Kernel `aculta_portal` e de
`schema_metatag`, mas não do submódulo opcional `aculta_index`; sua tag provider
fica inerte quando o Index não está ativo. O Index não exige que Activity esteja
habilitado. O Portal mantém apenas comportamento transversal de canonicalização
e metadados.

Roadmap: [`ACULTA-ACTIVITY-ROADMAP.md`](../../../../../../docs/modules/ACULTA-ACTIVITY-ROADMAP.md).

Em `0.1.0-dev.11`, a suíte Unit + Kernel passou 13 testes/143 assertions no
MariaDB `aculta_simpletest`, com 121 depreciações de Core/contrib ao carregar a
cadeia completa. O dispatcher real cobre agrupamento do formulário, as três
regras condicionais de validação e os metadados de localização, inclusive
preservação de override editorial. O contrato do Index 0.1 não exige eventos
adicionais para grants externos: Activity usa Entity Access padrão sob o
purpose `main`, coberto no Kernel com publicação, rascunho e Domain ausente.
Revisão visual/Runtime ainda depende de servir o candidato; o ciclo 0.1.x será
concluído junto do Kernel `aculta_portal`, sem RC independente.
