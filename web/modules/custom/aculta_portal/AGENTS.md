# Portal — regras locais

Herde `AGENTS.md` da raiz. Consulte `docs/AGENT-POLICIES.md` **somente nas seções relevantes** e os contratos `docs/portal/GUARDRAILS.md` e `docs/portal/DRUPAL-11-STANDARDS.md` quando a alteração exigir.

- Core/contrib são a fonte de verdade; preserve a fronteira Core/contrib → Portal → contrato neutro → tema.
- Endereço e CEP: use exclusivamente o contrato central em `AccountAddressForm` e `aculta_portal_cep`. O país é sempre Brasil (`BR`), sem seletor de país em formulário algum. Validação no servidor obrigatória (`country_code = BR`). Preserve número, S/N e complemento no CEP. Ver ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`.
- Valide DI, Entity API, access, render/cacheability e gates do escopo.
- Para rotas/domínios, consulte `docs/architecture/AGENT-ROUTES.md` e `docs/architecture/ROUTES-SITEMAP.md`.
- Regras adicionais de submódulos só se aplicam ao submódulo alterado.

- Antes de criar ou editar captura de endereço, leia `docs/decisions/ADR-033-endereco-busca-e-locais.md` e siga `docs/portal/ACCOUNT-ADDRESS.md`: reutilize o adaptador central e CEP para endereço completo; Brasil padrão, sem seletor.
- Busca por logradouro é exclusiva de Events/Maps e não está implementada. Preserve o comportamento de Conta/checkout. Para Activity, integre Local de Events em vez de manter campos postais avulsos.
