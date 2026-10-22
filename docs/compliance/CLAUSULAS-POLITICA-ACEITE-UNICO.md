# Cláusulas propostas para a Política de Privacidade (aceite único)

Rascunho para publicação pelo responsável. **Não publicado.** Decisão do responsável (2026-10-21): o consentimento
embutido no aceite único atende o propósito jurídico do projeto; não há revisão jurídica adicional pendente sobre esse ponto. Enquanto o texto não estiver publicado, as funções abaixo permanecem desligadas
(ver `docs/portal/ACEITE-UNICO.md`).

## 1. Acompanhamento do progresso nos cursos

Para cursos da ACULTA, registramos a situação de cada aluno em cada curso (não iniciado, em andamento, aguardando
avaliação, aprovado ou reprovado), por dia, para acompanhar a turma e o próprio progresso.

- **Finalidade:** acompanhamento do curso pela equipe educadora e pelo aluno.
- **Dados:** identificação da conta, curso e situação. Não registramos respostas, textos enviados nem notas por
  atividade nesta série.
- **Quem vê:** o aluno, sobre o próprio dado; a equipe com permissão para avaliar a turma. Nunca em página pública.
- **Prazo:** 90 dias contados do dia do registro. Excluir a conta apaga esses registros.
- **Base:** aceite único dos Termos e da Política de Privacidade.

## 2. Rastreamento de e-mails da ACULTA

Podemos registrar se um e-mail enviado pela ACULTA foi aberto e se algum link dele foi clicado, para medir se as
mensagens chegam e são lidas.

- **Finalidade:** medir a entrega e a leitura de mensagens transacionais e de avisos.
- **Dados:** identificador da mensagem, data e tipo de evento. O endereço do destinatário não fica em claro.
- **Prazo:** 90 dias. Excluir a conta apaga esses registros.
- **Base:** aceite único dos Termos e da Política de Privacidade.

## 3. Acesso a arquivos de mídia

Registramos, por arquivo, quantas vezes um arquivo privado de mídia foi aberto, sem identificar quem abriu. Serve
para saber quais materiais são usados. Os registros ficam 90 dias. Este tratamento faz parte do consentimento geral
ao aceitar os Termos e a Política.

## 4. Exclusão da conta

Ao excluir a conta, apagamos os dados pessoais que a ligam às funções acima, no prazo de até 90 dias. Os dados de
evidência do aceite (data, versão do texto e identificadores) são mantidos como prova do aceite pelo prazo legal.

## 4. Cookies de visitante sem conta

O banner de cookies para visitantes sem conta registra a escolha em cookie no navegador. Não há conta, por isso essa
escolha não entra no registro de aceites. Ver a exceção em `docs/portal/ACEITE-UNICO.md`.

## 5. Aviso de revisão da Wiki (quando ativa)

Quando um verbete entra em revisão editorial, avisamos as caixas de e-mail
editoriais cadastradas pela Associação para que a curadoria o revise.

- **Finalidade:** curadoria editorial do conteúdo publicado na Wiki.
- **Dados:** título do verbete, estado editorial e link de edição. O aviso não
  traz o nome nem o identificador de quem editou.
- **Quem vê:** apenas as caixas editoriais cadastradas em
  `/painel-administrativo/aculta/wiki`. Não é enviado a visitantes nem a pessoas
  que contribuíram.
- **Prazo:** o registro do envio segue o prazo do `aculta_mailer` (90 dias, com
  o endereço guardado só como identificador criptográfico). O conteúdo do
  verbete segue o prazo em que permanecer publicado.
- **Base:** interesse legítimo na curadoria editorial (art. 7º, IX), já
  declarado na política de privacidade para a Wiki.
- **Estado:** desligado por padrão e sem destinatários enquanto a tela não for
  salva.

## Itens para o responsável decidir antes da publicação

- Confirmar o prazo de 90 dias para cada função, ou ajustar nas configurações do `aculta_statistics`.
- Confirmar a redação das cláusulas antes da publicação.
- Decidir se a função 2 é ligada: a constante `AcultaUserAgreementTrackingConsent::FUNCTION_PUBLISHED` só deve passar a
  verdadeira depois da publicação.
