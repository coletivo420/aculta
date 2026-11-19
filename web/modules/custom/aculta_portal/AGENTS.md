# Portal — regras locais

Herde `AGENTS.md` da raiz. Consulte `docs/AGENT-POLICIES.md` **somente nas seções relevantes** e os contratos `docs/portal/GUARDRAILS.md` e `docs/portal/DRUPAL-12-STANDARDS.md` quando a alteração exigir.

## Rotas e domínios vigentes

- Caminhos e tabela das Suites: `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md` (retificado em 2026-10-30). Conta e Perfil: ADR-048. Não repetir tabelas aqui. Matriz de rotas: `docs/architecture/ROUTES-SITEMAP.md`.
- WE SATIVA e Green Stash são as exceções aprovadas ao prefixo retirado; `aculta_studio` permanece framework compartilhado.
- Entrada da Wiki: domínio `wiki420` (canônico), URL pública `/`, reescrita internamente para `aculta_portal.wiki_home`. Entrada da loja: `/` no purpose `shop`. `/wiki`, `/loja`, `/wesativa`, `/greenstash` e `/studio*` respondem 404 sem redirect.
- Conta e Perfil (ADR-048, 2026-11-05): a raiz de `conta` apresenta o dashboard do Studio, reescrito por path interno protegido. Minha Conta fica em `/conta`; Meu Perfil privado fica em `/meu-perfil` e filhos, separado da Conta. `/salvos` preserva seu owner e a Conta legada fica em `/conta-legada`.
- Suites: rotas declaram o purpose correto e validam usuário, owner e permissão no servidor. Purpose incorreto responde 404, inclusive POST. Links cross-domain são absolutos via `DomainPurposeManager`; não há POST nem proxy de escrita entre purposes.
- Novas Suites escolhem host, purpose e slug antes de declarar rotas; não criar `/studio/{suite}`. Hosts vêm da configuração ativa por ambiente. Não renomear `wiki420` para `wiki.aculta.*`.
- Ao mudar `PurposeFrontPathProcessor`, `FrontPathPolicy`, subscriber de propósito ou navegação, testar a raiz de cada purpose e as rotas da Suite. Preservar route IDs e owners sempre que possível. Migração não transfere ownership de entidades, formulários, ACL ou workflows.
- Sem redirects legados; canonical e links apontam direto para os novos hosts e caminhos.

## Regras gerais

- Core/contrib são a fonte de verdade; preserve a fronteira Core/contrib → Portal → contrato neutro → tema.
- Endereço e CEP: política Brasil/BR e módulo central `aculta_portal_cep` em `AGENTS.md` e ADR-034; contrato de formulário em `AccountAddressForm`. Não crie formulário de endereço paralelo. Antes de criar ou editar captura de endereço, leia `docs/decisions/ADR-033-endereco-busca-e-locais.md` e `docs/portal/ACCOUNT-ADDRESS.md`. Busca por logradouro é exclusiva de Events/Maps e não está implementada; preserve o comportamento de Conta e checkout.
- Independência do tema: os módulos do Portal funcionam sem o tema ACULTA420. Valide contrato e renderização sem trocar o tema padrão do Runtime.
- Valide DI, Entity API, access, render/cacheability e gates do escopo.
- Rotas administrativas somente no MAIN (regra na raiz). Em subdomínio não renderize toolbar, link contextual ou ação administrativa. Política: `docs/portal/ADMIN-DOMAIN-POLICY.md`.
- Regras adicionais de submódulos só se aplicam ao submódulo alterado.

## Culturas

- Decisão de produto e domínio do ACULTA Studio Culturas: `web/modules/custom/aculta_portal/modules/aculta_events/AGENTS.md`.

