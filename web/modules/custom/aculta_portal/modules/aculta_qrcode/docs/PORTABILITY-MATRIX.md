# Matriz de portabilidade — ACULTA QR Code

Estado em 2026-10-20 (0.1.0-dev.1). Classificação por recurso. Origens e licenças: [AUDITORIA.md](AUDITORIA.md) e [LICENSING.md](LICENSING.md). A coluna “Versão alvo” remete ao [ROADMAP.md](ROADMAP.md).

Categorias: **COPIAR** (aproveitável quase integralmente, com licença preservada); **PORTAR** (adaptar ao padrão ACULTA); **REIMPLEMENTAR** (mesma função, solução Drupal nativa diferente); **REUTILIZAR** (já existe no ACULTA); **DESCARTAR** (redundante ou incompatível); **ADIAR** (válido, fora da fase atual).

Origens: `QRG` = `qr_generator` 2.x `6335abd`; `BC` = `barcodes` 2.1.x `a78b494`; `END` = `endroid_qr_code` 4.0.x `acb5fc4`; `QR` = `qrcode` 1.0.x `e3af5ff`; `QRF` = `qrcode_fields` 2.1.x `1336820`; `QRFM` = `qr_code_formatter` `41756ef`.

## Geração e codificação

| Recurso | Origem (arquivos) | Categoria | Justificativa | Versão alvo |
| --- | --- | --- | --- | --- |
| Motor de codificação SVG/PNG/WebP | `endroid/qr-code` 6.1.3 (dependência já no lock; usada por `aculta_links`) | REUTILIZAR | Um único motor. Nenhuma cópia de biblioteca de terceiros. | 0.2.0 |
| Wrapper de geração (`QrCodeSvgGenerator`) | ACULTA `aculta_links/src/Service/QrCodeSvgGenerator.php` | PORTAR | Mesma configuração (correção `Medium`, margem, SvgWriter), mas com preset e sem acoplamento ao `aculta_links`. | 0.2.0 |
| Configuração de formato (tamanho, margem, rótulo) | `END` `src/Plugin/Field/FieldFormatter/EndroidQrCodeFormatter.php` | PORTAR | Padrão enxuto de configuração; sem rota pública. | 0.2.0 |
| Cores, logo e legenda | `QRG` `src/QRCodeExportService.php` (cores, `hexToColor`, correção de erro, logo) | PORTAR | Regras de cor e correção são úteis. A origem do logo muda: somente arquivo do `aculta_image`. | 0.4.0 |
| Logo por URL remota (`logo_source=url`) | `QRG` `QRCodeExportService::getLogoPath()` | DESCARTAR | Permite leitura de URL arbitrária no servidor (SSRF). | — |
| Exportação PNG e WebP | `endroid` `PngWriter`, `WebPWriter` | REUTILIZAR | Writers já presentes na dependência. | 0.4.0 |
| Exportação PDF | `QRG` `PdfWriter` com `setasign/fpdf`; alternativa Entity Print/Dompdf (`entity_print` no contrib) | ADIAR | Depende da decisão 1 de `ARQUITETURA.md`. | 0.4.0 |
| Recursos visuais de `qrcode` (estilos, animações) | `QR` `src/` | ADIAR | Animação compromete leitura. Estilos entram como presets validados. | 0.4.0 |
| Presets de aparência com validação de contraste | — | REIMPLEMENTAR | Não há equivalente nos projetos; regra nova do ACULTA. | 0.2.0 |

## Entidade, dinâmico e redirecionamento

| Recurso | Origem | Categoria | Justificativa | Versão alvo |
| --- | --- | --- | --- | --- |
| Entidade de QR com destino e validade | `QRG` `src/Entity/QRCode.php` (campos `field_redirect_url`, `field_expires`) | REIMPLEMENTAR | Entidade nova `aculta_qr_code`, com anotação substituída por atributo do Core 11 e campos alinhados ao modelo do ACULTA. | 0.3.0 |
| Verificação de validade na requisição | `QRG` `QRRestResource` (comparação com tempo atual) | PORTAR | Regra simples; a exceção vira resposta de página, não exceção HTTP bruta. | 0.3.0 |
| Redirecionamento por UUID via REST | `QRG` `QRRestResource` | DESCARTAR | `access()` permite tudo e não há Entity Access. Substituído por rota própria. | — |
| Redirecionamento por hash no kernel | `QR` `src/EventSubscriber/QRCodeRequestSubscriber.php` | REIMPLEMENTAR | Ideia de identificador opaco aproveitada. Gravação por leitura (`countHit()` com `save()`) descartada. | 0.3.0 |
| Contador de visitas gravado por requisição | `QR` `src/Entity/QRCode.php::countHit()` | DESCARTAR | Escrita no banco a cada leitura; incompatível com cache. | — |
| Redirecionamento curto já existente (`/redirecionar/{codigo}`) | ACULTA `aculta_links` | REUTILIZAR | Já usa `TrustedRedirectResponse` e `DomainPurposeManager`. Manter até a 0.7.0. | 0.7.0 |
| Código opaco de 12 caracteres | ACULTA `aculta_links` (destinos curtos) | REUTILIZAR | Gerador e unicidade já existentes; adotar o mesmo padrão. | 0.3.0 |
| Endereço do QR dinâmico | `DomainPurposeManager` + `aculta_deployer` (`environment_bound`) | REUTILIZAR | Nenhum hostname fixo. | 0.3.0 |

