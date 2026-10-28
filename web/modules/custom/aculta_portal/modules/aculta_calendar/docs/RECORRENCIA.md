# Recorrência — ACULTA Calendário

Status: **decidido**, D-14 (recorrência entra na v1). Implementação: `rlanvin/php-rrule` 3.0.
Base de comportamento: o sistema do OpenCulturas (`smart_date` + `smart_date_recur`), com
modificações próprias indicadas explicitamente abaixo.

## 0. Origem e licença

- O OpenCulturas usa `drupal/smart_date` `^4.3.0` e o submódulo `smart_date_recur` (GPL-2.0-or-later).
  Lido em `smart_date` 4.3.0, pacote oficial do drupal.org.
- Replicamos o **comportamento** (regras de contagem, cancelamento, reagendamento e horizonte),
  não o código. Se algum trecho de código for copiado, ele entra em `ORIGEM-OPENCULTURAS.md` com
  a linha `Adaptado de ...`.
- Biblioteca de cálculo: `rlanvin/php-rrule` 3.0, MIT, sem dependência de runtime. Não puxa
  `doctrine/collections`. Substitui `simshaun/recurr`, que ficou de fora pelo conflito com
  `doctrine/collections` 3.x.

## 1. Como o OpenCulturas faz (base)

| Ponto | Comportamento no OpenCulturas (`smart_date_recur`) |
| --- | --- |
| Geração | A biblioteca gera as ocorrências, numeradas por índice (`rrule_index`). Elas são **materializadas** como linhas do campo. |
| Série aberta | Sem `limit` (COUNT/UNTIL), o horizonte é `month_limit` meses a partir de agora. O padrão é 12, em `field_date`. |
| `COUNT` e cancelamento | Cancelar uma ocorrência **não libera** a contagem: a ocorrência cancelada continua gerada, e só é ocultada depois. |
| Cancelar uma ocorrência | Registro de exceção (`smart_date_override`) com o índice e **sem** horário. A ocorrência some da saída. |
| Reagendar uma ocorrência | Mesma exceção, com novo início e fim. A ocorrência aparece no novo horário. |
| Chave da exceção | O índice (`rrule_index`) da ocorrência gerada. |

## 2. Decisões (respostas aos três pontos em aberto)

1. **Contagem e cancelamento:** igual ao OpenCulturas e ao RFC 5545. Ocorrência cancelada
   continua contando em `COUNT`.
2. **Editar uma ocorrência:** **entra na v1**, com o mesmo modelo: exceção por ocorrência com
   cancelamento ou novo horário.
3. **Série aberta:** **permitida**, com horizonte de `month_limit` meses, padrão 12, configurável
   no campo. Igual ao OpenCulturas.

## 3. Modificações próprias (em relação ao OpenCulturas)

| # | O que muda | Por quê |
| --- | --- | --- |
| M1 | A exceção é chaveada pelo **início original** da ocorrência (RECURRENCE-ID), não pelo índice. | O índice muda se a regra mudar (ex.: alterar `BYDAY`). O início original não muda. Isso bate com o RFC 5545 e com o `.ics`. |
| M2 | Cálculo na leitura por janela (`RRule::getOccurrencesBetween`), sem materializar ocorrências. | Uma única fonte de verdade (a regra e as exceções). Evita sincronizar linhas a cada edição. |
| M3 | Teto de **366 ocorrências** por consulta. Acima disso, erro explícito, não truncamento silencioso. | Proteção de desempenho. Não existe no OpenCulturas. |
| M4 | Fuso fixo `America/Sao_Paulo` (D9), sem sobrescrita por evento. | Regra do site. O OpenCulturas permite fuso por campo. |
| M5 | `.ics` emite a série com `RRULE`, `EXDATE` para cancelamentos e VEVENT com `RECURRENCE-ID` para reagendamentos. | Agenda externa recalcula sozinha. Nenhuma ocorrência é expandida no arquivo. |

## 4. Modelo de dados

