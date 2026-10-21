# Licenciamento e atribuições — ACULTA QR Code

Estado em 2026-10-20 (0.1.0-dev.1). **Nenhum arquivo de terceiros foi copiado.** Este documento registra as origens, as licenças observadas e as obrigações que valerão quando a portagem começar. Não é parecer jurídico.

## Origens analisadas

| Origem | Versão e commit | Licença observada | Arquivos consultados | Estado |
| --- | --- | --- | --- | --- |
| `qr_generator` | `2.x` · `6335abd` | GPL-2.0-or-later (`composer.json`; `LICENSE.txt` GPL-2.0) | `src/`, `qr_generator.*.yml`, `composer.json`, `LICENSE.txt` | Nada copiado. |
| `barcodes` | `2.1.x` · `a78b494` | GPL-2.0-or-later (`composer.json`); sem `LICENSE` no clone | `src/`, `barcodes.*`, `composer.json` | Nada copiado. |
| `endroid_qr_code` | `4.0.x` · `acb5fc4` | GPL-2.0+ (`composer.json`); sem `LICENSE` no clone | `src/`, `*.routing.yml`, `composer.json` | Nada copiado. |
| `qrcode` | `1.0.x` · `e3af5ff` | GPL-2.0-or-later (`LICENSE.txt` GPL-2.0) | `src/`, `composer.json`, `LICENSE.txt` | Nada copiado. |
| `qrcode_fields` | `2.1.x` · `1336820` | GPL-2.0-or-later (`composer.json`); sem `LICENSE` no clone | `src/` (amostra) | Nada copiado. Descartado. |
| `qr_code_formatter` | branch padrão · `41756ef` | GPL-2.0-or-later (`composer.json`) | `src/Plugin/Field/FieldFormatter/` | Nada copiado. |

Os projetos Drupal.org são GPL-2.0-or-later. A raiz deste repositório traz `LICENSE` em GPL-3.0; ainda não foi confirmado pelo responsável se a combinação de código GPL-2.0-or-later com o restante do repositório segue a licença “or later”. **Pendência para o responsável.**

## Dependências Composer candidatas

| Pacote | Versão | Licença (Packagist) | Uso proposto | Estado |
| --- | --- | --- | --- | --- |
| `endroid/qr-code` | 6.1.3 (já no `composer.lock`) | MIT | Motor de codificação e SVG/PNG/WebP. | Em uso no `aculta_links`. Reutilizar. |
| `bacon/bacon-qr-code` | 3.1.1 (já no lock) | BSD-2-Clause | Dependência transitiva do Endroid. | Em uso. |
| `dasprid/enum` | 1.0.7 (já no lock) | BSD-2-Clause | Dependência transitiva do Endroid. | Em uso. |
| `setasign/fpdf` | 1.8.x / 1.9.0 | MIT (1.9.0) | Writer PDF do Endroid. | Não instalado. Depende da decisão 1 em [ARQUITETURA.md](ARQUITETURA.md). |
| `chillerlan/php-qrcode` | 4.x (qrcode, qr_code_formatter) | Verificar por versão (6.0.1 declara MIT e Apache-2.0) | Não adotado: o motor escolhido é o Endroid. | Não adotado. |
| `tecnickcom/tc-lib-barcode` | 2.17.2 | LGPL-3.0-or-later | Motor de códigos de barras do `barcodes`. | Não adotado: fora do escopo de QR e licença LGPL. |

Nenhuma dependência nova é instalada em 0.1.0. Cada uma exige justificativa no momento da adoção, conforme `AGENTS.md`.

## Obrigações para a portagem (quando houver código copiado)

1. Manter o cabeçalho de copyright e a licença de cada arquivo copiado. Não reescrever a atribuição.
2. Registrar, por arquivo copiado: origem, projeto, versão, commit, alterações e data.
3. Quando algum projeto não tiver `LICENSE` no clone, confirmar o cabeçalho de cada arquivo antes de copiar.
4. Manter os textos de licença das dependências Composer no `LICENSES/` do módulo quando a dependência for adotada.
5. Não copiar código de `qrcode_fields` nem de `qrcode` sem reescrita independente da lógica; o código deles não será copiado no 0.x.

## Itens bloqueados por licença ou política

- `tc-lib-barcode` (LGPL-3.0-or-later): não adotado.
- Código de geração de QR por serviços externos (`GoogleChartAPI`, `QrCodeTecIt`): descartado por política de dados, não por licença.
