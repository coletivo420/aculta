# Revista — rótulos de categoria

O teste abre o host de desenvolvimento em Firefox ESR visível e aplica, somente
no DOM da página, cada uma das quatro classes aprovadas aos rótulos sintéticos.
Mede cores computadas e overflow em 1280 px e 320 px e salva capturas em
`$SUITE_OUT`. Não cria nem altera Nodes, termos, configuração ou sessão do Drupal.

Ambiente: servidor de desenvolvimento (`https://aculta.toca.net.br/`), Firefox
ESR em `DISPLAY=:0`, geckodriver e a biblioteca reutilizável
`tests/browser/aculta-cpf-checkout/wdlib.py`.

```sh
DISPLAY=:0 SUITE_OUT=/home/piradopirata/aculta-suite-out/e8-category \
  python3 tests/browser/revista-color/visual.py
```

Limitação: os rótulos são sintéticos para verificar a apresentação e as regras
CSS. A suíte Unit cobre a aplicação da classe pelo formatter. O Runtime não tem
publicações elegíveis para capturar um cartão real, e o teste não grava uma cor
em termos editoriais existentes.