- `aculta_event_date.field_quando` (daterange do core): primeiro intervalo da série.
- `aculta_event_date.field_dia_inteiro` (boolean).
- `aculta_event_date.field_regra` (string, até 255): RRULE normalizada, por exemplo
  `FREQ=WEEKLY;BYDAY=MO,WE;COUNT=10`. Vazio = evento único.
- `aculta_event_date.field_limite_meses` (integer, padrão 12): horizonte de série aberta. Só vale
  sem `COUNT` nem `UNTIL`. Substitui o `month_limit` do OpenCulturas.
- **Entidade `aculta_event_override`** (substitui `field_excecoes`):
  - `field_data_pai` (entity_reference a `aculta_event_date`).
  - `field_inicio_original` (datetime): chave da exceção (M1).
  - `field_inicio` e `field_fim` (datetime, vazios = cancelada).
  - `field_motivo` (string, opcional): texto mostrado na ocorrência cancelada.

## 5. Subconjunto de RRULE da v1

| Parte | Valores | Observação |
| --- | --- | --- |
| `FREQ` | `DAILY`, `WEEKLY`, `MONTHLY` | `YEARLY` fica fora da v1 |
| `INTERVAL` | 1 a 99 | Padrão 1 |
| `BYDAY` | `MO` a `SU` (só com `WEEKLY`) | Sem prefixo numérico |
| `BYMONTHDAY` | 1 a 31 (só com `MONTHLY`) | Dia inexistente no mês é pulado |
| `COUNT` ou `UNTIL` | opcional; sem os dois, vale `field_limite_meses` | Série aberta |
| `EXDATE` | não usado na base; cancelamento via `aculta_event_override` | Ver M5 para o `.ics` |

Fora da v1: `BYSETPOS`, `BYHOUR`, `BYMINUTE`, `WKST` diferente de `MO`, prefixo em `BYDAY`
(`1MO`, `-1FR`), `RDATE`, `YEARLY`.

## 6. Expansão

1. Partir de `field_quando` (início) e da duração (`end - início`).
2. Gerar os candidatos com `rlanvin/php-rrule` dentro da janela pedida.
3. Se não houver `COUNT` nem `UNTIL`, o limite final é `agora + field_limite_meses`.
4. Contar cada candidato em `COUNT`, mesmo que depois seja cancelado (decisão 1).
5. Para cada ocorrência, buscar exceção pelo início original (M1):
   - sem exceção: usa o horário gerado;
   - exceção com início e fim: usa o horário da exceção (reagendada);
   - exceção sem horário: cancelada, não entra na saída.
6. `MONTHLY` com `BYMONTHDAY` 29 a 31: meses curtos são pulados, sem ajuste para o último dia.

Fuso fixo `America/Sao_Paulo`. O horário de verão não existe no Brasil desde 2019, então não há
transição a tratar.

## 7. Validação

Lista branca, não gramática geral:

- Só as partes da seção 5. Parte desconhecida é erro.
- `BYDAY` fora de `WEEKLY` e `BYMONTHDAY` fora de `MONTHLY` são erro.
- `UNTIL` anterior ao início é erro.
- Mais de 366 ocorrências na janela é erro, com a contagem na mensagem (M3).
- A regra é validada ao salvar e de novo ao ler.

## 8. Testes

Unit (sem banco), no validador e no expansor:

- `DAILY` com `INTERVAL=3` e `COUNT=5`.
- `WEEKLY` com `BYDAY=MO,WE` começando numa quarta.
- `MONTHLY` com `BYMONTHDAY=31`: meses de 30 dias pulados.
- `COUNT` conta a ocorrência cancelada (decisão 1).
- Exceção cancelada remove só aquela ocorrência.
- Exceção reagendada muda só aquela ocorrência e mantém a chave original (M1).
- Série aberta: horizonte de `field_limite_meses`.
- Teto de 366 gera erro.
- Cada regra inválida da seção 7 gera erro com mensagem clara.

Kernel (com banco): salvar série, criar exceção, ler janela, `.ics` com `RRULE`, `EXDATE` e
`RECURRENCE-ID`.

## 9. Ordem de implementação

