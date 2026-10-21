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
| [`aculta_portal`](web/modules/custom/aculta_portal/README.md) | `0.4.0-dev.239` | Seguir dívidas e roadmap pelo ciclo Dev → RC → Alpha → produção; CHANGELOG com a versão atual no topo para o gate de conta raiz ([ciclo](docs/operations/RELEASE-LIFECYCLE.md), [dívidas](docs/operations/DEBT-REGISTER.md)) | Dev |
| [`aculta_associado_id`](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md) | `0.3.0-dev.1` | Validar migração em cópia isolada e fechar escopo/minimização ([decisão](docs/modules/ACULTA-ASSOCIADO-ID.md)) | Dev |
| [`aculta_deployer`](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md) | módulo `0.1.25`; CLI `0.1.27` | Corrigir pendências incluindo `session_cookie`; toda preparação/gates prontos antes de RC; deploy Alpha e produção depois ([plano](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md)) | Dev |
| [`aculta_donation`](web/modules/custom/aculta_portal/modules/aculta_donation/README.md) | `0.1.0-dev.21` | Completar fluxo de sandbox e fechar decisão sobre recorrência ([README](web/modules/custom/aculta_portal/modules/aculta_donation/README.md)) | Dev |
| [`aculta_forum`](web/modules/custom/aculta_portal/modules/aculta_forum/README.md) | `0.1.0-dev.126` | Homologar workflow, anexos via `aculta_image`, avisos e concorrência HTTP; completar tudo antes de RC e Runtime em Alpha ([roadmap](web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md); caminhos administrativos em `/painel-administrativo/aculta`) | Dev |
| [`aculta_forum_lms`](web/modules/custom/aculta_portal/modules/aculta_forum_lms/README.md) | `0.1.0-dev.23` | Contexto de moderação curso→turma implementado (Unit 18/194; Kernel 1/115, 42 depreciações); Runtime: rebuild de rotas e árvore de cursos PASS; smoke autenticado pendente; implementar busca/notificações privadas e fechar integração de atividades ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_forum_lms/CHANGELOG.md)) | Dev |
| [`aculta_gallery`](web/modules/custom/aculta_portal/modules/aculta_gallery/README.md) | `0.1.0-preRC.4` (marcador histórico) | Validar diálogo e galerias no navegador e em aparelho real antes de RC; validar Runtime em Alpha após deploy ([arquitetura](web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md)) | Dev |
| [`aculta_game`](web/modules/custom/aculta_portal/modules/aculta_game/README.md) | `0.1.0-dev.15` | Concluir G2/G3/G7 e homologar integrações de participação antes de RC ([roadmap](web/modules/custom/aculta_portal/modules/aculta_game/docs/ROADMAP.md)) | Dev |
| [`aculta_id_cpf`](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md) | `0.2.0-dev.2` | Completar Kernel e validar migração/unicidade em cópia isolada ([README](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md)) | Dev |
| [`aculta_image`](web/modules/custom/aculta_portal/modules/aculta_image/README.md) | `0.1.0-dev.28` | Confirmar binários necessários para produção e avançar integração Growroom; concluir requisitos de RC antes do deploy ([plano](web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md)) | Dev |
| [`aculta_links`](web/modules/custom/aculta_portal/modules/aculta_links/README.md) | `0.4.0-dev.30` | Navegação por seção; serviço `aculta_qrcode` confirmado no Runtime, sem página publicada para download HTTP positivo; revisão visual autenticada pendente ([roadmap](web/modules/custom/aculta_portal/modules/aculta_links/docs/ROADMAP.md)) | Dev |
| [`aculta_lms`](web/modules/custom/aculta_portal/modules/aculta_lms/README.md) | `0.1.0-dev.77` | F7: smoke Runtime do pedido gratuito concluído confirmou membership com origem, role global estudante e preservação de matrícula manual; Unit cobre pedido gratuito, pagamento concluído, reembolso parcial/integral, pedido incompleto e remoção manual permitida/bloqueada conforme origem (origem inválida e pagamento malformado falham fechados; EnrollmentFormHooksTest 7/49; CourseOrderEnrollmentManagerTest 9/47; active-order item reconciliation removes stale paid-class access, preserves valid/manual memberships, and transfers to another active purchase); checkout HTTP, pagamento real, reembolso, confirmação visual da interface de remoção e isolamento A/B pendentes; F11: carga horária mínima de uma hora aplicada no Runtime; Kernel valida elegibilidade, snapshot, formato/unicidade e os quatro dados públicos, sem e-mail/motivo de revogação; smoke Runtime temporário confirmou emissão, verificação pública, QR SVG e PDF por HTTP (200), com limpeza integral; Kernel executa os submits de emissão/revogação e confirma opção de conclusão para educador contextual (5/159), mas smoke Runtime autenticado e revisão visual permanecem pendentes; F6/F12 usam take course no curso pai e somente aulas publicadas; Kernel confirma access contextual, progresso e certificados próprios entre duas contas (LearningDashboardKernelTest 1/41); revisão visual/Runtime do estudante pendente; regressão combinada LMS + Messenger: Unit 89/482 e Kernel MariaDB 24/772, PASS; revisão visual/Runtime pendente; F13: avisos escolhidos para mudanças acionáveis persistidas; teste confirma edição editorial sem evento (liberação de conteúdo, feedback/publicação e prazo); edições editoriais sem efeito não notificam; lembrete permanece em 24h até decisão de intervalo; prazo por atividade/turma, com fuso da conta, fallback UTC e apresentação positiva no fuso da estudante cobertos por teste; revisão visual/acessível pendente; F14: implementação Dev concluída — educador controla lista por turma, padrão oculto; nomes apenas de estudantes ativos da mesma turma; formulário e access contextual cobertos; Kernel 1/28 PASS (36 depreciações upstream), rota incluída e configuração de campo aplicada por importação parcial revisada no Runtime; smoke autenticado e revisão visual pendentes; administração agora começa pelo curso e contextualiza aulas, atividades, turmas, prazos e fóruns; F15: vínculo atividade-discussão e smoke Runtime pendentes; hierarquia course-first: Unit 71/394, Kernel 23/764 PASS (93 depreciações), Runtime: rebuild de rotas e árvore de menu course-first PASS; smoke autenticado pendente; F16 inclui lock de reemissão, contexto user.group_permissions no access de certificados, isolamento de entrega, painel de turma testado com grant contextual grade students e negação a estudante, exclusão de conta bloqueada do agregado com invalidação por conta, referências LMS ausentes toleradas em avisos, formulário de prazo, respostas abertas, histórico e certificados (422 no manager; opção omitida no formulário), access fail-closed via AccountProxy, negação por relação ausente invalidada ao adicionar vínculo da turma, smoke parcial no Runtime, cache privado preservado e Kernel LMS/Messenger 24/772; download privado nega após exclusão do CourseStatus enquanto a limpeza está enfileirada (AttemptArchiveLifecycleTest 10/409); certificado revogado retorna 404 sem invocar Dompdf/QR; composer audit sem advisories; PHP 8.5 não validado; matriz de acesso revisada para certificados snapshot/Entity Print/Dompdf/QR, painéis via LMS/Group e as regras de matrícula manual ligada a compras; Unit cobre reembolso integral com outra compra ativa, mantendo membership e transferindo proveniência; Unit LMS/Messenger 89/482, AcultaLmsInstallTest 4/42 confirms Commerce insert/update hooks, Kernel dedicado 24/772, gate Portal 1010 checks e índice 29 componentes PASS ([roadmap](docs/modules/ACULTA-LMS-ROADMAP.md)) | Dev |
| [`aculta_lms_h5p`](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md) | versão não declarada | Fechar regressão multidomínio e ciclo do editor/player no Runtime ([README](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md)) | Dev |
| [`aculta_lms_messenger`](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md) | `0.1.0-dev.15` | F13: Runtime smoke passou; falta revisão visual/acessível com sessão autenticada após aceite do acordo pelo responsável; F16: referências LMS ausentes em respostas e conclusões suprimem avisos sem erro 500; provider real de digest descarta certificado removido e conclusão sem curso; Unit LMS/Messenger 89/482, Kernel 24/772 ([README](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md)) | Dev |
| [`aculta_magazine`](web/modules/custom/aculta_portal/modules/aculta_magazine/README.md) | `0.1.0-dev.16` | Homologar comentários e moderação no contexto das publicações ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_magazine/CHANGELOG.md)) | Dev |
| [`aculta_mail_confirmer`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/README.md) | `0.1.0-dev.2` | Validar confirmação, troca de e-mail e login bloqueado no navegador; depois revisão visual antes de RC | Dev |
| [`aculta_mail_confirmer_user`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/modules/aculta_mail_confirmer_user/README.md) | `0.1.0-dev.2` | Validar troca de e-mail e bloqueio de login no navegador antes de RC | Dev |
| [`aculta_mailer`](web/modules/custom/aculta_portal/modules/aculta_mailer/README.md) | `0.1.0-dev.57` | Dev: confirmador de e-mail incorporado (aculta_mail_confirmer); validar o fluxo no navegador antes de RC1; F12 e produção fora deste candidato ([roadmap](web/modules/custom/aculta_portal/modules/aculta_mailer/docs/ROADMAP.md)) | Dev |
| [`aculta_messenger`](web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) | `0.1.1-dev.63` | Completar eventos, privacidade e revisão visual antes de RC; validar Runtime em Alpha ([roadmap](web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md)) | Dev |
| [`aculta_pagamentos_mercadopago`](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md) | `0.1.0-dev.7` | Concluir homologação sandbox e gates/visual antes de RC; validar integração Runtime em Alpha ([README](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md)) | Dev |
| [`aculta_patcher`](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md) | `0.1.0-dev.2` | Validar ciclo completo e idempotência da aplicação de patches ([README](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md)) | Dev |
| [`aculta_pdf_viewer`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) | `0.1.0-dev.38` | PV7: revisar Kernel e leitura acessível antes de RC; controles do PDF.js revistos visualmente no X11 ([roadmap](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md)) | Dev |
| [`aculta_pdf_viewer_group`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md) | `0.1.0-dev.4` | Matriz de acesso Runtime homologada; manter documentos privados e concluir os gates PV7 compartilhados antes de RC ([README](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md)) | Dev |
| [`aculta_portal_cep`](web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md) | `0.3.0-dev.10` | Homologar fontes/ordenação e fallback de CEP no Runtime ([contrato](docs/portal/ACCOUNT-ADDRESS.md)) | Dev |
| [`aculta_portal_sitemap`](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md) | `0.1.1` | Fechar decisões de indexação e validação externa; concluir requisitos antes de RC, depois deploy Alpha e produção ([README](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)) | Dev |
| [`aculta_qrcode`](web/modules/custom/aculta_portal/modules/aculta_qrcode/README.md) | `0.4.0-dev.2` | Serviço compartilhado de geração usado pelo `aculta_links`; componente está na configuração para ativação; validar Runtime e completar preview/logo conforme roadmap ([roadmap](web/modules/custom/aculta_portal/modules/aculta_qrcode/docs/ROADMAP.md)) | Dev |
| [`aculta_statistics`](web/modules/custom/aculta_portal/modules/aculta_statistics/README.md) | `0.4.0-dev.3` | Fase 0.4.0 em andamento: conclusões por turma e resumo de estatísticas no aculta_mailer validados no Runtime (valores zerados); banner com contraste corrigido (revisão de clique pendente) ([roadmap](web/modules/custom/aculta_portal/modules/aculta_statistics/docs/ROADMAP.md)) | Dev |
| [`aculta_user`](web/modules/custom/aculta_portal/modules/aculta_user/README.md) | `0.4.0-dev.54` | Completar variantes, testes/visual de privacidade e cache antes de RC; validar Runtime em Alpha ([variantes](web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-VARIANTS.md)) | Dev |
| [`aculta420`](web/themes/custom/aculta420/README.md) | `0.6.0-dev.134` | Validar banner de consentimento (`consent-banner`, ligado pelo Portal) e componentes QR (`qr-preview`, `qr-download`) e micropáginas ACULTA Links em responsividade, claro/escuro, teclado e leitor de tela antes de RC ([roadmap](web/themes/custom/aculta420/docs/roadmap.md)) | Dev |
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
