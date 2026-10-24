# Roadmap — ACULTA Messenger

Ciclo: [Dev → RC → Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md). Marcos mantidos: 0.1.1 RC, 0.2.0 API e integrações LMS/User/Fórum, 0.3.0 demais módulos/experiência e 1.0.0 estabilidade. Sem nova PR, merge ou deploy. Runtime/Alpha só são declarados com evidência. Histórico detalhado em [ROADMAP-HISTORY.md](ROADMAP-HISTORY.md).

A decisão normativa está em [ARCHITECTURE.md](ARCHITECTURE.md): Messenger centraliza mensagens privadas, avisos e orquestração de e-mail; Mailer mantém transporte e infraestrutura; módulos de domínio publicam eventos e preservam seus workflows. Chats contextuais foram substituídos e permanecem apenas no histórico.

## M0 — Auditoria e caracterização

| Produtor | Eventos/destinatário | Fonte e armazenamento | Canais/deduplicação | Autorização/evidência |
| --- | --- | --- | --- | --- |
| Fórum | Comment publicado/aprovado, primeira publicação ForumPost, revisão pública; seguidores elegíveis exceto autoria | Comment/ForumPost/Flag; `aculta_aviso` e ledger atual | Site obrigatório; e-mail off/direto/digest; agregação por conta/tipo/tópico | Access/publicação/Domain revalidados; Kernel anterior 23/515; Runtime pendente. |
| LMS | Matrícula, certificado, conclusão, avaliação/feedback, conteúdo e prazo | Entidades/Group LMS; Message | Site obrigatório; preferência off/direto/digest | Consumer central e publisher LMS; Kernel 2/18 PASS nesta revisão; Runtime pendente. |
| Wiki | Revisão editorial e resultado de proposta, destinatários elegíveis do workflow | Workflow Wiki; Message | Site obrigatório; preferência off/direto/digest | Consumer central e publisher Wiki; Kernel 2/23 PASS nesta revisão; Runtime pendente. |
| Loja | `store.order_placed` para cliente ativo conforme tipo de pedido Loja | Drupal Commerce Order; Message | Site obrigatório; categoria `loja` off/direto/digest | Consumer central e publisher Commerce; Kernel 2/23 nesta revisão; recibo para convidados/fallback. |
| Doações | `donation.order_placed` e `donation.payment_approved`; o segundo ocorre uma vez para cada cobrança aprovada, inclusive recorrente | Commerce Order/Payment; Message | Site obrigatório; categoria `doacao` off/direto/digest | Consumer central; publisher `aculta_donation`; Kernel 2/22 PASS para persistência/dedupe e acesso; recibo Commerce suprimido apenas após publicar pedido para conta registrada, com fallback em falha/convidado. Runtime e sandbox pendentes. |
| User | Pedido/aceite de amizade | Entidade/workflow `aculta_friendship`; Message | Message Notify e preferência existente; idempotência por transição | `FriendshipManager`; Kernel integrado pendente. |
| Private Message | Nova mensagem privada; destinatários elegíveis exceto remetente | Private Message/Message | Notificação nativa e Mailer segundo preferência | Thread access, bloqueio e destinatário revalidados; Unit existente. |

Esta é a matriz técnica produtor → evento → destinatário → fonte → storage → canal → preferência → deduplicação → autorização → testes. Marca código e testes, não homologação de Runtime. Revista e outros produtores só entram após confirmar workflow e destinatários reais.

## M1 — Estabilização 0.1.1 RC

Concluir privacidade de conversas individuais/grupo, bloqueios, acesso/IDOR, CSRF, Flood, cache privado, retenção paginada e recuperável, mensagens de amizade/privadas sob transporte controlado e UI acessível. Conversas são voluntárias; seguir ou interagir com conteúdo nunca cria thread. Nenhuma limpeza real será executada.

**Estado:** “somente amigos” restringe novas conversas; respostas em threads existentes continuam válidas. Regra artificial `course` removida. Demais gates permanecem parciais.

## M2 — API central de eventos (0.2.0)

