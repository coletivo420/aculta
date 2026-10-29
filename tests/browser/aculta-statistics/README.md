# Revisão visual do painel de estatísticas

Firefox ESR visível no servidor de desenvolvimento `aculta.toca.net.br`. O
runner autentica a conta administrativa pelo link único de uso único gerado no
host `conta.aculta.toca.net.br`, abre as oito rotas do painel (visão geral e
sete áreas), mede overflow em 1280, 768, 390 e 320 CSS px e percorre os links por
teclado para conferir `:focus-visible`. Não altera dados nem configuração.
Na visão geral, acrescenta uma fixture sem dados para medir o reflow dos
filtros em 320 e 768 CSS px; isso verifica a geometria da folha, não o
formulário real nem a disponibilidade de filtros em outras rotas.
Capturas e `summary.json` ficam fora do repositório em `SUITE_OUT`.
O perfil temporário do Firefox também fica dentro de `SUITE_OUT` e é removido
ao encerrar o runner.

```sh
SUITE_OUT=/caminho/fora-do-repositorio/aculta-statistics \
ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
DISPLAY=:0 python3 -I tests/browser/aculta-statistics/visual.py
```

Para pré-visualizar um CSS ainda não instalado no servidor, defina
`STATISTICS_CSS_PREVIEW=/caminho/absoluto/statistics-dashboard.css`. Esse modo
injeta o arquivo só na sessão do Firefox; não comprova que a biblioteca Drupal
foi anexada nem substitui a revisão visual do CSS instalado.

Usa o usuário administrativo UID 1 já existente no Runtime de desenvolvimento.
Não registra a URL de login, cookies, dados ou tokens. Esta revisão não cobre
leitor de tela, zoom de 200%, consultas reais GA4/Search Console nem Olivero.
