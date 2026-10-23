## Descontinuado — 2026-10-23

- Decisão do responsável: `aculta_id_cpf` permanece como módulo oficial de CPF/CNPJ. Este submódulo será removido após verificação de Runtime. Nenhuma alteração funcional nesta versão.
- O código de `src/`, `tests/` e `config/install/` é idêntico ao de `aculta_id_cpf`, após normalizar o nome do módulo.

## 0.3.0-dev.1 — identidade e escopo Associado ID — 2026-10-13

- Introduz machine name `aculta_associado_id` como identidade canônica para
  orquestração futura dos dados de associados usados em atas, relatórios e
  obrigações legais. O escopo de dados adicionais e geração de documentos ainda
  não foi implementado.
- Mantém implementação atual de CPF/CNPJ em cópia independente, serviços novos e
  namespace novo. Preserva o Key ID `aculta_id_cpf_fingerprint`, o separador
  HMAC `aculta_id_cpf:v1`, a chave de cifra e seus contratos de ambiente.
- Migração ainda não validada em cópia isolada. Não habilitar no Runtime nem
  desinstalar o legado até comprovar preservação dos campos e dados.
- **RUNTIME STATUS: DEFERRED**.

# Changelog

## 0.2.0-dev.2 — unicidade por fingerprint e migração do índice — 2026-10-12

Classificação: MINOR, capacidade opcional em desenvolvimento.

- `AccountDocumentStore` recusa o mesmo documento em outra conta, por consulta ao
  `field_documento_indice`. Mensagem genérica: não revela se o documento existe.
- Consulta do perfil passa a incluir perfis despublicados (antes, `loadByUser`
  filtrava por status e podia criar um segundo perfil participante).
- Campo `field_documento_indice` (config/install) e `aculta_associado_id_update_10001`
  para instalações existentes: indexa documentos gravados; para se houver conflito.
- Chave `aculta_id_cpf_fingerprint` (`ACULTA_ID_CPF_FINGERPRINT_KEY`) no contrato de
  segredos, na allowlist e em `docs/operations/SECRETS.md`.
- `DocumentFingerprint::isAvailable()`; testes unitários de máscara, detecção e cifra.
- Limite: unicidade não é transacional (ver README). Kernel do store pendente.
- Runtime: **RUNTIME STATUS: DEFERRED**.

## 0.2.0-dev.1 — persistência cifrada e Outros Dados — 2026-10-12

Classificação: MINOR, nova capacidade opcional em desenvolvimento.

- `DocumentValidator::detectType()` detecta CPF ou CNPJ pelo formato, com ou sem máscara.
- `DocumentCipher`: cifra libsodium com chave do Drupal Key (`aculta_document_encryption_key`, 64 caracteres hexadecimais). Sem chave, a cifra fica indisponível.
- Exibição mascarada: `DocumentFormatter::partial()` mostra 5 caracteres e `*` no restante (974.12*.***-**). O valor completo não é exibido.
- `AccountDocumentStore` implementa `AccountDocumentInterface` (Portal) e grava em `profile.participante.field_documento` (privado do perfil; não entra em formulários, exibições ou Views).
- Dependências adicionadas: `key` e `profile`.
- Runtime: **RUNTIME STATUS: DEFERRED**.

## 0.1.0-dev.3 — impressão digital HMAC para integração de unicidade — 2026-10-12

- Adicionado `aculta_associado_id.fingerprint`: canonicaliza CPF/CNPJ e retorna
  HMAC-SHA-256 com separação de versão e tipo de documento.
- Segredo obtido por DI do Drupal Key (Key ID fixo
  `aculta_id_cpf_fingerprint`); falha fechada sem chave de pelo menos 32 bytes.
- Isso é uma primitiva para a integração futura, não implementa persistência,
  índice/garantia de unicidade, campos ou proteção de leitura.
- Chave pseudonimiza o valor de índice, mas o fingerprint ainda é dado pessoal;
  rotação precisa recalcular índices.
- Runner: 248 checks PASS; lint PHP e gate Drupal 11+: 583 checks PASS em PHP
  8.3 local. PHPUnit não executado neste checkout sem `vendor/`; PHP 8.4 e
  runtime completo continuam DEFERRED para esta alteração.

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

