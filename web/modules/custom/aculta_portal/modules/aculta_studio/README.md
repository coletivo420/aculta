# ACULTA Studio (`aculta_studio`)

## ADR-038 — estado atual

`aculta_studio` permanece como framework compartilhado, registry e camada de composição. Não existe hub público `/studio`. Por decisão explícita do responsável, as áreas integradas **ACULTA STUDIO WE SATIVA** (`wiki:/studio/we-sativa`) e **ACULTA STUDIO GREEN STASH** (`shop:/studio/green-stash`) usam esses paths canônicos; `/studio` e os demais paths `/studio/*` continuam 404, sem alias nem redirect.

| Suite | Purpose | Caminho |
| --- | --- | --- |
| Marley | `account` | `/marley` |
| Ganjalab | `courses` | `/ganjalab` |
| ACULTA STUDIO WE SATIVA | `wiki` | `/studio/we-sativa` |
| ACULTA STUDIO GREEN STASH | `shop` | `/studio/green-stash` |
| Culturas | `eventos` | `/culturas` |

A raiz de `account` apresenta a entrada da Conta; Conta (`/conta/*`) e Perfil (`/perfil/*`) permanecem separados. Salvos usam `/salvos`, Mercado Pago usa `/conta/mercado-pago/planos` e `/conta-legada` permanece. URLs absolutas são resolvidas pelo `DomainPurposeManager`; o Domain Wiki canônico é `wiki420`, sem migração de host planejada.

Implementado e publicado na PR #299 até `999a889ab`. Gates focados Studio/Portal/Commerce: 89 testes / 653 assertions; Kernels EventDomainBoundary e EventLocationModalAndAdmin: 15 testes / 373 assertions; cobertura focal de rotas, Conta/Perfil e políticas Green Stash: 30 testes / 391 assertions; aliases: PASS. Matriz HTTP: 275/275 respostas GET/POST/AJAX esperadas; Firefox ESR autenticado verificou Culturas em 200. Permanecem pendentes homologação integral de grants e fluxos de escrita, sobretudo Shop, e auditoria ampliada de acessibilidade. Os registros anteriores ao ajuste dos paths são históricos; as duas rotas `/studio/` atuais estão declaradas acima.

## Histórico de S-R5 (2026-10-25)

# ACULTA Studio (`aculta_studio`)


## Histórico — S-R4 dev.22 (antes do ADR-038)

O editor histórico `/studio/perfil` usa os quatro SDCs do tema; na ausência
de apelido fornecido pelo User, apresenta agora o rótulo neutro
`Apelido não informado` em vez do nome/usuário cadastral. Testes
Unit e Kernel preparados, mas não executados; ver
[handoff para validação e S-R5](docs/HANDOFF-S-R4-S-R5-2026-10-25.md).

## Histórico — S-R4, Studio dev.21

A tela histórica `/studio/perfil` consome os quatro componentes experimentais
`aculta420:profile-editor`, `profile-identity`, `profile-bio` e
`profile-social` via render arrays `#type: component` e slots Core.
O ACULTA User mantém a identidade, os valores de bio e redes, privacidade,
links canônicos e Form API. O `aculta_image` produz a foto do próprio
titular apenas no contexto privado. Os botões de edição originais não
são duplicados: o Studio usa suas rotas Form API, mantendo o modal
de foto original. Nenhum dado de Minha Conta migrou para Meu Perfil.

A Conta legada continua em `/conta-legada`; `/` no ACCOUNT serve
o hub. Os testes Unit foram atualizados, **não executados**.
PHPUnit/Kernel/Functional, SDC discovery, UI Patterns, cache, no-JS,
Firefox ESR/X11 e Runtime permanecem pendentes antes de S-R5.

## Histórico — S-R3-B3, Studio dev.20 (código, sem homologação)

Minha Conta Studio inclui agora as páginas privadas `/studio/conta/pedidos`, `/studio/conta/apoio` e `/studio/conta/preferencias`, além de cadastro, endereço, documentos opcionais e segurança. Os três destinos são exclusivos do purpose ACCOUNT e reusam os owners do Portal:

