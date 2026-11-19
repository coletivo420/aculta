# ACULTA420 V2 — visual Wiki

Verifica o SDC `wiki-entry` e os estilos de listagem servidos pelo tema V2 com
Firefox ESR visível. O teste faz somente GET, não autentica e não altera dados.

Defina `WIKI_BASE_URL` com o endereço de teste descrito em
`docs/architecture/multidomain.md` e `SUITE_OUT` fora do repositório. O runner
captura home, listagem e um verbete publicado descoberto pela listagem em
320, 390, 767, 768 e 1440 CSS px, nos modos claro e escuro, e valida H1 único,
ausência de overflow, reflow do artigo e presença do shell. O modo escuro força
`data-theme="dark"` apenas na página aberta do navegador para inspecionar os
tokens; não muda a preferência persistida do usuário.

Requer `DISPLAY=:0`, Firefox ESR, geckodriver e `websockets` para WebDriver
BiDi. Perfis Firefox ficam dentro de `$SUITE_OUT/profiles`. As capturas e o
resumo JSON ficam fora do repositório. A suíte não substitui leitor de tela,
teste autenticado ou validação de zoom físico. A antiga rota `/wiki/busca`
responde 404 no Runtime atual, e a home não expõe formulário de busca; esses
fluxos precisam ser revisados pelo owner da Wiki antes de receber cobertura.

```sh
WIKI_BASE_URL="$WIKI_TEST_BASE" SUITE_OUT="$SUITE_OUT/aculta420-v2-wiki" \
  DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/aculta420-v2-wiki/profiles" \
  python3 -I tests/browser/wiki/aculta420-v2-wiki/visual.py
```
