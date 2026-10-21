# ACULTA LMS Messenger

Adapter opcional entre `aculta_lms` e o sistema de avisos global do
`aculta_messenger`. Não cria caixa, entidade ou storage paralelo: persiste os
avisos pessoais em Message, acrescenta-os pela extensão pública do Messenger e
usa Message Notify para e-mails transacionais.

## Estado

Versão `0.1.0-dev.12`. Matrícula, certificado, conclusão, avaliação publicada,
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

O módulo está em Dev e está habilitado no Runtime de teste. O Kernel comprova
persistência e deduplicação em Message real; a compilação e instanciação do
manager/worker também passaram no Runtime. Foram importadas isoladamente as
cinco configurações da relação de prazo por turma. O Runtime não tem turmas LMS
nem prazos cadastrados, então o fluxo acionado por evento/Cron e uma mensagem
visível ainda não foram exercitados. Revisão visual da gaveta segue pendente. A
entrega de digest pelo serviço compartilhado foi exercitada com
providers educacionais no Unit.

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

Testes unitários locais do subscriber e gates do Portal são registrados no
CHANGELOG; o smoke de navegador/Runtime continua DEFERRED.
