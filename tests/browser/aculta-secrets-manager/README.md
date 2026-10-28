# Painel de credenciais (aculta_secrets_manager)

Verifica as abas Produção/Testes do painel `/painel-administrativo/aculta/segredos`: estado da aba,
ausência de botões na aba do outro ambiente, troca de aba sem alterar o ambiente, teclado (setas, Home) e
ausência de rolagem horizontal em 1280, 390 e 320 px.

- **Ambiente:** servidor de desenvolvimento (`*.aculta.toca.net.br`). Não é o ambiente local.
- **Execução:** `DISPLAY=:0 python3 tests/browser/aculta-secrets-manager/painel-visual.py`
- **Variáveis:** `SECRETS_UID` (padrão 205, conta de teste `qa_segredos`), `SUITE_OUT` (capturas, fora do Git).
- **Login:** link de uso único do Drush, gerado no subprocesso e nunca impresso.
- **Capturas:** em telas estreitas a captura pode trazer a gaveta do menu administrativo (Claro), que não está no DOM da página; por isso o layout é medido pelo DOM (rolagem e largura da tabela), não só pela imagem.
- **Limites:** não altera dados. Não cobre o conteúdo das credenciais (isso está no teste HTTP documentado em
  `docs/operations/SECRETS-SM9-INTEGRACAO.md`), nem o tema Olivero (pendente).

## Popups (somente leitura): `popups-visual.py`

- **Execução:** `DISPLAY=:0 python3 tests/browser/aculta-secrets-manager/popups-visual.py`
- **Cobre:** olho com token CSRF no link (sem clicar); alterar e apagar abrem com título e fecham pelo botão do
  diálogo, sem enviar formulário.
- **Não cobre:** revelar (o olho mostra o valor real do servidor de desenvolvimento), gravar e apagar de fato. Essas ações
  pedem credenciais sintéticas, em rodada própria.

## Histórico de execução

- 2026-10-26, checkout do servidor de desenvolvimento em `c1e210934`: `painel-visual.py` 16/16 PASS (1280, 390 e 320 px);
  `popups-visual.py` 7/7 PASS. Conta `qa_segredos` (uid 205).
- Correção do seletor da tabela: o módulo marca o contêiner com `data-portal-ui="secrets-table-scroll"`, e não com a
  classe. O script procurava a classe e acusava falso "não encontrado" (`excesso=None`). O seletor agora usa o atributo,
  como o CSS e o teste Unit `SecretsTabsTest` já fazem.


## Foco por teclado, alvos e cor do ícone de apagar (Claro): `claro-foco-alvos.py`

- **Ambiente:** servidor de desenvolvimento (`*.aculta.toca.net.br`), tema Claro. Firefox ESR visível em `DISPLAY=:0`.
- **Execução:** `DISPLAY=:0 SECRETS_UID=205 python3 tests/browser/aculta-secrets-manager/claro-foco-alvos.py`
- **Cobre:** Tab alcança revelar, alterar e apagar em 1280, 390 e 320 px, com foco visível (estilo computado);
  abas recebem foco; alvos de toque ≥ 44 px; cor computada do ícone de apagar (esperado `rgb(179, 38, 30)`).
- **Não cobre:** clicar em revelar, alterar ou apagar; gravar ou apagar credenciais.
- **Resultado de 2026-10-26, checkout em `78f5bb199`:** 25/28 PASS. Falha única: a cor do ícone de apagar em 1280,
  390 e 320 px. A cor computada é `rgb(35, 36, 41)`, a cor do texto. As variáveis `--aculta-status-error-text` e
  `--color-danger` estão **vazias** na raiz do documento do Claro. A regra CSS cai para `currentColor`. Correção
  pertence ao Local 2 (definir o token de erro para a página do Claro).
- Nota de método: a varredura de Tab recarrega a página em cada largura. Sem recarregar, o foco da largura anterior
  ficava no fim do documento e a varredura seguinte não registrava elementos.
