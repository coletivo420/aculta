# Revisão visual do formulário de resposta com arquivo (LMS)

## Smoke de navegação somente leitura

O `learning-readonly.mjs` verifica a página inicial do aluno, a jornada, a primeira aula e a primeira atividade
vinculada, em duas larguras de janela. Além de conferir conteúdo e rolagem horizontal, a atividade precisa conter
o formulário `aculta-lms-answer-submission` e ao menos um controle de resposta habilitado e visível. O harness não
seleciona respostas, envia formulários nem aceita termos; se o modal de termos aparecer, ele é apenas registrado.

Use uma conta e URLs de teste já provisionadas. O script cria um link de login único pelo Drush; não registra o link
nem valores dos controles no relatório.

```sh
GECKODRIVER="$GECKODRIVER" \
LMS_UID="$LMS_UID" \
LMS_LOGIN_URL="$LMS_LOGIN_URL" \
LMS_HOME_URL="$LMS_HOME_URL" \
LMS_COURSE_URL="$LMS_COURSE_URL" \
LMS_COURSE_TITLE="$LMS_COURSE_TITLE" \
LMS_OUT="$LMS_OUT" \
node tests/browser/courses/aculta-lms/learning-readonly.mjs
```

`LMS_OUT` deve apontar para uma pasta de artefatos fora do Git. O Firefox deve estar visível na sessão X11; rode
`DISPLAY="$DISPLAY" geckodriver --host 127.0.0.1 --port 4445 --log error` em outro terminal. O script termina com
código 1 quando uma checagem falha.

## Revisão visual do formulário com arquivo

Revisão no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver (protocolo W3C WebDriver). Não usar headless para validação. Verifica o formulário de resposta do tipo File Upload com a política de upload do LMS
(aculta_portal, aba `lms`): texto de ajuda, recusa de extensão fora do teto, aceite de PNG, layout sem rolagem
horizontal e ausência de erros de JavaScript.

## Onde roda

Somente no **servidor de desenvolvimento** (`https://cursos.aculta.toca.net.br`, runtime `aculta_runtime`). A atividade de
teste precisa existir antes (tipo de atividade File Upload, aula, curso e matrícula da conta de teste). O cenário
usado na revisão foi criado e removido ao final; ver o CHANGELOG do aculta_lms (0.1.0-dev.80).

## Pré-requisitos

- Firefox ESR e `geckodriver` (0.37.1 testado): `DISPLAY=:0 geckodriver --port 4444`.
- Node 22 ou superior (sem dependências npm).
- Drush do projeto (`vendor/drush/drush/drush.php`), para o link de login único.

## Execução

```sh
LMS_PAGE="https://cursos.aculta.toca.net.br/curso/<curso>/<aula>/<atividade>" \
  node tests/browser/courses/aculta-lms/answer-upload-visual.mjs
```

| Variável | Padrão | Uso |
| --- | --- | --- |
| `LMS_PAGE` | (obrigatória) | URL da atividade de teste |
| `LMS_LOGIN_URI` | `https://conta.aculta.toca.net.br` | URI do Drush para o link de login |
| `LMS_USER` | `teste.comum` | Conta de teste |
| `LMS_OUT` | `.suite-out/lms-upload` | Capturas e `results.json` (fora do Git) |
| `LMS_HEADLESS` | (janela) | Não usar para validação; manter a janela na sessão X11 |
| `GECKODRIVER` | `http://127.0.0.1:4444` | Endereço do geckodriver |

O script termina com código 1 se alguma verificação falhar.

## Limites conhecidos

- A janela do Firefox não reduz abaixo de 500 px. Por isso o celular é emulado pelo comando BiDi `browsingContext.setViewport` (320 px por padrão), e o script confere `window.innerWidth` antes da captura.
- O desktop usa 1440 px por padrão. `LMS_DESKTOP_WIDTH` e `LMS_MOBILE_WIDTH` podem ajustar as larguras em execuções específicas.
- Ao mudar a largura, o menu administrativo pode abrir por cima do conteúdo. A verificação recarrega a página
  em largura reduzida para capturar o formulário com o menu fechado.
- A revisão envia uma resposta sintética com PNG para a atividade marcada; não remova a tentativa sem ordem separada.
