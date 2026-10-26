# Micropágina pública: layout mobile no navegador

Script `verifica_micropagina.py`. Roda no **Firefox ESR visível** na sessão X11 local (`DISPLAY=:0`), pelo geckodriver,
via `tests/browser/aculta-cpf-checkout/wdlib.py`. Não usa Chromium nem headless.

## O que cobre

Para a micropágina `LINK_SLUG` publicada no servidor de teste, como visitante anônimo, em 1280 px e 390 px:

| Verificação | Esperado |
| --- | --- |
| resposta HTTP | 200 |
| rolagem horizontal | ausente |
| alvos de toque do conteúdo (dentro de `<main>`) | nenhum com altura menor que 44 px (WCAG 2.5.5) |

## O que não cobre

- **Cromo do tema** (barra institucional e rodapé): é do ACULTA420 e está fora do escopo desta frente.
- **Página com administrador logado** e edição de itens.
- **Páginas sem nenhum link publicado** (o estado vazio tem outro layout).

## Pré-requisito: página publicada

O script não cria páginas. Para o teste, é preciso uma micropágina publicada no servidor de teste. A execução de
referência usou uma página de teste criada por Drush (`slug = pagina-teste-mobile`, um item de botão) e removida ao
final. Não há credencial no script.

## Como executar

```sh
DISPLAY=:0 LINK_SLUG=<slug-publicado> SUITE_OUT=/tmp/aculta-links-micropagina \
  python3 tests/browser/aculta-links/verifica_micropagina.py
```

Variáveis: `LINKS_HOST` (padrão `https://links.aculta.toca.net.br`), `LINK_SLUG` (padrão `pagina-teste-mobile`),
`SUITE_OUT`, `TIMEOUT`, `FIREFOX`, `GECKODRIVER`.

Código de saída: `0` se tudo passou; `1` se alguma verificação falhou.

## Histórico de execução

- Antes da correção: falha, com 1 alvo de 20 px ("Botão de teste") em 390 px e em 1280 px. Motivo: o link do item
  não tinha classe nem estilo de área de toque (`cfa192096` corrige, com a biblioteca `aculta_links/micropagina`).
- Depois da correção: 2/2 PASS.
