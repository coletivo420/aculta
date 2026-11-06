# ACULTA Editor — acesso anônimo, editor e administrador

`access.py` usa Firefox ESR visível via `DISPLAY=:0` contra o servidor de
desenvolvimento, com os hosts de `deploy.json`. Sem criar contas nem salvar
conteúdo no Runtime, valida em três sessões isoladas:

1. **Visitante anônimo:** acesso negado à edição do formato `full_html` e à
   criação de `node/add/aculta_adm`, além de inspeção de página editorial
   publicada (`node/6`) sem tags/atributos executáveis e sem overflow horizontal
   em `1280`, `390` e `320` px.
2. **Editor de conteúdo (`uid=15`, `content_editor`):** acesso negado a
   `full_html` e `node/add/aculta_adm`, e carregamento do CKEditor 5 em
  `node/add/revista` no host principal (`EDITOR_BASE_URL`), com
  `revista_editorial` (sem expor `full_html`) e sem overflow em `1280`,
   `390` e `320` px.
3. **Administrador (`uid=1`, `administrator`):** o formulário
   `node/add/aculta_adm` e a página `node/add/page` abrem sem overflow em
   `1280`, `390` e `320` px; CKEditor 5 e barra de ferramentas são verificados
   em `node/add/page`.

```sh
SUITE_OUT="${SUITE_OUT:?informe uma saída externa ao projeto}" DISPLAY=:0 \
  python3 -I tests/browser/transversal/aculta-editor/access.py
```

`EDITOR_BASE_URL` e `EDITOR_SERVED_ROOT` podem substituir o host principal e o
checkout padrão de desenvolvimento. Por padrão, ambos são derivados do projeto
e do `deploy.json`. O formulário de criação é carregado no
host principal: o host da Revista retorna 404 para essa rota administrativa
por política de domínio. Os perfis Firefox temporários ficam em
`$SUITE_OUT/profiles` e as capturas/JSON em `$SUITE_OUT`.

Login: o script gera um link de uso único com `drush ev` +
`user_pass_reset_url()` e o caminho `/entrar` (`user.reset.login`) no host
`SUITE_LOGIN_URI`, se definido; senão usa `EDITOR_ACCOUNT_URL` (host de conta
de `deploy.json`). O cookie tem escopo de domínio compartilhado, então a
sessão acompanha a navegação até o host principal. Firefox usa WebDriver BiDi,
exige largura CSS exata de `1280`,
`390` e `320` px e espera a grade do Gin/CKEditor estabilizar após o resize.
Login atualiza o último acesso das contas; nenhum
conteúdo, conta ou configuração é criado ou salvo.
