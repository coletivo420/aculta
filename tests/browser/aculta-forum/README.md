# Suíte visual X11 do ACULTA Forum

`progress-visual.mjs` verifica a apresentação e a interação da barra de progresso
em Firefox visível numa sessão X11. Ela usa uma página pública de publicação que
já carrega a biblioteca de discussão e injeta seis mensagens **somente no DOM**.
Não autentica, não envia formulários e não grava Nodes ou Comments.

## Pré-requisitos

- Node.js 22+, Firefox ESR e geckodriver;
- uma sessão gráfica X11 ativa (por exemplo, `DISPLAY=:0`);
- o Runtime de teste ACULTA e uma URL pública de publicação com
  `field_aculta_discussion` renderizado;
- geckodriver acessível em `http://127.0.0.1:4444` ou `GECKODRIVER`.

## Execução

```sh
DISPLAY=:0 geckodriver --host 127.0.0.1 --port 4444
```

Em outro terminal da mesma sessão X11:

```sh
DISPLAY=:0 SUITE_PAGE='https://<host-de-teste>/<publicacao>' \
SUITE_OUT='/home/<usuario>/private/aculta/visual-tests/forum-progress' \
node tests/browser/aculta-forum/progress-visual.mjs
```

Use somente hosts de teste declarados em
`aculta_deployer/config/deploy.json`. `SUITE_OUT` deve ser um caminho absoluto
fora do checkout; arquivos são criados com permissões privadas. A suíte valida
viewports CSS exatos de 320, 360, 390, 500 e 900 px via WebDriver BiDi,
estados claro/escuro, overflow introduzido pelos controles, tamanho mínimo do
controle móvel e anúncio da primeira mensagem marcada como não lida. Capturas
PNG e `results.json` ficam no diretório indicado.

Ela não substitui inspeção com leitor de tela, dispositivo físico ou validação
de comentários reais autenticados pelo History. A página deve ser de teste e
somente leitura; a suíte encerra sua própria sessão WebDriver ao terminar.
