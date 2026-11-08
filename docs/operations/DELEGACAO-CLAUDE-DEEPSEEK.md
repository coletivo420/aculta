# Delegação ao claude-deepseek

Documento operacional para agentes. Vale para delegar tarefas de leitura, edição em lote e validação ao `claude-deepseek`, com foco em economia de tokens e de créditos.

## Quando delegar

- Edição mecânica em muitos arquivos com regra clara (ex.: trocar um padrão por outro, com critério verificável por grep).
- Varredura e levantamento (listar ocorrências, classificar, registrar exceções).
- Escrita de testes e documentação a partir de um critério já decidido.

Não delegue decisões de arquitetura, conversões que mudam comportamento sem critério objetivo, nem revisão final. A revisão do diff é sempre sua.

## Como invocar

- Use `claude-deepseek -p "<prompt>"` com `--permission-mode acceptEdits` e `--allowedTools` restrito (somente leitura e os comandos de validação necessários).
- Rode em segundo plano (`run_in_background: true`) quando a tarefa passar de ~2 minutos, e aguarde a notificação. Não faça polling.
- Não encerre o processo entre tarefas da mesma sessão: o cache de contexto é reaproveitado e encerrar gasta créditos a cada nova invocação.
- Esforço baixo (`low`) para tarefas mecânicas; médio apenas para levantamento que exige julgamento local.

## O que colocar no prompt

1. Diretório de trabalho (a worktree atual) e a proibição de executar `git`.
2. Proibição de ler ou imprimir segredos (`secrets/`, `*.env`, `settings*.php`) e de rodar suítes que exijam `SIMPLETEST_DB`.
3. O critério exato de cada mudança, com exemplos do código real e o que NÃO converter.
4. Os arquivos ou o grep de escopo, já filtrados (sem `tests/` quando for código de produção).
5. A saída esperada: lista de arquivos alterados, o que foi feito por item, o que ficou de fora com motivo, resultado de `php -l` e dos gates.

## Economia de tokens

- Entregue o escopo já reduzido: o grep e a lista de arquivos vêm prontos do orquestrador, o agente não reexplora o repositório.
- Peça saída curta: lista e motivos, não o conteúdo dos arquivos.
- Uma tarefa por invocação, com critério verificável. Tarefas grandes se dividem por módulo.
- Não peça o arquivo inteiro de log de volta; leia só o final (`tail`) e o resumo.

## Revisão obrigatória antes do commit

1. `git status` e `git diff --stat`: nada fora do escopo.
2. `php -l` em cada PHP alterado.
3. Gates: `validate-aculta420-ui-styles.php` e `validate-aculta420-v2-foundations.php`.
4. Testes novos: rodar com o banco de teste (`docs/operations/BANCO-DE-TESTE.md`), sem imprimir segredos. Teste que falha não é publicado: corrige ou descarta.
5. Documentação: conferir que cada afirmação corresponde ao código. Não publique "convertido" sem a rota e o teste.

## Erros conhecidos de delegação (para não repetir)

- Relatar "convertido" sem o código correspondente. Conferir sempre com `git grep` no diff.
- Testes criados e não executados. Só publicar o que foi rodado.
- Asserções rígidas demais (ex.: `outerHTML` exato quando o Htmx acrescenta `ignoreTitle:true`). Preferir prefixo quando a biblioteca adiciona sufixo.
- Seletores de teste que só aceitam `input[type=submit]` depois que o envio vira `<button>`.

## Publicação

- Um commit por tarefa delegada, com trailer `Claude-Session: f48a7ec7-d1b4-5daa-894c-7504009dfac1`.
- `git fetch origin dev` e `git rebase origin/dev` antes do push. Sem force.
