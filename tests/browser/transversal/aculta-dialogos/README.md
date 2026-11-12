# ACULTA — diálogos e gaveta de Mensagens

`dialogos.mjs` abre os diálogos disparados por `data-aculta-dialog-trigger` e
verifica, em claro/escuro e 320/1024 CSS px:

- o diálogo abre e o foco entra nele;
- 20 Tabs mantêm o foco dentro do diálogo;
- `Escape` fecha e devolve o foco ao gatilho;
- não há overflow horizontal;
- axe `color-contrast` não acusa violação dentro do diálogo.

Casos: carrinho e menu da conta (anônimos), gaveta de Mensagens (conta de teste
administrativa). A conta comum recebe o modal de aceite dos termos, que prende
o foco por desenho até o aceite; por isso a gaveta usa a conta administrativa e
não grava aceite.

## Como rodar

```sh
SUITE_OUT=/caminho/externo/fora-do-repo \
  node tests/browser/transversal/aculta-dialogos/dialogos.mjs
```

- `SUITE_OUT` obrigatório, absoluto e fora do repositório; recebe
  `dialogos.json`.
- `DIALOGO_WIDTHS` substitui `320,1024`.
- Credenciais vêm de `secrets/` via `tests/browser/auth-login.mjs`; nada é
  impresso nem versionado.
- Motor: Firefox do Playwright, headless (método 1 de
  `docs/operations/NAVEGADOR-TESTES.md`).
- Códigos: 0 passou; 1 falhou; 2 pré-requisito ausente (ex.: aceite de termos
  pendente na conta).

## Histórico

- 2026-11-12: 12/12 PASS (3 diálogos × claro/escuro × 320/1024).
