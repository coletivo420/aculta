# Changelog — ACULTA QR Code

## 0.2.0-dev.1 — geração estática e presets — 2026-10-20

- Gerador `aculta_qrcode.generator` produz SVG e PNG em memória, sem gravar arquivo e sem serviço externo. Usa `endroid/qr-code` 6.1.3, já fixado no lock.
- Presets `aculta_padrao` e `aculta_impressao` em `aculta_qrcode.preset_registry`, validados no construtor: cores `#RRGGBB`, lado entre 128 e 2048 px, margem entre 0 e 200 px e contraste mínimo de 4,5:1 (WCAG).
- Validador `aculta_qrcode.payload_validator`: URLs `http`/`https` com host, sem credenciais, até 2048 caracteres; texto até 1000 caracteres sem caracteres de controle. Rejeita `javascript:`, `data:` e `file:`.
- Contrato em [API.md](docs/API.md). Sem entidade, rota pública ou redirecionamento.
- Validação: 25 testes Unit, 38 asserções, PASS (PHPUnit do Core, PHP 8.4.26 local). PHPCS com padrão Drupal e DrupalPractice: PASS. Boundary gate: PASS. Kernel não executado (fase sem entidade).
- O PHP local é 8.4; o projeto declara 8.5. A validação em 8.5 fica para o servidor.

## 0.1.0-dev.1 — auditoria, licenças e arquitetura — 2026-10-20

- Cria o submódulo com manifesto `0.1.0-dev.1`, sem código de geração, entidade, rota, permissão ou dependência Composer.
- Auditoria do código atual do ACULTA (`aculta_links`: Endroid 6.1.3, SVG, sem leitura registrada) e de seis projetos Drupal.org: `qr_generator`, `barcodes`, `endroid_qr_code`, `qrcode`, `qrcode_fields` e `qr_code_formatter`. Commits e licenças registrados em [AUDITORIA.md](docs/AUDITORIA.md) e [LICENSING.md](docs/LICENSING.md).
- Achados que bloqueiam a portagem literal: logo por URL remota no `qr_generator` (SSRF), recurso REST sem Entity Access no `qr_generator`, gravação por leitura no `qrcode`, envio de conteúdo a serviços externos no `qrcode_fields`.
- Arquitetura proposta, modelo de dados, acesso, domínio, cache, segurança e cinco decisões pendentes em [ARQUITETURA.md](docs/ARQUITETURA.md).
- Decisões do responsável registradas em [ARQUITETURA.md](docs/ARQUITETURA.md): PDF pelo Entity Print/Dompdf; caminho `/qr/{codigo}`; exportações pelo `aculta_image` como temporários, sem acúmulo; analytics como contagem diária por código. Lote segue pendente.
- Matriz de portabilidade por recurso, com categorias COPIAR, PORTAR, REIMPLEMENTAR, REUTILIZAR, DESCARTAR e ADIAR, em [PORTABILITY-MATRIX.md](docs/PORTABILITY-MATRIX.md).
- Estado atual de QR e recursos relacionados no ACULTA, classificado por busca direcionada, em [CURRENT-STATE.md](docs/CURRENT-STATE.md): QR SVG existente em `aculta_links`, componentes de QR e compartilhamento ausentes no ACULTA420.
- Roadmap de 0.1.0 a 1.0.0 em [ROADMAP.md](docs/ROADMAP.md).
- Validação: apenas gates documentais (ver a mensagem do commit e o relatório da sessão). Nenhum teste automatizado foi executado porque não há código.
