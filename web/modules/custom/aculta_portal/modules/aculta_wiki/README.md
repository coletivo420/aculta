# ACULTA Wiki — Wiki420 como submódulo

Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- `0.1.0-dev.18`: o backend e Form API registram “pedir ajustes” e “recusar”
  como novas revisões privadas, com nota editorial, checagem de conflito e lock
  contra decisões simultâneas. Kernel MariaDB completo: 8 testes / 301
  assertions, sem warnings (284 depreciações Drupal/contrib e 9 PHPUnit).
  Aceite/aplicação e integração Studio seguem pendentes até decidir como o
  conteúdo entra no Node.
- `0.1.0-dev.17`: propostas exigem e-mail confirmado por `aculta_portal`.
  Verbetes publicados exibem link contextual apenas a colaboradores confirmados
  sem permissão de edição direta; o render varia cache por conta, permissões e
  domínio. Kernel completo: 8 testes / 290 assertions, sem warnings (284
  depreciações Drupal/contrib e 9 PHPUnit); verifica a regra e a recusa de conta
  não confirmada.
- `0.1.0-dev.16`: pessoas com `submit wiki proposals` podem abrir o Form API em
  `/wiki/verbetes/{node}/contribuir`. A rota exige acesso de visualização ao
  verbete e o serviço revalida estado e VID-base ao salvar. A proposta continua
  privada e não é aplicada automaticamente. Kernel verifica rota e callback.
- `0.1.0-dev.15`: serviço `aculta_wiki.editorial_queue` consulta propostas
  pendentes e revisões atuais em `review`. A fila de Nodes revalida acesso de
  leitura, revisão e atualização e exige transição válida sob o purpose `wiki`;
  proposta usa permissão editorial e paginação por cursor. Kernel completo da
  classe: 8 testes / 274 assertions; módulo permanece desabilitado no Runtime.
- `0.1.0-dev.14`: existe persistência privada revisionável para propostas de
  terceiros, com revisão-base e serviço que valida acesso, publicação e
  purpose `wiki`. Nenhuma expiração
  automática foi criada; retenção para propostas encerradas aguarda decisão.
- `0.1.0-dev.13`: rotas públicas e View da listagem são descobertas pelo
  bootstrap Kernel real; o acesso à busca é caracterizado com grants diferentes
  entre contas da mesma função. Busca vazia/negativa varia por
  `user.node_grants:view`. O ciclo de imagem e os contratos do Index continuam
  conforme dev.12.
- `0.1.0-dev.12`: upload da imagem de destaque usa armazenamento privado e
  transação compartilhada com o salvamento do verbete; a mídia só é publicada
  quando a revisão submetida do verbete está publicada. O incremento dev.11
  mantém cache da busca por grants e o CTA usa acesso real de criação. O
  provider opcional do Content Index permanece na versão de projeção 3.
- Kernel MariaDB também cobre a remoção da imagem na revisão atual: a Media e o
  arquivo são preservados enquanto uma revisão antiga do verbete os referencia.
- `WikiLeadImageLifecycleKernelTest` inicializa `aculta_wiki` e verifica
  `/wiki`, `/wiki/busca`, `/wiki/busca/previa`, a View `/wiki/verbetes` e o
  requisito `access content`. A busca real filtra grants de contas diferentes
  da mesma função e mantém contexto de cache nos resultados vazios/negativos.
- O módulo permanece desabilitado no Runtime de teste; a configuração
  exportada e o Runtime não são equivalentes.
- O módulo **não está habilitado** e não altera o Runtime.
- As rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa` estão definidas neste
  submódulo com os nomes `aculta_portal.wiki_*`; `/wiki/verbetes` permanece
  contrato da listagem. A disponibilidade no site depende da ativação efetiva
  do módulo no Runtime.

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

### Handoff para o ACULTA Content Index

O contrato Wiki está em `WikiContentIndexProvider` v3: `node:wiki_entry`,
revisão padrão, purpose `wiki`, metadados allowlisted e relações
`related_entry` → `node:wiki_entry`, `wiki_category` →
`taxonomy_term:wiki_category` e `wiki_image` → `media:image`. O provider dos
termos pertence à Wiki; o de Media pertence a `aculta_image`. Destinos seguem
sujeitos a Entity Access na consulta do Index. Alteração semântica futura exige
incremento da versão e rebuild de geração; esta entrega não dispara rebuild.
Termos/categorias e imagens não devem bloquear infraestrutura genérica ou
outros providers do Index. A projeção não inclui corpo, revisão pendente,
autoria, descrição, URL ou arquivo. Homologação Runtime continua separada.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
