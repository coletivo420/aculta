# Documentação ACULTA420

Índice do tema e do design system. Só documentos normativos e de referência vigentes.

1. [architecture.md](architecture.md) — camadas, fronteiras e regras de apresentação.
2. [classes-hooks.md](classes-hooks.md) — inventário das classes PHP, hooks e métodos atuais do tema.
3. [shell.md](shell.md) — contrato do shell multidomínio.
4. [components.md](components.md) — componentes SDC, schemas e status.
5. [component-catalog.md](component-catalog.md) — biblioteca visual, stories e regras de acesso.
6. [design-system.md](design-system.md) — tokens, modos de cor e Bootstrap.
7. [accessibility.md](accessibility.md) — requisitos de acessibilidade.
8. [development.md](development.md) — gates, overrides de Twig e regras de desenvolvimento.
9. [decisions.md](decisions.md) — decisões de arquitetura do tema.
10. [versioning.md](versioning.md) — SemVer, subversões e releases.
11. [roadmap.md](roadmap.md) — saneamento e features de produto.
12. [branding-wiki420.md](branding-wiki420.md) — identidade estática e integração da Wiki420.
13. [branding-podplant420.md](branding-podplant420.md) — identidade estática e política de imagens do Podplant420.
14. [branding-baque-sativa.md](branding-baque-sativa.md) — kit de logomarcas do Grupo de Percussão Baque Sativa (cards e páginas do grupo; fora do cabeçalho global).
15. [branding-bloco-sativa420.md](branding-bloco-sativa420.md) — kit de artes do Bloco Sativa 420 (cards e páginas de projeto; fora do cabeçalho global; autoria e licença pendentes).
16. [mobile-first-audit.md](mobile-first-audit.md) — inventário responsivo do ACULTA420 e do Studio, com evidências e sequência MF.

Histórico de versões: [../CHANGELOG.md](../CHANGELOG.md). Estado de QA de releases: Git e changelog.

- [Messenger: guia de desenvolvimento do tema](messenger.md): componentes, tokens, atributos, JS e receitas do Messenger.

## Novo caminho: Design System transversal

[Roadmap visual transversal ACULTA420](visual-cross-product-roadmap.md) concentra o planejamento de componentes compartilhados para GanjaLab420, GanjaLab, Revista, Wiki, Fórum, Galeria e Conta. Ele separa o ownership visual do tema das integrações de LMS e kernels, e exige manutenção do cabeçalho multidomínio em todas as telas. Complementa, sem substituir, o [roadmap existente](roadmap.md).

- [theme-ux-0.8-a-ui-suite-audit.md](theme-ux-0.8-a-ui-suite-audit.md) — próxima fase de auditoria de componentes/consumidores e uso efetivo das ferramentas UI Suite.

- [theme-ux-0.8-b0-execution-matrix.md](theme-ux-0.8-b0-execution-matrix.md) — matriz operacional de evidências Runtime do shell e ferramentas UI Suite; ainda não executada.

- [sdc-ui-suite-inventory-2026-10-22.md](sdc-ui-suite-inventory-2026-10-22.md) — inventário estático dos SDCs, cobertura de stories, lacunas e migrações M0–M6.

- [theme-ux-0.8-b0-static-followup.md](theme-ux-0.8-b0-static-followup.md) — continuação da auditoria estática, revisão de metadados e primeiro slice contextual condicionado ao Runtime.

- [theme-ux-0.8-m0-s-structural-audit.md](theme-ux-0.8-m0-s-structural-audit.md) — achados e decisões da inspeção estrutural SDC, QR, abas e Context Header.

- [theme-ux-0.8-m2-b1-context-header.md](theme-ux-0.8-m2-b1-context-header.md) — contrato da próxima fase SDC Context Header, consumidores e gates multidomínio.

- [theme-ux-0.8-b1-source-audit.md](theme-ux-0.8-b1-source-audit.md) — inspeção de breadcrumb, H1/H2 e contrato preliminar de Context Header sem duplicar o shell.

- [theme-ux-0.8-b1-02-ownership-h1.md](theme-ux-0.8-b1-02-ownership-h1.md) — fase B1-02: consumidores, renderização seletiva e H1 único sob responsabilidade do SDC contextual.

- [theme-ux-0.8-b1-02a-consumer-code-map.md](theme-ux-0.8-b1-02a-consumer-code-map.md) — inventário de rotas/controllers do LMS e H1 existente no SDC Wiki; gate para B1-02b.

- [theme-ux-0.8-b1-02b-visual-contract.md](theme-ux-0.8-b1-02b-visual-contract.md) — contrato visual mobile-first de Context Header, variantes, H1 e UI Suite.

- [theme-ux-0.8-b1-02b-visual-contract.md](theme-ux-0.8-b1-02b-visual-contract.md) — agora inclui o primeiro SDC experimental de Context Header e gates de adoção.

- Integração B1-02d: Wiki verbete completo já chama `aculta420:context-header` (experimental), com supressão localizada do H1 no `wiki-entry`; homologação em Drupal pendente. Ver [contrato visual](theme-ux-0.8-b1-02b-visual-contract.md).

- [theme-ux-0.8-b1-02e-runtime-validation.md](theme-ux-0.8-b1-02e-runtime-validation.md) — gates de homologação do Context Header integrado ao LMS e à Wiki, pendentes de Runtime.

- [theme-ux-0.8-b2-context-tabs.md](theme-ux-0.8-b2-context-tabs.md) — fase B2, SDC de abas por rotas, auditoria de Studio e integração ainda pendente.

- B2-03: primeiro consumidor de `context-tabs` em `aculta_studio`, com validação visual/Runtime pendente. [Plano B2](theme-ux-0.8-b2-context-tabs.md).

- [theme-ux-0.8-b2-05-static-gates.md](theme-ux-0.8-b2-05-static-gates.md) — checagem estática B2, com homologação no servidor pendente.
- [theme-ux-0.8-b3-feedback-audit.md](theme-ux-0.8-b3-feedback-audit.md) — auditoria inicial dos estados e feedback SDC/UI Suite do portal.

- B3: [auditoria e implementação de empty-state](theme-ux-0.8-b3-feedback-audit.md) — SDC compartilhado Wiki/LMS; homologação Runtime pendente.

- [theme-ux-0.8-b4-utility-panels.md](theme-ux-0.8-b4-utility-panels.md) — auditoria de avisos, mensagens, carrinho e painel Minha Conta; correções CSS/SDC e gates Runtime.
