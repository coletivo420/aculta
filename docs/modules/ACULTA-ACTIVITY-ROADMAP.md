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

- **Kernel/DI entregue em 0.1.0-dev.2:** tag descoberta pelo registry do Index, projeção de Node `activity` validada e save agenda o trabalho incremental. Teste SQLite temporário: 1 teste / 18 assertions.
- A política de acesso continua delegada ao Entity Access com purpose `main`; o teste Unit verifica a declaração do purpose. Cobertura com domínio real, usuário/grants e purpose ausente permanece no gate de integração/homologação do Portal.
- Hooks de Activity transferidos do Portal em 0.1.0-dev.4 após auditoria de ownership; não foi encontrada duplicação com Magazine, que agora usa apenas `revista`.
- Confirmar inclusão no rebuild e consulta do Content Index na homologação conjunta. Runtime permanece sem habilitação.
- Atualizar matriz de ownership, changelog, status e versão do módulo; executar gates localizados.

## Posterior

Workflow editorial, campos, experiência de edição, migração de dados e relações
com outros módulos exigem auditoria e decisão próprias. Não fazem parte do
adapter mínimo.
