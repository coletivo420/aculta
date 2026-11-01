# ACULTA Forum LMS — diálogo sob conteúdo H5P

`h5p-discussions.py` faz um smoke visual autenticado no servidor de teste
`toca`, em Firefox ESR visível (`DISPLAY=:0`). O script exige o checkout local
do Runtime de teste em `ACULTA_TEST_ROOT` e confirma host, docroot e banco antes
de criar qualquer fixture.

O roteiro cria uma conta QA sem senha conhecida, um Group temporário de curso,
um tópico publicado de teste e associa a primeira entidade H5P já existente ao
curso. Não altera o conteúdo H5P. Confirma o link da discussão no curso em
1280/390/320 CSS px e remove o tópico, o Group e a conta em `finally`, depois
confirma que os três foram removidos. Não envia formulários, e-mail ou conteúdo
para serviços externos. Não use em produção.

Execute a partir da raiz do checkout de desenvolvimento do candidato, com
`vendor/` e as dependências Python instaladas:

```sh
DISPLAY=:0 \
ACULTA_TEST_ROOT=/home/piradopirata/projetos/aculta \
SUITE_OUT=/home/piradopirata/SUITE_OUT/forum-lms-h5p \
python3 -I tests/browser/aculta-forum-lms/h5p-discussions.py
```

Requer um item H5P já instalado, configuração LMS/Group ativa (`lms_course`, o
papel `lms_course-teacher`, `field_h5p_activities` e a relação
`group_node:aculta_lms_topic`) e os hosts de teste `conta` e `cursos`. O login é
feito por ULI com link único capturado em memória. Screenshots e `results.json`
ficam em `SUITE_OUT`, com permissões privadas. O teste cobre uma conta
Educadora; acesso de estudante, outsider e tópico sem vínculo é coberto pelos
Kernels, não por este smoke.
