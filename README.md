# Associação Cultural Antiproibicionista — plataforma Drupal

Plataforma Drupal 11 multidomínio da Associação Cultural Antiproibicionista.

## Contextos

| Purpose | Produção | Responsabilidade |
| --- | --- | --- |
| `main` | `aculta.org` | institucional |
| `account` | `conta.aculta.org` | identidade e conta |
| `support` | `apoio.aculta.org` | apoio |
| `magazine` | `coletivo420.aculta.org` | editorial |
| `wiki` | `wiki420.aculta.org` | Wiki420 |
| `shop` | `loja.aculta.org` | comércio |
| `courses` | `cursos.aculta.org` | aprendizagem |
| `forum` *(planejado)* | `forum.aculta.org` | comunidade |

## Componentes próprios

- [aculta_portal](web/modules/custom/aculta_portal/README.md) — integração e
  orquestração.
- [ACULTA420](web/themes/custom/aculta420/README.md) — tema público e Bootstrap
  Component Design System.
- [aculta_portal_sitemap](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)
  — sitemap multidomínio por purpose, índice central em `/sitemap.xml` e `robots.txt`
  com a diretiva `Sitemap:`. Versão 0.1.0 (fases A a J), habilitado no runtime de teste.
- [aculta_deployer](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md)
  — submódulo do `aculta_portal` que troca hosts de teste por produção no build de
  deploy, verifica `robots`, sitemaps e caminhos privados por ambiente, e mantém o
  registro de correções de deploy. Versão 0.1.5, não habilitado no Drupal; CLI
  standalone em `bin/aculta-deployer`.

## Arquitetura

```text
Core + contrib -> aculta_portal -> ACULTA420 -> Bootstrap5
```

User/Profile/Commerce/LMS/Group/Node/Views/Domain continuam fontes de verdade.
O Portal integra. ACULTA420 apresenta.

## Ambientes

- Homelab (servidor de testes `*.aculta.toca.net.br`): Debian + Apache + PHP-FPM + MariaDB. É noindex.
- Produção (`*.aculta.org`): Hostinger + Apache + PHP + MariaDB. Recebe deploy só no RC de todos os módulos, temas e subtemas.

Apache é o baseline de servidor web. Configuração específica de ambiente não
pertence ao tema.

## Documentação

Comece em [docs/README.md](docs/README.md).

Referências transversais:

- [Camadas anti-regressão](docs/ANTI-REGRESSION.md)
- [Política de documentação](docs/DOCUMENTATION.md)
- [Operação, testes e releases](docs/operations/README.md)
- [Deployment](docs/operations/DEPLOYMENT.md)
- [aculta_deployer — uso](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/USO.md),
  [guardrails](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/GUARDRAILS.md),
  [registro](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/REGISTRO.md),
  [arquitetura](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/ARQUITETURA.md)
  e [planejamento](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md)

- Portal: [docs/portal](docs/portal/README.md)
- Módulos: [docs/modules](docs/modules/README.md)
- Tema/design system: [ACULTA420 docs](web/themes/custom/aculta420/docs/README.md)
- Prontidão para RC e bateria de testes: [RC-READINESS](docs/operations/RC-READINESS.md)

Segredos e settings locais nunca pertencem ao Git.
