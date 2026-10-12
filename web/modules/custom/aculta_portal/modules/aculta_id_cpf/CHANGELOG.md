# Changelog

## 0.1.0-dev.1 — núcleo de documentos — 2026-10-12

Classificação: MINOR, nova capacidade opcional em desenvolvimento.
Base: `368ec263189d670d459e385b068d7bb188af95b3`.

- Núcleo PHP independente de banco: enum de tipos, normalizador, validadores e
  formatter de CPF/CNPJ numérico/alfanumérico.
- Form API com atributos de plugin e callbacks DI resolvidos como serviço.
- Máscaras locais progressivas, behaviors/once e testes de seleção/composição.
- Sem alteração dos formulários da Conta, login, tema, banco ou config sync.
- STATIC/DOMAIN: PASS — PHP 8.3.6, PHPUnit 11.5.57 (34 testes, 63 asserções),
  runner (248 verificações), Node (8 testes), lint (13 arquivos PHP), gate do
  Portal (583 verificações).
- **RUNTIME STATUS: DEFERRED** — Kernel, Drupal completo, Homelab, MariaDB e
  PHP 8.5 ainda não executados. A versão é inicial de desenvolvimento; não é
  release homologada. Nenhuma tag criada.

Próxima entrega: homologar elementos e definir persistência privada/Field API
sobre os contratos de User/Profile/Key existentes, seguida de unicidade.
