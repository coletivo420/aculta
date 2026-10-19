# Avisos e notificações

Status: **política aprovada pelo responsável (2026-10-16)**. Implementação prevista na F5.2 do Fórum.
Escopo: avisos dentro do site e e-mails de participação (respostas em tópicos seguidos). Não cria sistema novo.

O texto original foi definido para tópicos do Forum contrib. A reconstrução
aprovada em 2026-10-19 mantém `aculta_messenger` como único dono de entrega e
estende o contrato a contextos de comentário. Destinatários, agrupamento e
opt-in devem ser definidos por contexto; todo envio revalida publicação e acesso
atual. Discussões LMS/Wiki não entram em avisos públicos. Ver
`aculta_forum/docs/CONTEXTS.md`.

## 1. Regra geral

- Toda notificação usa o **sistema existente**, nos dois canais: o **sistema de mensagens** do `aculta_messenger`
  (caixa de mensagens, contadores do `MessengerCounts`, modelos `aculta_msg_*` com tentativas e estado de entrega) e os
  **avisos do site** (`aculta_notice`, com flags de leitura). Não criar tabela, fila ou canal paralelo.
- Notificações do mesmo tópico são **agrupadas**: uma entrada por tópico, com contador ("3 novas respostas"), em vez de
  uma entrada por resposta. Isso economiza espaço na tela e no e-mail.
- Avisos gerais do site com conteúdo semelhante também são agrupados, com contador, pela mesma regra de chave.

## 2. Aviso no site (obrigatório)

- Quem segue um tópico (flag `seguir_topico`) **recebe o aviso no site**, sem poder desligar. O aviso aparece na caixa
  de mensagens e no contador da barra do site.
- O aviso mostra o tópico, o número de respostas novas desde a última leitura e o link para o tópico.
- Ao abrir o tópico, o contador do aviso é zerado para aquela conta (leitura por flag, como os avisos atuais).
- Um aviso por tópico por conta. Respostas novas somam ao contador do mesmo aviso.

## 3. Resumo por e-mail (opt-in)

- O e-mail é **opt-in**: a conta liga o recebimento na área de conta. Desligado por padrão.
- Só entram contas com o e-mail confirmado (`EMAIL-CONFIRMATION-POLICY.md`).
- O envio é um **resumo diário**, pela fila existente do messenger, e **só quando houve atualização** desde o último
  resumo. Sem novidade, não há e-mail.
- O resumo agrupa por tópico: uma linha por tópico seguido, com o número de respostas novas e o link. Nunca um e-mail
  por resposta.
- Cada resposta entra em no máximo um resumo por conta (deduplicação pelo identificador do comentário).

## 4. Agrupamento geral: tipo + assunto

- Avisos e mensagens do sistema são agrupados pela chave **tipo + assunto**. Itens com a mesma chave viram uma entrada
  com contador, e não várias.
- Para respostas de fórum, o assunto é o tópico: é a chave "tipo = resposta de fórum, assunto = tópico".
- O mesmo agrupamento vale para o resumo por e-mail, que segue a mesma chave.

## 5. Limites

- Não há notificação para conteúdo restrito que a conta não pode ver (fóruns restritos, `PAPEIS-PARTICIPACAO.md`).
- Não há notificação para conteúdo histórico importado (autorias legadas não são contas).
- Não há notificação de conquista por este caminho (`aculta_game` tem regras próprias).

## 6. Dúvidas abertas

Nenhuma no momento.
