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
| [`aculta_portal`](web/modules/custom/aculta_portal/README.md) | `0.4.0-dev.238` | Seguir dívidas e roadmap pelo ciclo Dev → RC → Alpha → produção; CHANGELOG com a versão atual no topo para o gate de conta raiz ([ciclo](docs/operations/RELEASE-LIFECYCLE.md), [dívidas](docs/operations/DEBT-REGISTER.md)) | Dev |
| [`aculta_associado_id`](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md) | `0.3.0-dev.1` | Validar migração em cópia isolada e fechar escopo/minimização ([decisão](docs/modules/ACULTA-ASSOCIADO-ID.md)) | Dev |
| [`aculta_deployer`](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md) | módulo `0.1.25`; CLI `0.1.27` | Corrigir pendências incluindo `session_cookie`; toda preparação/gates prontos antes de RC; deploy Alpha e produção depois ([plano](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md)) | Dev |
| [`aculta_donation`](web/modules/custom/aculta_portal/modules/aculta_donation/README.md) | `0.1.0-dev.21` | Completar fluxo de sandbox e fechar decisão sobre recorrência ([README](web/modules/custom/aculta_portal/modules/aculta_donation/README.md)) | Dev |
| [`aculta_forum`](web/modules/custom/aculta_portal/modules/aculta_forum/README.md) | `0.1.0-dev.126` | Homologar workflow, anexos via `aculta_image`, avisos e concorrência HTTP; completar tudo antes de RC e Runtime em Alpha ([roadmap](web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md); caminhos administrativos em `/painel-administrativo/aculta`) | Dev |
| [`aculta_forum_lms`](web/modules/custom/aculta_portal/modules/aculta_forum_lms/README.md) | `0.1.0-dev.22` | Implementar busca/notificações privadas e fechar integração de atividades ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_forum_lms/CHANGELOG.md)) | Dev |
| [`aculta_gallery`](web/modules/custom/aculta_portal/modules/aculta_gallery/README.md) | `0.1.0-preRC.4` (marcador histórico) | Validar diálogo e galerias no navegador e em aparelho real antes de RC; validar Runtime em Alpha após deploy ([arquitetura](web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md)) | Dev |
| [`aculta_game`](web/modules/custom/aculta_portal/modules/aculta_game/README.md) | `0.1.0-dev.14` | Concluir G2/G3/G7 e homologar integrações de participação antes de RC ([roadmap](web/modules/custom/aculta_portal/modules/aculta_game/docs/ROADMAP.md)) | Dev |
| [`aculta_id_cpf`](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md) | `0.2.0-dev.2` | Completar Kernel e validar migração/unicidade em cópia isolada ([README](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md)) | Dev |
| [`aculta_links`](web/modules/custom/aculta_portal/modules/aculta_links/README.md) | `0.4.0-dev.24` | Formulários, composição mobile e dashboard confirmados em 390 px; UX, licenças, a11y, integrações e hardening seguem antes de RC ([roadmap](web/modules/custom/aculta_portal/modules/aculta_links/docs/ROADMAP.md)) | Dev |
| [`aculta_image`](web/modules/custom/aculta_portal/modules/aculta_image/README.md) | `0.1.0-dev.28` | Confirmar binários necessários para produção e avançar integração Growroom; concluir requisitos de RC antes do deploy ([plano](web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md)) | Dev |
| [`aculta_lms`](web/modules/custom/aculta_portal/modules/aculta_lms/README.md) | `0.1.0-dev.15` | F11/F12 implementadas; F13 inclui matrícula/certificado, conclusão, avaliações e respostas pendentes; faltam eventos, integração Runtime e revisão visual antes de RC ([roadmap](docs/modules/ACULTA-LMS-ROADMAP.md)) | Dev |
| [`aculta_lms_h5p`](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md) | versão não declarada | Fechar regressão multidomínio e ciclo do editor/player no Runtime ([README](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md)) | Dev |
| [`aculta_lms_messenger`](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md) | `0.1.0-dev.4` | F13: avisos de matrícula, certificado, conclusão, avaliação e resposta pendente no `aculta_messenger`; integrar eventos restantes, validar digest e revisão visual/Runtime ([README](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md)) | Dev |
| [`aculta_magazine`](web/modules/custom/aculta_portal/modules/aculta_magazine/README.md) | `0.1.0-dev.16` | Homologar comentários e moderação no contexto das publicações ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_magazine/CHANGELOG.md)) | Dev |
| [`aculta_mailer`](web/modules/custom/aculta_portal/modules/aculta_mailer/README.md) | `0.1.0-dev.56` | Dev: confirmador de e-mail incorporado (aculta_mail_confirmer); validar o fluxo no navegador antes de RC1; F12 e produção fora deste candidato ([roadmap](web/modules/custom/aculta_portal/modules/aculta_mailer/docs/ROADMAP.md)) | Dev |
| [`aculta_mail_confirmer`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/README.md) | `0.1.0-dev.2` | Validar confirmação, troca de e-mail e login bloqueado no navegador; depois revisão visual antes de RC | Dev |
| [`aculta_mail_confirmer_user`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/modules/aculta_mail_confirmer_user/README.md) | `0.1.0-dev.2` | Validar troca de e-mail e bloqueio de login no navegador antes de RC | Dev |
| [`aculta_messenger`](web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) | `0.1.1-dev.61` | Concluir eventos, privacidade, testes e visual aplicável antes de RC; validar Runtime em Alpha ([roadmap](web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md)) | Dev |
| [`aculta_pagamentos_mercadopago`](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md) | `0.1.0-dev.7` | Concluir homologação sandbox e gates/visual antes de RC; validar integração Runtime em Alpha ([README](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md)) | Dev |
| [`aculta_patcher`](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md) | `0.1.0-dev.2` | Validar ciclo completo e idempotência da aplicação de patches ([README](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md)) | Dev |
| [`aculta_pdf_viewer`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) | `0.1.0-dev.38` | PV7: revisar Kernel e leitura acessível antes de RC; controles do PDF.js revistos visualmente no X11 ([roadmap](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md)) | Dev |
| [`aculta_pdf_viewer_group`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md) | `0.1.0-dev.4` | Matriz de acesso Runtime homologada; manter documentos privados e concluir os gates PV7 compartilhados antes de RC ([README](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md)) | Dev |
| [`aculta_portal_cep`](web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md) | `0.3.0-dev.10` | Homologar fontes/ordenação e fallback de CEP no Runtime ([contrato](docs/portal/ACCOUNT-ADDRESS.md)) | Dev |
| [`aculta_portal_sitemap`](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md) | `0.1.1` | Fechar decisões de indexação e validação externa; concluir requisitos antes de RC, depois deploy Alpha e produção ([README](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)) | Dev |
| [`aculta_qrcode`](web/modules/custom/aculta_portal/modules/aculta_qrcode/README.md) | `0.4.0-dev.2` | Personalização visual registrada (cores validadas, legenda e moldura em PDF); logo por arquivo pendente de modelagem; validar no ambiente de testes antes de RC ([roadmap](web/modules/custom/aculta_portal/modules/aculta_qrcode/docs/ROADMAP.md)) | Dev |
| [`aculta_statistics`](web/modules/custom/aculta_portal/modules/aculta_statistics/README.md) | `0.2.0-dev.2` | Núcleo 0.2.0 ativo no Runtime de teste (schema instalado); banner de consentimento implementado no Portal e no tema, desligado por padrão; ligar e validar visualmente antes de fechar a 0.2.0 e seguir para a 0.3.0 (Commerce) ([roadmap](web/modules/custom/aculta_portal/modules/aculta_statistics/docs/ROADMAP.md)) | Dev |
| [`aculta_user`](web/modules/custom/aculta_portal/modules/aculta_user/README.md) | `0.4.0-dev.49` | Completar variantes, testes/visual de privacidade e cache antes de RC; validar Runtime em Alpha ([variantes](web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-VARIANTS.md)) | Dev |
| [`aculta420`](web/themes/custom/aculta420/README.md) | `0.6.0-dev.132` | Validar banner de consentimento (`consent-banner`, ligado pelo Portal) e componentes QR (`qr-preview`, `qr-download`) e micropáginas ACULTA Links em responsividade, claro/escuro, teclado e leitor de tela antes de RC ([roadmap](web/themes/custom/aculta420/docs/roadmap.md)) | Dev |
<!-- PROJECT-STATUS-INDEX:END -->

Estados vigentes: **Dev** indica implementação/revisão em andamento; **RC** indica
código concluído, gates/testes e revisão visual pré-deploy aprovados; **Alpha**
indica deploy no servidor de desenvolvimento e validação de ambiente em andamento
ou registrada. Alpha é status e ambiente. `preRC` foi aposentado. Fluxo e critérios:
[ciclo de releases](docs/operations/RELEASE-LIFECYCLE.md). O sufixo no manifesto,
sozinho, não determina o estado.

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
