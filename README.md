# ACULTA — plataforma institucional

Site institucional da ACULTA — Associação Cultural Antiproibicionista, construído
em Drupal 11. O projeto integra capacidades do Core e de módulos contrib; não é
uma loja de cannabis. O Portal concentra integrações funcionais e o tema
ACULTA420 apresenta a experiência.

## Comece por aqui

- [Documentação do projeto](docs/README.md) — arquitetura, políticas e operação.
- [Arquitetura dos Kernels ACULTA](docs/architecture/aculta-kernels.md) — Kernel transversal, subkernels Fórum/Galeria e matriz de consumidores.
- [ADR-011: Kernels e Studio](docs/decisions/ADR-011-aculta-kernels-studio.md) — responsabilidades e fronteiras aprovadas.
- [Roadmap Studio + Revista](docs/portal/STUDIO-MAGAZINE-ROADMAP.md) — unificação editorial, abas do Studio e fases de implementação.
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
| [`aculta_portal`](web/modules/custom/aculta_portal/README.md) | `0.4.1-dev.1` | Upload compartilhado com Fórum e LMS integrados (0.4.0-dev.252; teto LMS verificado por leitura no Runtime, container montando; smoke HTTP autenticado e revisão visual no Firefox/X11 de envio por aluno OK); Wiki sem campo de envio por usuário; retomar Fórum em Workflow e moderação após fechar as evidências visuais no Runtime e seguir dívidas do roadmap antes de RC ([uploads](docs/portal/USER-UPLOADS.md), [roadmap](docs/portal/ROADMAP.md), [dívidas](docs/operations/DEBT-REGISTER.md)) | Dev |
| [`aculta_associado_id`](web/modules/custom/aculta_portal/modules/aculta_associado_id/README.md) | `0.3.0-dev.1` | Validar migração em cópia isolada e fechar escopo/minimização ([decisão](docs/modules/ACULTA-ASSOCIADO-ID.md)) | Dev |
| [`aculta_deployer`](web/modules/custom/aculta_portal/modules/aculta_deployer/README.md) | módulo `0.1.25`; CLI `0.1.27` | Corrigir pendências incluindo `session_cookie`; toda preparação/gates prontos antes de RC; deploy Alpha e produção depois ([plano](web/modules/custom/aculta_portal/modules/aculta_deployer/docs/PLANEJAMENTO.md)) | Dev |
| [`aculta_document_viewer`](web/modules/custom/aculta_portal/modules/aculta_document_viewer/README.md) | `0.1.0-dev.10` | Homologação RC: gates do Portal e do tema sem falhas introduzidas, revisão visual pré-RC em Firefox e viewports, DOCX e planilhas nos consumidores públicos e LMS só após decisão do responsável ([roadmap](web/modules/custom/aculta_portal/modules/aculta_document_viewer/docs/ROADMAP.md)) | Dev |
| [`aculta_document_viewer_group`](web/modules/custom/aculta_portal/modules/aculta_document_viewer/modules/aculta_document_viewer_group/README.md) | `0.1.0-dev.1` | Teste de revogação de membership bloqueado no ambiente de teste (aculta_user.aculta_user_agreement_ledger ausente em Kernel); integração Group dos documentos de turma com migração do plugin já aplicada no Runtime ([README](web/modules/custom/aculta_portal/modules/aculta_document_viewer/modules/aculta_document_viewer_group/README.md)) | Dev |
| [`aculta_donation`](web/modules/custom/aculta_portal/modules/aculta_donation/README.md) | `0.1.0-dev.21` | Completar fluxo de sandbox e fechar decisão sobre recorrência ([README](web/modules/custom/aculta_portal/modules/aculta_donation/README.md)) | Dev |
| [`aculta_editor`](web/modules/custom/aculta_portal/modules/aculta_editor/README.md) | `0.1.0-dev.5` | Formatos unificados, Editorial com filtro e tipo aculta_adm com HTML completo. Próximo: restringir a criação de aculta_adm à administração e validar o tipo no navegador ([auditoria](docs/portal/EDITOR-CKEDITOR5-AUDITORIA.md)) | Dev |
| [`aculta_forum`](web/modules/custom/aculta_portal/modules/aculta_forum/README.md) | `0.1.0-dev.181` | Revisar a permissão do formato forum diante do drift em email_confirmed; validar upload contextual e preview local DOCX/planilhas com arquivos autorizados, incluindo fallback e revisão visual; depois integrar cartão PDF ao aculta_pdf_viewer. Homologar avisos e concorrência HTTP/cache ([roadmap](web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md); uploads em `/painel-administrativo/configuracoes/aculta/uploads-usuarios`) | Dev |
| [`aculta_forum_lms`](web/modules/custom/aculta_portal/modules/aculta_forum_lms/README.md) | `0.1.0-dev.29` | F15: smoke e revisão visual autenticados do vínculo H5P–Fórum ainda dependem de conteúdo/tópico no Runtime; avançar notificações e integrações Gallery, Magazine e Wiki ([CHANGELOG](web/modules/custom/aculta_portal/modules/aculta_forum_lms/CHANGELOG.md)) | Dev |
| [`aculta_gallery`](web/modules/custom/aculta_portal/modules/aculta_gallery/README.md) | `0.1.0-dev.14` (galerias globais e renderização de imagens anexadas pelo Fórum) | Validar diálogo e galerias do Fórum no navegador e em aparelho real antes de RC; validar Runtime em Alpha após deploy ([arquitetura](web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md)) | Dev |
| [`aculta_game`](web/modules/custom/aculta_portal/modules/aculta_game/README.md) | `0.1.0-dev.15` | Concluir G2/G3/G7 e homologar integrações de participação antes de RC ([roadmap](web/modules/custom/aculta_portal/modules/aculta_game/docs/ROADMAP.md)) | Dev |
| [`aculta_id_cpf`](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md) | `0.2.0-dev.2` | Completar Kernel e validar migração/unicidade em cópia isolada ([README](web/modules/custom/aculta_portal/modules/aculta_id_cpf/README.md)) | Dev |
| [`aculta_image`](web/modules/custom/aculta_portal/modules/aculta_image/README.md) | `0.1.0-dev.29` | Confirmar binários necessários para produção e avançar integração Growroom; concluir requisitos de RC antes do deploy ([plano](web/modules/custom/aculta_portal/modules/aculta_image/docs/PLANO.md)) | Dev |
| [`aculta_links`](web/modules/custom/aculta_portal/modules/aculta_links/README.md) | `0.4.0-dev.31` | Cliques contados no aculta_statistics (0.4.0-dev.31; tabela própria removida no Runtime pela atualização 10008, tabela vazia antes); navegação por seção; serviço `aculta_qrcode` confirmado no Runtime, sem página publicada para download HTTP positivo; revisão visual autenticada pendente ([roadmap](web/modules/custom/aculta_portal/modules/aculta_links/docs/ROADMAP.md)) | Dev |
| [`aculta_lms`](web/modules/custom/aculta_portal/modules/aculta_lms/README.md) | `0.1.0-dev.80` | Respostas com arquivo sob a política central de upload (0.1.0-dev.80; teto por atividade, Unit 3/10; smoke HTTP autenticado no Runtime: .exe recusado com a mensagem do teto (png pdf) e PNG gravado na resposta; revisão visual no Firefox/X11 8/8 com ajuda corrigida para o teto; cenário de teste removido); F7: smoke Runtime do pedido gratuito concluído confirmou membership com origem, role global estudante e preservação de matrícula manual; Unit cobre pedido gratuito, pagamento concluído, reembolso parcial/integral, pedido incompleto e remoção manual permitida/bloqueada conforme origem (origem inválida e pagamento malformado falham fechados; EnrollmentFormHooksTest 7/49; CourseOrderEnrollmentManagerTest 9/47; active-order item reconciliation removes stale paid-class access, preserves valid/manual memberships, and transfers to another active purchase); checkout HTTP, pagamento real, reembolso, confirmação visual da interface de remoção e isolamento A/B pendentes; F11: carga horária mínima de uma hora aplicada no Runtime; Kernel valida elegibilidade, snapshot, formato/unicidade e os quatro dados públicos, sem e-mail/motivo de revogação; smoke Runtime temporário confirmou emissão, verificação pública, QR SVG e PDF por HTTP (200), com limpeza integral; Kernel executa os submits de emissão/revogação e confirma opção de conclusão para educador contextual (5/159), mas smoke Runtime autenticado e revisão visual permanecem pendentes; F6/F12 usam take course no curso pai e somente aulas publicadas; Kernel confirma access contextual, progresso e certificados próprios entre duas contas (LearningDashboardKernelTest 1/41); revisão visual/Runtime do estudante pendente; regressão combinada LMS + Messenger: Unit 89/482 e Kernel MariaDB 24/772, PASS; revisão visual/Runtime pendente; F13: avisos escolhidos para mudanças acionáveis persistidas; teste confirma edição editorial sem evento (liberação de conteúdo, feedback/publicação e prazo); edições editoriais sem efeito não notificam; lembrete permanece em 24h até decisão de intervalo; prazo por atividade/turma, com fuso da conta, fallback UTC e apresentação positiva no fuso da estudante cobertos por teste; revisão visual/acessível pendente; Visibilidade de participantes (requisito complementar): implementação Dev concluída — educador controla lista por turma, padrão oculto; nomes apenas de estudantes ativos da mesma turma; formulário e access contextual cobertos; Kernel 1/28 PASS (36 depreciações upstream), rota incluída e configuração de campo aplicada por importação parcial revisada no Runtime; smoke autenticado e revisão visual pendentes; F14 Conexões: pedido/aceite publica aviso Message e e-mail mínimo via Messenger, implementação no commit e8da6bb4; cobertura focal 8/56 (subscriber, formulário e falhas seguras do worker); smoke autenticado/visual, entrega bem-sucedida e retentativas do worker pendentes; pedidos recebidos aparecem na aba Mensagens; administração agora começa pelo curso e contextualiza aulas, atividades, turmas, prazos e fóruns; F15: `aculta_forum_lms` links Group-authorized discussions to LMS activities, including H5P, and reuses the shared `usuario_aculta` image pipeline (Unit 22/256, Kernel 2/132 PASS); authenticated Runtime smoke, visual review, focal Kernel access fixture, and Gallery/Magazine/Wiki integrations pending; hierarquia course-first: Unit 71/394, Kernel 23/764 PASS (93 depreciações), Runtime: rebuild de rotas e árvore de menu course-first PASS; smoke autenticado pendente; F16 inclui lock de reemissão, contexto user.group_permissions no access de certificados, isolamento de entrega, painel de turma testado com grant contextual grade students e negação a estudante, exclusão de conta bloqueada do agregado com invalidação por conta, referências LMS ausentes toleradas em avisos, formulário de prazo, respostas abertas, histórico e certificados (422 no manager; opção omitida no formulário), access fail-closed via AccountProxy, negação por relação ausente invalidada ao adicionar vínculo da turma, smoke parcial no Runtime, cache privado preservado e Kernel LMS/Messenger 24/772; download privado nega após exclusão do CourseStatus enquanto a limpeza está enfileirada (AttemptArchiveLifecycleTest 10/409); certificado revogado retorna 404 sem invocar Dompdf/QR; composer audit sem advisories; PHP 8.5 não validado; matriz de acesso revisada para certificados snapshot/Entity Print/Dompdf/QR, painéis via LMS/Group e as regras de matrícula manual ligada a compras; Unit cobre reembolso integral com outra compra ativa, mantendo membership e transferindo proveniência; Unit LMS/Messenger 89/482, AcultaLmsInstallTest 4/42 confirms Commerce insert/update hooks, Kernel dedicado 24/772, gate Portal 1010 checks e índice 29 componentes PASS ([roadmap](docs/modules/ACULTA-LMS-ROADMAP.md)) | Dev |
| [`aculta_lms_h5p`](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md) | versão não declarada | F15: fechar gates finais e revisão do diff para decisão RC; player H5P, PDF privado e discussão H5P–Fórum já passaram por smoke autenticado e as fixtures foram removidas. F10 permanece homologada ([README](web/modules/custom/aculta_portal/modules/aculta_lms_h5p/README.md)) | Dev |
| [`aculta_lms_messenger`](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md) | `0.1.0-dev.15` | F13: Runtime smoke passou; falta revisão visual/acessível com sessão autenticada após aceite do acordo pelo responsável; F16: referências LMS ausentes em respostas e conclusões suprimem avisos sem erro 500; provider real de digest descarta certificado removido e conclusão sem curso; Unit LMS/Messenger 89/482, Kernel 24/772 ([README](web/modules/custom/aculta_portal/modules/aculta_lms_messenger/README.md)) | Dev |
| [`aculta_lms_resources`](web/modules/custom/aculta_portal/modules/aculta_lms_resources/README.md) | `0.1.0-dev.2` | F15: instalar configuração e campos no Runtime de teste, executar smoke autenticado e revisão visual; validar PHP 8.5 antes de RC ([roadmap](docs/modules/ACULTA-LMS-ROADMAP.md)) | Dev |
| [`aculta_magazine`](web/modules/custom/aculta_portal/modules/aculta_magazine/README.md) | `0.4.0-dev.1` | Fechar drift de Config Sync após o workflow do Fórum chegar ao Runtime e executar os gates de conclusão 0.5.0; envio de comentário pendente validado no Runtime de teste e overflow horizontal do documento não reproduzido ([roadmap](docs/portal/STUDIO-MAGAZINE-ROADMAP.md)) | Dev |
| [`aculta_mail_confirmer`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/README.md) | `0.1.0-dev.2` | Validar confirmação, troca de e-mail e login bloqueado no navegador; depois revisão visual antes de RC | Dev |
| [`aculta_mail_confirmer_user`](web/modules/custom/aculta_portal/modules/aculta_mail_confirmer/modules/aculta_mail_confirmer_user/README.md) | `0.1.0-dev.2` | Validar troca de e-mail e bloqueio de login no navegador antes de RC | Dev |
| [`aculta_mailer`](web/modules/custom/aculta_portal/modules/aculta_mailer/README.md) | `0.1.0-dev.69` | Dev: confirmador de e-mail incorporado (aculta_mail_confirmer); validar o fluxo no navegador antes de RC1; F12 e produção fora deste candidato ([roadmap](web/modules/custom/aculta_portal/modules/aculta_mailer/docs/ROADMAP.md)) | Dev |
| [`aculta_messenger`](web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) | `0.1.1-dev.73` | F14 Conexões — Unit confirma pedido/aceite, destinatário, deduplicação, conta inativa, formulário de pedidos, falhas seguras, envio bem-sucedido e limite de três retentativas (10/99; Unit Messenger 55/257). Smoke autenticado e revisão visual pendentes; Runtime smoke confirmou Message e enqueue, sem envio de e-mail; validar em Alpha após deploy autorizado ([roadmap](web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md)) | Dev |
| [`aculta_pagamentos_mercadopago`](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md) | `0.1.0-dev.7` | Concluir homologação sandbox e gates/visual antes de RC; validar integração Runtime em Alpha ([README](web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/README.md)) | Dev |
| [`aculta_patcher`](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md) | `0.1.0-dev.2` | Validar ciclo completo e idempotência da aplicação de patches ([README](web/modules/custom/aculta_portal/modules/aculta_patcher/README.md)) | Dev |
| [`aculta_pdf_viewer`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) | `0.1.0-dev.40` | PV7: revisar Kernel e leitura acessível antes de RC; PDF contrib legado removido, PDFPreview permanece para miniaturas ([roadmap](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md)) | Dev |
| [`aculta_pdf_viewer_group`](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md) | `0.1.0-dev.4` | Matriz de acesso Runtime homologada; manter documentos privados e concluir os gates PV7 compartilhados antes de RC ([README](web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md)) | Dev |
| [`aculta_portal_cep`](web/modules/custom/aculta_portal/modules/aculta_portal_cep/README.md) | `0.3.0-dev.10` | Homologar fontes/ordenação e fallback de CEP no Runtime ([contrato](docs/portal/ACCOUNT-ADDRESS.md)) | Dev |
| [`aculta_portal_sitemap`](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md) | `0.1.1` | Fechar decisões de indexação e validação externa; concluir requisitos antes de RC, depois deploy Alpha e produção ([README](web/modules/custom/aculta_portal/modules/aculta_portal_sitemap/README.md)) | Dev |
| [`aculta_qrcode`](web/modules/custom/aculta_portal/modules/aculta_qrcode/README.md) | `0.4.0-dev.2` | Serviço compartilhado de geração usado pelo `aculta_links`; componente está na configuração para ativação; validar Runtime e completar preview/logo conforme roadmap ([roadmap](web/modules/custom/aculta_portal/modules/aculta_qrcode/docs/ROADMAP.md)) | Dev |
| [`aculta_statistics`](web/modules/custom/aculta_portal/modules/aculta_statistics/README.md) | `0.9.0-dev.5` | Fases 0.6 (clientes GA4 e Search Console no código, desligados sem credencial), 0.7 (mídia), 0.8 (painel) e 0.9 (auditoria) no código; pendentes: provisionar credenciais e validar consultas reais ([roadmap](web/modules/custom/aculta_portal/modules/aculta_statistics/docs/ROADMAP.md)) | Dev |
| [`aculta_studio`](web/modules/custom/aculta_portal/modules/aculta_studio/README.md) | `0.1.0-dev.1` | Diretiva visual do Studio concluída e validada no Runtime de teste: quatro níveis com URL própria; Revista (listagens por estado, Editar pelo formulário administrativo do domínio principal, formulário com propriedades secundárias recolhíveis e aviso de alterações não salvas); Wiki (verbetes e revisões); Cursos (turmas e aba por curso); Fórum (fila de moderação); Galerias (acesso ao papel de editor no Runtime de teste; captura visual); paridade claro e escuro; contraste WCAG (7,84:1 claro, 11,51:1 escuro); teclado; leitor de tela validado com o Orca (fala gerada e som medido), decisão do responsável para esta fase. Validação com usuário de leitor de tela fica para fase futura. Suítes: visual 26/26 no Firefox/X11 e Orca 5/5. Decisão do responsável: não publicar em Alpha agora | Dev |
| [`aculta_user`](web/modules/custom/aculta_portal/modules/aculta_user/README.md) | `0.4.0-dev.66` | Revisão autenticada, privacidade/termos e gates antes de RC; validar Runtime em Alpha ([documentação](web/modules/custom/aculta_portal/modules/aculta_user/README.md)) | Dev |
| [`aculta420`](web/themes/custom/aculta420/README.md) | `0.6.1-dev.1` | Validar visualmente o novo template da publicação Revista junto da inspeção dos modais e componentes QR antes de RC ([roadmap](web/themes/custom/aculta420/docs/roadmap.md)) | Dev |
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
