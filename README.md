# ACULTA — plataforma institucional

Site institucional da ACULTA — Associação Cultural Antiproibicionista, construído
em Drupal 11. O projeto integra capacidades do Core e de módulos contrib; não é
uma loja de cannabis. O Portal concentra integrações funcionais e o tema
ACULTA420 apresenta a experiência.

## Comece por aqui

- [Documentação do projeto](docs/README.md) — arquitetura, políticas e operação.
- [ACULTA Portal](web/modules/custom/aculta_portal/README.md) — módulo principal
  e índice das extensões próprias.
- [ACULTA420](web/themes/custom/aculta420/README.md) — tema, componentes e índice
  do design system.
- [Módulos por domínio](docs/modules/README.md) — integrações Core/contrib e
  documentação transversal.
- [Portal: normas e contratos](docs/portal/README.md) — políticas funcionais e
  fronteiras entre Core, Portal e tema.
- [Operação](docs/operations/README.md) — testes, releases, segurança e deploy.

## Componentes próprios

### Módulo principal

- [aculta_portal](web/modules/custom/aculta_portal/README.md) — integração de
  conta, Commerce, LMS, Domains, conteúdo e serviços institucionais.

### Submódulos do Portal

Cada README abaixo é a entrada local para escopo, contratos e documentação do
componente. A lista de capacidades habilitadas em cada ambiente é definida pelo
código e pela configuração Drupal, não por este índice.

- [aculta_associado_id](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md)
- [aculta_deployer](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md)
- [aculta_donation](web/modules/custom/aculta_portal/modules/aculta_donation/README.md)
- [aculta_forum](web/modules/custom/aculta_portal/modules/aculta_forum/README.md)
- [aculta_game](web/modules/custom/aculta_portal/modules/aculta_game/README.md)
- [aculta_gallery](web/modules/custom/aculta_portal/modules/aculta_gallery/README.md)
- [aculta_id_cpf](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md)
- [aculta_image](web/modules/custom/aculta_portal/modules/aculta_image/README.md)
- [aculta_lms](web/modules/custom/aculta_portal/modules/aculta_lms/README.md)
- [aculta_lms_h5p](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md)
- [aculta_magazine](web/modules/custom/aculta_portal/modules/aculta_magazine/README.md)
- [aculta_messenger](web/modules/custom/aculta_portal/modules/aculta_messenger/README.md)
- [aculta_patcher](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md)
- [aculta_pdf_viewer](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md)
- [aculta_pdf_viewer_group](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md)
- [aculta_portal_sitemap](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)
- [aculta_user](web/modules/custom/aculta_portal/modules/aculta_user/README.md)

### Tema e identidade visual

- [Tema ACULTA420](web/themes/custom/aculta420/README.md)
- [Documentação e catálogo do tema](web/themes/custom/aculta420/docs/README.md)
- [Componentes reutilizáveis](web/themes/custom/aculta420/components/README.md)
- [Documentação dos assets de marca](web/themes/custom/aculta420/assets/branding/)

## Arquitetura e ambientes

```text
Drupal Core + contrib → aculta_portal → contrato de apresentação → ACULTA420
```

Core e contrib são fontes de verdade para entidades e capacidades existentes.
O Portal integra dados, acesso e cache; o tema implementa a apresentação.

- Homelab/testes: Debian, Apache, PHP-FPM e MariaDB, em hosts `*.aculta.toca.net.br`.
- Produção: Hostinger Cloud Startup, LiteSpeed, PHP e MariaDB, em `*.aculta.org`.
- O checkout local não é, por si só, uma validação do Runtime de testes.

Detalhes e regras operacionais: [ambientes](docs/architecture/environments.md),
[multidomínio](docs/architecture/multidomain.md) e
[runbook de deploy](docs/operations/DEPLOYMENT.md).

## Como encontrar o estado vigente

- Regras permanentes: `AGENTS.md` e os documentos normativos indexados em
  [docs/README.md](docs/README.md).
- Estado de implementação e releases: CHANGELOG do componente e manifest Drupal.
- Pendências transversais: [registro de dívidas](docs/operations/DEBT-REGISTER.md).
- Planejamento: roadmap do Portal ou do tema, conforme o componente.
- Histórico de revisão: Git e pull requests; snapshots antigos ficam identificados
  como históricos e não substituem as fontes acima.

Não mantenha cópias de versões, resultados de testes ou status de PR neste índice.
