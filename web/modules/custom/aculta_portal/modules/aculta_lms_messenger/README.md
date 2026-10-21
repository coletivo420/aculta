# ACULTA LMS Messenger

Adapter opcional entre `aculta_lms` e o sistema de avisos global do
`aculta_messenger`. Não cria caixa, entidade ou storage paralelo: persiste os
avisos pessoais em Message, acrescenta-os pela extensão pública do Messenger e
usa Message Notify para e-mails transacionais.

## Estado

Versão `0.1.0-dev.1`. A matrícula efetivada e a emissão de certificado geram
aviso no painel de avisos da barra multidomínio. Um link revalida acesso à
matrícula ou ao certificado, marca o aviso como lido e encaminha para o purpose
correto. O e-mail usa somente resumo e link. Deduplicação usa UUID da origem e
destinatário.

O módulo está em Dev e não foi habilitado no Runtime nesta entrega. Ainda faltam
resumo diário opt-in para outros eventos, cobertura dos eventos LMS restantes,
testes de integração e inspeção visual da gaveta.

## Contratos

- Matrícula/certificado usam e-mail transacional sem confirmação de endereço,
  conforme decisão do produto.
- Avisos no site são sempre mostrados; o adapter usa os hooks
  `aculta_messenger_personal_notices` e
  `aculta_messenger_personal_notice_count` do Messenger.
- Acesso a matrícula é revalidado por membership atual, turma, curso e
  permissões. Acesso a certificado confere o titular e a verificação pública.
- Mensagens não expiram. Os valores de origem guardam somente UUID e tipo do
  evento; não guardam resposta, nota ou arquivo.
- A rota de abertura é `/avisos/educacionais/{message}/abrir` em `account` e
  redireciona com URL preparada pelo `DomainPurposeManager`.

## Validação

Testes unitários locais do subscriber e gates do Portal são registrados no
CHANGELOG; o smoke de navegador/Runtime continua DEFERRED.
