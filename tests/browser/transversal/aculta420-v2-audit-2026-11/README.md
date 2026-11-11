# Homologação visual do aculta420_v2 — auditoria 2026-11-11

Frente transversal criada para a auditoria de CSS e layout do tema
`web/themes/custom/aculta420_v2`. Mede os domínios públicos nos dois modos de
cor e nas larguras pedidas pelo plano.

## Como rodar

```bash
SUITE_OUT=/caminho/externo \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ \
node tests/browser/transversal/aculta420-v2-audit-2026-11/audit.mjs
```

- `SUITE_OUT` é obrigatório, absoluto e fora do repositório; guarda capturas
  PNG e `audit-summary.json`.
- O manifest versionado fica em
  `tests/browser/capturas/aculta420-v2-audit/2026-11-11/manifest.json`.
- Motor: Firefox do Playwright, headless (método 1 de
  `docs/operations/NAVEGADOR-TESTES.md`).

## Cobertura

- Domínios: home, Fórum (`growroom420.aculta.*`), Links, Wiki, Revista,
  Eventos, Loja, Apoio, Cursos e login da Conta.
- Larguras: 320, 390, 768, 959, 960, 1024 e 1440 CSS px.
- Modos: claro (como o servidor entrega) e escuro (forçado com
  `data-theme="dark"` no `<html>`; o CSS é o mesmo que a preferência da pessoa
  usuária aciona no servidor).

Cada combinação verifica:

1. canvas esperado (`#f2f7f0` no claro, `#171513` no escuro);
2. ausência de overflow horizontal (`scrollWidth <= innerWidth + 1`);
3. exatamente um `main` e no máximo um `h1`;
4. contraste do cabeçalho institucional (mínimo 4,5:1);
5. `aside` visualmente à esquerda do conteúdo a partir de 960 px.

## Limitações conhecidas

- A UI Patterns Library exige autenticação e não é coberta por este laço.
- O modo escuro é forçado por `data-theme`; a preferência salva no servidor é
  coberta pelos testes do Portal, não por esta varredura.
- Contraste de todos os pares de tokens é coberto pelo gate
  `scripts/validate-aculta420-v2-foundations.php`; aqui só o cabeçalho
  institucional é medido no navegador.
