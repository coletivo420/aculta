# ACULTA QR Code

Serviço central de QR Codes do ACULTA Portal, planejado para uso por todos os módulos da plataforma. Módulo do `aculta_portal`.

## Estado

- Versão: `0.1.0-dev.1`. Estado: **Dev**.
- Esta entrega é documental. Não há código de geração, entidade, rota, permissão, serviço ou dependência Composer.
- O módulo pode ser lido, mas não tem funcionalidade a ser habilitada.

## Documentação

- [Auditoria do código do ACULTA e dos projetos de referência](docs/AUDITORIA.md)
- [Licenças e atribuições](docs/LICENSING.md)
- [Arquitetura proposta e decisões pendentes](docs/ARQUITETURA.md)
- [Matriz de portabilidade por recurso](docs/PORTABILITY-MATRIX.md)
- [Estado atual de QR no ACULTA](docs/CURRENT-STATE.md)
- [Roadmap de 0.1.0 a 1.0.0](docs/ROADMAP.md)
- [Histórico de versões](CHANGELOG.md)

## Escopo

QR estático (conteúdo codificado diretamente), QR dinâmico (URL estável com destino editável), personalização, exportação, estatísticas agregadas, geração em lote e controle de acesso. O escopo completo está no roadmap.

## Limites

- Os QR Codes já existentes (`aculta_links`) continuam com a implementação atual até a 0.7.0.
- Nenhum serviço externo recebe conteúdo de QR.
- Estatísticas de QR estático não existem por design.
