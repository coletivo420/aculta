# Estado atual de QR Codes e recursos relacionados no ACULTA

Auditoria de 2026-10-20 (0.1.0-dev.1), por busca direcionada (`rg` por `qrcode|qr_code|qr-code|barcode|endroid|chillerlan|redirect` em `web/modules/custom`, `web/themes/custom` e `config`) e leitura dos arquivos encontrados. Classificações: FUNCIONAL E REUTILIZÁVEL; PARCIALMENTE IMPLEMENTADO; DUPLICADO; OBSOLETO; NECESSITA INTEGRAÇÃO; AUSENTE.

## QR Codes existentes

| Recurso | Localização | Classificação | Observação |
| --- | --- | --- | --- |
| Gerador SVG | `aculta_links/src/Service/QrCodeSvgGenerator.php` | FUNCIONAL E REUTILIZÁVEL | Endroid 6.1.3, `SvgWriter`, 320 px, margem 12, correção `Medium`. Teste Unit em `tests/src/Unit/QrCodeSvgGeneratorTest.php`. |
| QR da micropágina | `aculta_links/src/Controller/LinkPageQrCodeController.php`; rota `/pagina/{slug}/codigo-qr.svg` | PARCIALMENTE IMPLEMENTADO | Só SVG. Não registra leitura. Aceita apenas página publicada, com `DomainPurposeManager`. |
| QR do redirecionamento curto | `aculta_links/src/Controller/LinkShortCodeQrCodeController.php`; rota `/redirecionar/{codigo}/codigo-qr.svg` | PARCIALMENTE IMPLEMENTADO | Mesmo gerador. Depende de destino publicado. |
| Ação de QR no painel | `aculta_links/src/Controller/LinksDashboardController.php` (operações `qr_code` e `short_qr_code`) | FUNCIONAL E REUTILIZÁVEL | Operação de dashboard, sem duplicação do gerador. |
| Dependências de QR no lock | `composer.lock`: `endroid/qr-code` 6.1.3, `bacon/bacon-qr-code` 3.1.1, `dasprid/enum` 1.0.7 | FUNCIONAL E REUTILIZÁVEL | Única biblioteca de QR no projeto. |
| QR de verificação de certificado (LMS F11) | Contrato em `docs/portal/LMS-INTEGRATION-CONTRACTS.md` (alteração em andamento em outra frente) | AUSENTE | Declarado como dependente de `aculta_qrcode`. Não há código. |
| QR em ACULTA420 (componentes `qr-*`) | `web/themes/custom/aculta420/components/` | AUSENTE | Nenhum componente de QR. |
| Compartilhamento (botão ou componente) com QR | Busca por “share”/“compartilh” em `web/modules/custom` | AUSENTE | Nenhum recurso de compartilhamento com QR. |
| Gerador de QR de entidade (node, produto, evento) | — | AUSENTE | Nenhum. |
| Logos, estilos e presets de QR | — | AUSENTE | Nenhum. |
| Exportação PNG, WebP ou PDF de QR | — | AUSENTE | Writers do Endroid existem, mas não são usados. |

## Recursos que o serviço central reutilizará

| Recurso | Localização | Classificação | Observação |
| --- | --- | --- | --- |
| Resolução de domínio e URL canônica | `aculta_portal/src/Domain/DomainPurposeManager.php` | FUNCIONAL E REUTILIZÁVEL | Usado por `aculta_links` e outros módulos. |
| Redirecionamento seguro | `TrustedRedirectResponse` via `DomainPurposeManager` (usado em `aculta_links`) | FUNCIONAL E REUTILIZÁVEL | Padrão do projeto para redirecionamento entre hosts. |
| Código opaco de destino | `aculta_links` (12 caracteres, globalmente único) | FUNCIONAL E REUTILIZÁVEL | Mesmo padrão para o QR dinâmico. |
| Endereços por ambiente | `aculta_deployer` (`environment_bound`) | FUNCIONAL E REUTILIZÁVEL | Obrigatório para qualquer URL absoluta (`web/modules/custom/aculta_portal/modules/aculta_deployer/docs/ENDERECOS-AMBIENTE.md`). |
| Armazenamento de imagens e logos | `aculta_image` | NECESSITA INTEGRAÇÃO | Único caminho permitido para arquivos de imagem. Exportações e logos ainda não passam por ele. |
| Tokens | `token` (contrib instalado) | FUNCIONAL E REUTILIZÁVEL | Para QR de entidade. |
| Views | Core `views` | FUNCIONAL E REUTILIZÁVEL | Listagem administrativa futura. |
| Batch API | Core `Drupal\Core\Batch` | FUNCIONAL E REUTILIZÁVEL | Geração em lote futura. |
| PDF | `entity_print` (contrib, no `composer.json` do trabalho em andamento) | NECESSITA INTEGRAÇÃO | Decisão 1 pendente; pode duplicar com `setasign/fpdf`. |
| Cache de redirecionamento | Não há política específica para QR | AUSENTE | Será definida em `ARQUITETURA.md` (seção Cache). |

## Duplicidades e pontos de atenção

- O QR SVG já existe em dois controladores do `aculta_links`, mas ambos usam o mesmo serviço. Não há duplicidade de geração; a migração ao serviço central fica para 0.7.0.
- Não há outro motor de QR no projeto. Nada a remover nesta fase.
- Nenhum recurso existente é OBSOLETO.
