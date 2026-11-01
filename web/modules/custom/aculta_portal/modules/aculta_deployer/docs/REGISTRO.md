# Registro de correções de deploy

Arquivo: `registry/deploy-registry.json`. Schema 1.

## Campos

| Campo | Obrigatório | Descrição |
| --- | --- | --- |
| `id` | sim | `DEP-NNNN`, sequencial, gerado por `register`. |
| `kind` | sim | `canonical`, `sitemap`, `link`, `redirect` ou `other`. |
| `status` | sim | `open` ou `resolved`. |
| `blocking` | não | `true` impede o build de produção enquanto a entrada estiver aberta. |
| `page` | sim | Página ou recurso afetado. |
| `current` | sim | Valor observado no servidor de desenvolvimento. |
| `expected_production` | sim | Valor esperado em produção. |
| `reason` | sim | Por que o código não resolve sozinho. |
| `owner` | sim | Responsável pela decisão. |
| `decision` | sim | `pendente` ou a decisão tomada. |

## Ciclo de vida

1. Um problema é observado (validador de navegador, revisão ou teste).
2. `register` cria a entrada como `open`. Use `--blocking` se o problema bloqueia a produção.
3. A correção é feita no código ou no deploy, e o `decision` é atualizado.
4. Depois do deploy e da verificação, a entrada passa a `resolved`.

Entradas resolvidas permanecem no arquivo como histórico.
