# Roadmap — ACULTA Messenger

Ciclo: [Dev → RC → Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Marcos mantidos: 0.1.1 RC (estabilização), 0.2.0 (API e Fórum/User/LMS), 0.3.0 (outros adapters e experiência), 1.0.0 (plataforma estável). Sem nova PR, merge ou deploy. Runtime/Alpha não são inferidos de testes locais.

A arquitetura normativa é [ARCHITECTURE.md](ARCHITECTURE.md). O roadmap anterior, com incrementos e evidências históricas, está preservado em [ROADMAP-HISTORY.md](ROADMAP-HISTORY.md). Propostas de chats automáticos/contextuais foram substituídas e permanecem apenas em registros históricos. Estado abaixo separa implementação existente de trabalho comprovado nesta execução.

## M0 — Auditoria e caracterização

| Produtor | Evento / destinatário | Fonte e armazenamento | Canal / preferência / deduplicação | Autorização e evidência |
| --- | --- | --- | --- | --- |
| Fórum — resposta | Publicação/aprovação de Comment legado ou primeira publicação de `ForumPost`; seguidores atuais de `seguir_topico`, exceto autoria | Comment/ForumPost e Flag são as fontes; `aculta_aviso` agrega por destinatário/tipo/tópico e `aculta_messenger_forum_notice_event` deduplica `(uid, event_type, event_id)` | Central de avisos e resumo diário opt-in pelo ACULTA Mailer; contagem no site não depende do opt-in | Hooks publicam envelope no Messenger; producer e consumer revalidam publicação, assinatura e acesso. Kernel do ledger/eventos: 23/515 anteriormente aprovado; Runtime pendente. |
| Fórum — atualização de tópico | Nova revisão pública; seguidores atuais de `seguir_topico`, exceto autoria da revisão | Node/revisão e Flag; aviso em `aculta_aviso`, deduplicação no ledger compartilhado por destinatário/tipo/revisão | Central de avisos e resumo diário opt-in pelo ACULTA Mailer | Fila Core `aculta_messenger_message_subscribe_event` transporta IDs Node/revisão; worker valida publicação/revisão/alias e publica envelope Messenger. Apesar do nome legado da fila, não chama `Subscribers::sendMessage()` nem entrega Message Subscribe; F-D21 evita o caminho paralelo. Runtime pendente. |
| User | Pedido/aceite de amizade; membro alvo da transição | `aculta_friendship` é fonte do workflow; Message para aviso | Message Notify em fila; política de e-mail/preferência existente; chave de transição | `FriendshipManager` valida transição/conta; Unit do subscriber e worker. |
| Private Message | Nova mensagem privada; participantes elegíveis exceto remetente | Private Message + Message; fila carrega ID da mensagem | E-mail mínimo via Message Notify, conforme preferência; chave por mensagem/destinatário | Revalida thread, bloqueio, destinatário e acesso no worker; Unit existente. |
| LMS adapter | Matrícula, certificado, conclusão, avaliação, feedback, conteúdo e prazo; titular/participantes elegíveis | Entidades/Group do LMS; Message como aviso | Drawer/contador por alter público; e-mail transacional ou digest segundo tipo; deduplicação por origem e versão | Adapter valida membership e permissões ao gerar/abrir; Unit e Kernel próprios; Runtime descrito no README LMS como smoke anterior. |
| Wiki | `aculta_wiki_messenger` consome proposta/revisão editorial e outcome elegíveis; estado Dev.1 parcial | Workflow Wiki; aviso em Message | Site obrigatório; off/direto/resumo via preferência e Mailer | Unit 3/20; Kernel/Runtime pendentes; notifiers legados suprimidos com adapter ativo. |
| Loja | `aculta_commerce_messenger` publica `store.order_placed` para cliente ativo do tipo Loja configurado; exclui LMS/doação | Drupal Commerce Order; aviso em Message | Site obrigatório; categoria `loja` off/direto/resumo via Mailer | Unit 6/43; Kernel/Runtime pendentes; recibo nativo suprimido após aceite; convidados e falha preservam fallback. |
| Revista | Adapter futuro conforme workflows e destinatários reais | Conteúdo editorial proprietário | Mesmo contrato comum | Inventário antes de implementar. |

A matriz é caracterização baseada em código, testes e evidências documentais, não homologação Runtime. O nome histórico da fila de atualização do Fórum não indica uso do dispatcher de destinatários Message Subscribe. Fechar a contagem de tipos Message, workers/filas, Flags/leitura e digest como parte do M0 antes de migrar outros produtores.

**Existente:** caracterização Kernel XK-C2 cobre idempotência dos avisos atuais do Fórum; inventários em `INTEGRATIONS.md`, `MESSENGER.md`, documentação LMS/User e testes.
**Entrega:** matriz produtor → evento → destinatário → fonte → storage → canal → preferência → deduplicação → autorização → testes; localizar duplicidades e registrar estado codificado/testado/Runtime. Acrescentar caracterização antes de refatorar fluxo complexo.
**Aceite:** cada produtor e fila real tem contrato e evidência; sem presumir homologação.

## M1 — Estabilização 0.1.1 RC

**Nesta revisão (dev.93):** Comments, revisões de `ForumPost` e atualizações de tópico são publicados pelo envelope compartilhado e consumidos pelo agregador existente. O consumer resolve a origem por UUID/revisão, valida a chave canônica e reusa as revalidações de assinatura/acesso e o ledger transacional; o fluxo não cria storage nem mensagem paralela. Kernel SQLite nesta revisão: 23 testes / 515 assertions, incluindo round-trip pelo EventDispatcher para Comment, revisão de tópico e ForumPost, replay idempotente, revogação de acesso entre publicação e consumo, comparação do caminho direto e do event-driven no mesmo agregado e envio de uma linha única de resumo via Mailer mockado em `pt-br`; a execução MariaDB anterior também passou em 23 / 515. Unit Messenger: 82 / 386 (inclui revalidação de modo por categoria em providers do digest); PHPCS Drupal/DrupalPractice e lint nos arquivos alterados passam. O worker de revisões usa `datetime.time` para o prazo de uma semana de alias, coberto por 2 testes / 23 assertions que verificam requeue de item recente e descarte/log de item vencido. O resumo diário sempre envia em `pt-br`, independentemente de preferência individual. Leitura do tópico aceita o `NodeInterface` upcast pelo Drupal Router e IDs numéricos; F-D21 remove candidatos Message Subscribe para tópicos Forum e Comments hospedados neles, preservando outros conteúdos. MariaDB não foi executado nesta revisão; concorrência entre processos e homologação Runtime seguem pendentes. Também removida a restrição de amizade para respostas em thread existente; bloqueios continuam. O conceito artificial de retenção `course` foi removido.
**Pendente:** cenários de amizade, bloqueio bilateral, autorização/IDOR, usuários inativos, grupos voluntários (incluindo fallback/teclado), retenção paginada e recuperação de falhas, transporte de teste e revisão visual autenticada. Não rodar limpeza em dados reais.
**Aceite:** gates estáticos e automatizados pertinentes aprovados; smoke visual/Runtime identificado separadamente antes de RC conforme ciclo.

## M2 — API central de eventos (0.2.0)

Implementado: `MessengerNoticeEvent` e `MessengerNoticePublisherInterface` publicam envelope pequeno via EventDispatcher Drupal, com metadata validada e sem persistência própria. Fórum adapta Comments, primeira publicação de `ForumPost` e revisão de tópico; o consumer mantém `aculta_aviso`, revalida assinatura/acesso e usa o ledger existente. O adapter LMS já consome quatro tipos e encaminha ao Message existente pelo manager idempotente. A cobertura restante inclui integração de amizade, eventos LMS restantes, testes comparativos em Runtime e canais da central/e-mail. Manter interfaces pequenas, DI, payload mínimo e sem novo subsistema de storage.

**Aceite:** testes de contrato/idempotência/ACL; eventos repetidos não duplicam; opt-out não remove aviso do site; adapters opcionais ausentes não causam erro.

## M3 — Fórum e User (0.2.0)

Fórum conserva `seguir_topico`, avisos atuais/agregação por usuário-tipo-tópico, leitura, digest e F-D21; valida Domain, publicação, revisão, acesso, aprovação tardia, republicação e movimentação. O alter de Message Subscribe remove candidatos para tópicos do Fórum e Comments hospedados neles, mantendo outros contextos. Não usar publicação paralela nem iniciar conversas por interação no Fórum. User conserva workflow/entidade de amizade; o adapter do Messenger converte pedido/aceite em `MessengerNoticeEvent` e o consumer revalida estado, origem e destinatário antes de persistir no `Message` já existente. Unit Messenger: 79 testes / 356 assertions; Kernel integrado do caminho User continua pendente.

## M4 — LMS (0.2.0)

Matrícula, certificado, conclusão, avaliação, feedback, submissão, liberação e prazos publicam pelo envelope compartilhado; o consumer mantém Message e revalida acesso. Canal de site obrigatório; e-mail selecionável por usuário entre off/direto/resumo e sujeito às políticas administrativas do Mailer. Concluir testes de armazenamento, idempotência, cada canal, revogação e remoção dos caminhos de entrega legados. Manter cursos, atividades, Groups e matrículas sob ownership LMS; sem chat de turma ou caixa paralela. A integração LMS precede Wiki, Fórum e Loja; manter a ordem de execução acordada LMS → Wiki → Fórum → Loja.

## M5 — Wiki, Revista e demais (0.3.0)

Wiki: consumer Dev.1 cobre revisão editorial e resultado de proposta, persiste em Message e alimenta central/contador, digest e entrega direta. Entrega Wiki legada é suprimida quando o adapter está habilitado. Kernel, concorrência, Runtime e prova de ausência de duplicação permanecem pendentes. Em seguida, consolidar o Fórum preservando F-D21 e seu ledger. Depois migrar o evento de pedido da Loja via `aculta_commerce_messenger`, mantendo aviso obrigatório no site, preferência off/direto/resumo e fallback do recibo nativo. Revista segue após inventário do workflow real.

## M6 — Experiência e administração (0.3.0)

Aprimorar central existente: filtros, agrupamento, abertura segura, leitura quando fonte permite, assinaturas, preferências, estados vazios/erro/carregamento e compatibilidade drawer/conta. Manter distinção Avisos/Mensagens. Administração configura somente tipos/políticas permitidos. Lógica e persistência ficam no módulo; apresentação no ACULTA420.

## M7 — Hardening e lançamento (1.0.0)

Validar concorrência/idempotência, locks/retry, filas, retenção, paginação/contadores/N+1, cache por usuário/contexto, revogação, observabilidade sem dados pessoais, módulos opcionais, schema/update, e-mails, acessibilidade e responsividade. Atualização incremental de UI ou nova dependência somente com necessidade comprovada.

## Requisitos transversais e registro

1. Aviso nunca vaza conteúdo inacessível, rascunho, nem ação própria quando política a suprime; acesso revalidado ao abrir.
2. Reprocessamento não duplica; histórico continua válido após refatoração.
3. Opt-out de e-mail não apaga aviso no site; digest exclui aviso inacessível/já entregue.
4. Mensagens respeitam bloqueio, privacidade, CSRF, rate limit e autorização de thread.
5. Transporte de teste somente para destinatários controlados; sem e-mail real não autorizado.
6. Cada incremento registra comando, resultado e limites no changelog/documentação. Somente testes executados são aprovados.
7. Não operar no Runtime sem identificar host/diretório/branch/URI/banco e consultar `environments.md`; nenhum dado real será limpo nesta refatoração.

### Estado nesta execução (2026-10-24)

- M0: parcial; contratos e inventário documental iniciados; matriz completa pendente.
- M1: parcial; correção de amizade em respostas e remoção da retenção course implementadas; gates a executar.
- M2: parcial; envelope/publisher e adapters Fórum/User/LMS implementados nos eventos descritos; Fórum tem Kernel round-trip dos três eventos, comparação do agregado e resumo combinado. Amizade ainda carece Kernel integrado, além dos eventos LMS restantes e da homologação Runtime.
- M3 (LMS): parcial; todos os produtores conhecidos publicam eventos pelo contrato compartilhado e preservam Message; seleção off/resumo/direto implementada e coberta por Unit 27/165; PHPCS passa. Kernel desta sessão não executou por falta de `SIMPLETEST_DB`; Runtime não homologado. Remoção dos fluxos legados e gate Kernel ainda pendentes.
- M5 (Wiki): consumer Dev.1 funcional em Unit e conectado a Message/central/digest/Mailer; Kernel e Runtime pendentes. Notifiers legados ficam suprimidos somente quando o adapter está habilitado.
- Loja: submódulo `aculta_commerce_messenger` Dev.1 implementado para pedido registrado; Unit 6/43 e PHPCS/lint/YAML passaram, mas Kernel/configuração instalada/Runtime ainda não foram validados. Recibo de convidados permanece no Commerce; reenvio administrativo nativo continua explícito.
- Fórum/User, M6–M7: permanecem nos gates documentados; não marcados como concluídos nesta execução.
- Runtime: não homologado nesta execução.
