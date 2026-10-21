# Changelog — ACULTA QR Code

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
