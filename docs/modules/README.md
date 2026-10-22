# Módulos e integrações Drupal

- [ACULTA Activity — roadmap](ACULTA-ACTIVITY-ROADMAP.md)

Este índice leva à documentação transversal dos módulos Core/contrib e às
entradas dos componentes próprios. O inventário instalado é definido por
`composer.json`, `composer.lock` e pela configuração Drupal (`core.extension`);
esta página não replica listas de versões ou de módulos habilitados.

## Documentação transversal

- [Autenticação e identidade](AUTHENTICATION.md)
- [Conta, AJAX e Views](ACCOUNT-UI.md)
- [Commerce, apoio e pagamentos](COMMERCE.md)
- [Domains, URLs e SEO](DOMAIN-SEO.md)
- [Editorial e mídia](EDITORIAL-MEDIA.md)
- [Plataforma de formulários Webform](WEBFORM-PLATFORM.md)
- [LMS e Group](LMS-GROUP.md)
- [Compatibilidade LMS/PDF](LMS-COMPATIBILITY.md)
- [Operações de módulos e infraestrutura](OPERATIONS.md)
- [Roadmap do ACULTA LMS](ACULTA-LMS-ROADMAP.md)
- [Auditoria H5P/LMS](LMS-H5P-AUDIT.md)
- [Wiki420 como submódulo (planejamento)](WIKI-SUBMODULO.md)

## Componentes próprios

- Módulo principal: [aculta_portal](../../web/modules/custom/aculta_portal/README.md).
- Tema: [ACULTA420](../../web/themes/custom/aculta420/README.md).
- Submódulos do Portal: consulte o [índice central](../../README.md#submódulos-do-portal),
  que aponta para o README ou documento de entrada de cada extensão.

## Regras para mudanças

- Core e contrib continuam donos das capacidades que já fornecem; o Portal integra
  sem criar armazenamento paralelo.
- Antes de adicionar dependência ou código custom, consulte os documentos de
  domínio acima e as normas de [Drupal 11+](../portal/DRUPAL-11-STANDARDS.md).
- Mudanças de módulo que afetem a apresentação também atualizam o
  [mapa módulo–tema](../portal/MAPA-MODULO-TEMA.md) e seguem a
  [fronteira Portal–tema](../portal/FRONTEIRA-MODULO-TEMA.md).

## Kernels e ferramentas transversais

Consultar [hierarquia de kernels](../architecture/aculta-kernels.md), [ADR](../decisions/ADR-011-aculta-kernels-studio.md) e [roadmap Studio/Revista](../portal/STUDIO-MAGAZINE-ROADMAP.md). Fórum e Galeria têm estruturas compartilhadas próprias. User, Image, Document Viewer, Messenger, confirmação de e-mail e Portal compõem serviços comuns. Studio é a interface consumidora por abas; essa nomenclatura não altera as fontes de verdade Core/contrib.
