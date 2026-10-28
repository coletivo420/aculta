# Recorrência própria — estudo (ACULTA Calendário)

Status: **estudo**, decisão D-14 (recorrência entra na v1). Sem código ainda: o primeiro passo
de implementação é o parser e a expansão, com testes Unit, antes de qualquer campo novo.

Motivo: o `smart_date` foi descartado (D-13) por causa da dependência `simshaun/recurr`, que
não aceita `doctrine/collections` 3.x. A recorrência precisa existir sem essa biblioteca.

## 0. Bibliotecas estudadas e escolha

**Como o `smart_date` faz (release 4.3.0, GPL-2.0-or-later):**

- O núcleo não calcula recorrência. Ele guarda `rrule` e `rrule_index` por linha de campo.
- O submódulo `smart_date_recur` faz o cálculo com `simshaun/recurr` 5.x: `new Rule($regra, $inicio, $fim)`
  e `ArrayTransformer::transform($rule, $constraint)`, com constraints `Between`, `Before` e `After`.
- Ele **materializa** as ocorrências como linhas do campo. As exceções vão numa entidade própria
  (`SmartDateOverride`).
- A dependência que causou o bloqueio é só a `simshaun/recurr` 5.x (`doctrine/collections ~1.6||^2.0`).

**O que usamos da ideia:** o cálculo por janela (`Between`), que já está no plano. **O que não
usamos:** a materialização e a entidade de exceção. Nossas exceções são o campo `field_excecoes`.

**Escolha:** `rlanvin/php-rrule` `^3.0` (MIT, PHP ≥ 7.3, sem dependência de runtime). Não puxa
`doctrine/collections`. Verificado com dry-run: 1 instalação, nenhuma atualização nem remoção. Teste
de fumaça: regra `FREQ=WEEKLY;BYDAY=MO,WE;COUNT=3` a partir de quarta 28/10/2026 às 19h
(America/Sao_Paulo) gera 28/10, 02/11 e 04/11, todas `-03`.

Uso previsto: só a classe de expansão (`RRule\RRule`) e a validação de partes, encapsuladas num
serviço do `aculta_calendar`. A whitelist da seção 4 fica no nosso código, não na biblioteca.

## 1. Escopo da v1

Subconjunto de RFC 5545 (RRULE), não a norma inteira:

| Parte | Valores aceitos | Observação |
| --- | --- | --- |
| `FREQ` | `DAILY`, `WEEKLY`, `MONTHLY` | `YEARLY` fica fora da v1 |
| `INTERVAL` | inteiro de 1 a 99 | Padrão 1 |
| `BYDAY` | `MO`…`SU` (só com `WEEKLY`) | Lista, sem prefixo numérico |
| `BYMONTHDAY` | 1 a 31 (só com `MONTHLY`) | Dia que não existe no mês é pulado |
| `COUNT` ou `UNTIL` | obrigatório um dos dois | Evita série infinita |
| `EXDATE` | lista de datas de início de ocorrências | Cancela uma ocorrência |

Fora da v1: `BYSETPOS`, `BYHOUR`, `BYMINUTE`, `WKST` diferente de `MO`, prefixos em `BYDAY`
(`1MO`, `-1FR`), `RDATE`, e ocorrências com fuso diferente do site.

Limites: no máximo **366 ocorrências** por série e **2 anos** de horizonte. Acima disso a regra
é recusada na validação, não truncada em silêncio.

## 2. Modelo de dados

Ver `MODELO-DE-DADOS.md`, `aculta_event_date`. Proposta:

- `field_regra` (string, até 255): RRULE normalizada, por exemplo `FREQ=WEEKLY;BYDAY=MO,WE;COUNT=10`.
  Vazio significa evento único.
- `field_excecoes` (datetime, multivalor): datas de início canceladas nesta série.
- `field_quando` continua sendo o **primeiro** intervalo da série. As ocorrências seguintes são
  calculadas a partir dele; **não são entidades separadas**.

Decisão de armazenamento: calcular na leitura, não materializar. Materializar uma série de 366
ocorrências cria 366 linhas por evento e exige sincronizar a cada edição. Calcular na leitura
cabe no limite de 366 e mantém uma única fonte de verdade (a regra).

