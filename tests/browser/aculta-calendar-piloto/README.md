# Piloto isolado — DHTMLX Scheduler (edição GPL)

Teste exploratório, **não é código de produção**. Avalia se a visão interativa do calendário é viável, antes de qualquer decisão de adoção (D-21 pendente). Resultados e conclusões estão em `ESTUDO-DHTMLX-SCHEDULER.md`.

- **Ambiente:** servidor local `127.0.0.1:8765` com dados fictícios. Não fala com `*.aculta.toca.net.br` nem com produção.
- **Pré-requisitos:** `FIREFOX` apontando para o Firefox ESR (padrão `/usr/bin/firefox`), `geckodriver` no PATH, `DISPLAY=:0`.
- **Páginas (`site/`):** `index.html` (calendário com 40 eventos fictícios, mês de novembro de 2026; `?keynav=1` ativa o plugin `key_nav`) e `viewport320.html` (iframe de 320 px que carrega `index.html`). As bibliotecas do componente não estão no repositório: copie `dhtmlxscheduler.js` e `dhtmlxscheduler.css` do pacote npm `dhtmlx-scheduler@7.2.15` para `$OUT_PILOTO/site/dhtmlx/` e sirva a pasta `site/`.
- **Por que iframe em 320 px:** o Firefox desta sessão não reduz a janela abaixo de cerca de 500 px (o WebDriver devolve `innerWidth` = 500). O teste de 320 px usa o iframe, que tem viewport próprio.
- **Execução (uma vez por variante):**

```sh
export DISPLAY=:0 SUITE_OUT=/caminho/fora/do/repo PILOT_CSS=$OUT_PILOTO/site/dhtmlx/dhtmlxscheduler.css
PILOT_LABEL=padrao PILOT_URL=http://127.0.0.1:8765/index.html python3 tests/browser/aculta-calendar-piloto/piloto_dhtmlx.py
PILOT_LABEL=keynav PILOT_URL="http://127.0.0.1:8765/index.html?keynav=1" python3 tests/browser/aculta-calendar-piloto/piloto_dhtmlx.py
```

- **Saída** (em `$SUITE_OUT`): `piloto-<variante>-resultados.json`, `piloto-<variante>-1280px.png` e `piloto-<variante>-320px.png`.
- **Limitações conhecidas:** não testa leitor de tela (só atributos ARIA); não testa o modo de alto contraste; não mede o tamanho servido com compressão do servidor; não testa recorrência; não testa a integração com Drupal.
