# Contratos de integração do aculta_qrcode

Documento da auditoria do agente executor 2. Lista o que a missão pede e **não está implementado**, e o contrato
que o módulo espera de cada dependência. Nada aqui cria serviço duplicado.

## Já implementado e testado

| Item | Onde | Prova |
|---|---|---|
| Geração SVG, PNG, WebP e PDF | `Service/QrCodeGenerator`, `QrCodeExporter`, `Value/QrCodeFormat` | Unit `QrCodeGeneratorTest` |
| Presets e nível de correção | `Service/QrCodePresetRegistry`, `Value/QrCodePreset` | Unit `QrCodePresetTest` |
| Validação de URL e texto | `Service/QrCodePayloadValidator` | Unit `QrCodePayloadValidatorTest` (só http/https; bloqueia `javascript:`, `data:`, `file:` e credenciais na URL) |
| Entidade estática e dinâmica, redirecionamento | `Entity/QrCode`, `Controller/QrCodeRedirectController` | Kernel `QrCodeEntityKernelTest` (exige banco) |

## Segurança verificada na auditoria

- **SSRF:** o módulo não busca URL remota. Não existe `http_client` nem `file_get_contents` sobre URL. A única leitura de
  arquivo é o derivado local em `QrCodeExporter`, com nome derivado de hash do payload e do preset. Nenhum caminho
  vem do usuário.
- **Redirecionamento aberto:** o destino passa pelo validador de payload antes de ser gravado.

## Pendente: depende de outro módulo (contrato, não implementação local)

| Item da missão | Dono | Contrato esperado pelo aculta_qrcode |
|---|---|---|
| Logo por imagem local autorizada | `aculta_image` | Receber um arquivo já validado e redimensionado pelo `aculta_image`. O QR Code não processa imagem. Hoje `@?aculta_image.generated_file` só gerencia o derivado. Sem logo, nada muda. |
| Métricas de leitura do redirecionamento | `aculta_statistics` | Chamar `aculta_statistics.recorder->record(metric, dimension, amount)` no redirecionamento. Métrica sugerida: `qrcode_redirect`, dimensão = id do QR Code. **Não criar contador próprio.** A métrica precisa entrar no catálogo do `aculta_statistics` (integração central). |
| Prévia no formulário | `aculta_qrcode` | Pode ser feita neste módulo com rota dedicada e o gerador atual. Não implementada nesta execução. |
| Geração em lote | `aculta_qrcode` | Pode ser feita com o `QrCodeExporter` atual. Não implementada nesta execução. |
| Formatter de campo | `aculta_qrcode` | Pendente. Depende de decisão de tipo de campo. |
| Componentes visuais | Tema (`aculta420`) | Contrato SDC/UI Suite a documentar para o agente principal. Não altera o tema nesta missão. |

## Integração central (agente principal)

1. Adicionar `qrcode_redirect` ao catálogo de métricas do `aculta_statistics`.
2. Confirmar que a dependência `@?aculta_image.generated_file` continua opcional na instalação de produção.
