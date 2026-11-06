# Smoke do aculta_file_uploader (navegador)

Firefox ESR visível em `DISPLAY=:0`, pelo geckodriver. Não usar headless.
Somente no servidor de desenvolvimento, com a conta de teste `aculta_visual_admin`
(login único gerado por Drush; nenhuma senha vai ao repositório).

```sh
export SUITE_OUT=/caminho/externo-ao-repositorio/aculta-uploader
LINK=$(php vendor/drush/drush/drush.php user:login --uid=257 --uri=https://conta.aculta.toca.net.br | tail -1)
DISPLAY=:0 LOGIN_URL="$LINK" BASE=https://aculta.toca.net.br FILTER_FORMAT=forum \
  python3 tests/browser/transversal/aculta-uploader/dropzone-smoke.py
```

Confere a zona de arrastar (`.aculta-dropzone`), a borda tracejada, `.is-dragover` ao arrastar,
a área de status e a recusa de MIME incorreto. Grava capturas e `resultado.json` em `SUITE_OUT`.
Status atual: a rota `/forum/anexos/adicionar/forum` renderiza em branco no Firefox; investigar antes de
considerar a suíte válida.
