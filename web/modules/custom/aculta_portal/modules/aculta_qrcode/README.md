# ACULTA QR Code

Serviço central de QR Codes do ACULTA Portal, planejado para uso por todos os módulos da plataforma. Módulo do `aculta_portal`.

## Estado

- Versão: `0.4.0-dev.2`. Estado: **Dev**.
- Entregue na 0.2.0: geração estática em SVG e PNG em memória, presets com contraste validado (mínimo 4,5:1) e validação de URL e texto.
- Entregue na 0.3.0: entidade `aculta_qr_code` com validade e histórico revisionável, manager de gravação, redirecionamento público `/qr/{codigo}` e permissão própria. Testes Unit e Kernel em `tests/`.
- Entregue na 0.4.0 (backend): painel administrativo em `/painel-administrativo/aculta/codigos-qr` e exportação SVG, PNG, WebP e PDF.
- Entregue em 0.4.0-dev.2: personalização visual (cores validadas, legenda e moldura em PDF).
- Ainda não existe: logo por arquivo, prévia no painel, estatística de acesso, integração com outros módulos.

## Documentação

- [API de QR Codes (0.2.0 e 0.3.0)](docs/API.md)
- [Roadmap de 0.1.0 a 1.0.0](docs/ROADMAP.md)
- [Arquitetura e decisões do responsável](docs/ARQUITETURA.md)
- [Auditoria do código do ACULTA e dos projetos de referência](docs/AUDITORIA.md)
- [Matriz de portabilidade por recurso](docs/PORTABILITY-MATRIX.md)
- [Estado atual de QR no ACULTA](docs/CURRENT-STATE.md)
- [Licenças e atribuições](docs/LICENSING.md)
- [Histórico de versões](CHANGELOG.md)

## Limites

- Os QR Codes já existentes (`aculta_links`) continuam com a implementação atual até a 0.7.0.
- Nenhum serviço externo recebe conteúdo de QR.
- Estatísticas de QR estático não existem por design.
