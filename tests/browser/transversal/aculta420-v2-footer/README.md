# ACULTA420 V2 — rodapé e regions do shell

`visual.py` captura a página inicial do servidor de desenvolvimento no Firefox
ESR visível (`DISPLAY=:0`) e confere, em 320, 390 e 1440 CSS px, nos modos claro
e escuro:

- Institution Bar e Domain Header presentes e inalterados na estrutura;
- rodapé institucional renderizado com conteúdo;
- ausência de rolagem horizontal da página;
- presença condicional dos regions `highlighted`, `help`, `breadcrumb` e
  `sidebar_first` quando o Drupal os preenche.

Artefatos em `$SUITE_OUT`, fora do repositório. Este script complementa a
revisão visual humana do rodapé; não substitui a aceitação do responsável.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-footer" \
  python3 tests/browser/transversal/aculta420-v2-footer/visual.py
```
