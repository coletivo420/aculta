# ACULTA Studio (`aculta_studio`)

## Contrato vigente — Conta e Perfil separados (dev.13)

A arquitetura foi corrigida: **Minha Conta Studio** fica em
`/studio/conta` e **Meu Perfil Studio** fica em `/studio/perfil`,
como experiências independentes no hub `/studio`. `/studio/pessoal/*`
não existe mais e **não tem alias de compatibilidade**.

Minha Conta **legada é permanente** na raiz `/` do Domain ACCOUNT,
com shell, menus, dados e formulários intactos, para comparação
antes e depois do aceite. O caminho técnico direto `/minha-conta`
continua 404; não o habilitar. Conta Studio reutiliza links/forms
do Portal e Perfil Studio apenas funções do ACULTA User; o apelido
ainda exige editor isolado futuro. Nenhum PHP/Unit/Kernel/Runtime ou
Firefox foi executado nesta edição pelo GitHub.

Contrato principal:
[Conta e Perfil separados](../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
**As seções dev.8–12 abaixo são registros históricos e estão superadas.**


## STUDIO-P2-A — edição inicial de Meu Perfil (dev.12)

**Novo endereço `/studio/pessoal/perfil`**: página privada que compõe as
caixas oficiais de bio e redes do ACULTA User, links para seus modais de
edição, foto/avatar no editor de upload e recorte existente, e link ao
perfil público somente quando o serviço `aculta_user.public_profile_link`
aprova. Apelido/dados e privacidade continuam temporariamente nos
formulários originais; os handlers, dados, permissões e `/u/{apelido}`
permanecem sob seus donos.

`/studio/pessoal` já oferece link ao editor. Dependências User opcionais
não quebram a página sem o módulo; exige login, purpose account e permissão.
**Não foram criados aliases nem movidos dados ou shells**. PHPUnit,
Kernel/Functional, Runtime/Drush e Firefox ESR/X11 seguem pendentes, e
o fallback sem JavaScript dos modais bio/redes precisa evoluir na P2-B.

## Estado vigente — dev.11 (sem aliases temporários)

A única entrada global é **`/studio`** (route ID `aculta_studio.hub`).
A entrada editorial é **`/studio/marley`**, mais as subrotas canônicas.
Meu Espaço: `/studio/pessoal`. Ganjalab: `/studio/ganjalab`;
WE SATIVA: `/studio/we-sativa`.

Foram removidas as rotas transitórias `/studio/inicio`,
`/studio/Marley`, `/ganjalab`, `/ganjalab420`,
`/minha-aprendizagem` e suas árvores antigas. **Não redirecionam**.
A documentação dev.8–10 abaixo é histórica e não autoriza restabelecer
aliases. Código no Git; Unit/Kernel/Runtime/Firefox ainda requerem
execução neste incremento. Política: `AGENTS.md` e
`scripts/validate-no-temporary-route-aliases.php`.



ACULTA Studio é a plataforma transversal de interfaces próprias na conta. O machine name do submódulo permanece `aculta_studio`.

## STUDIO-P1-C — Hub e Marley com rota canônica (dev.10)

**Entrada geral do Studio: `/studio`. Entrada inicial do Marley:
`/studio/marley` (minúscula).** O hub apresenta links de experiências
autorizadas, com filtro de módulo opcional e checagem de rota existente.
Marley continua no `MarleyTabManager` e usa o mesmo controller e subrotas.

Compatibilidade: `/studio/Marley` redireciona temporariamente para a
entrada minúscula; `/studio/inicio`, antiga prévia, redireciona
temporariamente para `/studio`, apenas em GET/HEAD. O route ID
`aculta_studio.home` foi preservado. Meu Espaço, a Conta atual,
Ganjalab, WE SATIVA e o perfil público seguem independentes.

**Código implementado, homologação pendente:** testes PHPUnit, Kernel,
Runtime e Firefox ESR/X11 desta fase ainda não foram executados. O corte
da navegação antiga de Minha Conta e a edição do perfil ainda pertencem
às fases posteriores P2–P7.

## STUDIO-P1-B — Marley no registry e prévia do hub (dev.9)

O catálogo agora registra também `MarleyExperienceProvider`, que usa as
áreas autorizadas pelo `MarleyPresenter` e **não** substitui
`MarleyTabManager`. `StudioExperienceManager::all()` fornece a enumeração
ordenada, e a prévia `/studio/inicio` (purpose `account`, GET/HEAD) mostra
links das experiências disponíveis **com autorização** e rotas existentes.

**`/studio` ainda é a entrada de compatibilidade do Marley**, e a home
`/` do Domain ACCOUNT continua Minha Conta. Esta prévia não cria menu
unificado definitivo nem migra dados, formulários ou o shell atual. Testes
Unit foram adicionados, mas ainda não executados no Runtime nesta entrega.
Ver [ROADMAP](docs/ROADMAP.md), P1-B.

## STUDIO-P1-A — Meu Espaço: entrada mínima implementada (dev.8)

Proposta de 2026-10-24: evoluir `/studio` para hub geral, criar a experiência
autenticada **Meu Espaço** (`pessoal`), oferecer navegação global unificada
com seções contextuais, e migrar a **edição** de Minha Conta/Meu Perfil para
a interface do Studio. `/u/{apelido}` permanece página pública em `main`.

**A entrada `/studio/pessoal` e o provider `pessoal` já existem em código, mas
não foram homologados no Runtime nesta entrega.** O hub geral, a navegação
unificada e o editor de perfil continuam planejados. `/studio` ainda é rota
legada do Marley; a conta mantém seu shell e a home
na raiz `/` do purpose `account`. O Studio não se torna dono de Profile,
dados pessoais, formulários, autenticação, inbox, e-mails ou regras de acesso.
Reutilizar `StudioExperienceManager`, preparar ponte de metadados para Marley
sem alterar `MarleyTabManager`, e impedir shells aninhados.

Contrato canônico: [Hub e Meu Espaço](../../../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).
Execução faseada: [ROADMAP.md](docs/ROADMAP.md), marcos STUDIO-P0–P7.

## Experiências

- **Hub geral**: `/studio`, com descoberta e links autorizados; `/studio/inicio` é alias temporário seguro.
- **ACULTA Studio Marley (`marley`)**: espaço editorial de criação, edição e moderação. Usa contratos exclusivos `MarleyTab`, `MarleyTabInterface`, `MarleyTabBase`, `MarleyTabManager`, `MarleyPresenter`, `MarleyListingBuilder` e `MarleyController`. Os IDs funcionais das áreas Revista, Wiki, Cursos, Fórum e Galerias não mudam.
- **ACULTA Studio Ganjalab (`ganjalab`)**: entrada estudantil composta pelo `GanjalabExperienceProvider` e `GanjalabController`. `StudioExperienceManager` permanece registro compartilhado. `LearnerDashboardComposer`, `LearningDashboardManager` e `aculta_lms.student_dashboard` mantêm seus contratos neutros.
- **Meu Espaço (`pessoal`)**: entrypoint inicial no purpose `account`, com
  links para a Conta existente e autorização no registry; sem novos formulários
  nem substituição do shell de Conta. A rota é `/studio/pessoal`.
- **WE SATIVA**: experiência independente de contribuição Wiki, nas rotas `/studio/we-sativa` e `/studio/we-sativa/inicio`.

A infraestrutura apresenta e encaminha. Os módulos proprietários continuam donos de entidades, formulários, workflow, permissões, matrículas, avaliações, progresso e cache de negócio.

## Rotas

Todas as experiências usam `_aculta_domain_purpose: account`, `access aculta portal` e o domínio resolvido pelo Portal.

- Meu Espaço: `/studio/pessoal` (P1-A; Runtime ainda não homologado).
- Marley: `/studio/marley` (entrada canônica, minúscula), `/studio/marley/{aba}`, `/studio/marley/{aba}/{secao}`, `/studio/marley/{aba}/{secao}/{sub}`.
- Ganjalab: `/studio/ganjalab` (estudante autenticado; declarada pelo LMS).
- WE SATIVA: `/studio/we-sativa` e `/studio/we-sativa/inicio`.

A entrada antiga `/studio/ganjalab` não pode continuar editorial: agora pertence exclusivamente a Ganjalab. As entradas antigas não colidentes usam redirects temporários 302 apenas em GET/HEAD: `/ganjalab` e níveis profundos editoriais para Marley; `/ganjalab420`, `/minha-aprendizagem` e `/studio/ganjalab420` para Ganjalab. Métodos de escrita não são redirecionados. O registro completo está em [contrato de identidade](../../../../../../docs/portal/ACULTA-STUDIO-IDENTITY.md) e no [mapa de rotas](../../../../../../docs/architecture/ROUTES-SITEMAP.md).

## Navegação e access

A navegação de Marley oferece links reais sem JavaScript e aprimoramento AJAX com histórico do navegador, foco e anúncio `aria-live`. IDs de plugin das áreas permanecem estáveis. Cada aba exige disponibilidade do módulo dono e sua permissão real; ações continuam sujeitas ao acesso da rota proprietária. O dashboard de Ganjalab preserva autenticação, autorização e dados do LMS.

Atributos `data-portal-ui` e `data-aculta-studio` são contratos de comportamento/apresentação; o tema ACULTA420 fornece o visual por SDC/UI Suite. O módulo também precisa funcionar sem tema específico.

## Histórico e validação

Documentos e changelogs anteriores podem usar os nomes antigos para registrar decisões passadas. Eles não definem a identidade vigente; consulte o contrato acima. Runtime e navegador não foram alterados por esta implementação local.

Gates do escopo: PHP lint, PHPCS disponível, Unit/Kernel/Functional aplicáveis, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php` e `git diff --check`. Testes visuais seguem `tests/browser/aculta-studio/visual.mjs` com Firefox ESR/X11 autorizado.
