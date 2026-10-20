# ACULTA — plataforma institucional

Site institucional da ACULTA — Associação Cultural Antiproibicionista, construído
em Drupal 11. O projeto integra capacidades do Core e de módulos contrib; não é
uma loja de cannabis. O Portal concentra integrações funcionais e o tema
ACULTA420 apresenta a experiência.

## Comece por aqui

- [Documentação do projeto](docs/README.md) — arquitetura, políticas e operação.
- [Mapa de caminhos e redirecionamentos](docs/architecture/ROUTES-SITEMAP.md) — caminhos por domínio, rotas administrativas, estados e redirects.
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

### Versões e próximas fases — índice permanente

Esta tabela fixa a visão geral do módulo, dos submódulos e do tema. Agentes não
devem removê-la nem retirar linhas: atualize a versão a partir do manifesto e a
próxima fase a partir do roadmap/changelog canônico no mesmo commit da mudança
correspondente. O gate `php scripts/validate-project-status-index.php` confere
presença, cobertura, links, versões e a alteração da linha correspondente no
mesmo commit; `AGENTS.md` torna a atualização obrigatória para módulos,
submódulos e tema.

<!-- PROJECT-STATUS-INDEX:BEGIN -->
| Componente | Última versão declarada | Próxima fase registrada | Estado |
| --- | --- | --- | --- |
| [`aculta_portal`](web/modules/custom/aculta_portal/README.md) | `0.4.0-dev.227` | Seguir as dívidas abertas no registro e o roadmap vigente ([dívidas](docs/operations/DEBT-REGISTER.md)) | Dev |
| [`aculta_associado_id`](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md) | `0.3.0-dev.1` | Validar migração em cópia isolada e fechar escopo/minimização ([decisão](docs/modules/ACULTA-ASSOCIADO-ID.md)) | Dev |
| [`aculta_deployer`](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md) | módulo `0.1.25`; CLI `0.1.27` | Corrigir pendências do plano e preparar provisionamento de produção para RC ([plano](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md)) | Dev |
| [`aculta_donation`](web/modules/custom/aculta_portal/modules/aculta_donation/README.md) | `0.1.0-dev.20` | Completar fluxo de sandbox e fechar decisão sobre recorrência ([README](web/modules/custom/aculta_portal/modules/aculta_donation/README.md)) | Dev |
| [`aculta_forum`](web/modules/custom/aculta_portal/modules/aculta_forum/README.md) | `0.1.0-dev.126` | Homologar aprovação, anexos e avisos de comentários movidos antes de fechar o mover ([roadmap](web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md)) | Dev |
| [`aculta_forum_lms`](web/modules/custom/aculta_portal/modules/aculta_forum_lms/README.md) | `0.1.0-dev.22` | Implementar busca/notificações privadas e fechar integração de atividades ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_forum_lms/CHANGELOG.md)) | Dev |
| [`aculta_gallery`](web/modules/custom/aculta_portal/modules/aculta_gallery/README.md) | `0.1.0-preRC.4` | Validar diálogo e galerias no navegador e em aparelho real; somente validação visual permanece ([arquitetura](web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md)) | preRC |
| [`aculta_game`](web/modules/custom/aculta_portal/modules/aculta_game/README.md) | `0.1.0-dev.14` | Concluir G2/G3/G7 e homologar integrações de participação ([roadmap](web/modules/custom/aculta_portal/modules/aculta_game/docs/ROADMAP.md)) | Dev |
| [`aculta_id_cpf`](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md) | `0.2.0-dev.2` | Completar Kernel e validar migração/unicidade em cópia isolada ([README](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md)) | Dev |
| [`aculta_image`](web/modules/custom/aculta_portal/modules/aculta_image/README.md) | `0.1.0-dev.27` | Confirmar binários na Hostinger e avançar importação de imagens após desbloqueio do Fórum ([plano](web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md)) | Dev |
| [`aculta_lms`](web/modules/custom/aculta_portal/modules/aculta_lms/README.md) | `0.1.0-dev.8` | Homologar permissões/configuração do Fórum LMS no Runtime ([roadmap](docs/modules/ACULTA-LMS-ROADMAP.md)) | Dev |
| [`aculta_lms_h5p`](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md) | versão não declarada | Fechar regressão multidomínio e ciclo do editor/player no Runtime ([README](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md)) | Dev |
| [`aculta_magazine`](web/modules/custom/aculta_portal/modules/aculta_magazine/README.md) | `0.1.0-dev.16` | Homologar comentários e moderação no contexto das publicações ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_magazine/CHANGELOG.md)) | Dev |
| [`aculta_mailer`](web/modules/custom/aculta_portal/modules/aculta_mailer/README.md) | `0.1.0-dev.44` | Avançar fases abertas F7/F9–F12 e resolver entregas/transporte pendentes ([roadmap](web/modules/custom/aculta_portal/modules/aculta_mailer/docs/ROADMAP.md)) | Dev |
| [`aculta_messenger`](web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) | `0.1.1-dev.57` | Continuar eventos, privacidade e homologação da linha 0.1.1 ([roadmap](web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md)) | Dev |
| [`aculta_pagamentos_mercadopago`](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md) | `0.1.0-dev.7` | Homologar sandbox e remover apresentação residual do módulo ([README](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md)) | Dev |
| [`aculta_patcher`](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md) | `0.1.0-dev.2` | Validar ciclo completo e idempotência da aplicação de patches ([README](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md)) | Dev |
| [`aculta_pdf_viewer`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) | `0.1.0-dev.20` | Fechar PV7: CSP estrita, acessibilidade ampliada, navegadores e paridade LiteSpeed ([roadmap](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md)) | Dev |
| [`aculta_pdf_viewer_group`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md) | `0.1.0-dev.4` | Concluir validação contextual no Runtime e regras de rollback ([README](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md)) | Dev |
| [`aculta_portal_cep`](web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md) | `0.3.0-dev.8` | Homologar fontes/ordenação e fallback de CEP no Runtime ([contrato](docs/portal/ACCOUNT-ADDRESS.md)) | Dev |
| [`aculta_portal_sitemap`](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md) | `0.1.1` | Fechar decisões de indexação e validação externa conforme política ([README](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)) | Dev |
| [`aculta_user`](web/modules/custom/aculta_portal/modules/aculta_user/README.md) | `0.4.0-dev.47` | Completar variantes de autoria e validar privacidade/cache com os contextos ([variantes](web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-VARIANTS.md)) | Dev |
| [`aculta420`](web/themes/custom/aculta420/README.md) | `0.6.0-dev.122` | Corrigir gates compartilhados e concluir revisão responsiva/acessível das integrações ([roadmap](web/themes/custom/aculta420/docs/roadmap.md)) | Dev |
<!-- PROJECT-STATUS-INDEX:END -->

Estados: **Dev** indica trabalho funcional, operacional ou revisão ainda pendente;
**preRC** indica que só falta validação visual; **RC** só após os quatro critérios
de `AGENTS.md` (fases, testes/gates, visual e revisão final após merge). Nenhum
status `RC` é inferido apenas por uma versão escrita no manifesto.

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

O índice de versão/fase acima é uma exceção fixa solicitada pelo responsável;
resultados de testes e status de PR continuam apenas nos changelogs e no GitHub.
