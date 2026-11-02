# Estudo do Opigno: o que aproveitar para xAPI, tentativas e atividades

**Data:** 2026-11-02
**Status:** pesquisa estática. Não há código copiado, não há instalação e não há execução do Opigno.
**Relaciona-se com:** ADR-041 (xAPI 2.0, LRS próprio), ADR-042 (remoção das dependências do LMS contrib), `docs/modules/OPIGNO-CODE-AUDIT.md`, `docs/modules/OPIGNO-ACULTA-INDEPENDENCE-PLAN.md`.

## 1. Fontes lidas

Pacotes em anexo, extraídos em diretórios isolados do scratchpad:

- `opigno_lms` 3.2.7 (perfil e distribuição), `opigno_scorm` 3.2.7, `opigno_module` 3.2.8 e `opigno_learning_path` 3.2.8.
- Licença: GNU GPL v2, a mesma base do projeto. Reaproveitar ideia não exige registro de código; copiar trecho exige entrada em `ORIGEM-OPIGNO.md`.
- Não li `opigno_tincan_api` (pacote de emissão de eventos xAPI), que não está nos anexos. A auditoria anterior já o cobre por análise estática.

## 2. Achados

### 2.1 xAPI: o Opigno não recebe declarações

O tipo `opigno_tincan_activity` não tem endpoint de recebimento. O `TincanContentAnswerAssistant` consulta um LRS externo pela biblioteca TinCanPHP (`RemoteLRS`):

1. Gera um UUID de registration no servidor e o guarda em `opigno_tincan_activity_answers`.
2. Pede as declarações da registration com verbo `passed` e, se não houver, com verbo `failed`.
3. Calcula a nota a partir da primeira declaração encontrada.

**Aproveitar:**
- A registration é gerada no servidor e guardada por tentativa. Isso confirma a decisão do ADR-041 de vincular o `registration` ao progresso do LMS.
- A busca por verbo final (`passed`, depois `failed`) é um critério de conclusão simples. Nosso F2 já cobre isso, pelos verbos e por `result.completion`.

**Não aproveitar:**
- A dependência da biblioteca TinCanPHP. Nosso LRS é próprio (D-67); o Composer não ganha dependência nova.
- Consultar um LRS externo como fonte de verdade. Nosso caminho é o endpoint próprio, com a credencial por tentativa.

### 2.2 Cálculo da nota

`getScoreFromStatement` aplica esta ordem:

1. `result.score.scaled` com valor `>= 0`: `scaled × max_score`.
2. Sem `scaled`: `raw` com `min` (padrão 0) e `max`: `(raw − min) ÷ (max − min) × max_score`.
3. Sem nota, mas com `success` definido: **nota máxima**.
4. Sem nada: zero.

**Comparação com a F2 (`XapiProgressDeriver`):**
- Igual na ordem: `scaled` primeiro, depois `raw` com `min` e `max`.
- Diferente em `min`: o Opigno assume `min = 0` quando ausente. Nossa F2 exige `min`, senão não há nota. A especificação xAPI deixa `min` opcional. **Sugestão:** adotar o padrão `min = 0` da F2, desde que `max` exista. Isso exige aprovação, porque muda o comportamento da F2 já commitada.
- Diferente em `success`: o Opigno dá **nota máxima** quando só há `success`. Não devemos copiar. Nossa F2 não atribui nota a partir de `success`; a pontuação continua só pela nota.
- Diferente em `scaled` negativo: o Opigno ignora e cai no `raw`. Nossa F2 limita a zero. Os dois são defensáveis; a escolha atual da F2 é mais conservadora.

### 2.3 Tentativas

- O Opigno usa `UserModuleStatus` como pai das respostas de uma tentativa de módulo. Isso é o mesmo papel do `lms_lesson_status` no ACULTA, já escolhido como referência opaca (ADR-041).
- Os métodos de avaliação do módulo são `best` e `last`, aplicados sobre as tentativas do usuário. O `AttemptRule` do ACULTA já cobre `KEEP_BEST` e `KEEP_LATEST`, com o mesmo desempate pela tentativa mais recente.
- **Não aproveitar:** o Opigno calcula o melhor resultado com laços sobre entidades e cache em memória. Nossa `AttemptRule` é função pura, sem consulta.

### 2.4 H5P: a nota vem do cliente

Em `opigno_h5p`, o relatório xAPI do H5P é enviado pelo navegador em um campo oculto do formulário (`#activity-h5p-xapi-data`). No `OpignoAnswerForm`, a nota é lida de `$form_state->getValue('score')`, ou seja, do que o cliente enviou.

**Não aproveitar:** nota vinda do cliente. Isso é um risco de integridade: um aluno pode alterar a nota antes do envio. No ACULTA, a nota só entra pelo endpoint autenticado com o token da tentativa, e o servidor a deriva das declarações. Se o H5P for usado no futuro, a nota deve ser recalculada no servidor a partir do relatório validado, nunca aceita como valor pronto.

### 2.5 Tipos de atividade

O Opigno tem seis tipos: `opigno_file_upload`, `opigno_h5p`, `opigno_long_answer`, `opigno_scorm_activity`, `opigno_tincan_activity` e `opigno_video`.

**Aproveitar como lista de referência para o P3 (plugins de resposta do `aculta_lms`):**
- `file_upload`, `long_answer` e `video` já têm equivalentes no ACULTA. Vale conferir a cobertura contra esta lista.
- `h5p` já tem módulo próprio no ACULTA (`aculta_lms_h5p`). Não há ganho em trocar.
- `scorm` e `tincan` estão superados: o SCORM foi removido (D-66) e o xAPI tem o caminho próprio (ADR-041).

## 3. Resumo de decisões sugeridas

| Item | Origem | Decisão sugerida |
| --- | --- | --- |
| Registration gerada no servidor por tentativa | 2.1 | Adotar, já está no ADR-041 |
| Verbo final `passed`, depois `failed` | 2.1 | Adotar; o F2 já cobre |
| `min` padrão 0 quando ausente | 2.2 | Sugerir ao responsável; mudança na F2 |
| Nota máxima por `success` sem nota | 2.2 | **Não adotar** |
| TinCanPHP e LRS externo | 2.1 | **Não adotar** |
| Nota vinda do cliente (H5P) | 2.4 | **Não adotar**; nota derivada no servidor |
| Melhor ou última tentativa | 2.3 | Já coberto pelo `AttemptRule` |
| Lista de tipos de atividade | 2.5 | Usar como checklist do P3 |

## 4. Próximos passos sugeridos

1. Decidir se o `min` padrão 0 entra na F2. É uma mudança pequena, com teste próprio.
2. Usar a lista de tipos de atividade como checklist de cobertura do P3, dentro da fase P0 do ADR-042.
3. Manter a regra de não copiar código sem registro de origem. Qualquer trecho copiado exige entrada em `web/modules/custom/aculta_portal/modules/aculta_lms/docs/ORIGEM-OPIGNO.md`.

## 5. Limites

- Análise estática de código. Não executei nada do Opigno.
- Não li `opigno_tincan_api` nem a parte de avaliação do `opigno_learning_path` em profundidade.
- A afirmação sobre o campo `score` do cliente em `OpignoAnswerForm` vem da leitura do `getValue`. Não reproduzi o envio de um valor alterado; deve ser tratada como risco a confirmar, não como falha comprovada.
