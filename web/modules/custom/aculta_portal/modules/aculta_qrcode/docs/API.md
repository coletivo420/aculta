# API de QR Codes — versões 0.2.0 e 0.3.0

Contrato dos serviços disponíveis em 0.3.0-dev.1. Registrados em `aculta_qrcode.services.yml`, com injeção de dependência. Geração estática (0.2.0) não grava nada; o código dinâmico (0.3.0) grava a entidade `aculta_qr_code` por meio do `QrCodeManager`.

## Fluxo

1. `aculta_qrcode.payload_validator` valida o conteúdo (URL ou texto).
2. `aculta_qrcode.preset_registry` fornece o preset (aparência e correção de erro).
3. `aculta_qrcode.generator` gera o SVG ou PNG em memória.

Qualquer valor inválido lança `Drupal\aculta_qrcode\Exception\InvalidQrCodeException` (subclasse de `\InvalidArgumentException`). Quem chama decide a resposta ao usuário, sem expor detalhes internos.

## Serviços

### `aculta_qrcode.payload_validator` — `QrCodePayloadValidator`

| Método | Entrada | Saída | Regras |
| --- | --- | --- | --- |
| `validateUrl(string $url): string` | URL absoluta | URL sem espaços nas bordas | Somente `http` e `https`, com host. Rejeita credenciais embutidas, URL relativa e mais de 2048 caracteres. |
| `validateText(string $text): string` | Texto simples | Texto inalterado | Não vazio, até 1000 caracteres, sem caracteres de controle. |

### `aculta_qrcode.preset_registry` — `QrCodePresetRegistry`

| Método | Saída | Observação |
| --- | --- | --- |
| `get(string $id): QrCodePreset` | Preset | Lança exceção para identificador desconhecido. |
| `default(): QrCodePreset` | Preset `aculta_padrao` | Atalho para o padrão. |
| `ids(): string[]` | Identificadores | Lista os presets disponíveis. |

Presets de 0.2.0 (no código; migração para configuração em 0.4.0):

| Identificador | Módulos | Fundo | Lado | Margem | Correção de erro |
| --- | --- | --- | --- | --- | --- |
| `aculta_padrao` | `#1b1b1b` | `#ffffff` | 320 px | 12 px | Medium |
| `aculta_impressao` | `#000000` | `#ffffff` | 1024 px | 32 px | High |

### `QrCodePreset` (valor)

Criado com validação: identificador de máquina (`^[a-z][a-z0-9_]{2,31}$`), cores `#RRGGBB`, lado entre 128 e 2048 px, margem entre 0 e 200 px e **contraste mínimo de 4,5:1** (razão WCAG entre módulos e fundo). Presets que não atendem não podem ser construídos.

### `aculta_qrcode.generator` — `QrCodeGenerator`

| Método | Saída |
| --- | --- |
| `generate(string $payload, QrCodePreset $preset, QrCodeFormat $format): string` | Conteúdo do SVG (XML) ou bytes do PNG |

O `$payload` precisa ter passado pelo validador. O serviço não revalida o conteúdo.

### `QrCodeFormat` (enum)

| Caso | Tipo MIME |
| --- | --- |
| `Svg` (`svg`) | `image/svg+xml` |
| `Png` (`png`) | `image/png` |

WebP e PDF não fazem parte de 0.2.0. A exportação de arquivo segue as decisões de `ARQUITETURA.md`.

## Exemplo

```php
$payload = $validator->validateUrl('https://aculta.org/apoie');
$preset = $presets->get('aculta_padrao');
$svg = $generator->generate($payload, $preset, QrCodeFormat::Svg);
```

## Código QR dinâmico (0.3.0)

### Entidade `aculta_qr_code`

| Campo | Tipo | Regra |
| --- | --- | --- |
| `title` | string (255) | Obrigatório; nome administrativo, não aparece no QR. |
| `code` | string (12) | Gerado pelo manager; `^[a-z0-9]{12}$`; único; não muda depois de criado. |
| `target_url` | uri | Obrigatório; `http` ou `https`, sem credenciais, até 2048 caracteres (constraint `AcultaQrCodeTarget`). |
| `preset` | string (32) | Padrão `aculta_padrao`. |
| `valid_from`, `valid_until` | timestamp | Opcionais; fim deve ser posterior ao início. |
| `uid`, `status`, `created`, `changed` | — | Dono, publicação e datas. Revisionável, com registro administrativo. |

