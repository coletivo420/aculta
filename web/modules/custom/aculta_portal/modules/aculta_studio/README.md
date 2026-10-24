# ACULTA Studio (`aculta_studio`)

ACULTA Studio é a plataforma transversal de interfaces próprias na conta. O machine name do submódulo permanece `aculta_studio`.

## Próxima evolução — Meu Espaço e Hub (STUDIO-P0, documentação)

Proposta de 2026-10-24: evoluir `/studio` para hub geral, criar a experiência
autenticada **Meu Espaço** (`pessoal`), oferecer navegação global unificada
com seções contextuais, e migrar a **edição** de Minha Conta/Meu Perfil para
a interface do Studio. `/u/{apelido}` permanece página pública em `main`.

**Nenhum desses caminhos ou providers novos foi implementado nesta fase.**
`/studio` ainda é rota legada do Marley; a conta mantém seu shell e a home
na raiz `/` do purpose `account`. O Studio não se torna dono de Profile,
dados pessoais, formulários, autenticação, inbox, e-mails ou regras de acesso.
Reutilizar `StudioExperienceManager`, preparar ponte de metadados para Marley
sem alterar `MarleyTabManager`, e impedir shells aninhados.

Contrato canônico: [Hub e Meu Espaço](../../../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).
Execução faseada: [ROADMAP.md](docs/ROADMAP.md), marcos STUDIO-P0–P7.

## Experiências

- **ACULTA Studio Marley (`marley`)**: espaço editorial de criação, edição e moderação. Usa contratos exclusivos `MarleyTab`, `MarleyTabInterface`, `MarleyTabBase`, `MarleyTabManager`, `MarleyPresenter`, `MarleyListingBuilder` e `MarleyController`. Os IDs funcionais das áreas Revista, Wiki, Cursos, Fórum e Galerias não mudam.
- **ACULTA Studio Ganjalab (`ganjalab`)**: entrada estudantil composta pelo `GanjalabExperienceProvider` e `GanjalabController`. `StudioExperienceManager` permanece registro compartilhado. `LearnerDashboardComposer`, `LearningDashboardManager` e `aculta_lms.student_dashboard` mantêm seus contratos neutros.
- **WE SATIVA**: experiência independente de contribuição Wiki, nas rotas `/studio/we-sativa` e `/studio/we-sativa/inicio`.

A infraestrutura apresenta e encaminha. Os módulos proprietários continuam donos de entidades, formulários, workflow, permissões, matrículas, avaliações, progresso e cache de negócio.

## Rotas

Todas as experiências usam `_aculta_domain_purpose: account`, `access aculta portal` e o domínio resolvido pelo Portal.

- Marley: `/studio/marley`, `/studio/marley/{aba}`, `/studio/marley/{aba}/{secao}`, `/studio/marley/{aba}/{secao}/{sub}`.
- Ganjalab: `/studio/ganjalab` (estudante autenticado; declarada pelo LMS).
- WE SATIVA: `/studio/we-sativa` e `/studio/we-sativa/inicio`.

A entrada antiga `/studio/ganjalab` não pode continuar editorial: agora pertence exclusivamente a Ganjalab. As entradas antigas não colidentes usam redirects temporários 302 apenas em GET/HEAD: `/ganjalab` e níveis profundos editoriais para Marley; `/ganjalab420`, `/minha-aprendizagem` e `/studio/ganjalab420` para Ganjalab. Métodos de escrita não são redirecionados. O registro completo está em [contrato de identidade](../../../../../../docs/portal/ACULTA-STUDIO-IDENTITY.md) e no [mapa de rotas](../../../../../../docs/architecture/ROUTES-SITEMAP.md).

## Navegação e access

A navegação de Marley oferece links reais sem JavaScript e aprimoramento AJAX com histórico do navegador, foco e anúncio `aria-live`. IDs de plugin das áreas permanecem estáveis. Cada aba exige disponibilidade do módulo dono e sua permissão real; ações continuam sujeitas ao acesso da rota proprietária. O dashboard de Ganjalab preserva autenticação, autorização e dados do LMS.

Atributos `data-portal-ui` e `data-aculta-studio` são contratos de comportamento/apresentação; o tema ACULTA420 fornece o visual por SDC/UI Suite. O módulo também precisa funcionar sem tema específico.

## Histórico e validação

Documentos e changelogs anteriores podem usar os nomes antigos para registrar decisões passadas. Eles não definem a identidade vigente; consulte o contrato acima. Runtime e navegador não foram alterados por esta implementação local.

Gates do escopo: PHP lint, PHPCS disponível, Unit/Kernel/Functional aplicáveis, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php` e `git diff --check`. Testes visuais seguem `tests/browser/aculta-studio/visual.mjs` com Firefox ESR/X11 autorizado.
