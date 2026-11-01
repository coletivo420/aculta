# Política de envio (F5)

Status: implementada em 0.1.0-dev.11. Hierarquia global → tipo, com ajustes em plugins. Ajuste por instância de mensagem
fica para depois.

## Hierarquia

1. `aculta_mailer.policy:global`: vale para todos os tipos.
2. `aculta_mailer.policy:types.<tipo>`: prevalece sobre o global, chave por chave.

Cada camada é aplicada em sequência. Um valor inválido é ignorado e não apaga o valor da camada anterior.

## Ajustes (plugins `AcultaMailerAdjuster`)

| Chave | Valor aceito | Efeito |
|---|---|---|
| `transport` | `smtp` ou `smtp2go` | Transporte do envio. Só vale se o chamador não escolheu um. |
| `reply_to` | e-mail válido | Remetente de resposta (Reply-To). |
| `priority` | inteiro de 1 (alta) a 5 (baixa) | Cabeçalho `X-Priority`. |
| `skip` | booleano | Suspende o envio: registro `cancelled`, sem envio e sem retry. |

Novos ajustes entram como plugins: classe com `#[AcultaMailerAdjuster]` em `src/Plugin/AcultaMailerAdjuster`.

## Efeito no envio

- `skip`: `AcultaMailer::deliver()` devolve `SendResult::cancelled()`; o worker não reagenda.
- `transport`: a fila grava `transport = NULL` na primeira tentativa, e a política decide. Retries carregam o transporte
  explícito, para o failover funcionar.
- `reply_to` e `priority`: vão como `reply-to` e `params.priority` ao `mail()` do Core, e o hook grava `X-Priority`.

## Configuração

Exportável (`config/sync`), sem credenciais. Sem valores, a política é vazia e o envio segue como antes. Schema em
`config/schema/aculta_mailer.policy.schema.yml`.

## Limites

- Sem interface de edição nesta versão: a política é editada por configuração.
- Ajuste por instância de mensagem (por exemplo, prioridade de um envio específico) fica para fase própria.
