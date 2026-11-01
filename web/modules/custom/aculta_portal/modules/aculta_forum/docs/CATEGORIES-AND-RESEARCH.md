# Categorias públicas e referências de organização

Data: 2026-10-19. Escopo: organização do Growroom420 público para homologação.

## Decisão

O fórum usa termos hierárquicos de Taxonomy no vocabulário
`aculta_forum_categories`, ligados ao Node nativo `aculta_forum_topic`. “Fórum”
e “subfórum” são níveis da taxonomia, não bundles/entidades separados. O módulo
contribuído `forum` e seu vocabulário legado `forums` não são usados pelo motor
novo.

A árvore inicial tem quatro áreas amplas e duas subcategorias em cada uma. A
descrição de cada termo define escopo; assuntos transversais podem futuramente
usar tags, sem multiplicar subfóruns. A hierarquia é uma base para navegação e
testes, e não afirma que cobre toda a estrutura histórica do Growroom.

## Pesquisa consultada

- [Discourse: Create a category](https://meta.discourse.org/t/create-a-category-in-discourse/197224): categorias organizam assuntos, descrições esclarecem o uso e subcategorias herdam o contexto de acesso da categoria superior.
- [Discourse: Organize an online community](https://blog.discourse.org/2022/02/organize-an-online-community/): recomenda estrutura legível e evitar divisão excessiva que dificulte descoberta.
- [Drupal Core: Taxonomy Term storage](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21TermStorage.php/function/TermStorage%3A%3Acreate/11.x): termos são entidades nativas e recebem relação pai/raiz conforme a API de Taxonomy.
- [Drupal Core: Taxonomy Term entity](https://api.drupal.org/api/drupal/core%21modules%21taxonomy%21src%21Entity%21Term.php/class/Term/11.x): contrato de entidade do termo no Core.
- [Pathauto: configuring patterns](https://www.drupal.org/docs/8/modules/pathauto/configuring-pathauto): padrões se aplicam por tipo de entidade e critérios, incluindo bundles.

Os links do Discourse são referências de produto e organização; os termos e
aliases continuam sendo entidades/configuração Drupal. A versão instalada do
Pathauto e o campo base `path` de Node foram inspecionados no Runtime Drupal
11.4.10 antes de corrigir o pattern. A configuração `enabled_entity_types`
permanece `user`: Node já declara o campo `path`, portanto não se habilita a
extensão de campo Pathauto para todos os Nodes.

## Árvore planejada para fixtures

| Categoria | Subcategoria | Escopo |
| --- | --- | --- |
| Cultivo | Ambiente interno | Cultivo em espaços internos e controle ambiental. |
| Cultivo | Ambiente externo | Cultivo ao ar livre e sazonalidade. |
| Genéticas e sementes | Variedades e linhagens | Identificação e características de variedades. |
| Genéticas e sementes | Sementes e conservação | Seleção, armazenamento e preservação de sementes. |
| Cultura e comunidade | Cultura e história | História e expressões culturais da comunidade. |
| Cultura e comunidade | Convivência e apresentações | Apresentações e convivência entre participantes. |
| Direitos e políticas de drogas | Política de drogas | Debate público e políticas de drogas. |
| Direitos e políticas de drogas | Direitos humanos | Direitos, redução de danos e impactos sociais. |

## Alias de tópicos

Tópicos novos usam Pathauto no padrão português `/topico/{titulo}`. O pattern
`aculta_forum_topic` seleciona exclusivamente o bundle `aculta_forum_topic`.
Não deve selecionar o bundle legado `forum`. A configuração Sync e a cópia de
instalação pertencente ao `aculta_forum` têm a mesma definição.
