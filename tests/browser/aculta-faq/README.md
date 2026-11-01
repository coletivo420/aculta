# Revisão visual do ACULTA FAQ

Firefox ESR visível no servidor de desenvolvimento `aculta.toca.net.br`. O
script abre a página pública e confirma que as perguntas/respostas fornecidas
estão publicadas, mede o reflow em 1280, 390 e 320 CSS px e salva capturas e
`summary.json` fora do repositório em `SUITE_OUT`. Também percorre por Tab os
links únicos da FAQ e confirma `:focus-visible`. Durante a navegação, não cria
nem altera conteúdo.

Execute com uma lista JSON de títulos exatos e trechos exclusivos das respostas:

```sh
SUITE_OUT=/caminho/fora-do-repositorio/aculta-faq \
ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
SUITE_FAQ_ITEMS='[{"title":"Pergunta publicada?","body":"Trecho da resposta."}]' \
DISPLAY=:0 python3 -I tests/browser/aculta-faq/visual.py
```

Requer Firefox ESR, geckodriver e `websockets` Python. Esse script verifica
presença, geometria horizontal e acesso por teclado; não substitui revisão de
leitor de tela ou zoom. Sanitização do formato é coberta pelo teste Kernel do
módulo.
