# ACULTA — teclado e foco visível

`teclado.mjs` percorre os primeiros elementos focáveis com Tab em claro e
escuro, em 320 e 1440 CSS px, e exige indicador de foco (outline ou
box-shadow) em cada parada. Também confere que o foco continua dentro do
documento após o laço. Não autentica, não envia formulários e não altera
conteúdo.

## Como rodar

```sh
SUITE_OUT=/caminho/externo/fora-do-repo \
  node tests/browser/transversal/aculta-teclado-foco/teclado.mjs
```

- `SUITE_OUT` é obrigatório, absoluto e fora do repositório; recebe
  `teclado.json`.
- `TECLADO_URLS` substitui a matriz padrão (main, Fórum, Revista, Eventos e o
  login da Conta); `TECLADO_WIDTHS` substitui `320,1440`.
- Motor: Firefox do Playwright, headless (método 1 de
  `docs/operations/NAVEGADOR-TESTES.md`). Transições e animações são
  desligadas antes da varredura.
- Códigos: 0 passou; 1 há parada sem foco visível ou erro de carga;
  2 falta `SUITE_OUT`.

## Limites

- Não prova leitor de tela nem ordem de leitura semântica; cobre foco de
  teclado e indicador visível.
- Diálogos e gavetas abertos por JavaScript exigem um roteiro próprio; este
  script mede a página como entregue.

## Histórico

- 2026-11-12: 20/20 PASS (5 URLs × claro/escuro × 320/1440, 30 paradas cada).
