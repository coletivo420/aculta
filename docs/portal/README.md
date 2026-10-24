# Documentação do Portal

Índice do `aculta_portal`. Só documentos normativos e de referência vigentes.

## ACULTA Studio — Hub e Meu Espaço (planejamento P0, 2026-10-24)

- [Hub geral e migração de Minha Conta e Meu Perfil](../architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md) — contrato-alvo, fases P0–P7, ownership, navegação e anti-regressão; P1-A possui entrada pessoal mínima no código, ainda sem homologação Runtime.
- [Identidade atual do Studio](ACULTA-STUDIO-IDENTITY.md) — Marley, Ganjalab e WE SATIVA e seus caminhos já existentes, com distinção explícita do futuro hub.
- [Menu atual da Minha Conta](ACCOUNT-MENU.md) — comportamento existente mantido durante a transição, inclusive raiz do domínio de conta e proteção de `/minha-conta`.
- [Plataforma e experiências](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md) — contratos arquiteturais do Studio e a nova frente P0.

## Normas

- [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md) — padrão Drupal 11+ e regras de código.
- [GUARDRAILS.md](GUARDRAILS.md) — regras de Fórum, Revista, Loja, Wiki, Conta e AJAX.
- [COMPONENTES-PROPRIOS.md](COMPONENTES-PROPRIOS.md) — rótulo único "ACULTA Portal" para módulos próprios e gate de pacote.
- [IMPLEMENTATIONS.md](IMPLEMENTATIONS.md) — inventário de implementações, helpers e integrações aplicadas pelo Portal.
- [WEBFORM-BOOTSTRAP-AVALIACAO.md](WEBFORM-BOOTSTRAP-AVALIACAO.md) — avaliação de portabilidade do Webform Bootstrap (3.x, deprecado) para tema e Portal.
- [LINKS-DE-USO-UNICO.md](LINKS-DE-USO-UNICO.md) — links de acesso único: 24 horas em todos os módulos, pedido novo substitui o anterior, reenvio não reaproveita link, e a única exceção (retentativa automática).
- [ACEITE-UNICO.md](ACEITE-UNICO.md) — um único formulário de aceite (Termos e Política), sem novos aceites; funções descritas na política; gate `validate-single-acceptance.php`.
- [EMAIL-TRANSPORT.md](EMAIL-TRANSPORT.md) — envio de e-mail pela camada de envio E-Mails ACULTA (M0 a M2 concluídas; validado no runtime de teste).
- [SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md) — fonte de verdade de cada dado.
- [ARCHITECTURE.md](ARCHITECTURE.md) — papel do módulo e fronteiras.
- [ACULTA-CONTENT-INDEX.md](ACULTA-CONTENT-INDEX.md) — catálogo transversal, fronteiras, estado e roadmap do `aculta_index`.
- [Schema do Content Index](ACULTA-CONTENT-INDEX-SCHEMA-0.1-C.md) — modelo SQL por geração e limites da projeção.
- [Bridge Index ↔ Statistics](ACULTA-CONTENT-INDEX-STATISTICS-BRIDGE.md) — correlação opcional sem mover agregações ou métricas.
- [Auditoria IDX 0.1-A](ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md) — matriz de reaproveitamento, auditoria de Statistics e proveniência dos projetos contrib.
- [ADR-013 — ACULTA Content Index](../decisions/ADR-013-aculta-content-index.md) — decisão arquitetural do catálogo derivado.
- [AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) — roteamento de modelos para agentes.
- [EDITOR-CKEDITOR5-AUDITORIA.md](EDITOR-CKEDITOR5-AUDITORIA.md) — auditoria do CKEditor 5 Plugin Pack, dependências, submódulos e decisões pendentes.
- [ADR-009 — sitemap multidomínio](../decisions/ADR-009-sitemap-multidominio.md) — decisão arquitetural do sitemap.
- [ACULTA Associado ID](../modules/ACULTA-ASSOCIADO-ID.md) — escopo de dados dos associados, compatibilidade e migração controlada.
- [ACULTA Gallery — arquitetura](../../web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md) — decisões e contratos de galerias incorporadas.
- [ACULTA Messenger: documentação do projeto](MESSENGER.md) — módulo, submódulo, rotas, hooks, classes e contratos com o tema.
- [Tipos de conversa do Messenger: política vigente](MESSENGER-CONVERSATION-KINDS.md) — somente conversas privadas voluntárias; propostas históricas de chat automático por conteúdo estão substituídas.
- [Auditoria do Messenger](MESSENGER-AUDIT.md) — separação entre módulo, submódulo e tema, com os achados.
- [ACULTA Messenger](../../web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) — entrada local do submódulo.
- [PDF global](PDF-VIEWER-INTEGRATION.md) — contrato compartilhado de Media, viewer, acesso a arquivos e previews.
- [Uploads de arquivos por usuários](USER-UPLOADS.md) — política central, responsabilidades dos consumidores e sequência de migração.
- [Política de limpeza de conteúdo e anexos](CONTENT-CLEANUP-POLICY.md) — prévia, verificação de referências, exclusão explícita e reaproveitamento de `aculta_image`.
- [Limpeza autorizada do Runtime Magazine](MAGAZINE-TEST-CLEANUP-2026-10-22.md) — inventário, backups e verificação da exclusão dos 12 posts de teste.
- [ACULTA PDF Viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md) — documentação do submódulo, formatter, serviço, biblioteca, hooks e testes.
- [ACULTA PDF Viewer Group](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/modules/aculta_pdf_viewer_group/README.md) — relação opcional de Media `document` com turmas Group e acesso por membership.
- [Contratos de integração LMS](LMS-INTEGRATION-CONTRACTS.md) — avaliações, certificados, Messenger, User/conquistas e apresentação.
- [Matriz de segurança e arquivos LMS](SECURITY-ACCESS-FILES-MATRIX.md) — papéis, acesso, upload, cache e isolamento por turma.
- [Roadmap do aculta_lms](../modules/ACULTA-LMS-ROADMAP.md) e [compatibilidade LMS/PDF](../modules/LMS-COMPATIBILITY.md).