`MessengerNoticeEvent` e `MessengerNoticePublisherInterface` usam EventDispatcher Drupal, payload mínimo e sem novo storage. Consumers LMS/Wiki/Loja consolidados no núcleo; Fórum mantém `aculta_aviso` e o ledger existentes. Confirmar envelope, idempotência sob concorrência, preferências e módulos opcionais.

Nesta revisão, publishers LMS, Wiki e Loja ficaram nos módulos de domínio; consumers, managers, hooks, rotas de abertura, workers e config Message estão em `aculta_messenger`. Os antigos `*_messenger` perderam classes, serviços, rotas, testes e configurações executáveis. Manifests ocultos remanescentes só preservam IDs de extensão habilitados; não executam fluxos e aguardam uninstall operacional autorizado.

## M3 — Fórum e User (0.2.0)

Preservar `seguir_topico`, F-D21/F-D42, `aculta_aviso`, leitura, digest e amizades. Revalidar publicação, revisão, Domain, acesso, autoria e assinatura; não reativar Message Subscribe nem iniciar conversas por interação editorial. Kernel User e cenários de aprovação tardia/revisões fora de ordem permanecem pendentes.

## M4 — LMS (0.2.0)

LMS publica eventos educacionais; Messenger persiste avisos e define canais. Aviso no site é obrigatório. Membro e administradores configuram e-mail desligado, direto ou digest/resumo, sujeito às regras do Mailer. Cursos, matrículas e Groups continuam no LMS; sem chat de turma ou inbox paralela. Unit integrada e Kernel LMS PASS; completar revogação e Runtime.

## M5 — Wiki, Loja, Doações e demais (0.3.0)

Consumers Wiki/Loja/Doações centralizados em Message. Site obrigatório; e-mail off/direto/digest; Mailer transporta. Kernels LMS/Wiki/Loja anteriores 6/64 PASS; Donation Kernel 2/22 PASS, incluindo deduplicação, pagamentos distintos e rejeição de pedido inacessível. Donation direct Mailer worker 2/27 PASS, cobrindo envio imediato e opt-out revalidado. Recibo nativo permanece para convidados e fallback. Completar digest/revogação, concorrência, update/configuração instalada e Runtime. Revista segue após inventário de workflows/destinatários reais.

## M6 — Experiência e administração (0.3.0)

Evoluir central existente com filtros, agrupamentos, leitura, assinaturas, preferências, estados vazios/erro/carregamento e responsividade. Distinguir Avisos de Mensagens. Autorização/consulta/persistência ficam no Messenger; ACULTA420 cuida da apresentação.

## M7 — Hardening e lançamento (1.0.0)

Validar retry/locks, concorrência, retenção, filas, N+1/paginação, cache por usuário/contexto, revogação, logs sem PII, compatibilidade de módulos opcionais, schema/update, e-mails e acessibilidade. O resumo combinado usa lock persistente por destinatário (lease de 120 s) além do intervalo mínimo de envio; a proteção entre processos/servidores e a recuperação após lease expirar continuam pendentes. Workers LMS/Wiki/Loja/Doações adquirem lock antes de carregar o Message e contam exceções do Mailer nos limites de tentativas. O Fórum persiste correlation UUID por evento no ledger para manter a correlação Mailer nas tentativas; validar ainda o ciclo operacional de retry do Queue API. Sem serviço externo ou WebSocket sem necessidade comprovada.

## Evidências desta revisão — 2026-10-24