- **Pedidos:** `PortalController::orders()`, Commerce e política existente de pedidos próprios; detalhe do pedido e Mercado Pago permanecem com seus fluxos.
- **Meu apoio:** `SupportController::mySupport()`, com dados de apoio vinculados à própria conta e ações de assinatura nas rotas originais do Portal.
- **Preferências:** `AppearanceForm` original via Core Form API, com gravação de `ColorModePreference` do Portal, seus próprios links de configurações e o contexto de POST da rota Studio.

O Studio somente envolve os render arrays e aplica navegação contextual; não duplica entity, workflow, form, tabela ou shell, nem altera o Perfil público. As rotas originais `/meu-apoio` e `/configuracoes` e o legado permanente `/conta-legada` continuam disponíveis; o hub ACCOUNT fica em `/` (`/studio` técnico bloqueado diretamente).

**Unit novos escritos mas não executados**; PHPUnit/Kernel/Functional, PHP lint, PHPCS, gates, Domain, cache, HTTP autenticado/anônimo, checkout, duas contas, Firefox ESR/X11, 320 px, Olivero e no-JS permanecem pendentes. Sem RC, merge ou deploy.

## Histórico — auditoria S-R3-B1, Studio dev.19

A rota privada `/studio/conta/documentos` mantém o mesmo `AccountOtherDataForm` e serviço opcional `aculta_id_cpf` do Portal. Sem serviço, acesso direto agora retorna 404 após autorização. Quando o serviço existe mas `isAvailable()` é falso, a página mantém o aviso seguro do owner sem apresentar input gravável e a navegação omite Documentos. Checkout permitido continua prioritário ao retorno para a origem.

Foram escritos testes Unit complementares, **não executados**; Kernel/Functional, Firefox, CSRF/no-JS e Runtime continuam pendentes. A consulta de fingerprint HMAC do owner não assegura unicidade transacional entre gravações concorrentes. A experiência B2 e a Conta legada em `/conta-legada` permanecem intactas.

## Histórico — S-R3-B2, Studio dev.18

O Hub do ACULTA Studio responde em `/` no domínio ACCOUNT; `/studio` é somente caminho interno (404 direto). **Minha Conta original é permanente em `/conta-legada`.** Esta etapa separa o uso do mesmo Profile, sem armazenamento duplicado:

- **`/studio/conta/dados`**: formulário cadastral privado do Portal com nome e WhatsApp, excluindo `field_nickname` apenas no Studio; `/dados` do legado preserva todos os campos atuais.
- **`/studio/perfil/apelido`**: formulário isolado do ACULTA User, único responsável pela validação de nickname, gravando `Profile.field_nickname`. Sem CPF/CNPJ, endereço, senha ou e-mail de conta.
- Editores existentes de endereço, documentos opcionais, segurança, OAuth, bio, privacidade e redes foram mantidos, assim como checkout e shell legados.

Os testes Unit novos constam no Git, **mas não foram executados**. Gates PHP, Kernel/Functional, HTTP, cache, CSRF/no-JS, Firefox ESR e Runtime seguem obrigatórios. Documentação abaixo de dev.17 é histórica.

## Situação anterior — S-R3-B1, Studio dev.17

A raiz **`/` do Domain ACCOUNT serve o hub ACULTA Studio**,
`/studio` é caminho técnico interno não público, e
**`/conta-legada` é a Minha Conta original permanente**.

Nova página privada **`/studio/conta/documentos`**. Ela reutiliza
`PortalController::otherData()` e `AccountOtherDataForm`, que já
atendem `/dados/outros-dados` na interface legada. O serviço opcional
`aculta_id_cpf.account_document` continua responsável por validar,
cifrar, mascarar e armazenar uma única vez CPF/CNPJ. Link de Documentos
é apresentado apenas quando o owner está disponível.
Salvamento sem destino de checkout retorna à interface de origem.

