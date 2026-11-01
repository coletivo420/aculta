# Revisão visual das opções administrativas do ACULTA Image

O teste abre `/painel-administrativo/imagens/configuracoes` no servidor de
desenvolvimento (`aculta.toca.net.br`) usando Firefox ESR visível em
`DISPLAY=:0`. Confere os controles de compressão, WebP, MIME e formato de saída,
o painel de bibliotecas detectadas e a ausência de overflow em 1280, 390 e 320
CSS px.

O script cria uma conta temporária e um papel com a única permissão
`administer aculta images`. O login por link ULI é mantido em memória e nunca
impresso. Conta e papel são removidos em `finally`; a execução deve ser feita
somente no servidor de desenvolvimento com o banco `aculta_runtime`. Não salva
as opções nem altera arquivos de imagem.

```sh
SUITE_OUT=/caminho/absoluto/fora-do-repositorio/aculta-image-admin \
ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
DISPLAY=:0 python3 -I tests/browser/aculta-image-admin/visual.py
```

As capturas e o resumo JSON ficam em `SUITE_OUT`. O script reutiliza a camada
WebDriver de `tests/browser/aculta-cpf-checkout/wdlib.py` e a API de viewport
BiDi do Firefox.
