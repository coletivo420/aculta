# QA HTTP - Moderação própria da Wiki no WE Sativa

A suíte Playwright é **read-only** e não é parte do runner global.
Os testes só executam com manifesto privado confirmando `isolated=true`.
Accounts e storageState devem ser sintéticos, fora do Git, em banco de testes
separado do Runtime. O manifesto de exemplo não contém credenciais funcionais.

```bash
cd tests/browser
ACULTA_WIKI_MODERATION_QA_FIXTURE=/caminho/privado/wiki-moderation-fixture.json \
  npx playwright test --config qa-wiki-moderation/playwright.config.mjs
```

Sem fixture ou cookies, todos os casos ficam SKIP, o que **não equivale a PASS**.

A matriz inclui anonimato, papel só Fórum, moderação de propostas, edição
editorial, denúncias Wiki, papéis CGU combinados, alvo privado e negação no
domínio Fórum. A decisão efetiva via POST/CSRF, revisão concorrente,
permissões do editor no Content Moderation, contraste e WCAG exigem
Kernel/Browser autenticados em ambiente descartável antes de homologar.
