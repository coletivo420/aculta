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

## Posterior

Workflow editorial, campos, experiência de edição, migração de dados e relações
com outros módulos exigem auditoria e decisão próprias. Não fazem parte do
adapter mínimo.
