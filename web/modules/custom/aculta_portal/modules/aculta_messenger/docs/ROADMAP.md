# Roadmap — ACULTA Messenger

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Tudo que for requisito funcional, de teste, gate ou revisão visual aplicável deve estar pronto em RC; Alpha valida o deploy no servidor de desenvolvimento antes da migração a produção. Critérios antigos de RC devem ser lidos sob esta regra.

Documento de planejamento único do submódulo. O estado de execução pertence ao
Git, aos commits e ao changelog; esta lista registra sequência e critérios de entrega.

Atualização dev.77: a busca de membros usa uma região viva nomeada, com links
percorridos por Tab e sem semântica combobox. Smoke autenticado do drawer e
homologação no Runtime continuam pendentes.

Integração com o Fórum: o evento `aculta_forum.comment_subtree_moved` transfere
avisos agregados completos; testes específicos e smoke visual permanecem
pendentes. A transferência intencional inclui contagens históricas de respostas
do tópico de origem que não façam parte da árvore movida, conforme decisão do
responsável em 2026-10-21.
Avisos para a primeira publicação de posts revisionáveis foram implementados
em dev.74, com marcador de post separado. A dev.75 grava o ID da revisão
publicada no marcador para não perder aprovações fora da ordem de criação e
limita a integração à primeira publicação imutável. A dev.76 fecha a
caracterização de persistência desses avisos (XK-C2): um teste Kernel percorre
os eventos reais de primeira publicação e de atualização de tópico e confirma
um aviso por destinatário, sem duplicação e sem mudança de comportamento.
Validação Runtime e revisão visual seguem pendentes; o lock persistente do
worker de tópicos não é exercitado.

## 0.1.1 — pedidos de amizade e política de novas conversas

1. **dev.1 — pesquisa e decisão:** auditados Friendship, User Relationships,
   Flag, Private Message, Message e Message Notify. Sem nova dependência;
   workflow de amizade dentro de `aculta_user`, com entidade de conteúdo; o
   Messenger consome o serviço público e usa as integrações atuais.
2. **dev.2 — workflow e privacidade (implementado estaticamente):** entidade e
   serviço em `aculta_user`, ações em perfil/notificações, preferência individual
   e validação de novas threads; runtime DEFERRED.
3. **dev.3 — eventos de amizade (implementado; envio de e-mail pendente de validação):** criar notificações persistentes Message e
   integrar Message Notify para pedido/aceite, com deduplicação e e-mail mínimo,
   sem expor dados privados.
4. **dev.4 — hardening e homologação (em andamento):** Unit cobre as transições
   pedido/aceite, destinatário correto, deduplicação e conta inativa
   (`FriendshipNoticeSubscriberTest`: 4/33), além da lista de pedidos recebidos
   e revalidação do destinatário ao aceitar (`FriendRequestsFormTest`: 2/12).
   O worker cobre payload inválido e ausência de conta/Domain sem chamar
   transporte (`FriendshipNoticeEmailWorkerTest`: 2/11). Unit Messenger:
   53/214. Ainda faltam teste de entrega bem-sucedida/retentativa e revisão
   visual autenticada; o smoke Runtime em dev.63 confirmou somente persistência
   de Message e enqueue, sem enviar e-mail real. Validação em Alpha ocorre após
   deploy autorizado; Config Sync e testes estáticos devem permanecer prontos
   antes de RC.

Cada incremento registra os testes realmente executados e `RUNTIME STATUS`.
RC exige testes/gates e revisão visual aplicável pré-deploy; homologação do
Runtime e transporte controlado são validados em Alpha.

### Integração LMS F13 (parcial)

O adapter `aculta_lms_messenger` usa Message/Message Notify e estes pontos de
extensão para inserir avisos pessoais na lista e no contador da barra
multidomínio. Matrícula e certificado usam e-mail transacional; resumo diário
opt-in para outros eventos, integração visual e Runtime continuam pendentes.

## 0.1.0

