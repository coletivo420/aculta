# ACULTA Activity — roadmap

O submódulo `aculta_activity` estabelece ownership ACULTA para Nodes do bundle
`activity`. Seu ciclo 0.1.x será concluído junto do Kernel `aculta_portal`, sem
release independente enquanto o módulo for apenas o adapter e sua fronteira.

## 0.1 — escopo atual

- Provider `aculta_index.provider` em `aculta_activity`.
- Projeção mínima de identidade, idioma, título, domínio, datas e publicação.
- Revalidação Entity Access pelo purpose canônico `main`.
- Exclusão de corpo, URL de evento, dados de pessoas e relações sem contrato.
- Hooks do formulário/Metatag e validação condicional agora pertencem a
  `aculta_activity`; Portal mantém canonicalização e metadados transversais.

## Antes de concluir com aculta_portal

- **Contrato opcional em 0.1.0-dev.7:** a suíte Unit Activity passou 6/19; o
  teste focal confirma dependência do Kernel Portal e ausência de dependência
  direta de `aculta_index`, além da tag real do provider (1/5). Boot Kernel sem
  o Index ainda está pendente.
- **Kernel/DI entregue em 0.1.0-dev.5:** o teste ativa `aculta_activity`, descobre a tag no registry e cobre backfill, update e delete do Node `activity` pelo worker real. MariaDB: 2 testes / 29 assertions. A política de domínio/grants continua fora deste teste.
- **Integração opcional em 0.1.0-dev.6:** Activity depende do Kernel `aculta_portal`, dono dos contratos de provider, e não requer `aculta_index`. A tag fica inerte quando o consumidor está desabilitado. O manifesto/grafo foi revisado; um boot Kernel com Activity habilitado e Index ausente ainda não foi executado.
- A política de acesso continua delegada ao Entity Access com purpose `main`. Em
  0.1.0-dev.8 o Kernel passa pelo `ContentIndexAccessFilter`, inicia em
  `courses`, muda para o Domain `main` persistido, restaura o contexto, revalida
  publicação e falha fechado quando `main` está ausente (3/49 MariaDB, 5
  depreciações Core/contrib). A fixture usa Entity Access padrão de Node; grants
  específicos do Portal e boot sem Index continuam pendentes.
- Hooks de Activity transferidos do Portal em 0.1.0-dev.4 após auditoria de ownership; não foi encontrada duplicação com Magazine, que agora usa apenas `revista`.
- Backfill, update e delete já são cobertos no Kernel quando Activity e Index
  estão ativos. O Kernel Magazine também compila os providers Magazine e Activity
  a partir dos YAMLs owner e confirma que `related_activity` aponta para uma
  entrada `node:activity` no rebuild (MariaDB: 1 teste / 18 assertions). Consulta
  outgoing de Activity, grants específicos do Portal, integração de
  formulários/Metatag e homologação conjunta continuam pendentes; Runtime
  permanece sem habilitação.
- Manifesto, changelog, status, versão e roadmap atualizados em
  `0.1.0-dev.8`; manifesto/grafo e status index validados. A matriz de
  ownership e a homologação de acesso/formulários continuam no gate conjunto.

### Bloqueio de verificação — 2026-10-28

- Baseline revalidado: Unit 6 testes / 19 assertions; Kernel 3 testes / 49
  assertions, com 5 depreciações de Core/contrib.
- A tentativa de acrescentar cobertura de `EntityHooks` do Portal falhou no
  Kernel ao afirmar que `aculta_portal` estava habilitado. A fixture atual
  registra um `DomainPurposeManager` substituto e não carrega esse módulo; a
  falha ocorreu antes das novas asserções de acesso. Os testes Unit exploratórios
  de formulário e Metatag passaram (11/33), mas não foram mantidos após a falha
  do gate do módulo. Nenhum comportamento de produção foi alterado.
- O boot Kernel sem `aculta_index` não foi executado. Reabrir esta fase com um
  harness que carregue o Portal real, sem substituir sua regra de acesso. A
  homologação Runtime segue pendente; Activity permanece em Dev.

### Revalidação do harness — 2026-10-29

- Baseline reexecutado: Unit + Kernel 9 testes / 68 assertions PASS, com 5
  depreciações de Core/contrib. O teste de acesso usa a classe real
  `DomainPurposeManager`, mas a fixture substitui o serviço e não carrega o
  módulo `aculta_portal`.
- Tentativa de carregar `aculta_portal` real na fixture: 3 Kernel errors antes
  de executar asserções. O container não compilou porque
  `aculta_portal.unused_file_cleaner` depende de `file.usage`, serviço ausente
  nessa instalação reduzida. A alteração experimental do teste foi descartada;
  código de produção não mudou.
