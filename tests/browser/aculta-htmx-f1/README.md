# ACULTA420 V2 — HTMX F1 (busca e login)

`visual.py` valida no Firefox ESR visível o popup de busca do shell e o popup
de login contra o servidor de desenvolvimento (`*.aculta.toca.net.br`). Não
autentica e não grava no Runtime.

Cobre:

- abertura do popup por `data-hx-*` (sem `fetch` próprio), carga do formulário
  por HTMX e ausência do script legado `search-preview`;
- prévia da busca por `hx-get` na rota `/busca/previa`;
- matriz 320, 390, 768, 959, 960 e 1440 CSS px em claro/escuro, sem overflow
  horizontal da página, para busca e login;
- foco no campo de busca com contorno visível;
- zoom real de 200% (via `xdotool`);
- fallback sem JavaScript: o link de busca abre `/busca/popup` e o formulário
  nativo funciona.

Quando `secrets/teste_comum.env` existe, o script também autentica a conta de
teste pelo popup, abre as gavetas de mensagens e avisos por HTMX, captura a
matriz de avisos e confere que Escape fecha a gaveta devolvendo o foco ao
gatilho. A senha é lida do arquivo em tempo de execução e nunca é escrita no
script, no repositório ou na saída padrão. Sem o arquivo, esse bloco é
registrado como não executado.

O cadastro popup (`/criar-conta/janela`) responde a requisições HTMX, mas o
único consumidor atual é o checkout de doação (F5, aguardando autorização);
não há ponto de entrada no V2 para capturá-lo como popup.

Configure `SUITE_OUT` para um diretório persistente fora do repositório.
Capturas e `results.json` ficam nesse diretório; os perfis temporários do
WebDriver ficam em `$SUITE_OUT/profiles`.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta-htmx-f1" \
  python3 tests/browser/aculta-htmx-f1/visual.py
```

O script lê `ACULTA_DEV_HOST` (padrão `aculta.toca.net.br`). Requer
`geckodriver`, `node`, `xdotool` e o Firefox ESR visível em `DISPLAY=:0`.
