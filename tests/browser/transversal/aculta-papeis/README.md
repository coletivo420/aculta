# Cobertura visual por papel

Valida as rotas restritas de Wiki com `teste_wiki` e de Cursos com
`teste_educador`, em claro/escuro e larguras 320/1440 CSS px. Confere HTTP,
overflow, classes legadas, equivalência Bootstrap e contraste com axe-core.
Saídas ficam fora do Git.

As origens são lidas de `WIKI_BASE` e `COURSES_HOST`; o script exige
`ACULTA_BROWSER_ENV=desenvolvimento` e não contém hosts fixos. Credenciais são
lidas em tempo de execução dos arquivos ignorados em `secrets/` pelo helper
compartilhado `auth-login.mjs`.

```sh
ACULTA_BROWSER_ENV=desenvolvimento \
WIKI_BASE="${WIKI_BASE:?configure a origem do Runtime}" \
COURSES_HOST="${COURSES_HOST:?configure a origem do Runtime}" \
SUITE_OUT="${SUITE_OUT:?diretório externo}" \
node tests/browser/transversal/aculta-papeis/papeis.mjs
```

O teste não altera conteúdo. Zoom 200%, perfil anônimo e administrador são
aceites separados; a execução desta frente não substitui esses gates.