Minha Conta Studio e Meu Perfil Studio continuam separados.
Cadastro privado ainda depende do formulário original `/dados`, pois
o campo de apelido precisa ser extraído cuidadosamente. **A Conta
legada não será removida.** Testes novos dev.17 ainda aguardam execução
PHPUnit, Kernel, Functional e homologação Firefox/Runtime.
As notas de versões anteriores abaixo são históricas.


## S-R3-A — Minha Conta Studio sem substituir o legado (dev.14)

A experiência `/studio/conta` tem seções privadas reais em
`/studio/conta/endereco`, `/studio/conta/seguranca`,
`/studio/conta/seguranca/senha` e `/studio/conta/conexoes`.
Essas telas chamam os **métodos atuais do `PortalController`**, preservando
validação, Form API, OAuth e o endereço Commerce. Somente a navegação
da interface Studio é própria; o shell da Conta original não é aninhado.

O cadastro privado `/dados` permanece na Conta original até que
`field_nickname` seja extraído do formulário misto. Apoio, pedidos
e preferências ainda usam suas rotas originais neste incremento.
**A Conta legada em `/conta-legada` é permanente, não uma rota a retirar. A raiz `/` é o hub do Studio.**
`/minha-conta` direto responde 404 e a Minha Conta original está em `/conta-legada`. Testes Unit escritos e
gates de endereço ampliados; validação Runtime/Firefox pendente.

## Contrato vigente — Conta e Perfil separados (dev.13)

A arquitetura foi corrigida: **Minha Conta Studio** fica em
`/studio/conta` e **Meu Perfil Studio** fica em `/studio/perfil`,
como experiências independentes no hub `/studio`. `/studio/pessoal/*`
não existe mais e **não tem alias de compatibilidade**.

Minha Conta **legada é permanente** em `/conta-legada` no Domain ACCOUNT,
com shell, menus, dados e formulários intactos, para comparação
antes e depois do aceite. A raiz `/` do Domain ACCOUNT é o hub do Studio.
O caminho `/minha-conta` responde 404; não o habilitar. Conta Studio reutiliza links/forms
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
- Planos Mercado Pago: apresentação administrativa no Studio em `/studio/conta/mercado-pago/planos`; o módulo de pagamentos é dono do formulário, do endpoint POST `/integracoes/mercadopago/planos` e das operações no provedor.

A entrada antiga `/studio/ganjalab` não pode continuar editorial: agora pertence exclusivamente a Ganjalab. As entradas antigas não colidentes usam redirects temporários 302 apenas em GET/HEAD: `/ganjalab` e níveis profundos editoriais para Marley; `/ganjalab420`, `/minha-aprendizagem` e `/studio/ganjalab420` para Ganjalab. Métodos de escrita não são redirecionados. O registro completo está em [contrato de identidade](../../../../../../docs/portal/ACULTA-STUDIO-IDENTITY.md) e no [mapa de rotas](../../../../../../docs/architecture/ROUTES-SITEMAP.md).

## Navegação e access

A navegação de Marley oferece links reais sem JavaScript e aprimoramento AJAX com histórico do navegador, foco e anúncio `aria-live`. IDs de plugin das áreas permanecem estáveis. Cada aba exige disponibilidade do módulo dono e sua permissão real; ações continuam sujeitas ao acesso da rota proprietária. O dashboard de Ganjalab preserva autenticação, autorização e dados do LMS.

Atributos `data-portal-ui` e `data-aculta-studio` são contratos de comportamento/apresentação; o tema ACULTA420 fornece o visual por SDC/UI Suite. O módulo também precisa funcionar sem tema específico.

## Histórico e validação

Documentos e changelogs anteriores podem usar os nomes antigos para registrar decisões passadas. Eles não definem a identidade vigente; consulte o contrato acima. Runtime e navegador não foram alterados por esta implementação local.

Gates do escopo: PHP lint, PHPCS disponível, Unit/Kernel/Functional aplicáveis, `php scripts/validate-module-theme-boundary.php`, `php scripts/validate-aculta-portal-drupal11.php`, `php scripts/validate-project-status-index.php` e `git diff --check`. Testes visuais seguem `tests/browser/aculta-studio/visual.mjs` com Firefox ESR/X11 autorizado.

