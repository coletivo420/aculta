# ACULTA Activity — roadmap

O submódulo `aculta_activity` estabelece ownership ACULTA para Nodes do bundle
`activity`. Seu ciclo 0.1.x será concluído junto do Kernel `aculta_portal`, sem
release independente enquanto o módulo for apenas o adapter e sua fronteira.

## 0.1 — escopo atual

- Provider `aculta_index.provider` em `aculta_activity`.
- Projeção mínima de identidade, idioma, título, domínio, datas e publicação.
- Revalidação Entity Access pelo purpose canônico `main`.
- Exclusão de corpo, URL de evento, dados de pessoas e relações sem contrato.

## Antes de concluir com aculta_portal

- Confirmar no Kernel/DI que o provider é descoberto e que provider IDs não colidem.
- Cobrir acesso por usuário, grants e purpose `main`; falhar fechado se o purpose não existir.
- Auditar hooks e APIs legadas de `activity` hoje no Portal e decidir eventual transferência, sem alterar consumidores alheios sem necessidade.
- Integrar o provider ao rebuild, eventos incrementais e consulta do Content Index.
- Atualizar matriz de ownership, changelog, status e versão do Portal; executar gates localizados.

## Posterior

Workflow editorial, campos, experiência de edição, migração de dados e relações
com outros módulos exigem auditoria e decisão próprias. Não fazem parte do
adapter mínimo.
