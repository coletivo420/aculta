# Portal — instruções locais

Herde `AGENTS.md` da raiz. Leia apenas os contratos aplicáveis em `docs/portal/GUARDRAILS.md`, `docs/portal/DRUPAL-11-STANDARDS.md` e README do componente afetado.

- Core/contrib são fonte de verdade; não duplique storage ou capacidades existentes.
- Preserve fronteira Core/contrib → Portal → contrato neutro → tema.
- Verifique DI, Entity API, access, render/cacheability e testes pertinentes.
- Antes de mudar rotas ou acesso por purpose, consulte o mapa de rotas e contratos vinculados.
- Não leia documentação de outros submódulos sem dependência comprovada.
