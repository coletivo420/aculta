# Changelog — ACULTA Activity

## 0.1.0-dev.11 — hooks de formulário e Metatag integrados (2026-10-29)

- Kernel invoca os hooks pelo `module_handler` real: formulário de edição de
  Activity, callback condicional e localização Schema.org.
- O callback é executado com `NodeForm` e `EntityFormDisplay` reais e verifica
  URL online obrigatória, local presencial obrigatório e término posterior ao
  início. O hook Metatag gera VirtualLocation, remove placeholders de imagem
  vazios e preserva override editorial.
- Unit + Kernel: 13 testes/143 assertions PASS no MariaDB `aculta_simpletest`,
  com 121 depreciações de Core/contrib. PHPCS Drupal/DrupalPractice e lint
  passaram para os arquivos do módulo.
- O contrato do Index 0.1 dispensa eventos adicionais para grants externos;
  Activity usa Entity Access padrão sob `main`. Revisão visual/Runtime aguarda
  o candidato servido. O ciclo do adapter será concluído junto do Kernel Portal,
  sem release RC independente; nenhum dado Runtime foi alterado.

## 0.1.0-dev.10 — fixture Kernel com Portal real (2026-10-29)

- Kernel carrega a cadeia completa declarada do Portal e remove o override de
  `aculta_portal.domain_purpose`; o teste confirma o serviço real e que a
  checagem de acesso restaura o Domain `courses`, inclusive quando o Domain
  canônico `main` está ausente.
- Unit + Kernel: 10 testes/89 assertions PASS em MariaDB `aculta_simpletest`,
  com 113 depreciações de Core/contrib. PHPCS Drupal/DrupalPractice e PHP lint
  do arquivo alterado passaram.
- Grants específicos do Portal, formulário/Metatag e homologação Runtime ainda
  não foram cobertos; nenhum acesso ou dado Runtime foi alterado.

## 0.1.0-dev.9 — boot sem Content Index e PHPCS (2026-10-29)

- Kernel separado confirma que o serviço tagged de Activity inicializa sem `aculta_index` e preserva o ID `activity`.
- PHPCS Drupal/DrupalPractice: 0 erros e 0 avisos; Unit: 6 testes/19 assertions; Kernel: 4 testes/53 assertions, com 5 depreciações Core/Domain; PHP lint: 4/4.
- A fixture Kernel com a cadeia real do Portal, grants de Entity Access, formulário/Metatag e homologação Runtime continuam pendentes.

## 0.1.0-dev.8 — validar acesso Index no purpose canônico

- Kernel adiciona Domains persistidos `main` e `courses`, usa o negotiator
  real e passa pelo `ContentIndexAccessFilter` com o provider owner.
- Consulta ativa em `courses` muda para `main`, restaura o Domain original,
  revalida publicação atual e falha fechado se o Domain owner estiver ausente.
- MariaDB: 3 testes / 49 assertions PASS, com 5 depreciações Core/contrib.
- A fixture usa Entity Access padrão de Node; grants específicos do Portal,
  boot sem `aculta_index`, Runtime e formulários continuam pendentes.

## 0.1.0-dev.7 — proteger a integração opcional do Index

- Unit verifica que Activity depende dos contratos do Kernel Portal, não do
  submódulo `aculta_index`, e que seu provider continua registrado pela tag
  `aculta_index.provider`.
- Unit Activity: 6 testes / 19 assertions PASS (teste novo: 1/5). Isso protege
  o manifesto e o contrato de descoberta; não substitui um boot Kernel com
  Index desabilitado.

## 0.1.0-dev.6 — manter Content Index como integração opcional

- A dependência direta de `aculta_index` foi substituída pela dependência do
  Kernel `aculta_portal`, dono dos contratos compartilhados do provider. O
  adapter continua registrado pela tag `aculta_index.provider`; sem o submódulo
  Index, Activity pode ser habilitado sem carregar ou exigir o catálogo.
- O Kernel existente valida descoberta da tag, rebuild, update e delete quando
  Index está ativo (2/29 MariaDB). Não foi executado um boot com Activity sem
  Index nesta microfase; a alteração foi verificada no manifesto e no grafo de
  dependências declarado.

## 0.1.0-dev.5 — cobrir ciclo CRUD do provider Index

- Kernel ativa `aculta_activity` e verifica backfill, atualização e exclusão
  através do worker real do Index: 2 testes / 29 assertions PASS em MariaDB.
- A fixture instala `node_access`, necessário ao delete do Node no Core.

## 0.1.0-dev.4 — hooks e validação sob ownership do Activity

- Movidos para `aculta_activity` os hooks de formulário e Metatag exclusivos do
  bundle `activity`, além da validação condicional de modalidade, endereço e
  horário. Portal permanece responsável por canonical e metadados transversais.
- O módulo declara `schema_metatag` como dependência direta dos hooks que
  serializam localizações estruturadas.


## 0.1.0-dev.3 — versão declarada do provider Index

- Provider declara `projection_version=1` no fingerprint do rebuild; aumentar
  a versão quando mudar os campos, relações ou semântica indexáveis.

## 0.1.0-dev.2 — provider verificado no Kernel do Content Index

- Kernel verifica descoberta da tag `aculta_index.provider`, projeção de um Node `activity` e fila incremental no save (1 teste / 18 assertions, SQLite temporário).
- Unit do provider permanece cobrindo bundles/revisões e purpose `main`. Runtime continua sem ativação; ownership editorial legado não foi migrado.

## 0.1.0-dev.1 — fundação e adapter Content Index

- Criado o limite de ownership `aculta_activity` para o bundle Node `activity`.
- Adapter tagged projeta somente metadados mínimos e usa o purpose canônico `main` para revalidar Entity Access.
- Módulo inicial depende do `aculta_index`; o Portal e o Index não dependem do Activity.
- Apresentação, workflow, autoria, migração e comportamento editorial seguem fora deste primeiro recorte.