## Eventos e Locais - integração e ACULTA Studio Culturas (2026-10-30)

[Plano LOC](../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md), especialmente LOC-06, e [análise comparativa com OpenCulturas](../../../../../../docs/architecture/ANALISE-COMPARATIVA-OPENCULTURAS-ACULTA-STUDIO-CULTURAS.md).
Eventos não são uma aba de Marley. O HEAD auditado contém dashboard e fluxos iniciais em `/culturas`, enquanto `aculta_events` mantém Forms/CRUD e `aculta_index` fornece projeções/relações. Implementação parcial; testes no HEAD atual, validação Runtime e homologação visual pendentes. S-R4/S-R5, hub ACCOUNT e `/conta-legada` seguem seus roadmaps próprios.

## ACULTA Studio Culturas — implementação parcial, homologação pendente

## ACULTA Studio Culturas — decisão de produto e domínio (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota canônica `/culturas` está declarada em `aculta_events` no purpose `eventos` e a página autenticada foi verificada em HTTP 200 no Runtime de desenvolvimento. `/studio/culturas` e demais caminhos públicos `/studio/*` foram removidos sem alias/redirect. A entrada geral da Conta pode apresentar somente um link absoluto para essa experiência; grants e fluxos integrais de escrita ainda exigem homologação.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: decisão aprovada e implementação parcial presente na PR #299. Faltam completar experiência do dashboard e gates de testes, autorização/domínio em ambiente integrado e homologação visual/acessível.

A composição deve seguir o registry/provider atual de Studio e manter ownership, formulários e autorização em `aculta_events`. Criar experiência de Cultura própria, não adicioná-la como seção de Marley. Atualizar este README e o sitemap após implementação/homologação.

## ADR-038 — caminhos canônicos das Suites (implementação parcial)

`aculta_studio` permanece framework compartilhado, registry e camada de
composição. `/studio` e todas as rotas públicas `/studio/*` foram removidos,
sem alias ou redirect.

| Suite | Purpose | Caminho |
| --- | --- | --- |
| Marley | `account` | `/marley` |
| Ganjalab | `courses` | `/ganjalab` |
| ACULTA STUDIO WE SATIVA | `wiki` | `/studio/we-sativa` |
| ACULTA STUDIO GREEN STASH | `shop` | `/studio/green-stash` |
| Culturas | `eventos` | `/culturas` |

Conta (`/conta/*`) e Perfil (`/perfil/*`) permanecem separados; salvos usam
`/salvos`, Mercado Pago `/conta/mercado-pago/planos` e o legado
`/conta-legada` permanece. URLs entre purposes são produzidas pelo
`DomainPurposeManager`. A raiz `account` apresenta a entrada da Conta sem
rewrite para `/studio`.

O Domain Wiki canônico é `wiki420`. O responsável corrigiu a referência a
`wiki.aculta.*` no pedido inicial; não há mudança de host planejada. HTTPS e
captura autenticada foram verificados no ambiente de desenvolvimento; a
resolução de produção `wiki420.aculta.org` não foi confirmada neste ambiente.
A matriz de correspondência completa está no
[ADR-038](../../../../../../docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md).

Validação 2026-10-30: Firefox ESR autenticado capturou 20 páginas (homepage e
nove páginas ACULTA em 1440/390), sem documentos vazios ou overflow. Green
Stash deu acesso negado à conta de teste sem permissão, portanto não é
homologação de escrita. A jornada de Culturas mostrou HTTP 500 na primeira
captura e foi corrigida em `95314a5a7`; a captura repetida carregou HTTP 200.
Uma matriz HTTP focal verificou 275 respostas GET/POST/AJAX nos cinco hosts
configurados pelo `DomainPurposeManager`, com 404 no purpose errado e nas
rotas antigas. Testes Kernel seguem pendentes porque `SIMPLETEST_DB` não está
configurado.
