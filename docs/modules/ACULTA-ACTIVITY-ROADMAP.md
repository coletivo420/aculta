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

- **Kernel/DI entregue em 0.1.0-dev.5:** o teste ativa `aculta_activity`, descobre a tag no registry e cobre backfill, update e delete do Node `activity` pelo worker real. MariaDB: 2 testes / 29 assertions. A política de domínio/grants continua fora deste teste.
- **Integração opcional em 0.1.0-dev.6:** Activity depende do Kernel `aculta_portal`, dono dos contratos de provider, e não requer `aculta_index`. A tag fica inerte quando o consumidor está desabilitado. O manifesto/grafo foi revisado; um boot Kernel com Activity habilitado e Index ausente ainda não foi executado.
- A política de acesso continua delegada ao Entity Access com purpose `main`; o teste Unit verifica a declaração do purpose. Cobertura com domínio real, usuário/grants e purpose ausente permanece no gate de integração/homologação do Portal.
- Hooks de Activity transferidos do Portal em 0.1.0-dev.4 após auditoria de ownership; não foi encontrada duplicação com Magazine, que agora usa apenas `revista`.
- Backfill, update e delete já são cobertos no Kernel quando Activity e Index
  estão ativos. Consulta autorizada no purpose `main` com Domains/grants reais,
  integração de formulários/Metatag e homologação conjunta continuam pendentes;
  Runtime permanece sem habilitação.
- Manifesto, changelog, status, versão e roadmap atualizados em
  `0.1.0-dev.6`; manifesto/grafo e status index validados. A matriz de
  ownership e a homologação de acesso/formulários continuam no gate conjunto.

## Posterior

Workflow editorial, campos, experiência de edição, migração de dados e relações
com outros módulos exigem auditoria e decisão próprias. Não fazem parte do
adapter mínimo.