1. **dev.1 — fundação documental:** ownership, inventário, compatibilidade,
   permissões/privacidade, Domain, eventos, ADRs. Sem instalar dependências.
2. **dev.2 — módulo integrador:** Composer dry-run/instalação, inventário do
   código upstream, metadados Drupal, integração mínima e ciclo de vida seguro.
3. **dev.3 — Private Message:** inbox, conversas/respostas, não lidas,
   autorização, fallback sem JS e proteção contra interação indesejada.
4. **dev.4 — Message/Message Notify:** eventos autorizados, e-mail mínimo,
   deduplicação e tratamento de entrega.
5. **dev.5 — Message Subscribe:** concluída estaticamente para Nodes `forum`
   publicados com assinantes na flag existente `seguir_topico`; dispatch real
   aguarda Homelab. Group fica fora enquanto `gnode` não estiver habilitado.
6. **dev.6 — Site Alert e comunicados:** integração estática do bloco nativo
   Site Alert e tipo de comunicado persistente com segmentação por papel,
   workflow editorial, validade e Flag individual implementados; runtime aguarda
   Homelab.
7. **dev.7 — Minha Conta:** rotas portuguesas e shell de Mensagens implementados
   com inbox nativa, contador de não lidas e lista privada de eventos de
   mensagem; runtime aguarda Homelab. Avisos agora lista comunicados Node
   segmentados e estado individual pelo Flag.
8. **dev.8 — preferências:** implementado estaticamente o opt-out individual
   de e-mail, usando `private_message.receive_notification` nativo e aba na
   área de configurações. Bloqueio e assinatura continuam nos controles por
   membro/tópico; opções para comunicados e restrição global aguardam mecanismos
   e política. Homelab permanece pendente.
9. **dev.9 — Domain/ACULTA420:** integração estática com SDCs experimentais de
   lista, item, composição, aviso, notificação, badge e estados. Form API e
   render arrays são preservados; RUNTIME STATUS: DEFERRED para validação
   visual desktop/mobile, teclado e modos claro/escuro no Homelab.
10. **dev.11 — hardening:** autorização, cache, abuso, entrega, retenção,
    logs, desinstalação e testes automatizados.
11. **RC — candidato pronto:** gates, testes, Config Sync, isolamento de
    contas/domínios, revisão final e testes visuais pré-deploy. Depois, Alpha
    valida Runtime e e-mails controlados.

### Subfase dev.10 — barra, comunicados e estado de leitura

Inclui modais same-origin na barra multidomínio, tipo de comunicado Node
segmentado por papéis, validade e Flag individual para lido/não lido, além do
estado nativo por conversa do Private Message. Avisos temporários Site Alert
são contados enquanto ativos e não simulam estado individual.

## Critérios de avanço

- Cada fase tem commit claro na branch autorizada e atualiza o changelog.
  PR só quando solicitada pelo responsável.
- Não declarar comportamento verificado sem executar a verificação no ambiente
  correspondente.
- Dependências só entram depois de código instalado, compatibilidade e
  `composer require --dry-run` compatível com o lock.
- Nenhuma fase faz merge sem autorização expressa.
- Release só termina após testes proporcionais e gate de segurança; dados
  privados, conteúdo e configuração de outros módulos não são removidos na
  desinstalação.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-01/02/12: Messenger é centro de conversas e avisos; Private Message, Message/Message Notify, flags, avisos e contadores permanecem suas fontes atuais. Inventariar produtores User/Fórum/LMS/Wiki/Revista; tipar eventos, deduplicar, revalidar acesso/consentimento em cron e na entrega. Preferências de identidade pertencem ao User; não criar outra inbox ou transporte SMTP.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B / ADR-XK-02: catalogar produtores de eventos (Forum, Wiki, LMS Messenger, User), preferências, canais e idempotência antes de mudar adapters. Messenger é concentrador; Private Message, Message/Notify e avisos persistentes mantêm storage existente. Não fazer delivery SMTP no produtor nem inbox paralelo.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**
