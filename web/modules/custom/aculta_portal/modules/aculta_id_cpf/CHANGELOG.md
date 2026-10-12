# Changelog

## 0.1.0-dev.2 — serviços de domínio; campos adiados — 2026-10-12

- Removidos os elementos Form API `aculta_cpf`/`aculta_cnpj`, callback, máscara
  JavaScript e testes de Kernel/navegador associados. Os campos PF/PJ e a
  máscara serão implementados manualmente no `aculta_portal`.
- Mantidos normalizador, validadores de DV, enum e helpers de formatação.
- Atualizada a documentação para registrar o limite deliberado e a integração
  futura via DI.
- Rodada em PHP 8.4.26: runner 248 verificações, PHPUnit Unit 34 testes/63
  asserções, lint e gate Drupal 11+ 583 checks PASS; sem `composer update`.
  Homologação remota continua DEFERRED.

## 0.1.0-dev.1 — primeira implementação — 2026-10-12

- Implementação inicial dos serviços CPF/CNPJ, elementos Form API e máscara;
  testados em PHP 8.4.26/Core 11.4.8 e removidos nesta atualização por decisão
  de escopo do responsável.
- Domínio: runner 248 verificações e PHPUnit Unit 34 testes/63 asserções PASS.
- Homologação daquela implementação: Kernel em MariaDB isolado, 5 testes/8
  asserções PASS; Chromium local validou máscara, colagem, AJAX e seleção.
  Submissão sem JS não concluída porque CAPTCHA global exige desafio.
- Composer validate/audit, lint, cache rebuild e gates CAPTCHA/Conta passaram.
  Gate Portal commerce/security parou na asserção preexistente DT-O11
  `Fake runtime credentials were not persisted`. Servidor remoto DEFERRED.
- **RUNTIME STATUS: DEFERRED** — não é release homologada; sem tag.