- Próximo passo permanece técnico: identificar o conjunto mínimo de módulos
  Core/contrib exigido pelo serviço Portal, sem substituir o serviço nem
  relaxar Entity Access; então cobrir boot sem Index e formulário/Metatag.
  Runtime e revisão visual continuam pendentes.

### Tentativa controlada de carregar o Portal real — 2026-10-29

- Baseline após retirar a alteração experimental: Unit 6/19 e Kernel 3/49 PASS;
  Kernel registra 5 deprecações Core/Domain.
- Tentativa de habilitar `file`, `aculta_image` e `aculta_portal` (com `key`)
  na fixture passou pela ausência anterior de `file.usage`, mas o container
  falhou na validação de `aculta_portal.registration_mail_policy`, que exige
  `aculta_mailer.mailer`. A suíte parou antes de qualquer asserção (3 errors,
  0 assertions). As linhas experimentais foram removidas.
- O boot completo do Portal exige mais módulos da sua cadeia funcional. Não
  substituímos serviços do Portal nem alteramos produção/testes para ocultar a
  dependência. Próxima ação: definir uma fixture compartilhada de integração
  Portal que carregue as dependências reais ou separar os serviços auxiliares
  que hoje impedem o boot reduzido; então retomar o teste sem Index e Entity
  Access. Activity permanece em Dev.

### Integração Kernel com a cadeia real — 2026-10-29 (dev.10)

- A fixture passou a habilitar a cadeia declarada do Portal e a instalar os
  schemas/configurações usados pelos hooks ativos (Domain Source, Entity Usage,
  comentários, arquivos, Profile e apelidos). O serviço
  `aculta_portal.domain_purpose` não é mais substituído pelo teste.
- O Kernel confirma que o módulo Portal está ativo, que o serviço é a classe
  `DomainPurposeManager`, que o filtro acessa Activity sob o purpose `main` e
  restaura o Domain original `courses`. Sem o Domain `main`, o acesso nega e o
  contexto continua em `courses`.
- Suíte Unit + Kernel no MariaDB `aculta_simpletest`: 10 testes/89 assertions
  PASS; 113 depreciações upstream da cadeia carregada. PHPCS e lint do arquivo
  alterado passaram.
- Isso fecha o harness de integração real. Ainda faltam grants específicos do
  Portal, integração de formulário/Metatag, revisão conjunta e Runtime; Activity
  permanece Dev.

### Hooks de formulário e Metatag na integração — 2026-10-29 (dev.11)

- Kernel invoca `form_node_form_alter` e `metatags_alter` pelo `module_handler`
  real; o formulário usa `NodeForm`/`EntityFormDisplay` do Core. Confirma os
  grupos editoriais, callback registrado e três validações: URL para modalidade
  online, local para presencial e data final posterior à inicial.
- O hook de Metatag remove imagens sem mídia, gera `VirtualLocation` a partir
  do link online e mantém `schema_event_location` editorial já definido.
- Unit + Kernel: 13 testes/143 assertions PASS; 121 depreciações upstream do
  conjunto de dependências do Portal. PHPCS Drupal/DrupalPractice e PHP lint
  8/8 PASS.
- Entity Access com grants contextuais específicos, revisão visual e
  homologação conjunta no Runtime autorizado continuam pendentes; Activity
  permanece Dev.

### Sondagem adicional da cadeia do Portal — 2026-10-29

- Para identificar se a ausência de `aculta_mailer.mailer` era o último bloqueio,
  a fixture experimental incluiu também `aculta_mailer`, `aculta_user`,
  `comment`, `image`, `link` e `path_alias`, além dos módulos anteriores.
- O container então parou em `aculta_portal.email_confirmation_requester`, que
  requer `aculta_mail_confirmer`; os três casos Kernel falharam antes das
  asserções. A inclusão de módulos foi descartada, mantendo o baseline 9/68
  PASS. Isso confirma que a solução não é habilitar um único serviço e requer
  um harness compartilhado da cadeia de integração Portal.

### Boot do adapter sem o consumidor Index — 2026-10-29

- `ActivityWithoutIndexKernelTest` inicia o container com `aculta_activity`,
  sem habilitar `aculta_index`; confirma que o serviço tagged
  `aculta_activity.content_index_provider` está disponível e mantém o ID
  `activity`.
- PHPCS Drupal/DrupalPractice: 0 erros e 0 avisos após corrigir o baseline
  pequeno do módulo. Unit: 6/19; Kernel: 4/53, com 5 depreciações upstream
  de Domain/Core; PHP lint: 4/4.
- Isso fecha somente o boot isolado do adapter sem o consumidor. Não substitui
  o boot de integração com Portal real, nem cobre grants Portal, formulários,
  Metatag ou Runtime conjunto. Activity permanece em Dev.

## Posterior

Workflow editorial, campos, experiência de edição, migração de dados e relações
com outros módulos exigem auditoria e decisão próprias. Não fazem parte do
adapter mínimo.
