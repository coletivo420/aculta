# Auditoria de portabilidade — ACULTA QR Code

Data: 2026-10-20. Escopo: código-fonte de seis projetos Drupal.org e o código atual do ACULTA. Nenhum arquivo de terceiros foi copiado para o repositório. A licença e a decisão de cada projeto estão em [LICENSING.md](LICENSING.md) e [ARQUITETURA.md](ARQUITETURA.md).

## Método

- Clones Git de `git.drupalcode.org` em diretório temporário fora do repositório, nos branches indicados. Leitura de `*.info.yml`, `composer.json`, arquivos de licença, entidades, rotas, formatadores, serviços e subscribers.
- Core instalado (`web/core`, 11.4.10) conferido: existem `Drupal\Core\Field\Attribute\FieldFormatter` e `Drupal\Core\Entity\Attribute\ContentEntityType`.
- Dependência verificada no `vendor/` e no `composer.lock`: `endroid/qr-code` 6.1.3, com writers SVG, PNG, WebP, EPS e PDF. O PDF exige `setasign/fpdf`, que não está instalado.
- Fontes de metadados: pacotes Packagist (licenças das dependências) e páginas de projeto do Drupal.org. A página do Drupal.org é renderizada por JavaScript e não foi lida em HTML; por isso o estado de cobertura de segurança de cada projeto **não foi verificado** e fica como pendência.

## Projetos analisados

| Projeto (Drupal.org) | Branch e commit analisados | Core declarado | Dependências Composer | Licença observada | Decisão |
| --- | --- | --- | --- | --- | --- |
| `qr_generator` | `2.x` · `6335abd` (2026-09-29) | `^10.3 \|\| ^11` | `endroid/qr-code` `^5.0 \|\| ^6.0`; `setasign/fpdf` `^1.8` | GPL-2.0-or-later (`composer.json`; `LICENSE.txt` GPL-2.0) | **Referência principal**, com exclusões de segurança (ver abaixo). |
| `barcodes` | `2.1.x` · `a78b494` (2026-10-19) | `^10.3 \|\| ^11 \|\| ^12` | `tecnickcom/tc-lib-barcode` `^2.0` | GPL-2.0-or-later (`composer.json`); sem arquivo `LICENSE` no clone | **Integração**: formatador, filtro Twig e bloco. Não adotar `tc-lib-barcode`. |
| `endroid_qr_code` | `4.0.x` · `acb5fc4` (2026-04-15) | `^10.4 \|\| ^11` | `endroid/qr-code` `^6.1` | GPL-2.0+ (`composer.json`); sem arquivo `LICENSE` no clone | **Renderização enxuta**. Não portar rotas públicas genéricas nem o field type. |
| `qrcode` | `1.0.x` · `e3af5ff` (2023-02-24) | `^9.0 \|\| ^10` | PHP `^7.3 \|\| ^8.0`; `chillerlan/php-qrcode` `^4.3.0` | GPL-2.0-or-later (`LICENSE.txt` GPL-2.0) | **Descartado como código**. Usado só como referência de recursos visuais. |
| `qrcode_fields` | `2.1.x` · `1336820` (2025-11-15) | `^10 \|\| ^11` | `drupal/token` `^1.11` | GPL-2.0-or-later (`composer.json`); sem `LICENSE` no clone | **Descartado**: envia o conteúdo do QR a serviços externos. |
| `qr_code_formatter` | branch padrão · `41756ef` (2026-09-25) | `^10 \|\| ^11` | `chillerlan/php-qrcode` `^5.0` | GPL-2.0-or-later (`composer.json`) | **Referência secundária** para formatador de campo `link`. Não portar como está. |

Observação de nomenclatura: a proposta cita `qrcode_formatter`; o projeto publicado no Drupal.org é `qr_code_formatter`. A proposta também cita “QR Code” para recursos visuais, que corresponde ao projeto `qrcode` acima.

Versões de PHP: `qr_generator`, `barcodes`, `endroid_qr_code`, `qrcode_fields` e `qr_code_formatter` não declaram `php` no `composer.json`. A proposta indica PHP 8.3+ para o `qr_generator`, mas isso não foi confirmado no manifesto. O piso efetivo do ACULTA é PHP 8.5 (`AGENTS.md`).

## Achados por projeto

### `qr_generator` — referência principal

- Entidade `QRCode` com campos de destino (`field_redirect_url`) e validade (`field_expires`); listagem, formulários e exportação em `/admin/qr-code/export/{qr_code}`, protegida por `_entity_access` e `administer qr_code`.
- Exportação por `endroid/qr-code` (SVG, PNG e PDF). O PDF usa `setasign/fpdf`.
- Plugins e entidade usam **anotações** (`@ContentEntityType`, `@FieldFormatter`). Na portagem, o padrão do Core 11 é o atributo PHP (`#[ContentEntityType]`, `#[FieldFormatter]`).
- **Bloqueio de segurança:** com `logo_source=url`, `QRCodeExportService::getLogoPath()` devolve a URL informada e o motor a lê no servidor. Isso permite buscar URL arbitrária no servidor (SSRF). Na portagem, o logo só existe como arquivo enviado pelo `aculta_image`.
- **Bloqueio de acesso:** `QRRestResource::access()` responde `AccessResult::allowed()` e `permissions()` é vazio. O recurso REST redireciona por UUID sem Entity Access. A portagem substitui por rota própria, com checagem de publicação, validade e Entity Access.
- Expiração é verificada no momento da requisição (`field_expires` contra o tempo atual) e lança exceção HTTP própria. A regra é portável; a exceção deve virar resposta de página, não exceção bruta.
- Não há analytics. O PDF depende de biblioteca que ainda não é dependência do projeto (ver decisão 1 em [ARQUITETURA.md](ARQUITETURA.md)).

