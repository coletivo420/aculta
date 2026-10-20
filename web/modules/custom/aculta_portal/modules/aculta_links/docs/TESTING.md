# Testes e critérios de migração

## Fundação 0.1.0

- Validar sintaxe do manifesto YAML.
- Executar `php scripts/validate-project-status-index.php`.
- Executar `git diff --check`.
- Manter módulo desabilitado; sem DB, Config Sync import ou operação no Runtime.

## Próxima fase funcional

Antes de mover a landing, testar configuração e URL, render/cache, formulário,
fallback sem JavaScript e autorização administrativa. Comparar HTTP e apresentação
no purpose `links`; conferir que `/` e o caminho interno `/redes-sociais` não
perdem conteúdo. Criar mecanismo de reversão antes de qualquer migração de dados.

Para entidades: Unit/Kernel/Functional em MariaDB dedicado conforme
`docs/operations/BANCO-DE-TESTE.md`, cobrindo ownership, revisões, unicidade,
URLs inválidas, isolamento Domain, access, cache e publicação. Runtime é DEFERRED
até validação autorizada no servidor de testes. Nenhum resultado de Runtime é
reivindicado nesta fase.