- Unit Messenger + integrações e publishers: 126 testes / 657 assertions PASS.
- Kernels MariaDB LMS/Wiki/Loja: 6 testes / 64 assertions PASS; deprecações de descoberta de Message vieram de Core/contrib.
- PHPCS Drupal/DrupalPractice e lint das classes migradas aprovados após fechamento.
- Runtime Homelab (`toca`, Drupal 11.4.10, `aculta_runtime`): cache rebuild completo após correção dos handlers de rota e conflito de propriedades FormBase; smoke HTTP: principal, Fórum, cursos, Wiki e Loja 200; Conta 302 esperado. Sem update de banco/configuração, e-mail real ou alteração de dados.
- Runtime é smoke de desenvolvimento, não homologação Alpha. PHP CLI é 8.4.27; não comprova ambiente PHP 8.5 exigido no desenvolvimento.
- Doação: Unit ponte financeira 3/21, provider opcional (incluído em Units Messenger 92/455) e worker Mailer 2/27 PASS; Kernel MariaDB 2/22 PASS. Rodada Forum/Wiki/Donation 29/605 PASS com duas deprecações de discovery Message. PHPCS, lint, oito YAMLs de configuração e PROJECT-STATUS-INDEX PASS. Não houve Runtime, transporte real ou teste financeiro sandbox.
- Digest combinado: lock persistente por UID adquirido antes de validar a janela e liberado em `finally`, evitando que dois cron jobs enviem simultaneamente o mesmo resumo durante a lease. Unit de preferência/contenda e integração LMS: 4 testes / 30 assertions PASS; Kernel MariaDB ForumNoticeIdempotency: 25 testes / 560 assertions PASS. Ainda falta prova com processos independentes e envio Mailer lento/falhando.
- E-mail direto: workers LMS/Wiki/Loja recarregam Message após adquirir o lock e ignoram estado enviado ou limite de tentativas esgotado; Wiki/Loja contam exceções do Mailer como falhas limitadas. Unit Messenger e integrações: 123 testes / 714 assertions PASS, incluindo replay, tentativas e transporte lançado como exceção. PHPCS Drupal/DrupalPractice e PHP lint PASS. Sem teste com SMTP real ou workers concorrentes em processos separados.
- Retry Mailer: LMS e Doações agora persistem exceções de transporte como falha, reencaminham apenas abaixo do limite e deixam `failed` terminal após a última tentativa; Doações também encerra avisos antigos ainda `pending` com tentativas esgotadas. Suíte Unit Messenger + adapters: 125 testes / 744 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS. O fluxo real do Queue API e do transporte Mailer continua sem homologação Runtime.
- Fórum/Mailer: `ForumNoticeEmailWorker` usa correlation UUID estável gerado ao reivindicar o evento, armazenado no ledger `aculta_messenger_forum_notice_event`; `update_10005` adiciona a coluna sem recriar nem migrar o ledger. Exceções do Mailer atualizam o estado e respeitam o máximo de tentativas. Unit do worker 2/33, Kernel da coluna/update/retry estável 2/39, Kernel completo 26/581 e Units Messenger/adapters 126/765 PASS. A atualização não foi aplicada no Runtime.
- Digest/Mailer: `AvisosResumo` usa `deliver()` tipado; cancelamento por política administrativa descarta somente as pendências de e-mail e mantém os avisos no site, enquanto falhas temporárias permanecem retryable. Coberto junto à matriz de preferências por origem; Units Messenger/adapters 129/806 e Kernel ForumNoticeIdempotency 26/581 PASS.
- Amizades/fila: `FriendshipNoticeEmailWorker` serializa por Message, recarrega o estado após lock e mantém retries limitados. Unit do worker 5/61; Unit Messenger/adapters 130/813 PASS. Fila do Runtime ainda não homologada.
- LMS/idempotência: `LearningNoticeManager::record()` serializa consulta/criação e enqueue por destinatário, evento, origem e versão. Replays pendentes não enfileiram de novo; falha de enqueue permite recuperação sem duplicar o Message. Unit 1/8; Kernel LMS 2/22 PASS, incluindo enqueue único após replay (duas deprecações de `message` no Core/contrib); Unit Messenger/adapters 131/821 PASS. Runtime/fila não homologados.
- Amizades/idempotência: `FriendshipNoticeSubscriber` serializa a busca/criação por chave idempotente e espera/reconsulta durante um replay concorrente. O worker também mantém lock de entrega por Message. Unit subscriber 5/53; Unit Messenger/adapters 132/837 PASS. Runtime/fila não homologados.
