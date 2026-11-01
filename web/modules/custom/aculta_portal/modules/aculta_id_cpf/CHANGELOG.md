## 0.3.0-dev.2 — restrição transacional de unicidade — 2026-10-25

- `aculta_id_cpf_update_10002` adiciona um índice único ao campo de fingerprint no armazenamento ativo do Profile. A restrição SQL fecha a condição de corrida após a consulta de preflight.
- O update detecta grupos duplicados antes do DDL e aborta com contagem agregada, sem revelar fingerprints ou documentos. O hook de instalação aplica o mesmo contrato em instalações novas.
- `AccountDocumentStore` transforma a violação específica do índice em erro genérico, preservando falhas de armazenamento não relacionadas.
- Kernel MariaDB chama `10002` duas vezes para confirmar idempotência, rejeita duplicata no banco e bloqueia atualização quando já há conflito (2 testes/5 assertions). Nenhuma atualização foi aplicada no Runtime.

## 0.3.0-dev.1 — módulo oficial de CPF/CNPJ — 2026-10-23

- Decisão de 2026-10-23: `aculta_id_cpf` é a implementação oficial e permanente de identidade fiscal. Machine name, campos, storage, Key IDs, variáveis de ambiente e separador HMAC `aculta_id_cpf:v1` não mudam.
- Nome do módulo passa a ser ACULTA CPF ID; removido o texto de migração para `aculta_associado_id`; `hidden` removido.
- `aculta_portal` passa a resolver somente `aculta_id_cpf.account_document` (sem preferência nem fallback ao submódulo associado).
- Define a política de identidade fiscal compartilhada entre Loja e LMS em `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`. Implementação do checkout pendente.

# Changelog

## 0.3.0-dev.8 — login local por CPF (2026-10-29)

- `/entrar` e o popup aceitam CPF como identificador e o mostram no rótulo. O
  CPF válido é convertido temporariamente para o nome de conta durante a
  validação nativa da senha; a entrada original é restaurada antes da resposta.
- Flood, confirmação de e-mail, CAPTCHA e Social Auth seguem seus fluxos. CPF
  inválido, desconhecido, ambíguo ou sem chave/perfil não interrompe login por
  usuário ou e-mail. O popup reutiliza o wrapper Portal para respostas genéricas.
- Domínio 248 checks; Unit 48/116; Kernel 11/75 (8 depreciações); Portal Unit
  323/974 e Kernel 80/1530 (96 depreciações). Gate Drupal 11+, PHP lint e PHPCS
  Drupal/DrupalPractice PASS. Revisão visual aguarda o candidato servido.

## 0.3.0-dev.7 — limpar PHPCS Drupal/DrupalPractice (2026-10-29)

- Removidos docblocks vazios, descrições e quebras de linha ausentes nos
  serviços e testes; preservados os nomes públicos `DocumentType::CPF` e
  `DocumentType::CNPJ` por serem siglas legais.
- Runner de domínio: 248 PASS; Unit: 48 testes/116 assertions PASS; Kernel:
  7 testes/46 assertions PASS, 8 deprecações Core/contrib. PHPCS integral e
  PHP lint dos arquivos alterados PASS. Sem mudança funcional ou Runtime.
- D-51 autoriza login por CPF em todas as superfícies locais antes do RC.

## 0.3.0-dev.6 — integrar o formulário Portal ao store e testar rollback — 2026-10-29

- Novo Kernel integra `AccountOtherDataForm` ao `AccountDocumentStore` real:
  validação de CPF, gravação de valor cifrado e fingerprint, máscara em modo
  somente leitura e mensagem genérica para documento duplicado.
- Kernel `DocumentFingerprintUniqueIndexKernelTest` agora executa o rollback
  documentado: remove apenas o índice novo e confirma que a linha de índice
  continua íntegra.
- Runner de domínio: 248 checks PASS; Unit: 48 testes / 116 assertions PASS;
  Kernel: 7 testes / 46 assertions PASS, 8 depreciações Core/contrib. PHPCS e
  PHP lint dos arquivos alterados PASS. Update não executado no Runtime.
- Runtime verificado somente em leitura em 2026-10-29 no checkout detached
  `355e48a89`: módulo ativo 0.3.0-dev.4, schema 10002, índice único presente,
  2 linhas de documentos, 0 sem fingerprint e 0 grupos duplicados. Nenhuma
  atualização foi executada nesta fase; essa observação não valida a branch
  0.3.0-dev.6 nem a interface ainda não servida.

## 0.3.0-dev.4 — impedir unicidade falsa com documentos sem fingerprint — 2026-10-26

- O update 10002 agora verifica que todo documento não vazio do bundle Profile associado possui fingerprint antes de criar a restrição SQL. Se faltar índice ou tabela de origem, a operação falha fechada com contagem agregada e sem imprimir valores.
- Runner de domínio 248 checks, Unit 48/116 e Kernel MariaDB 3/8 PASS. O Kernel cobre sucesso/idempotência, fingerprints duplicados e recusa de documento sem fingerprint; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS.
- Runtime consultado em modo somente leitura: 1 documento associado, 1 fingerprint, zero não indexados, zero grupos duplicados e ainda sem índice SQL; nenhum update foi executado.

## 0.3.0-dev.3 — proteger diagnóstico de colisão concorrente — 2026-10-26

- Uma violação da chave única de fingerprint continua retornando `Document already registered.`, sem encadear a exceção SQL que pode conter o fingerprint no detalhe do banco. Falhas de armazenamento sem essa chave continuam sendo propagadas.
- `AccountDocumentStoreUniqueConflictTest` simula a corrida após a consulta prévia e confirma a mensagem genérica sem exceção anterior; Unit 48/116 e Kernel MariaDB 2/5 PASS. PHPCS Drupal/DrupalPractice e PHP lint dos arquivos modificados PASS.
- O Runtime não foi atualizado. Atualização `10002`, integração Profile/Form API e homologação permanecem pendentes.

## 0.2.0-dev.2 — unicidade por fingerprint e migração do índice — 2026-10-12

Classificação: MINOR, capacidade opcional em desenvolvimento.

- `AccountDocumentStore` recusa o mesmo documento em outra conta, por consulta ao
  `field_documento_indice`. Mensagem genérica: não revela se o documento existe.
- Consulta do perfil passa a incluir perfis despublicados (antes, `loadByUser`
  filtrava por status e podia criar um segundo perfil participante).
- Campo `field_documento_indice` (config/install) e `aculta_id_cpf_update_10001`
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

- Adicionado `aculta_id_cpf.fingerprint`: canonicaliza CPF/CNPJ e retorna
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

## 0.3.0-dev.5 — validar store contra entidades Profile reais (2026-10-29)

- `AccountDocumentStoreProfileKernelTest` cria usuários e perfis reais no banco
  simples: persiste apenas valor cifrado e fingerprint, lê a máscara de perfil
  despublicado e recusa CPF duplicado com erro genérico.
- Runner de domínio: 248 checks PASS; Unit: 48 testes / 116 assertions PASS;
  Kernel: 4 testes / 20 assertions PASS. O teste Kernel novo emite 7
  deprecações Core/contrib. PHPCS Drupal/DrupalPractice e PHP lint do teste PASS.
- Chaves são valores fictícios mantidos na fixture em memória. Nenhum dado
  pessoal real, Runtime ou atualização de schema foi usado.
- A integração do formulário Portal e a homologação operacional de `10002`
  continuam pendentes.

