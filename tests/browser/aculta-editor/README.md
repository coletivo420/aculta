# ACULTA Editor — acesso anônimo, editor e administrador

`access.py` usa Firefox ESR visível via `DISPLAY=:0` contra o servidor de
desenvolvimento (`https://aculta.toca.net.br`). Sem criar contas nem salvar
conteúdo no Runtime, valida em três sessões isoladas:

1. **Visitante anônimo:** acesso negado à edição do formato `full_html` e à
   criação de `node/add/aculta_adm`, além de inspeção de página editorial
   publicada (`node/6`) sem tags/atributos executáveis e sem overflow horizontal
   em `1280`, `390` e `320` px.
2. **Editor de conteúdo (`uid=15`, `content_editor`):** acesso negado a
   `full_html` e `node/add/aculta_adm`, e carregamento do CKEditor 5 em
   `https://revista.aculta.toca.net.br/node/add/revista` (`EDITOR_REVISTA_ADD_URL`)
   com `revista_editorial` (sem expor `full_html`) e sem overflow em `1280`,
   `390` e `320` px.
3. **Administrador (`uid=1`, `administrator`):** carregamento de
   `node/add/aculta_adm` e `node/add/page` com CKEditor 5 ativo, barra de
   ferramentas e sem overflow em `1280`, `390` e `320` px.

```sh
SUITE_OUT=/home/piradopirata/worktrees/suite-out/aculta-editor-rc DISPLAY=:0 \
  python3 -I tests/browser/aculta-editor/access.py
```

`EDITOR_BASE_URL` e `EDITOR_SERVED_ROOT` podem substituir o host e o checkout
padrão de desenvolvimento. Os perfis Firefox temporários ficam em
`$SUITE_OUT/profiles` e as capturas/JSON em `$SUITE_OUT`.

Login: o script gera um link de uso único com `drush ev` +
`user_pass_reset_url()` e o caminho `/entrar` (`user.reset.login`) no host
`EDITOR_ACCOUNT_URL` (padrão `https://conta.aculta.toca.net.br`). O login cria
sessão e atualiza o último acesso das contas existentes; nenhum conteúdo,
conta ou configuração é criado ou salvo.