1. Validador da regra e expansor, com `rlanvin/php-rrule`, em Unit.
2. Campos `field_regra`, `field_limite_meses` e `field_dia_inteiro` em `aculta_event_date` (F2).
3. Entidade `aculta_event_override` e seus testes Kernel (F2).
4. Agenda e grade consultando o expansor por janela (F5).
5. `RRULE`, `EXDATE` e `RECURRENCE-ID` no `IcsEventBuilder` (F5).

## 10. Implementação (arquitetura)

Pasta: `aculta_calendar/src/Recurrence/`. Sem dependência de Drupal: são classes puras, testáveis em Unit.

| Classe | Responsabilidade |
| --- | --- |
| `RecurrenceRuleParser` | Lista branca da regra (seção 5 e 7). `parse()` devolve partes normalizadas; `normalize()` devolve a forma canônica que vai para `field_regra`. |
| `RecurrenceExpander` | Expansão na janela (seção 6). Monta o RFC com `DTSTART` no fuso do site, chama `rlanvin/php-rrule` e aplica as exceções por início original (M1). |
| `RecurrenceException` | Regra inválida ou janela acima do teto (M3). |

Fluxo: o campo guarda `normalize($regra)`. Ao ler uma janela, o expansor recebe a regra, `field_quando`,
a duração, a janela, o limite de série aberta e as exceções indexadas pelo timestamp original.

Decisões tomadas ao codificar (acrescentam às seções anteriores):

- **Forma canônica ordenada:** `BYDAY` sai na ordem da semana (MO a SU) e `BYMONTHDAY` em ordem
  numérica. Sem isso, `BYDAY=WE,MO` e `BYDAY=MO,WE` seriam dois valores para o mesmo evento, e a
  comparação de regras quebraria. Pego pelo teste de canonização.
- **UNTIL em UTC:** o RFC 5545 exige `UNTIL` em UTC quando `DTSTART` tem fuso. O validador aceita
  `AAAAMMDD` (dia local) e o expansor converte o fim do dia de Brasília para UTC antes de passar à
  biblioteca. Pego pelo teste de `UNTIL`.
- **Janela e duração:** a janela pedida pega ocorrências que começam nela. Uma ocorrência que começa
  antes da janela e termina dentro dela não é devolvida. Quem chama deve alargar a janela pela
  duração máxima da série. Decisão de implementação, não de produto.

Testes: `RecurrenceRuleParserTest` (26) e `RecurrenceExpanderTest` (10), na suíte Unit do módulo.

## 11. Interface de edição (M4)

Implementada em `aculta_events`, porque a série é uma `EventDate`:

| Rota | Formulário | O que faz |
| --- | --- | --- |
| `/eventos/datas/{aculta_event_date}/recorrencia` | `EventDateRuleForm` | Edita `field_regra` e `field_limite_meses`. A regra é validada pelo `RecurrenceRuleParser` e gravada na forma canônica; regra inválida não altera o campo. |
| `/eventos/datas/{aculta_event_date}/ocorrencia/{inicio}` | `EventDateOccurrenceForm` | Cancela, reagenda ou restaura a ocorrência cujo início original é `inicio` (timestamp). |

Regras da interface:

- As duas rotas exigem `administer aculta events` e pertencem ao purpose `eventos`.
- A chave é sempre o início original (seção 3, M1). Mudar a regra não muda a chave de uma exceção já
  gravada; se a ocorrência deixar de existir na série, o POST é recusado na validação.
- Cancelar grava a exceção sem horário; reagendar grava novo início e fim; restaurar apaga a exceção.
- Novo início e fim entram e saem em `dd/mm/aaaa hh:mm` no fuso `America/Sao_Paulo`; o campo guarda UTC.
- Salvar data ou exceção invalida a tag `aculta_event_list`, que alimenta página e catálogo.
- Pendente (F6): link para os formulários na página do evento. Hoje as rotas só são alcançáveis
  digitando o endereço.

Teste: `RecurrenceManagementKernelTest` (8 testes), incluindo a ocorrência de meia-noite no fuso de
Brasília, que se desloca se o valor UTC for tratado como hora local.
