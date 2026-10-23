# ACULTA Wiki — Wiki420 como submódulo

Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- `0.1.0-dev.9`: provider opcional do Content Index implementado para
  metadados allowlisted e relação outgoing `related_entry`; categorias e
  imagem seguem adiadas. O módulo permanece desabilitado.
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
  publicação, datas e apenas `field_wiki_related` como `related_entry`; não
  projeta corpo, categorias ou mídia. Relações de categoria/imagem ficam como
  dívida até a finalização da Wiki. O provider só é descoberto quando `aculta_wiki` e
  `aculta_index` estiverem habilitados; não cria dependência obrigatória entre
  eles nem habilita o módulo.
- `WikiContentIndexProviderTest` cobre metadados allowlisted, revisão padrão,
  deduplicação de `related_entry` e declaração do purpose canônico `wiki`.
- `ContentIndexWikiProviderKernelTest` cobre backfill e CRUD com a classe real
  do provider (1 teste / 17 assertions); usa tag de fixture e não habilita o
  módulo Wiki nem valida Domain/grants reais. A declaração/tag owner e a
  consulta autorizada são cobertas separadamente pelo teste seguinte.
- `ContentIndexWikiAccessKernelTest` consulta a relação `related_entry` com o
  provider compilado do YAML owner e a policy Portal real via fixture. Origem e
  alvo são revalidados no Domain `wiki`, alvo não publicado é filtrado e o
  contexto `main` é restaurado (2 testes / 32 assertions, SQLite e MariaDB).
  Boot/DI completo da Wiki e homologação Runtime seguem pendentes.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
