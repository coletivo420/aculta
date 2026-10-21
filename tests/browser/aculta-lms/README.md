# Revisão visual do formulário de resposta com arquivo (LMS)

Revisão no Firefox pelo geckodriver (protocolo W3C WebDriver), com janela no X11 (`DISPLAY`) ou headless com
`LMS_HEADLESS=1`. Verifica o formulário de resposta do tipo File Upload com a política de upload do LMS
(aculta_portal, aba `lms`): texto de ajuda, recusa de extensão fora do teto, aceite de PNG, layout sem rolagem
horizontal e ausência de erros de JavaScript.

## Onde roda

Somente no **servidor de teste** (`https://cursos.aculta.toca.net.br`, runtime `aculta_runtime`). A atividade de
teste precisa existir antes (tipo de atividade File Upload, aula, curso e matrícula da conta de teste). O cenário
usado na revisão foi criado e removido ao final; ver o CHANGELOG do aculta_lms (0.1.0-dev.80).

## Pré-requisitos

- Firefox ESR e `geckodriver` (0.37.1 testado): `DISPLAY=:0 geckodriver --port 4444`.
- Node 22 ou superior (sem dependências npm).
- Drush do projeto (`vendor/drush/drush/drush.php`), para o link de login único.

## Execução

```sh
LMS_PAGE="https://cursos.aculta.toca.net.br/curso/<curso>/<aula>/<atividade>" \
  node tests/browser/aculta-lms/answer-upload-visual.mjs
```

| Variável | Padrão | Uso |
| --- | --- | --- |
| `LMS_PAGE` | (obrigatória) | URL da atividade de teste |
| `LMS_LOGIN_URI` | `https://conta.aculta.toca.net.br` | URI do Drush para o link de login |
| `LMS_USER` | `teste.comum` | Conta de teste |
| `LMS_OUT` | `.suite-out/lms-upload` | Capturas e `results.json` (fora do Git) |
| `LMS_HEADLESS` | (janela) | `1` para headless |
| `GECKODRIVER` | `http://127.0.0.1:4444` | Endereço do geckodriver |

O script termina com código 1 se alguma verificação falhar.

## Limites conhecidos

- O Firefox não reduz a janela abaixo de 500 px: a verificação "celular" mede 500 px, não 390 px.
- Ao mudar a largura, o menu administrativo pode abrir por cima do conteúdo. A verificação recarrega a página
  em largura reduzida para capturar o formulário com o menu fechado.
- A revisão cobre o formulário e o envio do arquivo; não envia a resposta final.
