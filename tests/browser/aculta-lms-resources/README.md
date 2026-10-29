# Revisão visual do campo de vídeos do curso

Valida no formulário administrativo `Group:lms_course` o widget
`field_lms_videos`, que referencia somente `Media:remote_video`, além do
reflow em 1280/390/320 px.

## Ambiente

- Servidor de desenvolvimento `toca`, Runtime `aculta_runtime`.
- Firefox ESR visível em `DISPLAY=:0` via geckodriver e WebDriver BiDi.
- O módulo e o campo precisam estar habilitados/instalados no Runtime. O teste
  não executa `updatedb`, `config:import` nem `drush cr`.
- O script cria um Media `remote_video` e um Group `lms_course` temporários pela
  Entity API, autentica com um link único Drush da conta admin (sem imprimi-lo),
  inspeciona `/group/{id}/edit` e remove ambos em `finally`.
- O banco deve ser `aculta_runtime` com as 607 tabelas verificadas em 2026-10-29;
  se o banco ou a contagem mudar, o script para antes de escrever.

## Execução

```sh
DISPLAY=:0 SUITE_OUT=/home/piradopirata/aculta-suite-out/lms-resources \
  python3 tests/browser/aculta-lms-resources/visual.py
```

Capturas e `results.json` ficam em `SUITE_OUT`. A fixture é efêmera; o script
registra os IDs em memória e remove primeiro o Group, depois o Media. Se o
processo for interrompido à força, confira e remova somente os registros com o
marcador `ACULTA_QA_COURSE_VIDEO_` após confirmar os IDs.

## Limites

- O teste confirma apresentação do campo de edição por uma conta admin. Não
  submete o formulário nem publica uma matrícula.
- A autorização da relação para estudante, educador e terceiro continua coberta
  pelo Kernel do Content Index (`testCourseVideoRelationUsesMembershipAccess`).
- O destino oEmbed é fictício e não é carregado pelo teste; nenhuma chamada a
  YouTube/Vimeo é realizada.
