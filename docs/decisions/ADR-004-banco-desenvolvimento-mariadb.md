# ADR-004: Banco de desenvolvimento (Runtime) em MariaDB

Status: Accepted (revisada em 2026-10-10; substitui a decisão original de banco em arquivo local)

## Contexto

A decisão original usava banco em arquivo local no Homelab para simplificar o desenvolvimento.
Produção usa MariaDB, e o Runtime do Homelab precisava refletir o mesmo motor
para reduzir divergências entre ambientes.

## Decisão

- O Runtime do Homelab usa **MariaDB** (banco `aculta_runtime`, usuário próprio
  com privilégio apenas nesse banco). Credenciais ficam em `secrets/`, fora do Git.
- O banco em arquivo anterior não é mais o Runtime. A cópia de segurança da
  migração foi descartada após a verificação do responsável.
- Produção continua em MariaDB, com credenciais próprias fora do repositório.
- Código customizado continua usando as APIs de Entity/Database do Drupal;
  nenhum código deve depender do motor específico.
- Estados (`estados/`) são backups privados dos servidores, não são versionados
  e não substituem backup de produção.

## Consequências

- Portabilidade e procedimentos de migração entre motores pertencem ao projeto
  **DBTNG-2**, não ao `aculta_portal`.
- A carga do banco anterior para MariaDB exigiu correção de `AUTO_INCREMENT` com `uid 0`
  (anônimo): o carregamento usa `NO_AUTO_VALUE_ON_ZERO`.
