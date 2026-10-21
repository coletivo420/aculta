# Changelog — ACULTA QR Code

## 0.4.0-dev.1 — painel administrativo e exportação — 2026-10-20

- Painel em `/painel-administrativo/aculta/codigos-qr`: lista com downloads por formato, criação, edição (cada salvamento cria revisão) e exclusão, com permissão `administer aculta qr codes`. O caminho administrativo fica no purpose `main` pelo subscriber do Portal.
- Validade usa campos `datetime` (widget nativo do Core). O formulário valida a janela pelo mesmo serviço da API.
- `aculta_qrcode.exporter` exporta o QR que codifica a URL pública `/qr/{codigo}`: SVG em memória; PNG e WebP como derivados temporários do `aculta_image`, com nome derivado do conteúdo (sem acúmulo); PDF pelo Entity Print, sem gravação. Sem `aculta_image`, a exportação de arquivo é recusada.
- Desvio registrado: o `aculta_image` aceita só imagens, então o PDF não passa por ele. Entregue em stream, sem gravação.
- Formato WebP adicionado ao gerador.
- Validação: Kernel 11 testes PASS (84 asserções) no banco `aculta_simpletest`; Unit 26 testes PASS; PHPCS PASS. Não testados: download PNG/WebP com `aculta_image` ativo, PDF com Entity Print, painel no navegador e no Runtime (DEFERRED).
- Pendente nesta versão: personalização visual (cores/moldura/legenda editáveis), logo por arquivo do `aculta_image`, prévia e componentes do ACULTA420. O tema exige autorização explícita (ver AGENTS.md).

## 0.3.0-dev.1 — código QR dinâmico com validade e redirecionamento — 2026-10-20

- Entidade `aculta_qr_code` revisionável, com código público de 12 caracteres único, destino `http`/`https` validado por constraint, preset e janela de validade. Instalação pelo `hook_install`.
- `aculta_qrcode.manager` é o único caminho de gravação: valida destino e janela, gera código sem colisão e cria revisão com mensagem administrativa.
- Redirecionamento público `/qr/{codigo}` no purpose `links`: 302 ao destino quando publicado e dentro da validade; 404 nos demais casos. Cache dependente da entidade e limitado ao fim da validade. Sem registro de leitura.
- Acesso por `administer aculta qr codes`; o redirecionamento público não passa pela permissão.
- `aculta_qrcode.url_resolver` monta a URL pública pelo `DomainPurposeManager`; a dependência é opcional no container.
- Registro em `ROUTES-SITEMAP.md` e `LGPD-INVENTARIO.md`.
- Validação: Kernel 8 testes/60 asserções PASS no banco `aculta_simpletest`; Unit 25 testes/38 asserções PASS; PHPCS PASS; gates de fronteira, Portal Drupal 11 e slugs públicos PASS. Runtime, update e HTTP DEFERRED.

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
