# Revisão de entradas ZIP/GZIP e leitura de anexos — 2026-11-02

Escopo: pontos do código custom da ACULTA que descompactam ou leem ZIP/GZIP/XLSX vindos de fora (upload de usuário, anexo de e-mail, pacote H5P). Revisão estática, sem execução no Runtime nem no Homelab.

## Resultado por superfície

| Superfície | Origem | Situação | Commit |
| --- | --- | --- | --- |
| Instalador de pacote H5P (`aculta_lms_h5p`, `aculta_H5PLibraryPackageInstaller`) | Upload de administrador | Corrigido: teto de entradas, de bytes por arquivo, de bytes totais e de razão, medidos por streaming antes da extração | `fac3d58` (enviado) |
| Parser DMARC (`aculta_mailer`, `DmarcReportParser`) | Anexo de remetente externo | Corrigido: `getFromIndex(0)` e `gzdecode` sem teto. Agora teto de 8 MiB de XML descompactado, medido ao ler | `bf6a089` (local) |
| Download IMAP (`aculta_mailer`, `CurlImapClient`) | Caixa de e-mail (mensagem externa) | Corrigido: teto de 25 MiB por mensagem, medido durante o download. Mensagem acima do teto é descartada e o cursor avança | `0d9d754` (local) |
| DOCX e XLSX (`DocxPackageValidator`) | Upload de usuário | Já media bytes reais por streaming (`measureEntry`, com orçamento), antes da correção | — |
| ODS (`SpreadsheetFileValidator::validateOds`) | Upload de usuário | Já tinha orçamento de bytes, razão de compressão e limite de entradas | — |

## Verificado nesta revisão

- Nenhum `exec`/`shell_exec` com entrada não sanitizada no código custom. Os scripts usam `escapeshellarg`.
- `unserialize` restrito: `scripts/diagnose-institution.php` usa `allowed_classes => FALSE`.
- Nenhum `extractTo` no código custom.
- Validação com o parser DMARC antigo: os testes de bomba GZIP e ZIP falham (2/6). Com a correção, passam (6/6).
- Validação do limite IMAP contra um servidor HTTP local com arquivo de 30 MiB: estouro gera exceção; corpo abaixo do teto volta idêntico; leitura sem teto continua íntegra. Pico de memória no estouro: 48 MiB.

## Pendências e limites

- `MailMonitorTest::testOversizedMessageIsSkippedAndQueueContinues` foi escrito e não rodou: o teste usa `createMock`, fora do shim local. Deve rodar no ambiente com PHPUnit oficial.
- PHPUnit oficial, Kernel do Mailer e gate do Portal não rodaram nesta sessão (Packagist bloqueado; sem Runtime).
- Kernel do instalador H5P (`aculta_simpletest`) e teste de bomba real: Homelab, com autorização.
- `QrCodeBatchController` cria ZIP de saída (não lê entrada externa). Não foi revisado em profundidade.
- Não portado do Opigno: fluxo de PPT com `exec` e nome de arquivo sem `escapeshellarg` (`ExternalPackageController`).
- Limite compartilhado entre módulos: descartado por decisão do responsável. Cada módulo mantém sua própria implementação.

## Versões

- `aculta_lms_h5p` 0.4.0-dev.166 (teto no código) e 0.4.0-dev.167 (teto vindo do painel).
- `aculta_mailer` 0.1.0-dev.75 (DMARC), 0.1.0-dev.76 (IMAP) e 0.1.0-dev.77 (tetos vindos do painel e guarda de
  relatório sem metadados). Ver CHANGELOG do módulo.
- `aculta_file_uploader` 0.1.0-dev.28: o objeto `IngestionLimits` e a subaba `/painel-administrativo/aculta/uploads/limites`.

Atualização de 2026-11-02 (mesma data, depois da revisão): os tetos de DMARC, IMAP e H5P deixaram de ser
constantes fixas e passaram a vir do painel de uploads, com os mesmos padrões e tetos máximos fixos no código.
As correções descritas acima continuam valendo; só a origem do valor mudou.

Status geral: DEFERRED até a execução no ambiente de testes.
