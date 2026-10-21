# ACULTA LMS Messenger

Adapter opcional entre `aculta_lms` e o sistema de avisos global do
`aculta_messenger`. Não cria caixa, entidade ou storage paralelo: persiste os
avisos pessoais em Message, acrescenta-os pela extensão pública do Messenger e
usa Message Notify para e-mails transacionais.

## Estado

Versão `0.1.0-dev.15`. Matrícula, certificado, conclusão, avaliação publicada,
resposta aberta pendente, conteúdo liberado, retorno do educador e definição ou
alteração de prazo por atividade/turma geram avisos no painel multidomínio. O
prazo fica em relação Group específica da turma, não na atividade LMS. Adotou-se
provisoriamente a recomendação de avisar ao salvar e lembrar na janela anterior
de 24 horas; cron enfileira UUID/version opacos e o worker revalida o estado,
sem e-mail transacional. Matrícula e
certificado têm e-mail transacional mínimo; conclusão, avaliação e resposta
pendente entram no resumo diário opt-in. Um link revalida acesso e encaminha ao
purpose correto. Republicações de avaliação usam versão opaca para deduplicar.
Opt-out limpa só o e-mail pendente; os avisos no site permanecem.
Matrícula na turma é validada pela permissão `take course` herdada no curso pai
por `lms_classes`, além da membership ativa na turma.

Avisos que dependem de uma resposta LMS revalidam as referências
`Answer → LessonStatus → CourseStatus → Course` por campos de referência. Se a
remoção assíncrona do LMS deixar a resposta sem um desses contextos, o aviso é
suprimido durante a janela pendente em vez de provocar erro no contador, gaveta
ou digest. Aviso de conclusão também resolve a referência ao curso diretamente
do campo: se o curso foi removido, a notificação não chama o getter tipado nem
gera erro 500. A suíte Unit LMS/Messenger passou com 84 testes / 445 assertions
(8 depreciações PHPUnit); Kernel MariaDB: 23 / 722 assertions PASS (95
depreciações Core/contrib). PHPCS Drupal/DrupalPractice PASS.

O módulo está em Dev e está habilitado no Runtime de teste. Kernel comprova
persistência e deduplicação em Message real. Smoke com turma temporária no
Runtime confirmou aviso persistido, aviso pessoal integrado, enqueue pelo hook
de Cron e persistência do lembrete pelo worker; todos os dados temporários e as
filas foram limpos, sem envio de e-mail. As cinco configurações da relação de
prazo por turma foram importadas isoladamente. O hook e o worker foram chamados
diretamente; não se aguardou Cron agendado. Revisão visual/acessível da gaveta
segue pendente. A entrega de digest pelo serviço compartilhado foi exercitada
com providers educacionais no Unit.

Tentativa visual em Firefox headless isolado em 2026-10-21 chegou à tela do
acordo geral `/agreement`; a configuração exige aceite por sessão. Não foi
submetido aceite em nome da conta. A gaveta autenticada não pôde ser revisada;
fixtures e perfil headless foram removidos. Repetir com uma sessão de teste já
autenticada e com o acordo vigente concluído pelo próprio responsável.

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
