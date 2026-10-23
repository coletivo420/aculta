# ACULTA Wiki — Wiki420 como submódulo

Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- `0.1.0-dev.10`: provider opcional do Content Index projeta metadados
  allowlisted e relações outgoing `related_entry`, `wiki_category` e
  `wiki_image`. O módulo permanece desabilitado.
- O módulo **não está habilitado** e não altera o Runtime.
- As rotas públicas continuam definidas em `aculta_portal.routing.yml` até a
  Fase 2. Os caminhos `/wiki`, `/wiki/verbetes`, `/wiki/busca` e
  `/wiki/busca/previa` são contrato e não mudam.

## Limites

- Domínio e purpose (`wiki420` → `/wiki`) continuam no Portal. Este módulo não
  cria purpose, host nem alias.
- O submódulo não depende de `aculta_statistics`. As métricas agregadas de
  publicação são produzidas pelo `WikiProvider` da estatística, que observa
  nodes do bundle `wiki_entry` sem importar classes da Wiki.
- Conteúdo sincronizado ou importado não gera evento de publicação.
- Configuração continua em `config/sync`, com UUIDs e IDs preservados. A
  propriedade de cada item é decisão pendente do responsável.
- `WikiContentIndexProvider` projeta título, UUID, bundle, idioma, domínio,
  publicação, datas e relações allowlisted de `field_wiki_related` (`related_entry`),
  `field_wiki_categories` (`wiki_category`) e `field_wiki_image` (`wiki_image`).
  `WikiCategoryContentIndexProvider` projeta apenas metadados mínimos para
  `taxonomy_term:wiki_category`; imagens usam o provider `media:image` já
  registrado por `aculta_image`. Nenhum provider copia corpo, descrição de
  termo, URL, arquivo ou binário. Os serviços só são descobertos pelas tags
  quando Index e owners estão ativos; não há dependência obrigatória entre
  `aculta_wiki` e `aculta_index`.
- Os testes Unit cobrem metadados allowlisted, revisão padrão, deduplicação das
  relações nomeadas, purpose `wiki` e a projeção mínima da categoria.
- `ContentIndexWikiProviderKernelTest` cobre backfill e CRUD com a classe real
  do provider (1 teste / 17 assertions); usa tag de fixture e não habilita o
  módulo Wiki nem valida Domain/grants reais. A declaração/tag owner e a
  consulta autorizada são cobertas separadamente pelo teste seguinte.
- `ContentIndexWikiAccessKernelTest` consulta `related_entry`, `wiki_category`
  e `wiki_image` com providers dos YAMLs owners Wiki e Image, dados reais Node,
  Term e Media e a policy Portal via fixture. Entity Access filtra categoria e
  imagem não publicadas; a origem/alvo Wiki são revalidados no purpose `wiki` e
  o Domain `main` é restaurado (MariaDB: 3 testes / 84 assertions, 5
  depreciações). O boot combinado dos owners também compila os services reais;
  Runtime e ativação completa permanecem pendentes.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
