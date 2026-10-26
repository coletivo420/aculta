# Changelog

## 0.1.0-dev.1

- Extraída a UI administrativa de `aculta_portal` para este submódulo: controller, formulários de
  alteração e remoção, rotas, permissão `administer aculta secrets`, link de menu e de aba, biblioteca
  das abas (JS e CSS).
- Mantidos os nomes das rotas (`aculta_portal.secrets_*`), do link de menu e da biblioteca do painel
  `aculta_portal/requirements-report`, porque o Portal gera links para eles (`DeployerStatusController`,
  `PortalRequirementsController`).
- Abas Produção e Testes (ARIA, teclado, mobile com rolagem horizontal).
- Escopo por ambiente no backend: `value()`, `save()` e `clear()` recusam credencial fora do ambiente
  ativo (no núcleo, em `aculta_portal`). Controller nega mostrar, editar e apagar fora do escopo (403).
- `Cache-Control: no-store, private` e `Pragma: no-cache` nas rotas do painel.
- Token CSRF (`_csrf_token`) nas rotas de revelar, alterar e apagar.
- Testes unitários: abas, escopo, cabeçalhos.
