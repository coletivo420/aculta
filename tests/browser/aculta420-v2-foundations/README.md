# Fundamentos visuais do ACULTA420 V2

`visual.py` verifica os purposes públicos fornecidos por `PURPOSES_JSON` com
Firefox ESR visível em `DISPLAY=:0`. Usa GET, não autentica nem envia
formulários, força o seletor explícito `data-theme` e mede reflow em 320, 360,
390, 599, 600, 768, 1024, 1280 e 1440 CSS px, em claro e escuro. Também testa
Tab e Shift+Tab, zoom nativo do Firefox em 200% e 400% (viewport efetiva de 320
CSS px), execução sem JavaScript e `prefers-reduced-motion: reduce`.

Cada entrada do resultado registra purpose, URL requisitada e final, modo,
viewport, estado CSS before/after do baseline, zoom, status HTTP, foco e
overflow. A matriz aceita somente GET e não inclui rotas privadas; login e
estados autenticados precisam de uma revisão focal autorizada quando forem
afetados.

Requer Firefox ESR, geckodriver, xdotool e o cliente `websockets` Python
disponíveis no ambiente. `wdlib.py` é reutilizado da suíte do Portal. Configure
`SUITE_OUT` para armazenamento persistente fora do repositório,
`BASELINE_REF` para o commit de comparação e `PURPOSES_JSON` como uma lista de
objetos `purpose`/`url` resolvidos pela documentação de ambientes. Para uma
execução focal, informe um único purpose.

```sh
DISPLAY=:0 BASELINE_REF="$BASELINE_REF" PURPOSES_JSON="$PURPOSES_JSON" SUITE_OUT="$SUITE_OUT" \
  python3 -I tests/browser/aculta420-v2-foundations/visual.py
```

O script grava PNGs e `results.json` somente em `$SUITE_OUT`. A sessão principal
roda com JavaScript habilitado; duas sessões adicionais testam sem JavaScript e
com movimento reduzido. Não substitui leitor de tela, fluxo autenticado,
interação funcional de cada SDC, teste em Core 12 nem revisão de contraste de
estados de interação em todos os componentes.
