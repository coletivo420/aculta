# ACULTA — contraste de UI Skins

`axe-nodes.mjs` mede `color-contrast` (axe-core, WCAG 2.2 AA) nos dez
domínios de desenvolvimento, em claro e escuro, e devolve o seletor, o HTML e
o par de cores de cada nó reprovado. É a frente de antes/depois para correções
de tokens e UI Skins; não autentica e não altera conteúdo.

## Como rodar

```sh
SUITE_OUT=/caminho/externo/fora-do-repo \
  node tests/browser/transversal/aculta-contraste/axe-nodes.mjs
```

- Sem argumentos, usa a matriz padrão (dez hosts mais a listagem de categorias
  do Fórum). URLs extras substituem a matriz.
- `SUITE_OUT` é obrigatório, absoluto e fora do repositório; recebe
  `contraste-axe.json`.
- Motor: Firefox do Playwright, headless (método 1 de
  `docs/operations/NAVEGADOR-TESTES.md`). O modo escuro é forçado por
  `data-theme="dark"`, o mesmo atributo que o Portal emite.
- Códigos: 0 todos os pares passam; 1 há violação ou erro de carga;
  2 falta `SUITE_OUT`.

## Histórico

- 2026-11-12: dois achados pré-existentes corrigidos (link do rodapé 3,45:1 e
  nível atual da trilha 3,58:1). Ver
  `docs/operations/testing/CONTRASTE-FOOTER-2026-11-12.md`.
