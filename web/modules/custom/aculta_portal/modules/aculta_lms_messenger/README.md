# ACULTA LMS Messenger

Adapter opcional entre `aculta_lms` e o sistema de avisos global do
`aculta_messenger`. Não cria caixa, entidade ou storage paralelo: persiste os
avisos pessoais em Message, acrescenta-os pela extensão pública do Messenger e
usa Message Notify para e-mails transacionais.

## Estado

Versão `0.1.0-dev.4`. Matrícula, certificado, conclusão, avaliação publicada e
resposta aberta pendente geram avisos no painel multidomínio. Matrícula e
certificado têm e-mail transacional mínimo; conclusão, avaliação e resposta
pendente entram no resumo diário opt-in. Um link revalida acesso e encaminha ao
purpose correto. Republicações de avaliação usam versão opaca para deduplicar.
Opt-out limpa só o e-mail pendente; os avisos no site permanecem.

O módulo está em Dev e não foi habilitado no Runtime nesta entrega. Ainda faltam
outros eventos LMS, testes de integração e inspeção visual da gaveta.

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
