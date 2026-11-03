# Medição de carga MariaDB para xAPI

**Data:** 2026-11-03
**Ambiente:** Homelab de desenvolvimento; banco descartável separado.
**Escopo medido:** persistência SQL da tabela `aculta_lms_xapi_statement`;
validação xAPI, emissão de token, HTTP e capacidade de produção não foram
medidas.

## Limites e isolamento

- Banco descartável: `aculta_carga`, contendo somente
  `aculta_lms_xapi_statement`, com a definição InnoDB da entidade ACULTA.
- A conexão local usou autenticação por socket Unix do sistema, sem senha de
  aplicação ou credencial nova. Nenhum segredo foi criado ou gravado.
- Dados sintéticos: ator e objeto artificiais, `uid=0`, sem referência a
  progresso (`lesson_status_id=NULL`) e payload JSON com cerca de 2 KiB.
- Limites definidos antes da escrita: 5.000 linhas, lotes de 100, 30 segundos
  de duração máxima e 64 MiB de alocação máxima da tabela. A rodada terminou
  pelo limite de linhas, em 0,783 segundo, abaixo dos demais limites.

## Resultado

- Inserções: 5.000 em 50 lotes; taxa observada de 6.389 linhas/s.
- Latência p95 da execução SQL individual: 0,118 ms.
- Latência p95 da transação de lote, incluindo commit: 14,843 ms.
- Payload médio: 2.261,8 bytes.
- Tablespace InnoDB: 81.920 bytes antes e 22.024.192 bytes depois; crescimento
  observado de 21.942.272 bytes (aproximadamente 20,9 MiB).
- A verificação encontrou 5.000 linhas sintéticas, todas com `uid=0` e
  `lesson_status_id=NULL`.

Esses valores são uma medição local de persistência MariaDB com conexão já
aberta. Não representam capacidade de produção nem incluem validação da
aplicação ou custo de rede.

## Estado do `aculta_runtime`

Nenhum comando de escrita foi direcionado a `aculta_runtime`; a carga gravou
somente em `aculta_carga`. Antes e depois, as contagens foram iguais: xAPI 0,
progresso 0, aula 0, atividade 0 e resposta 0. A soma informada por
`information_schema` para tamanho de tabelas passou de 135.217.152 para
135.233.536 bytes (+16.384 bytes) durante o intervalo; essa variação não foi
atribuída à carga e não houve tentativa de corrigi-la.

## Descarte

Após a medição, `aculta_carga` foi removido. A verificação posterior confirmou
zero schemas e zero tabelas com esse nome. Nenhuma configuração foi importada.