A entidade não guarda imagem. Acesso: permissão `administer aculta qr codes` (sem acesso por papel).

### `aculta_qrcode.manager` — `QrCodeManager`

| Método | Efeito |
| --- | --- |
| `create(title, targetUrl, ?presetId, ?validFrom, ?validUntil, ?log): QrCode` | Valida destino e janela, gera código único e publica. Dono: usuário atual. |
| `updateTarget(entity, targetUrl, ?log): QrCode` | Troca o destino, mantém o código e cria revisão. |
| `setPublished(entity, bool, ?log): QrCode` | Ativa ou desativa; desativado responde 404. |
| `findActive(code, now): ?QrCode` | Busca pública: publicado e dentro da validade. Retorna NULL em qualquer outro caso. |

Toda gravação valida antes de salvar; violação vira `InvalidQrCodeException` com o caminho do campo.

### `aculta_qrcode.url_resolver` — `QrCodeUrlResolver`

`publicUrl(QrCode): ?Url` monta `/qr/{codigo}` no purpose `links` pelo `DomainPurposeManager`. A dependência é opcional no container; sem o Portal, retorna NULL.

### Rota pública

| Rota | Caminho | Comportamento |
| --- | --- | --- |
| `aculta_qrcode.redirect` | `/qr/{codigo}` (purpose `links`) | GET/HEAD. 302 ao destino quando ativo; 404 caso contrário. Cache: dependência da entidade, com `max-age` até o fim da validade. Sem registro de leitura. |

## Exportação e painel (0.4.0)

### `aculta_qrcode.exporter` — `QrCodeExporter`

| Método | Saída | Regra |
| --- | --- | --- |
| `svg(QrCode): string` | SVG em memória | Sem arquivo. |
| `raster(QrCode, QrCodeFormat::Png|Webp): string` | Bytes do PNG ou WebP | Derivado em `public://aculta-qrcode/{hash}.{ext}`, registrado pelo `aculta_image.generated_file` como temporário. Sem `aculta_image`, recusa. |
| `pdf(QrCode): string` | Bytes do PDF | Entity Print (`dompdf`), sem recursos remotos, sem PHP embutido, sem log. Sem gravação. |

O QR codifica a URL pública `/qr/{codigo}`, não o destino. Sem URL pública (ambiente sem `aculta_portal`), a exportação falha.

### Rotas administrativas

| Rota | Caminho | Permissão |
| --- | --- | --- |
| `entity.aculta_qr_code.collection` | `/painel-administrativo/aculta/codigos-qr` | `administer aculta qr codes` |
| `entity.aculta_qr_code.add_form` | `…/codigos-qr/adicionar` | `administer aculta qr codes` |
| `entity.aculta_qr_code.canonical` | `…/codigos-qr/{aculta_qr_code}` | `administer aculta qr codes` |
| `entity.aculta_qr_code.edit_form` | `…/codigos-qr/{aculta_qr_code}/editar` | `administer aculta qr codes` |
| `entity.aculta_qr_code.delete_form` | `…/codigos-qr/{aculta_qr_code}/excluir` | `administer aculta qr codes` |
| `aculta_qrcode.download` | `…/codigos-qr/{aculta_qr_code}/baixar/{formato}` (`svg`, `png`, `webp`, `pdf`) | `administer aculta qr codes` |

## Limites da 0.3.0

- Sem painel administrativo (0.4.0): a entidade é gerida pelo manager e pela API de entidade.
- Sem logo, moldura, legenda ou exportação de arquivo (0.4.0).
- Sem estatística de acesso (0.6.0).
- Kernel: 8 testes, 60 asserções. Não há teste de rota HTTP com domínio real: a validação de purpose e Domain fica para o Runtime.
