# API de QR Codes — versão 0.2.0

Contrato dos serviços disponíveis em 0.2.0-dev.1. Registrados em `aculta_qrcode.services.yml`, com injeção de dependência. Nenhum serviço grava arquivo, cria entidade ou consulta serviço externo.

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

## Limites da 0.2.0

- Sem rota pública, sem entidade, sem redirecionamento dinâmico.
- Sem logo, sem moldura, sem legenda: essas opções vêm nas fases 0.4.0 e seguintes.
- Testes Unit apenas; Kernel começa na 0.3.0, quando houver entidade.