## Políticas

- [ADMIN-DOMAIN-POLICY.md](ADMIN-DOMAIN-POLICY.md) — painel administrativo e canonicalização para MAIN.
- [CROSS-DOMAIN-REQUEST-POLICY.md](CROSS-DOMAIN-REQUEST-POLICY.md) — requisições entre purposes.
- [PAYMENT-DOMAIN-POLICY.md](PAYMENT-DOMAIN-POLICY.md) — carrinho, checkout e pagamento no MAIN.
- [MERCADO-PAGO-PLANO.md](MERCADO-PAGO-PLANO.md) — contrato e limites da integração Mercado Pago.
- [FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md) — URLs e slugs em português.
- [TURNSTILE.md](TURNSTILE.md) — anti-bot: pontos de CAPTCHA, chaves de teste e produção, caixa de ativação.
- [ACCOUNT-MENU.md](ACCOUNT-MENU.md) — menu da Minha conta: lista no desktop e sanfona no celular.
- [CHECKOUT-BILLING.md](CHECKOUT-BILLING.md) — endereço único (cobrança, envio e Mercado Pago), meios de pagamento e campos do endereço.
- [SENHA-CONTA-OAUTH.md](SENHA-CONTA-OAUTH.md) — criação da primeira senha em conta criada com Google (Social Auth).
- [EMAIL-CONFIRMATION-POLICY.md](EMAIL-CONFIRMATION-POLICY.md) — confirmação de e-mail: quando a conta fica bloqueada e como é liberada.
- [REDIRECTS.md](REDIRECTS.md) — redirecionamentos mantidos pelo Portal (rotas antigas e slugs).
- [SEARCH.md](SEARCH.md) — busca pública e do painel por purpose.
- [WIKI.md](WIKI.md) — conteúdo, autoria, permissões e apresentação da Wiki420.

## Contratos

- [DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md) — contrato Portal → tema.
- [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md) — componentes e Bootstrap.
- [AJAX.md](AJAX.md) — política de AJAX e interatividade.
- [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md) — fronteira entre `aculta_portal` (função) e ACULTA420 (design); contrato `data-portal-ui`.

## Planejamento e validação

- [ROADMAP.md](ROADMAP.md) — planejamento do Portal.
- [Registro de dívidas](../operations/DEBT-REGISTER.md) — pendências com evidência e impacto.
- [TESTES-NAVEGADOR.md](TESTES-NAVEGADOR.md) — procedimento e registro de rodadas no navegador.
- [Histórico documental](historico/README.md) — snapshots preservados para consulta; não são o estado atual.

O estado implementado de cada componente é registrado no CHANGELOG e no código.
Pull requests e resultados pontuais de execução pertencem ao GitHub/Git; não são
duplicados neste índice.

## Extensões próprias

- [ACULTA Fórum / Growroom420](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md)
- [ACULTA Game](../../web/modules/custom/aculta_portal/modules/aculta_game/README.md)

Os READMEs dos componentes são a entrada para escopo, contratos e documentação
específica. O inventário de ativação por ambiente permanece na configuração Drupal.

## Direção arquitetural Studio / Revista / kernels

- [Kernel ACULTA e subkernels](../architecture/aculta-kernels.md) — classificação normativa e contratos dos serviços comuns, Fórum e Galeria.
- [ADR de arquitetura](../decisions/ADR-011-aculta-kernels-studio.md) — decisão aceita, implementação pendente.
- [Roadmap Studio e Revista](STUDIO-MAGAZINE-ROADMAP.md) — marcos de migração editorial e interface frontend por abas.
