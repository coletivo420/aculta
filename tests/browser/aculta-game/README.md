# Smoke visual do ACULTA Game

Valida a navegação da área Game em Conta, a apresentação de conquistas no
perfil público e verificações focais de rótulos e alternativas de imagem. Usa
Firefox ESR visível em `DISPLAY=:0` via geckodriver e reaproveita
`tests/browser/aculta-messenger/suite-lib.mjs`.

## Ambiente

- Servidor de desenvolvimento: somente `aculta_runtime` em `*.aculta.toca.net.br`.
- O smoke consulta a conta de teste existente `teste.comum` (UID 5) e o perfil
  público associado; não cria usuário, badge, configuração ou conteúdo.
- Gera link de login de uso único pelo Drush para a conta de teste. Não usa a
  conta root para a parte administrativa.
- O trecho administrativo é opcional. Se `GAME_ADMIN_ACCOUNT` for definido, o
  script exige conta ativa com `administer aculta game` e testa as rotas de
  administração. O valor não é impresso.

## Execução

```sh
mkdir -p "$SUITE_OUT"
DISPLAY=:0 TMPDIR="$SUITE_OUT" TMP="$SUITE_OUT" TEMP="$SUITE_OUT" geckodriver --port 4444
DISPLAY=:0 GAME_ACCOUNT_BASE=https://conta.aculta.toca.net.br \
  SUITE_BASE=https://aculta.toca.net.br \
  SUITE_LOGIN_URI=https://conta.aculta.toca.net.br \
  SUITE_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
  GAME_SKIP_RESPONSIVE=1 \
  SUITE_OUT="$SUITE_OUT" \
  node tests/browser/aculta-game/smoke.mjs
```

Defina `SUITE_OUT` para um diretório fora do repositório, por exemplo
`/home/piradopirata/aculta-suite-out/game`. O `TMPDIR` direciona o perfil
temporário criado pelo geckodriver para esse diretório. Remova o processo
geckodriver ao terminar somente quando não estiver compartilhado com outro teste.

`GAME_SKIP_RESPONSIVE=1` pula reflow em 390 px. Use-o somente quando o checkout
servido não contém o CSS do tema sob revisão; essa execução não aprova o layout
móvel. Sem essa variável, o smoke verifica controles visíveis e ausência de
overflow em 390 px. Para testar as rotas administrativas, provisione por fora
uma conta temporária com a permissão específica e passe seu nome em
`GAME_ADMIN_ACCOUNT`; remova a conta e o papel temporário ao terminar.

Capturas e `game-results.json` ficam em `SUITE_OUT`. O script não submete o
formulário de preferências nem modifica definições administrativas.