### `barcodes` — integração universal

- Formatador de campo, bloco, filtro Twig `barcode` (`filterBarcode`), tokens e comandos Drush. Retorna SVG inline ou PNG em data URI.
- Porta-se o **padrão de integração**: formatador com configuração de tamanho, cor e margem; filtro Twig com saída `html` controlada; bloco com contexto. Não se porta o motor `tc-lib-barcode`, que cobre códigos de barras lineares fora do escopo, e a licença do pacote é LGPL-3.0-or-later (ver [LICENSING.md](LICENSING.md)).
- O formatador PNG em data URI aumenta o HTML da página; a portagem deve preferir SVG inline e declarar cache tags da entidade de origem.

### `endroid_qr_code` — renderização enxuta

- Field type, widget e formatter próprios, mais duas rotas públicas: `/image-qr-generate/{content}` gera QR de qualquer texto na URL, e `/image-qr-generate-with-url` gera QR com URL externa.
- Porta-se a **abordagem** de formatador enxuto e de configuração de formato (tamanho, margem e rótulo). Não se portam as rotas genéricas, que permitem gerar QR de conteúdo arbitrário sem vínculo a uma entidade, nem o field type, que duplicaria a entidade do ACULTA.

### `qrcode` — recursos visuais (legado)

- Entidade `QRCode` com contador `hits`. `QRCodeRequestSubscriber` (`KernelEvents::REQUEST`, prioridade 33) trata `/qrcode/{hash}`, chama `countHit()` e **salva a entidade a cada leitura**. Isso escreve no banco em toda leitura e é incompatível com cache de página.
- Código de Drupal 9/10 e `chillerlan/php-qrcode` 4.x. O branch analisado tem último commit de 2023-02-24 e nenhum teste.
- Não se porta código. A ideia de identificador opaco para o código dinâmico é aproveitada; a estatística será agregada e não gravada por requisição.

### `qr_code_formatter` — formatador de campo `link`

- Um formatador (`QrCodeFormatterFormatter`, 59 linhas) que renderiza data URI do `chillerlan/php-qrcode` com `alt` igual à URL e um `h2` fixo com o título do link.
- Problemas para portagem: título fixo em `h2` (semântica decidida pelo formatador, não pelo tema); `alt` com a URL inteira; ausência de cache metadata do link e de URL absoluta por ambiente.

### `qrcode_fields` — campos estruturados

- 38 arquivos PHP (cerca de 7.600 linhas, incluindo widgets, blocos e tipos de campo para URL, texto, e-mail, telefone, SMS, vCard, MeCard, Wi-Fi e evento).
- O gerador padrão do módulo envia o conteúdo a **serviços externos**: `GoogleChartAPI` (`chart.apis.google.com`) e `QrCodeTecIt` (`qrcode.tec-it.com`). Wi-Fi com senha e vCard com dados pessoais sairiam do servidor.
- **Descartado.** Conflita com a política de dados pessoais (`docs/compliance/LGPD-INVENTARIO.md`) e com a regra de não enviar dados a terceiros sem base. Os tipos de conteúdo estruturado podem voltar como referência se a geração for local, em fase posterior.

## Código atual do ACULTA

- `aculta_links` 0.4.0-dev.15 já gera QR SVG: `QrCodeSvgGenerator` usa `endroid/qr-code` 6.1.3, `SvgWriter`, correção de erro `Medium`, 320 px e margem 12. Rotas: `/pagina/{slug}/codigo-qr.svg` e `/redirecionar/{codigo}/codigo-qr.svg`. Não há registro de leitura.
- `composer.lock` fixa `endroid/qr-code` 6.1.3, `bacon/bacon-qr-code` 3.1.1 e `dasprid/enum` 1.0.7. Nenhuma dependência nova é necessária para 0.2.0.
- O contrato F11 do `aculta_lms` (certificados com verificação pública por QR) declara que o QR será ligado a `aculta_qrcode`. Essa alteração está em andamento em outra frente e ainda não foi commitada.
- Não existe PNG, WebP, PDF nem logo para QR no ACULTA hoje.

## Lacunas e pendências desta auditoria

- Cobertura de segurança e manutenção no Drupal.org não verificada (página renderizada por JavaScript).
- Nenhum clone trouxe arquivo `LICENSE` para `barcodes` e `endroid_qr_code`; a licença é a declarada no `composer.json`. Antes de copiar qualquer arquivo, confirmar o cabeçalho de cada arquivo.
- Versão mínima de PHP do `qr_generator` não declarada no manifesto.
- Não houve execução dos módulos de origem; a análise é de código.

## Decisões do responsável

Registradas em [ARQUITETURA.md](ARQUITETURA.md), seção “Decisões do responsável (2026-10-20)”: motor de PDF Entity Print/Dompdf; caminho `/qr/{codigo}`; exportações pelo `aculta_image` sem acúmulo; analytics como contagem diária por código. A geração em lote segue sem resposta.