Cancelar uma ocorrência: entra em `field_excecoes`. O evento continua, e a ocorrência cancelada
aparece com aviso, igual ao cancelamento de data já definido no modelo.

## 3. Expansão

Algoritmo, em `DateTimeImmutable` com o fuso `America/Sao_Paulo`:

1. Partir de `field_quando.value` (início) e da duração (`field_quando.end_value - value`).
2. Iterar pelos candidatos da frequência, respeitando `INTERVAL`, `BYDAY` e `BYMONTHDAY`.
3. Parar em `COUNT` ocorrências, em `UNTIL`, ou no horizonte de 2 anos.
4. Descartar candidatos que estão em `field_excecoes`.
5. Para `MONTHLY` com `BYMONTHDAY` 29 a 31: meses curtos são pulados, não ajustados para o último
   dia. Ex.: `BYMONTHDAY=31` não gera em fevereiro.
6. Para `DAILY`/`WEEKLY` a hora-do-dia é a do início; a duração é fixa.

Fuso: o site é fixo em `America/Sao_Paulo` e o horário de verão não existe desde 2019, então a
expansão não precisa de transição de DST. Eventos anteriores a 2019 ficam com a mesma limitação
já registrada no VTIMEZONE do `IcsEventBuilder`.

## 4. Validação da regra

O parser é uma lista branca, não uma gramática geral:

- Só as chaves da tabela acima. Chave desconhecida é erro, não ignorada.
- Sem `COUNT` nem `UNTIL`: erro.
- `BYDAY` com `FREQ` diferente de `WEEKLY`: erro.
- `BYMONTHDAY` com `FREQ` diferente de `MONTHLY`: erro.
- `UNTIL` anterior ao início: erro.
- Valor que gera mais de 366 ocorrências: erro, com a contagem na mensagem.

A regra é validada ao salvar (constraint de entidade) e de novo ao ler, para dado antigo ou
editado fora da interface.

## 5. Integração

- **Agenda e grade (F5):** a consulta de uma janela de datas chama o expansor para cada série
  que intersecta a janela. Cache: tag da entidade do evento e contexto `url.query_args`.
- **`.ics` (F5):** em vez de expandir, a exportação emite uma única VEVENT com `RRULE` e `EXDATE`
  (ambos com `TZID=America/Sao_Paulo`). O `IcsEventBuilder` ganha `RRULE` e `EXDATE`. Assim a
  agenda externa recalcula as ocorrências sozinha, e o `UID` continua estável.
- **Edição:** mudar a regra muda o `UID`? Não: o `UID` deriva da entidade e da data de início,
  que não mudam. Mudar `field_quando` muda a data de início e, portanto, o `UID`, que é o
  comportamento já documentado.

## 6. Testes previstos

Unit (sem banco), no gerador de regra e no expansor:

- `DAILY` com `INTERVAL=3` e `COUNT=5`.
- `WEEKLY` com `BYDAY=MO,WE` começando numa quarta.
- `MONTHLY` com `BYMONTHDAY=31`: meses de 30 dias são pulados.
- `EXDATE` remove exatamente uma ocorrência.
- `COUNT` conta ocorrências canceladas? **Não**: `COUNT` conta só as ocorrências geradas;
  cancelada consome a contagem. Esta é a regra do RFC 5545 e fica documentada.
- Limite de 366 e horizonte de 2 anos.
- Regra inválida (cada item da seção 4) gera erro com mensagem clara.

Kernel (com banco): salvar série, ler janela, `.ics` com `RRULE` e `EXDATE`.

## 7. Pontos em aberto

1. **`COUNT` e cancelamento:** a regra acima segue o RFC. Se o responsável preferir que o
   cancelamento não consuma a contagem, a expansão muda. Precisa de decisão.
2. **Edição de uma única ocorrência** (mudar o horário de uma só): não está no escopo. Hoje a
   saída é cancelar e criar um evento avulso. Precisa de decisão se for requisito da v1.
3. **Recorrência sem data de fim** (série aberta): fica fora, por causa do limite de 366.

## 8. Ordem de implementação

1. Parser e validador da regra (Unit).
2. Expansor (Unit).
3. Campos `field_regra` e `field_excecoes` em `aculta_event_date` (F2, Kernel).
4. Integração com a agenda e a grade (F5).
5. `RRULE` e `EXDATE` no `IcsEventBuilder` (F5).
