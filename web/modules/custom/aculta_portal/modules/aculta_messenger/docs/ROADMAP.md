# Roadmap — ACULTA Messenger

Ciclo: [Dev → RC → Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Marcos mantidos: 0.1.1 RC (estabilização), 0.2.0 (API e Fórum/User/LMS), 0.3.0 (outros adapters e experiência), 1.0.0 (plataforma estável). Sem nova PR, merge ou deploy. Runtime/Alpha não são inferidos de testes locais.

A arquitetura normativa é [ARCHITECTURE.md](ARCHITECTURE.md). O roadmap anterior, com incrementos e evidências históricas, está preservado em [ROADMAP-HISTORY.md](ROADMAP-HISTORY.md). Propostas de chats automáticos/contextuais foram substituídas e permanecem apenas em registros históricos. Estado abaixo separa implementação existente de trabalho comprovado nesta execução.

## M0 — Auditoria e caracterização

| Produtor | Evento / destinatário | Fonte e armazenamento | Canal / preferência / deduplicação | Autorização e evidência |
| --- | --- | --- | --- | --- |
| Fórum | Comentário ou primeira publicação de `ForumPost`; seguidores de `seguir_topico`, exceto autor | Fórum + Flag; `aculta_aviso` agregado por usuário/tipo/tópico; ledger idempotente por destinatário/tipo/ID de evento | Central e resumo diário; watermarks de revisão e chave por evento | Publicação, assinatura e acesso ao tópico; Kernel XK-C2 caracteriza ordem tardia, replay e retenção. |
| Fórum | Atualização do tópico; destinatários segundo fluxo existente | Node/revisão + Message Subscribe queue e/ou `aculta_aviso`, conforme tipo de evento | F-D21 desliga entrega duplicada de publicação; deduplicação por revisão | Worker revalida tópico publicado e URL/purpose; Unit existente, Runtime pendente conforme changelog. |
| User | Pedido/aceite de amizade; membro alvo da transição | `aculta_friendship` é fonte do workflow; Message para aviso | Message Notify em fila; política de e-mail/preferência existente; chave de transição | `FriendshipManager` valida transição/conta; Unit do subscriber e worker. |
| Private Message | Nova mensagem privada; participantes elegíveis exceto remetente | Private Message + Message; fila carrega ID da mensagem | E-mail mínimo via Message Notify, conforme preferência; chave por mensagem/destinatário | Revalida thread, bloqueio, destinatário e acesso no worker; Unit existente. |
| LMS adapter | Matrícula, certificado, conclusão, avaliação, feedback, conteúdo e prazo; titular/participantes elegíveis | Entidades/Group do LMS; Message como aviso | Drawer/contador por alter público; e-mail transacional ou digest segundo tipo; deduplicação por origem e versão | Adapter valida membership e permissões ao gerar/abrir; Unit e Kernel próprios; Runtime descrito no README LMS como smoke anterior. |
| Wiki/Revista/Commerce | Nenhum produtor central registrado nesta auditoria inicial | Workflows permanecem com módulos proprietários | Não inferir canal nem preferência | Inventariar entidades/serviços antes de criar adapter; sem funcionalidade inventada. |

A matriz é caracterização inicial baseada em código e evidências documentais, não homologação Runtime. Fechar contagem de tipos Message, workers/filas, Flags/leitura e digest como parte do M0 antes de migrar produtores.

**Existente:** caracterização Kernel XK-C2 cobre idempotência dos avisos atuais do Fórum; inventários em `INTEGRATIONS.md`, `MESSENGER.md`, documentação LMS/User e testes.
**Entrega:** matriz produtor → evento → destinatário → fonte → storage → canal → preferência → deduplicação → autorização → testes; localizar duplicidades e registrar estado codificado/testado/Runtime. Acrescentar caracterização antes de refatorar fluxo complexo.
**Aceite:** cada produtor e fila real tem contrato e evidência; sem presumir homologação.

## M1 — Estabilização 0.1.1 RC

**Nesta revisão (dev.88):** Comments, revisões de `ForumPost` e atualizações de tópico são publicados pelo envelope compartilhado e consumidos pelo agregador existente. O consumer resolve a origem por UUID/revisão, valida a chave canônica e reusa as revalidações de assinatura/acesso e o ledger transacional; o fluxo não cria storage nem mensagem paralela. Kernel MariaDB do pipeline: 22 testes / 489 assertions, incluindo round-trip pelo EventDispatcher para Comment, revisão de tópico e ForumPost, replay idempotente, revogação de acesso entre publicação e consumo e comparação do caminho direto com o event-driven no mesmo agregado, sem depreciações. Unit Messenger: 72 / 345; PHPCS e lint passam. A migração de storage não foi aplicada no Runtime e concorrência entre processos segue não simulada. Também removida a restrição de amizade para respostas em thread existente; bloqueios continuam. O conceito artificial de retenção `course` foi removido.
**Pendente:** cenários de amizade, bloqueio bilateral, autorização/IDOR, usuários inativos, grupos voluntários (incluindo fallback/teclado), retenção paginada e recuperação de falhas, transporte de teste e revisão visual autenticada. Não rodar limpeza em dados reais.
**Aceite:** gates estáticos e automatizados pertinentes aprovados; smoke visual/Runtime identificado separadamente antes de RC conforme ciclo.

## M2 — API central de eventos (0.2.0)

Implementado: `MessengerNoticeEvent` e `MessengerNoticePublisherInterface` publicam envelope pequeno via EventDispatcher Drupal, com metadata validada e sem persistência própria. Fórum adapta Comments, primeira publicação de `ForumPost` e revisão de tópico; o consumer mantém `aculta_aviso`, revalida assinatura/acesso e usa o ledger existente. O adapter LMS já consome quatro tipos e encaminha ao Message existente pelo manager idempotente. A cobertura restante inclui integração de amizade, eventos LMS restantes, testes comparativos em Runtime e canais da central/e-mail. Manter interfaces pequenas, DI, payload mínimo e sem novo subsistema de storage.

**Aceite:** testes de contrato/idempotência/ACL; eventos repetidos não duplicam; opt-out não remove aviso do site; adapters opcionais ausentes não causam erro.

## M3 — Fórum e User (0.2.0)

Fórum conserva `seguir_topico`, avisos atuais/agregação por usuário-tipo-tópico, leitura, digest e F-D21; valida Domain, publicação, revisão, acesso, aprovação tardia, republicação e movimentação. Não usar publicação de Message Subscribe em paralelo e nunca iniciar conversas por interação no Fórum. User conserva workflow/entidade de amizade; o adapter do Messenger converte pedido/aceite em `MessengerNoticeEvent` e o consumer revalida estado, origem e destinatário antes de persistir no `Message` já existente. Unit Messenger: 72 testes / 345 assertions; Kernel integrado do caminho User continua pendente.

## M4 — LMS (0.2.0)

Matrícula, certificado, conclusão e avaliação já publicam pelo envelope compartilhado nesta execução; o consumer mantém Message, fila e digest existentes. Evoluir `aculta_lms_messenger` como adapter de feedback, submissão, liberação e prazos. Revalidar permissão na geração, entrega e abertura; manter cursos, atividades, Groups e matrículas sob ownership LMS. Sem chat de turma ou caixa educacional paralela. Migrar os produtores que ainda chamam o manager diretamente e testar storage, idempotência, digest/transacional e revogação.

## M5 — Wiki, Revista e demais (0.3.0)

Inventariar primeiro entidades e workflows existentes. Adicionar somente produtores com destinatário e autorização determináveis, por exemplo revisão/publicação editorial ou atualização seguida. Não inventar workflow. Casos sem decisão de produto ficam documentados com bloqueio específico, sem interromper trabalho independente.

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
- M2: parcial; envelope/publisher e adapters Fórum/User/LMS implementados nos eventos descritos, com Kernel round-trip de ForumPost, Comment e revisão de tópico; amizade possui cobertura Unit producer/consumer, mas Kernel integrado, eventos LMS restantes e integração comparativa/digest ampla pendentes.
- M3–M7: planejadas nesta referência; conclusão depende de auditoria e implementação/testes registrados, fase a fase.
- Runtime: não homologado nesta execução.