## Integração com Drupal e ACULTA

| Recurso | Origem | Categoria | Justificativa | Versão alvo |
| --- | --- | --- | --- | --- |
| Formatador de campo | `BC` `src/Plugin/Field/FieldFormatter/Barcode.php` | PORTAR | Padrão de configuração e saída SVG inline. Limitado a QR. | 0.5.0 |
| Formatador de campo `link` | `QRFM` `QrCodeFormatterFormatter.php` | REIMPLEMENTAR | Título não deve ser `h2` fixo; `alt` não deve ser a URL inteira; precisa de cache metadata do link. | 0.5.0 |
| Formatador de campo com geração a partir de `endroid` | `END` `EndroidQrCodeFormatter.php` | PORTAR | Mesmo papel, sem field type próprio. | 0.5.0 |
| Bloco de exibição | `BC` `src/Plugin/Block/Barcode.php` | PORTAR | Plugin com contexto de entidade. Quantidade e pager por `ctools_views` quando couber (`web/modules/custom/aculta_portal/docs/CTOOLS.md`). | 0.5.0 |
| Filtro Twig `barcode` | `BC` `src/Template/Barcode.php` | PORTAR | Saída `html` controlada; adicionar escape do valor. | 0.5.0 |
| Tokens | `token` contrib (já instalado) | REUTILIZAR | Usar o token existente para conteúdo de QR de entidade. | 0.5.0 |
| Views (listagem e campo) | Core `views` | REUTILIZAR | `QRCodeThumbnailField` de `QR` não é necessário; Views nativo cobre a listagem. | 0.4.0 |
| Comandos Drush | `BC` `src/Drush/Commands/BarcodesDrushCommands.php` | ADIAR | Útil para lote; depende da fase de lote. | 0.6.0 |
| Geração em lote | `BC` (sem lote próprio); Batch API do Core (`web/core/lib/Drupal/Core/Batch`) | REIMPLEMENTAR | Batch API nativo, não processamento em uma requisição. | 0.6.0 |
| Campos de conteúdo estruturado (vCard, Wi-Fi, SMS, e-mail) | `QRF` `src/Plugin/Field/*` | DESCARTAR | Geração externa (`GoogleChartAPI`, `QrCodeTecIt`) envia dados a terceiros. A reavaliação só ocorre com geração local. | — |
| Blocos de QR por tipo | `QRF` `src/Plugin/Block/QR*Block.php` | DESCARTAR | Dependem dos campos descartados. | — |
| Motor alternativo `chillerlan/php-qrcode` | `QR`, `QRFM` | DESCARTAR | Segundo motor concorrente; o motor é o Endroid. | — |
| Motor `tc-lib-barcode` | `BC` | DESCARTAR | Fora do escopo de QR; licença LGPL-3.0-or-later. | — |

## Acesso, domínio e cache

| Recurso | Origem | Categoria | Justificativa | Versão alvo |
| --- | --- | --- | --- | --- |
| Permissão de administração | `QRG` `administer qr_code` | REIMPLEMENTAR | Permissões por operação do ACULTA, sem papel com acesso direto. | 0.3.0 |
| Acesso por Entity Access em exportação | `QRG` `_entity_access` | PORTAR | Padrão correto; aplicar também ao redirecionamento. | 0.3.0 |
| Resolução de URL canônica | `DomainPurposeManager` (ACULTA Portal) | REUTILIZAR | Regra de domínio já existente. | 0.2.0 |
| Cache do redirecionamento e da imagem | Cache tags de entidade e Domain (ACULTA) | REIMPLEMENTAR | Política explícita: invalidar ao mudar destino, desativar ou expirar. | 0.3.0 |

## Imagens, PDF e rótulos

| Recurso | Origem | Categoria | Justificativa | Versão alvo |
| --- | --- | --- | --- | --- |
| Armazenamento de arquivos de imagem | `aculta_image` (ACULTA) | REUTILIZAR | Regra obrigatória do projeto para qualquer imagem. | 0.4.0 |
| Geração de PDF de certificado (LMS F11) | `entity_print` (contrib, `composer.json` do trabalho em andamento) | REUTILIZAR | Se a decisão 1 escolher Entity Print, o PDF do QR usa o mesmo motor. | 0.4.0 |

## Itens fora de escopo desta matriz

- Animações e efeitos visuais de `qrcode`: ADIAR, com revisão de legibilidade.
- Estatísticas: classificadas no roadmap pela decisão 4 de `ARQUITETURA.md`.
