# Revisão de entradas ZIP/GZIP e leitura de anexos — 2026-11-02

Escopo: pontos do código custom da ACULTA que descompactam ou leem ZIP/GZIP/XLSX vindos de fora (upload de usuário, anexo de e-mail, pacote H5P). Revisão estática, sem execução no Runtime nem no Homelab.

## Resultado por superfície

| Superfície | Origem | Situação | Commit |
| --- | --- | --- | --- |
| Instalador de pacote H5P (`aculta_lms_h5p`, `aculta_H5PLibraryPackageInstaller`) | Upload de administrador | Corrigido: teto de entradas, de bytes por arquivo, de bytes totais e de razão, medidos por streaming antes da extração | `fac3d58` (enviado) |
| Parser DMARC (`aculta_mailer`, `DmarcReportParser`) | Anexo de remetente externo | Corrigido: `getFromIndex(0)` e `gzdecode` sem teto. Agora teto de 8 MiB de XML descompactado, medido ao ler | `6f978b5` (origin/dev) |
| Download IMAP (`aculta_mailer`, `CurlImapClient`) | Caixa de e-mail (mensagem externa) | Corrigido: teto de 25 MiB por mensagem, medido durante o download. Mensagem acima do teto é descartada e o cursor avança | `42ed25f` (origin/dev) |
| DOCX e XLSX (`DocxPackageValidator`) | Upload de usuário | Já media bytes reais por streaming (`measureEntry`, com orçamento), antes da correção | — |
| ODS (`SpreadsheetFileValidator::validateOds`) | Upload de usuário | Já tinha orçamento de bytes, razão de compressão e limite de entradas | — |

## Verificado nesta revisão

- Nenhum `exec`/`shell_exec` com entrada não sanitizada no código custom. Os scripts usam `escapeshellarg`.
- `unserialize` restrito: `scripts/diagnose-institution.php` usa `allowed_classes => FALSE`.
- Nenhum `extractTo` no código custom.
- Validação com o parser DMARC antigo: os testes de bomba GZIP e ZIP falham (2/6). Com a correção, passam (6/6).
- Validação do limite IMAP contra um servidor HTTP local com arquivo de 30 MiB: estouro gera exceção; corpo abaixo do teto volta idêntico; leitura sem teto continua íntegra. Pico de memória no estouro: 48 MiB.

## Pendências e limites

### Resultado de execução no Homelab — 2026-11-02

- **PASS:** PHPUnit oficial do Mailer Unit, incluindo `MailMonitorTest::testOversizedMessageIsSkippedAndQueueContinues`, os casos GZIP/ZIP e a configuração de limites: 15 testes, 40 assertions.
- **PASS:** PHPUnit oficial de `aculta_lms`: `AttemptRuleTest`, 11 testes, 11 assertions.
- **PASS:** PHPUnit oficial de `aculta_lms_h5p` Unit: 6 testes, 40 assertions.
- **PASS:** Kernels do Mailer (`AcultaMailerKernelTest` e `MailSettingsKernelTest`): 7 testes, 33 assertions, no `aculta_simpletest`. Após os limites configuráveis em `dev.77`, a primeira execução faltou `file.upload_handler`; as fixtures agora habilitam `file` e `aculta_file_uploader`.
- **PASS:** Kernel do instalador H5P: 13 testes, 310 assertions, no `aculta_simpletest`. A fixture habilita `aculta_file_uploader` e instala o schema de quarentena pelo módulo dono.
- **PASS:** teste real de bomba ZIP/H5P autorizado pelo responsável: ZIP com entrada aleatória de 33 MiB e ZIP com entrada compressível de 2 MiB e razão acima de 100:1 foram criados e processados pelo instalador real. Ambos foram recusados antes da criação do diretório de extração; suíte específica: 2 testes, 78 assertions.
- **PASS:** gate `validate-aculta-portal-drupal11.php`: 1.159 checks; `validate-project-status-index.php`: 48 componentes.
- **PASS:** Runtime confirmado em `toca`, banco `aculta_runtime`; `updatedb` sem updates. Snapshot integral pré-import: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. `config:import` importou apenas `config/sync`, removendo `aculta_file_uploader.settings` que existia só no banco; os status finais de updates/configuração ficaram limpos.
- **REVISADO, sem mudança:** `QrCodeBatchController` cria arquivo ZIP de saída com `ZipArchive::addFromString`; não abre nem descompacta arquivo de entrada externa.
- **DEFERRED por escopo:** o fluxo PPT do `ExternalPackageController` do Opigno não foi portado. O plano de independência registra explicitamente PPT/importação/exportação como não objetivos; nenhuma execução desse fluxo foi feita.
- **MANTIDO:** não há validador compartilhado entre módulos; decisão registrada continua sendo implementação própria por superfície.
- **PASS:** `ForumRoutesFunctionalTest` no tema ACULTA420: 6 testes, 33 assertions; no tema Olivero: 6 testes, 33 assertions. O teste funcional precisou habilitar o módulo de suporte `aculta_portal_upload_test` para fornecer o schema de upload.
- **DEFERRED:** suíte funcional temática de Portal e Revista. Não há classes `BrowserTestBase`/`FunctionalJavascriptTestBase` nesses componentes. Uma smoke funcional temporária da Revista falhou durante instalação do módulo porque suas Views exigem `node.type.revista` e `field.field.node.revista.field_aculta_discussion`, fornecidos por `config/sync` e ausentes numa instalação limpa de teste. Portanto não há resultado de Portal/Revista sob ACULTA420 ou Olivero; a cobertura Kernel da Revista não exercita tema.

## Versões

- `aculta_lms_h5p` 0.4.0-dev.166 (teto no código), 0.4.0-dev.167 (teto vindo do painel), 0.4.0-dev.168 (fixture Kernel) e 0.4.0-dev.169 (testes ZIP reais).
- `aculta_mailer` 0.1.0-dev.75 (DMARC), 0.1.0-dev.76 (IMAP), 0.1.0-dev.77 (tetos vindos do painel e guarda de
  relatório sem metadados) e 0.1.0-dev.78 (fixtures Kernel). Ver CHANGELOG do módulo.
- `aculta_file_uploader` 0.1.0-dev.28: o objeto `IngestionLimits` e a subaba `/painel-administrativo/aculta/uploads/limites`.

Atualização de 2026-11-02 (mesma data, depois da revisão): os tetos de DMARC, IMAP e H5P deixaram de ser
constantes fixas e passaram a vir do painel de uploads, com os mesmos padrões e tetos máximos fixos no código.
As correções descritas acima continuam valendo; só a origem do valor mudou.

Status geral: Unit, Kernel Mailer/H5P, gates e teste real de bomba ZIP concluídos no Homelab. Fórum passou em ACULTA420 e Olivero. Portal/Revista sob esses temas permanecem deferred pela ausência de suítes funcionais executáveis; a tentativa isolada da Revista encontrou dependências de configuração do site.
