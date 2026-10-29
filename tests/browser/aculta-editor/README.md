# ACULTA Editor — acesso administrativo

`access.py` usa Firefox ESR visível via `DISPLAY=:0` contra o servidor de
desenvolvimento. Sem login ou escrita no Runtime, confere que visitantes
anônimos não recebem os formulários de edição do formato `full_html` nem de
criação do bundle `aculta_adm`. A sanitização de conteúdo continua coberta pela
suíte Kernel `AcultaEditorFormatsKernelTest`; este smoke de navegador não cria
nem submete conteúdo.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-editor-access DISPLAY=:0 \
  python3 -I tests/browser/aculta-editor/access.py
```

`EDITOR_BASE_URL` pode substituir o host padrão de desenvolvimento. O perfil
Firefox temporário também fica em `$SUITE_OUT/profiles`; o relatório vai para
`$SUITE_OUT` e não guarda HTML ou senha. O teste valida somente o
comportamento anônimo e não substitui a revisão autenticada como editor.
