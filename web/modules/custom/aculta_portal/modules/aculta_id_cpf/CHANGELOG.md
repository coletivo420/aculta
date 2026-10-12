# Changelog

## 0.1.0-dev.1 — núcleo de documentos — 2026-10-12

Classificação: MINOR, nova capacidade opcional em desenvolvimento.
Base: `368ec263189d670d459e385b068d7bb188af95b3`.

- Núcleo PHP independente de banco: enum de tipos, normalizador, validadores e
  formatter de CPF/CNPJ numérico/alfanumérico.
- Form API com atributos de plugin e callbacks DI resolvidos como serviço.
- Máscaras locais progressivas, behaviors/once e testes de seleção/composição.
- Sem alteração dos formulários da Conta, login, tema, banco ou config sync.
- Evidência histórica: PHP 8.3.6 em container, PHPUnit 11.5.57 (34 testes, 63
  asserções), runner (248 verificações), Node 24.19.0 (8 testes), lint (13
  arquivos PHP) e gate do Portal (583 verificações).
- Atualização de homologação (2026-10-12): PHP CLI/FPM 8.4.26 local, Composer
  2.8.8, Core 11.4.8, MariaDB 11.8.6 e Node 22.23.3. Runner 248, PHPUnit Unit
  34/63, máscaras 8 e gate 583: PASS. Composer validate/audit: PASS; Drush
  habilitou o módulo no Runtime local e reconstruiu cache. Sem `composer update`.
- Correção de homologação: Kernel recebe `#[RunTestsInSeparateProcesses]`,
  exigido pelo Core 11.3+. Kernel PASS em MariaDB separado: 5 testes/8
  asserções. Formulário temporário PASS no Chromium local (máscara, clipboard,
  AJAX e seleção; sem JS mantém entrada crua). Submissão sem JS não concluída
  porque CAPTCHA global requer desafio; servidor remoto continua DEFERRED.
- Regressões de CAPTCHA/Conta: gates PASS (15/1233 checks). O gate Portal de
  commerce/security não passou pela asserção `Fake runtime credentials were not
  persisted` (DT-O11); pendência registrada sem ler ou alterar valores.
- **RUNTIME STATUS: DEFERRED** — não é release homologada. Nenhuma tag criada.

Próxima entrega: homologar elementos e definir persistência privada/Field API
sobre os contratos de User/Profile/Key existentes, seguida de unicidade.
