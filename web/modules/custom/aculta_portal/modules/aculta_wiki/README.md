# ACULTA Wiki — Wiki420 como submódulo

Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- `0.1.0-dev.2`: provider opcional do Content Index implementado para metadados
  allowlisted de `wiki_entry`; o módulo permanece desabilitado.
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
  publicação e datas; não projeta corpo, referências, categorias ou mídia.
  Relações Wiki — inclusive `field_wiki_related` — ficam como dívida técnica
  até a finalização da Wiki. O provider só é descoberto quando `aculta_wiki` e
  `aculta_index` estiverem habilitados; não cria dependência obrigatória entre
  eles nem habilita o módulo.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
