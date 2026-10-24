# ACULTA LMS Messenger

Adapter opcional entre `aculta_lms` e o sistema de avisos global do
`aculta_messenger`. Não cria caixa, entidade ou storage paralelo: persiste os
avisos pessoais em Message, acrescenta-os pela extensão pública do Messenger e
usa a API `AcultaMailerInterface` para e-mails transacionais.

## Estado

Versão 0.1.0-dev.18. Todos os produtores LMS — matrícula, certificado,
conclusão, avaliação, submissão, liberação de curso/aula/atividade, feedback e
prazo — publicam `MessengerNoticeEvent`. O adapter educacional continua
responsável por resolver destinatários conforme matrícula, publicação,
permissões e contexto; o consumer encaminha os eventos ao
`LearningNoticeManager`, que mantém a persistência Message, deduplicação, fila,
digest e revalidação no acesso. Os e-mails transacionais usam a API
`AcultaMailerInterface` e tipo de template registrado pelo adapter; o worker
mantém o lock, revalida o acesso e repete falhas sem chamar `message_notify`
diretamente. Não existe caminho direto de gravação paralelo para os produtores
migrados nem storage novo.

Matrícula e certificado têm e-mail transacional mínimo; conclusão, avaliação e
resposta pendente entram no resumo diário opt-in. Um link revalida acesso e
encaminha ao purpose correto. Republicações de avaliação usam versão opaca
para deduplicar. Opt-out limpa só o e-mail pendente; os avisos no site
permanecem.
Matrícula na turma é validada pela permissão `take course` herdada no curso pai
por `lms_classes`, além da membership ativa na turma.

Avisos que dependem de uma resposta LMS revalidam as referências
`Answer → LessonStatus → CourseStatus → Course` por campos de referência. Se a
remoção assíncrona do LMS deixar a resposta sem um desses contextos, o aviso é
suprimido durante a janela pendente em vez de provocar erro no contador, gaveta
ou digest. Aviso de conclusão também resolve a referência ao curso diretamente
do campo: se o curso foi removido, a notificação não chama o getter tipado nem
gera erro 500. A suíte Unit LMS/Messenger passou com 86 testes / 460 assertions
(8 depreciações PHPUnit); Kernel MariaDB: 23 / 730 assertions PASS (95
depreciações Core/contrib). PHPCS Drupal/DrupalPractice PASS.

Unit LMS/Messenger: 24 testes / 137 assertions PASS; Kernel SQLite da
persistência Message e do round-trip/replay do evento: 2 testes / 18 assertions
PASS, com duas depreciações upstream de Message. PHPCS Drupal/DrupalPractice
PASS. O módulo está em Dev e está habilitado no Runtime de teste. Kernel comprova
persistência e deduplicação em Message real. Smoke com turma temporária no
Runtime confirmou aviso persistido, aviso pessoal integrado, enqueue pelo hook
de Cron e persistência do lembrete pelo worker; todos os dados temporários e as
filas foram limpos, sem envio de e-mail. As cinco configurações da relação de
prazo por turma foram importadas isoladamente. O hook e o worker foram chamados
diretamente; não se aguardou Cron agendado. Revisão visual/acessível da gaveta
segue pendente. A entrega de digest pelo serviço compartilhado foi exercitada
com providers educacionais no Unit.

Tentativa visual em Firefox headless isolado em 2026-10-21 chegou à tela do
antigo acordo geral `/agreement`; o módulo contrib foi desinstalado depois e o
fluxo passou ao ledger `aculta_user_agreement`. Não foi submetido aceite em
nome da conta. A gaveta autenticada não pôde ser revisada; fixtures e perfil
headless foram removidos. Repetir com sessão de teste autenticada após concluir
o aceite vigente no fluxo ACULTA.

## Contratos

- Matrícula/certificado usam e-mail transacional sem confirmação de endereço,
  conforme decisão do produto.
- Avisos no site são sempre mostrados; o adapter usa os hooks
  `aculta_messenger_personal_notices` e
  `aculta_messenger_personal_notice_count` do Messenger.
- Acesso a matrícula é revalidado por membership atual, turma, curso e
  permissões. Acesso a certificado confere o titular e a verificação pública.
- Mensagens não expiram. Os valores de origem guardam somente UUID, tipo e
  versão opaca do evento; não guardam resposta, nota ou arquivo.
- A rota de abertura é `/avisos/educacionais/{message}/abrir` em `account` e
  redireciona com URL preparada pelo `DomainPurposeManager`.

## Validação

Testes unitários locais, Kernels, smoke de Runtime e gates do Portal são
registrados no CHANGELOG. Revisão visual autenticada da gaveta: DEFERRED.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-01/12: permanece adapter LMS → aculta_messenger (Message/Message Notify), sem segunda caixa de entrada. Garantir deduplicação, versões de eventos e revalidação de Group, avaliações e consentimento ao entregar.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**
