# Avisos e notificações

Status: **política aprovada pelo responsável (2026-10-16)**. Implementação prevista na F5.2 do Fórum.
Escopo: avisos dentro do site e e-mails de participação (respostas em tópicos seguidos). Não cria sistema novo.

## 1. Regra geral

- Toda notificação usa o **sistema existente**: avisos do site (`aculta_notice`, contadores do `MessengerCounts` e
  flags de leitura) e a fila de e-mail do `aculta_messenger` (modelos de mensagem `aculta_msg_*`, com tentativas e
  estado de entrega). Não criar tabela, fila ou canal paralelo.
- Notificações do mesmo tópico são **agrupadas**: uma entrada por tópico, com contador ("3 novas respostas"), em vez de
  uma entrada por resposta. Isso economiza espaço na tela e no e-mail.
- Avisos gerais do site com conteúdo semelhante também são agrupados, com contador, pela mesma regra de chave.

## 2. Aviso no site (obrigatório)

- Quem segue um tópico (flag `seguir_topico`) **recebe o aviso no site**, sem poder desligar. Ele não depende de
  e-mail confirmado além do que o próprio tópico exige.
- O aviso mostra o tópico, o número de respostas novas desde a última leitura e o link para o tópico.
- Ao abrir o tópico, o contador do aviso é zerado para aquela conta (leitura por flag, como os avisos atuais).
- Um aviso por tópico por conta. Respostas novas somam ao contador do mesmo aviso.

## 3. E-mail (opt-in)

- O e-mail é **opt-in**: a conta liga o recebimento na área de conta. Desligado por padrão.
- Só entram contas com o e-mail confirmado (`EMAIL-CONFIRMATION-POLICY.md`).
- Os e-mails são enviados pela fila existente, **agrupados por tópico**: um e-mail por tópico por janela de envio,
  com todas as respostas novas daquela janela. Nunca um e-mail por resposta.
- Cada resposta entra em no máximo um e-mail por conta (deduplicação pelo identificador do comentário).

## 4. Avisos gerais agrupados

- Avisos do site com conteúdo semelhante (mesmo tipo e mesmo assunto) viram um aviso com contador, em vez de vários.
- A chave de agrupamento é definida por tipo de aviso. Para respostas de fórum, a chave é o tópico.

## 5. Limites

- Não há notificação para conteúdo restrito que a conta não pode ver (fóruns restritos, `PAPEIS-PARTICIPACAO.md`).
- Não há notificação para conteúdo histórico importado (autorias legadas não são contas).
- Não há notificação de conquista por este caminho (`aculta_game` tem regras próprias).

## 6. Dúvidas abertas

1. Janela de envio do e-mail agrupado: imediato por tópico, diário ou por hora?
2. Agrupamento de avisos gerais: a chave é só o tipo, ou tipo mais assunto?
