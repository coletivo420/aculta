# Revisão visual do ACULTA Document Viewer

Firefox ESR visível no servidor de desenvolvimento. `ACULTA_TEST_URL` deve
apontar para uma página pública descartável que incorpora uma Media `document`
de teste pelo filtro `media_embed`; não use PDF confidencial. O script confirma
o viewer e controles básicos, percorre controles por Tab e verifica o reflow
em 1280, 390 e 320 CSS px. Capturas e `summary.json` ficam fora do repositório,
em `SUITE_OUT`.

```sh
DISPLAY=:0 \
SUITE_OUT=/caminho/fora-do-repositorio/aculta-document-viewer \
ACULTA_TEST_URL=https://aculta.toca.net.br/node/ID \
ACULTA_EXPECTED_TITLE='Título do conteúdo de teste' \
ACULTA_PROJECT_ROOT=/home/piradopirata/worktrees/opencultures-f0-adm8-e8 \
python3 -I tests/browser/aculta-document-viewer/visual.py
```

Requer Firefox ESR, geckodriver e `websockets` Python. Não altera configuração
ou conteúdo. Não valida privacidade de documentos privados, leitor de tela,
CSP, PDF de grande porte ou o resultado após deploy.
