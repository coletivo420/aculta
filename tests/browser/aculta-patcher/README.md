# Revisão visual do ACULTA Patcher

Abre a listagem e o formulário de inclusão do Patcher no Firefox ESR visível,
no servidor de desenvolvimento `aculta.toca.net.br`. Gera o link único de
login para UID 1 no host de Conta e confirma a sessão no host principal; nunca
imprime nem persiste esse link e não submete
formulários. Capturas e resumo ficam fora do repositório em `SUITE_OUT`.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-patcher-visual \
DISPLAY=:0 ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
  python3 -I tests/browser/aculta-patcher/overview.py
```

Requer Firefox ESR, geckodriver, `websockets` Python, Drush conectado ao Runtime
de teste e conta UID 1 com acesso administrativo. Testa somente a listagem e os
campos visíveis em 1280, 390 e 320 px; não testa gravação, exportação por POST,
leitor de tela completo nem a aplicação Composer (coberta por outra fase).
