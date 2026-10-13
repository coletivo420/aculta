# Associação Cultural Antiproibicionista — plataforma Drupal

Plataforma Drupal 11 multidomínio da Associação Cultural Antiproibicionista.

## Contextos

| Purpose | Produção (`config/sync`) | Responsabilidade |
| --- | --- | --- |
| `main` | `aculta.org` | institucional, painel administrativo, carrinho e pagamento |
| `account` | `conta.aculta.org` | identidade e conta (Minha conta, Segurança, Meus pedidos) |
| `support` | `apoio.aculta.org` | apoio (contribuições) |
| `magazine` | `revista420.aculta.org` *(o AGENTS.md cita `coletivo420`; a confirmar)* | editorial |
| `wiki` | `wiki420.aculta.org` | Wiki420 |
| `shop` | `loja.aculta.org` | comércio (produtos, camisetas) |
| `courses` | `cursos.aculta.org` | aprendizagem (LMS) |
| `forum` | `forum.aculta.org` | comunidade (fórum) |
| `links` | `links.aculta.org` | redes sociais da ACULTA (página `/redes-sociais`) |

Os hosts de teste são `*.aculta.toca.net.br` (Homelab, noindex). Os hosts de produção são os da coluna acima.

## Componentes próprios

- [aculta_portal](web/modules/custom/aculta_portal/README.md) — integração e
  orquestração: conta, endereço único, checkout, pagamento pelo Mercado Pago,
  envio (Commerce Shipping), Social Auth e domínios. Versão 0.3.0-dev.18 na árvore
  de trabalho (ver o CHANGELOG do módulo).
- [ACULTA420](web/themes/custom/aculta420/README.md) — tema público e Bootstrap
  Component Design System. Versão 0.6.0-dev.11.
- [aculta_portal_sitemap](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)
  — sitemap multidomínio por purpose, índice central em `/sitemap.xml` e `robots.txt`
  com a diretiva `Sitemap:`. Versão 0.1.1 (fases A a J), habilitado no runtime de teste.
- [aculta_deployer](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md)
  — submódulo do `aculta_portal` que troca hosts de teste por produção no build de
  deploy, verifica `robots`, sitemaps e caminhos privados por ambiente, e mantém o
  registro de correções de deploy. Versão 0.1.20, não habilitado no Drupal; CLI
  standalone em `bin/aculta-deployer`.
- [aculta_associado_id](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md)
  — orquestração dos dados dos associados; escopo implementado atualmente: serviços protegidos de CPF/CNPJ.
  Versão 0.3.0-dev.1, **RUNTIME STATUS: DEFERRED**. A migração do módulo legado ainda precisa ser validada em cópia isolada.

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
