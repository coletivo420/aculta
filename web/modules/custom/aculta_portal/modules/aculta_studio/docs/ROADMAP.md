## Status visual consolidado — sequência OCS-F0–F11 e THEME-MF0–MF3 (2026-10-30)

Esta é a sequência aprovada mais recente e substitui numerações incompatíveis
em registros históricos abaixo: F0 inventário; F1 tokens; F2 navegação;
F3 SDCs; F4 Hub; F5 Marley; F6 GanjaLab; F7 WE Sativa; F8 Green Stash
integrado; F9 Conta/Perfil; F10 demais áreas; F11 gate de fidelidade, que
depende de THEME-MF3. OCS-A1–A4 só começam depois dos dois gates aprovados.

| Fase | Estado verificável neste HEAD |
|---|---|
| THEME-MF0 | Parcial: inventários lexicais/libraries recenseados; reteste no HEAD `4eb116482` verificou shell em 10 raízes anônimas × 9 larguras (90/90; ambos os headers uma vez, sem overflow); consumers por tela/purpose e cascata continuam incompletos. |
| THEME-MF1–MF2 | Implementação incremental; cobertura visual global incompleta. |
| THEME-MF3 | Pendente. Baseline servido teve 358/360 checks por overflow de 8px em `/entrar` a 320px; com `auth.css` candidata e marcador neutro simulado, a repetição passou 540/540 checks em dez purposes, nove larguras, claro/escuro e reduced motion, agora também medindo contraste dos links textuais do shell. O Runtime ainda não serve o hook Portal nem a CSS candidata; validação integrada e gates de acessibilidade/refluxo global continuam pendentes. Não homologar todo ACULTA420 como mobile-first. |
| OCS-F0 | Parcial: providers/rotas mapeados; contrato de shell no Portal e captura pós-migration das raízes em 10 purposes verificados em `4eb116482`; baseline Studio ainda não inclui tela autenticada equivalente do upstream nem todas as áreas/estados. |
| OCS-F1–F2 | Implementados parcialmente; CSS candidata verificada em rotas reais, implantação e cobertura total pendentes. |
| OCS-F3 | Variante `studio_fidelity` adicionada ao SDC `patterns/card` com story e consumidor real na entrada da Conta; schema 55/756, Unit 20/121 e CSS fixture Firefox 45/45 passaram. A repetição no worktree baseado em `82ff8538d` passou 216/216, cobrindo limites upstream 48/70/85/120em, container e tipografia Montserrat; capturas 320/1440px inspecionadas. HTML do preprocess e render Drupal/Runtime seguem pendentes. |
| OCS-F4 | Composição adapta links autorizados do registry e da entrada da Conta ao SDC existente; estado funcional vem dos providers. Captura Runtime com o novo adaptador pendente. |
| OCS-F5–F7 | Parcial/incompleto; Marley foi validado com publicações sintéticas, tabela responsiva e GET dos Form APIs sem submit. WE SATIVA no path legado `/wesativa` passou 279/279 invariantes autenticados em nove larguras, claro/escuro, incluindo Institution Bar textual com contraste 11,34:1/12,95:1. O Runtime ainda não serve `/studio/we-sativa`; Wiki preenchida, grants A/B, autoria/revisão/moderação, persistência/submissão, AJAX/no-JS, LMS e os demais estados continuam pendentes. |
| OCS-F8 | Fundação visual Studio e apresentação candidata das listagens integradas; fixture Firefox ESR 74/74 em nove larguras e claro/escuro. Operador autorizado, grants por loja e telas reais continuam sem homologação. |
| OCS-F9–F10 | Shell da Conta passou reteste Firefox 33/33 em dez larguras; fixtures de identidade e perfil público passaram 56/56 e 22/22. Contas/perfis reais, render do editor privado, ACL A/B e S-R4/S-R5 continuam pendentes; `/conta-legada` segue preservada. |
| OCS-F11 | Não aprovado: depende de THEME-MF3 e das evidências de todas as áreas/estados. |
| OCS-A1–A4 | Não iniciadas; gate anterior permanece fechado. |

### OCS-F10 — ACULTA Studio Culturas: composição mobile-first candidata

O tema agora aplica a composição visual ao wrapper real do dashboard
`aculta_events`: intro, guia, ações autorizadas, filtros, tabela e paginação.
O controller, suas render arrays, links, filtros GET, permissões e cache não
foram alterados. CSS mantém escopo em `body.path-culturas`; como os links reais
de ação chegam sem classes, a composição dá o mesmo acabamento secundário a
todos eles sem inferir permissões pelo índice. Fixture estrutural passou
110/110 checks em nove viewports e claro/escuro. Na rota autenticada real,
Firefox ESR passou 20/20 verificações: 18 combinações de nove viewports e
claro/escuro mais foco por teclado em ambos os modos. CSS candidata injetada,
shell único e sem overflow no documento; capturas
320/1440px foram inspecionadas. Reuso de SDCs: nenhum novo SDC; a composição usa
o dashboard/render arrays atuais e tokens já existentes. ACL A/B, grants
distintos, cache, AJAX/no-JS, zoom, WCAG e paridade pareada OpenCulturas não
foram validados; não aprova F10/F11/MF3. Ver evidências e execução em
[`tests/browser/aculta-studio/README.md`](../../../../../../../tests/browser/aculta-studio/README.md)
e [matriz de paridade](../../../../../../themes/custom/aculta420/docs/studio-visual/visual-parity-matrix.md).

### THEME-MF2 — desafio de verificação responsivo (2026-10-30)

No commit local `7dbca2b8a`, o Portal fornece o token de apresentação neutro
`challenge-frame` e o tema adapta o formulário estreito com container query.
Unit (2/2), PHP lint/PHPCS, Stylelint, Design Foundations e SDC schemas passaram.
Firefox validou CSS candidata e marcador simulado nas três rotas de autenticação,
320–1440 px; a integração ainda não foi servida no Runtime. A publicação está
pendente porque a referência de branch no GitHub não foi encontrada pelo fetch,
e o PR #299 permanece em `2493d7535`. O gate global permanece vermelho por
classes/tokens visuais de Eventos e Índice; não aprova THEME-MF3 nem OCS-F11.

## ADR-038 retificado - caminhos oficiais das áreas Studio (execução parcial, 2026-10-30)

Marley `account:/marley`; Ganjalab `courses:/ganjalab`; **ACULTA STUDIO WE SATIVA** `wiki:/studio/we-sativa`; **ACULTA STUDIO GREEN STASH** `shop:/studio/green-stash`; Culturas `eventos:/culturas`. O responsável retificou ADR-038 para aprovar esses dois paths sob seus purposes próprios; `/studio` e os demais prefixos antigos continuam 404. `aculta_studio` permanece framework compartilhado. A raiz de `account` é entrada da Conta; Conta e Perfil permanecem separados. O Domain Wiki canônico é `wiki420`. As seções abaixo são histórico quando descrevem paths substituídos.

## Inventário histórico pré-ADR-038

## ACULTA Studio Culturas - status de integração (2026-10-30)

ACULTA Studio Culturas é uma Suite independente do Marley no purpose `eventos`, com caminho `/culturas` conforme ADR-035/038. Eventos e Locais permanecem no owner `aculta_events`; a conta `account` pode oferecer link absoluto, sem persistência ou POST cross-domain.

No HEAD auditado, `aculta_events` já declara dashboard `/culturas`, criação/edição de Evento, gestão contextual de Datas e Locais, modal AJAX de Local e configuração administrativa de papéis. Isso representa implementação parcial, não homologação completa da Suite.

- [x] Rotas e composição inicial do organizador presentes no owner `aculta_events`.
- [x] Fluxo inicial Evento + Datas; operações de Data mantêm Evento obrigatório e vínculo validado.
- [x] Popup de seleção/cadastro de Local com CEP e validação de acesso.
- [x] Configuração administrativa de papéis para criação de Evento e Local; `content_editor` como padrão.
- [ ] Confirmar purpose, access handlers, POST/AJAX e exposição somente leitura em outros domínios no Runtime.
- [ ] Evoluir dashboard com filtros e paginação; revisar vínculo de organizadores e criação parcial Evento/Datas.
- [ ] Homologar acessibilidade, mobile-first, visual, teclado, estado de formulário e cache.

Referências: [plano LOC](../../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md), [análise comparativa OpenCulturas](../../../../../../../docs/architecture/ANALISE-COMPARATIVA-OPENCULTURAS-ACULTA-STUDIO-CULTURAS.md) e [ADR-035](../../../../../../../docs/decisions/ADR-035-aculta-studio-culturas.md). Não integrar Culturas como aba do Marley.

Não reabrir shell, mover conteúdo ou declarar OCS-F11/THEME-MF3 aprovados; manter demais frentes e versões.

## Dependências arquiteturais OpenCulturas - ARQ-01 a ARQ-09 (2026-10-29)

[Plano arquitetural transversal](../../../../../../../docs/architecture/PLANO-ARQUITETURAL-OPENCULTURAS-ACULTA-ARQ-01-09.md): Studio é **consumidor visual e de orquestração** da descoberta, salvos (ARQ-07), vínculos de organização (ARQ-05) e moderação autorizada (ARQ-06); owners são `aculta_index`, User/organizações, UGC e módulos de conteúdo. **Não duplicar** dados/ACL/workflow, não incorporar falsas operações, manter Conta e Perfil separados e Green Stash como área integrada. Fases OCS-F/A e THEME-MF seguem em paralelo, sem confundir gates com ARQ-09.

## DECISÃO SUPERSESSIVA - Green Stash integrado e fundação mobile-first global (2026-10-29)

A expressão antiga "Green Stash como experiência independente" está **revogada**. Green Stash é **a área de gestão comercial integrada ao Aculta Studio**; `green_stash` no registry é identificador técnico de entrada, não produto, shell, sistema ou identidade autônoma. Os serviços Commerce seguem como owners operacionais. **F8 continua dedicada a Green Stash, mas como área integrada**, sem menu global/checkout/conta paralelos.

O requisito **mobile-first vale para TODO o tema ACULTA420 e todos os purposes** (THEME-MF0 a MF3), não só Studio, Marley ou Green Stash. O plano normativo vigente é [plano visual retificado](../../../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md). Sequência: F0+MF0 → MF1/MF2 e F1-F10 conforme dependências → **MF3 e F11 obrigatórios** → somente então A1-A4. Sem declarar F11 se a regressão mobile-first global não estiver aprovada. Preservar S-R4/S-R5 e `/conta-legada`.

## Retificação consolidada vigente: Green Stash integrado (2026-10-29)

A seção imediatamente abaixo registra uma decisão histórica posteriormente
revogada. Green Stash **não é experiência independente**: é a área de gestão
comercial integrada ao Studio, preservando Commerce como owner funcional.

## Retificação histórica substituída: Green Stash incluído (2026-10-29)

**Não normativa; contradiz a decisão consolidada acima e não deve orientar
implementação.** Preservada apenas como histórico de decisão anterior.

**Registro normativo histórico da decisão anterior, substituído acima.** O plano anterior F0-F10 foi substituído por **OCS-F0-F11**. A afirmação de que Green Stash seria experiência independente está revogada; Green Stash é a área comercial integrada do Studio.

- [ ] OCS-F0: inventário dinâmico completo, **incluindo Green Stash**, Marley, GanjaLab, WE Sativa, Conta, Perfil e demais providers.
- [ ] OCS-F1-F4: fidelidade Next, shell contextual, SDCs reutilizados e hub orientado ao registry/ACL.
- [ ] OCS-F5: Marley.
- [ ] OCS-F6: GanjaLab.
- [ ] OCS-F7: WE Sativa.
- [ ] **OCS-F8: Green Stash** - navegação de gestão de loja mobile-first, cards, formulários e operações comerciais *efetivamente implementadas*, seguindo `GREEN-STASH-UX-MOBILE-FIRST.md` e `GREEN-STASH-OPERATIONS-CONTRACT.md`. Sem expor admin Commerce, checkout paralelo, produtos/pedidos de outra loja ou ações ainda planejadas.
- [ ] OCS-F9: Conta e Perfil separados, S-R4/S-R5, `/conta-legada`.
- [ ] OCS-F10: demais experiências verificadas no registry.
- [ ] **OCS-F11: gate final de fidelidade**, com testes de loja/usuário e demais experiências; somente após F11 entrar OCS-A1-A4.
- [ ] OCS-A1-A4: identidade própria Aculta também para **Green Stash**.

A API e ACL comerciais permanecem no `aculta_commerce`; alterações de services/controllers/forms/rotas da frente funcional são `Pede-A`. Todos os estados devem diferenciar implementado, condicional, planejado e DEFERRED. Nenhuma fase foi executada nesta correção documental.


## Retificação de escopo OCS - plataforma completa (2026-10-29)

**Substitui o bloco inicial "Frente visual aprovada" onde limita F5 ao Marley e encerra fidelidade em F7.** A fonte normativa agora é [plano visual atualizado](../../../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md), seção "Retificação vinculante de escopo transversal".

- [ ] **OCS-F0:** auditar todo `StudioTab`, providers, rotas e consumidores. Cobertura inicial: hub, Marley, GanjaLab, WE Sativa, Conta, Perfil e **demais experiências efetivamente registradas**; distinguir existentes, opcionais e planejadas.
- [ ] **OCS-F1-F3:** tokens Next escopados, navegação interna **derivada do registry/ACL**, SDCs e histórias reutilizáveis, sem shell institucional paralelo.
- [ ] **OCS-F4:** hub e grade dinâmica de experiências autorizadas, links reais e estados de ausência.
- [ ] **OCS-F5:** Marley e experiências editoriais proprietárias.
- [ ] **OCS-F6:** GanjaLab, cursos, turmas, biblioteca e jornada educacional nos limites do LMS existente.
- [ ] **OCS-F7:** WE Sativa, autoria Wiki e fluxos realmente presentes; autoria não é moderação.
- [ ] **OCS-F8:** Conta privada e Perfil público em separados; S-R4/S-R5 e `/conta-legada` preservados.
- [ ] **OCS-F9:** experiências adicionais inventariadas em F0, incluindo módulos opcionais e estados desligados.
- [ ] **OCS-F10:** **gate final da fidelidade**, com matriz interexperiências, screenshots e testes; **somente então** iniciar OCS-A1-A4.
- [ ] **OCS-A1-A4:** tokens e identidade Aculta, componentes, aplicação **a todas as experiências** e consolidação sem alterar contratos funcionais.

### Registro de implementação visual F4 — 2026-10-30

O tema adapta os links já autorizados pelo Hub ao SDC `aculta420:card` e aplica
grade de uma coluna na base mobile, ampliada para duas colunas em `48rem`.
`StudioHubController` continua dono do registry, da disponibilidade e dos
links. Unit do adaptador foi acrescentado; Runtime, Firefox visível e
comparação upstream seguem pendentes. O `user-dashboard-views.scss` upstream é
referência somente para listas de perfis/salvos, pois não foi encontrada uma
home autenticada equivalente. Portanto F4 está parcialmente implementada e
não homologada; nenhuma fase ou gate é marcado como concluído.

Não substituir o conteúdo de uma experiência pelo de outra. O Studio compõe e apresenta; LMS/Wiki/editorial/User/Portal continuam os donos de dados, formulários, permissões, eventos e workflow. Itens ausentes não devem ser renderizados nem anunciados como existentes. Nenhuma fase está concluída por esta retificação.

## Frente visual aprovada - OCS-F → OCS-A (2026-10-29)

**Ordem obrigatória:** primeiro reproduzir fielmente a aparência OpenCulturas **Next** (`opcult`, não `openculturas_base`); só depois do gate de fidelidade passar para identidade própria Aculta Studio. Plano técnico: [PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md](../../../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md).

- [ ] **OCS-F0:** inventário, baseline e matriz visual por tela/estado, auditoria de licenças e consumers SDC.
- [ ] **OCS-F1-F3:** tokens de fidelidade escopados, shell/navegação internos e variantes de SDCs existentes; não mudar Institution Bar/Domain Header nem CSS global.
- [ ] **OCS-F4:** dashboard fiel, dados e ações reais; sem salvos/recomendações/organização simulados.
- [ ] **OCS-F5:** Marley e ferramentas editoriais com a mesma aparência; módulos mantêm dados e operações.
- [ ] **OCS-F6:** Conta privada e Perfil público com apresentação consistente, mas contratos distintos, sem retirar `/conta-legada`; respeitar gates S-R4/S-R5.
- [ ] **OCS-F7 (gate):** screenshots de paridade e aceite de acessibilidade, cache, acesso A/B, no-JS, mobile e SDC Runtime.
- [ ] **OCS-A1-A4:** aplicar identidade institucional Aculta, refinar componentes, revisar as telas e remover estilos provisórios sem trocar props/slots/rotas.

F0-F3 podem ser implementadas sem desbloquear a homologação de S-R4/S-R5; F4-F6 seguem restrições funcionais existentes. Cada fase: commit independente na PR #299; **documentação não significa implementação**.

### Registro OCS-F1 — tipografia e tokens escopados (2026-10-30)

Adicionados tokens de tipografia, cores, espaçamento e superfícies do `opcult`
3.1.5 nos wrappers `.studio` e `.aculta-profile-editor`, sem alterar SDCs ou
contratos funcionais. Montserrat está local e licenciada sob SIL OFL 1.1, com
atribuição e licença no tema. A suíte Firefox ESR visível sobre o consumer real
Marley, com a folha candidata do worktree injetada no Runtime, passou 63/63
checks executáveis, incluindo Montserrat carregada, gutter global do shell
inalterado, contraste medido (6,49:1 claro; 11,51:1 escuro), mesma geometria
nos modos e ausência de overflow em 320/360/390/599/600/767/768/1024/1280/1440.
Uma verificação de link de edição foi `SKIP`, pois a conta sintética não tem
publicação própria; nenhuma ação foi aprovada por esse skip. Capturas estão em
`/home/piradopirata/aculta-suite-out/ocs-f1-candidate-20261030-after-500-fix/`.

Design Foundations passou 2.418 checks e 159 fixtures; schema SDC, mapa/boundary
tema-módulo e Foundation/Shell read-only passaram. Isto testa CSS candidata
sobre markup servido, não implantação no Runtime, equivalência com dashboard
OpenCulturas autenticado, todos os consumers ou o gate F7. PHPCS Drupal e
DrupalPractice dos dois scripts do Design Foundations passou após formatação e
documentação das funções. OCS-F1 está implementada e parcialmente validada;
comparação de referência e gates restantes permanecem pendentes, sem declarar
OCS-F11.

## Melhorias inspiradas no OpenCulturas (2026-10-28)

Origem: `docs/architecture/OPENCULTURAS-COMPARATIVO.md`. Entram depois do fechamento de S-R5.

- **I-07 — Meus salvos e recomendações.** Aba "Salvos" com o que o usuário marcou (`salvar_topico`,
  `seguir_topico` e `salvar_conteudo` a criar). Aba "Recomendações" depois, no mesmo padrão. Lista em
  cartões `compact`. Não coleta dado novo. Gates: acesso só do dono; cache por UID; teclado e 320 px.
- **I-08 — Primeiro acesso em cartões.** Passos reais (escolher apelido, completar perfil, salvar um
  conteúdo) no hub. Sem JavaScript obrigatório, sem biblioteca de tour. Fechamento por "Não mostrar de novo",
  gravado na conta.
- **I-06 — Seção "Minha organização".** Por vínculo verificado com organização e escopo de `content_editor` (D-01); **não** criar papel `organizador`.
  Lista os locais, eventos e perfis que a organização reivindicou ou criou.
- Visual de referência: V-6 do comparativo.

## Complemento: salvar no cartão (2026-10-28)

- **I-31** botão de salvar em cartões (`patterns/bookmark-toggle`), com estado salvo ou não. Usa a flag
  existente; a lista "Salvos" da I-07 mostra o mesmo conjunto. Sem novo dado coletado.

## S-R5 — instrumentação e matriz de paridade (2026-10-25; sem homologação)

- **Frente iniciada no código de testes e documentação:** [matriz S-R5](S-R5-MATRIZ-COMPARACAO.md) e [suíte Firefox ESR](../../../../../../../tests/browser/aculta-studio/README.md) para comparar Conta Studio, Conta legada e Perfil em MAIN, sem criar novo storage, rotas ou operações.
- Escopos contemplados: cadastro vs apelido, endereço/CEP/número/S-N, documentos CPF/CNPJ opcionais, e-mail/senha/OAuth, pedidos próprios, apoio/assinaturas, preferências, foto/avatar, bio, redes e acesso entre contas. Ao comparar pedidos, não supor rota legada `/pedidos`: Portal expõe o método proprietário, mas essa URL não foi identificada em `aculta_portal.routing.yml`.
- **Critério de avanço preservado:** S-R4 só se encerra após gates Unit/Kernel/Functional, SDC Runtime, privacidade A/B, Firefox ESR visível e no-JS/AJAX. A suíte S-R5 preparada não os substitui. Executar S-R5 funcional e visual com evidências somente depois de S-R4 homologada. Continuar SDCs `experimental` e versões Dev.
- **Testes reais desta rodada:** somente verificação estática de ownership/roteamento e parse JavaScript da suíte; PHP lint, PHPCS, PHPUnit, Firefox ESR/X11, HTTP/Drush, acesso, formulários e WCAG **não executados**. PR #297 preservada, sem merge/deploy.

# ACULTA Studio — Roadmap multi-experiência


## Revisão pré-homologação S-R4 / passagem para S-R5 (Studio dev.22)

- O nome de conta privada não é mais fallback de `profile-identity`: sem apelido resolvido, usa `Apelido não informado`. O Unit usa uma conta de teste com nome semelhante a e-mail para detectar regressão do vazamento. O resto da composição segue sendo a S-R4 dev.21.
- O ACULTA420 dev.2 contém teste Kernel de descoberta/slots/cache/escape dos quatro SDCs, ainda não executado. Handoff operacional detalhado: [HANDOFF-S-R4-S-R5-2026-10-25.md](HANDOFF-S-R4-S-R5-2026-10-25.md).
- Não chamar S-R4 de homologada ou iniciar a aceitação S-R5 sem executar os gates funcionais, de acesso/cross-account, Core SDC, responsividade e browser. Não criar duplicações de lógica de perfil/conta nem remover `/conta-legada`.

## Histórico — S-R4 — integração da tela Meu Perfil em SDCs (Studio dev.21)

- Código implementado na PR #297: `StudioProfileController` usa os quatro SDCs, com props e slots de render arrays providos por ACULTA User e `aculta_image`. `PublicProfileLink` continua árbitro do link público, e somente a pessoa dona da conta pode carregar o editor.
- Edição segue sem duplicação para `aculta_studio.profile_nickname`, `profile_privacy`, `profile_bio`, `profile_social` e o modal original `aculta_user.foto`. Bio textarea desativada, linhas sociais e tags de cache do User são preservadas; botões modais originais removidos somente na composição Studio.
- Testes Unit escritos, não executados: usuário anônimo, permissão negada, entidade inexistente, fallback sem owner, link público condicionado, componentes aninhados, render arrays, links de ações e cache.
- Gates pendentes: PHP lint/PHPCS, PHPUnit Unit/Kernel/Functional, tema e schema SDC/Core discovery, cache por UID e privacidade com duas contas, foto própria privada, fallback no-JS, modal AJAX, teclados, 320/390/768/1024/1440px e tema Olivero, Firefox ESR/X11, Domain ACCOUNT e Runtime. A fase S-R5 de comparação visual com `/conta-legada` somente depois das validações; não remover o legado.

## Histórico — Pré-S-R4 — SDCs do tema ACULTA420 (0.7.0-dev.1)

O tema contém agora os SDCs experimentais `profile-editor`,
`profile-identity`, `profile-bio` e `profile-social`, com
8 stories estáticas e teste de contrato escrito. Não há integração
funcional na experiência Meu Perfil ainda: ACULTA User, Form API,
privacidade e dados permanecem intactos. A S-R4 integra os slots
com render arrays autorizados e testa PHP, Kernel, cache,
Firefox ESR/X11, mobile e modos claro/escuro. Não declarar homologado.

## S-R3-B3 — pedidos, apoio e preferências privados (Studio dev.20)

- **Código entregue na PR #297:** três rotas canônicas `/studio/conta/{pedidos,apoio,preferencias}` com autenticação, permissão e purpose ACCOUNT. A navegação própria do Studio agora aponta a esses destinos; a Conta antiga em `/conta-legada` e as rotas originárias continuam disponíveis.
- **Ownership:** `PortalController::orders()` mantém o filtro e o link de pedido Commerce, `SupportController::mySupport()` mantém histórico e Form API de assinatura recorrente sem duplicação, e `AppearanceForm` mantém escrita e invalidação de cache via Core Form API. Os links secundários de preferências ainda seguem `SettingsTabs` no owner; são intencionalmente preservados para outras preferências não integradas.
- **Testes Unit escritos, não executados:** rota única, purpose/account, permissão e no-cache, navegação com/sem documentos, delegação dos métodos de pedidos e suporte, ações de assinatura, acesso negado, Form API de aparência com seus tabs. Não declarar gates PASS sem execução.
- **Gates antes de S-R5/RC:** instalar/check-out correto; PHP lint e PHPCS, `phpunit` Unit/Kernel/Functional, `validate-module-theme-boundary.php`, `validate-aculta-portal-drupal11.php`, `validate-project-status-index.php`, `validate-no-temporary-route-aliases.php`, teste de duas contas com Commerce e assinaturas, pagamento, ação de assinatura em POST/CSRF, preferência persistida e invalidada, login anônimo, Domain ACCOUNT, cache de usuário, AJAX/no-JS e Firefox ESR/X11 em 320 px e Olivero.
- **Limitação externa ainda aberta:** garantir unicidade transacional de CPF/CNPJ em B1; completar homologação B1/B2 sem mexer no legado. Próximo: fase S-R4 (SDC e edição de Perfil) e S-R5 (aceite Conta/Perfil com comparação visual), condicionadas a gates proporcionais.

## S-R3-B1 — revisão (Studio dev.19, Portal dev.31)

- Código: acesso direto sem `aculta_id_cpf.account_document` retorna 404; com owner indisponível, formulário Portal mostra indisponibilidade, sem link na Conta Studio.
- Unit novos escritos, não executados: owner ausente/temporariamente indisponível, autorização, máscara e somente leitura, validação genérica, URL segura de checkout e retorno por origem. Não criar outro armazenamento.
- Limite identificado: fingerprint HMAC evita colisões sequenciais, **não garante unicidade em gravações simultâneas**. Estratégia transacional e Kernel MariaDB pertencem ao owner.
- Gates pendentes: PHP lint, PHPUnit, Kernel/Functional, routes/Domain/aliases, CSRF, HTML sem vazamento, privacidade, Firefox ESR/X11, no-JS, checkout e comparação com `/dados/outros-dados` e `/conta-legada`. Não homologar como RC.
- Após validar B1/B2, avançar a S-R3-B3 (pedidos/apoio/preferências), conservando o legado.

## S-R3-B2 — separação de cadastro e apelido (Studio dev.18 / Portal dev.30 / User dev.74)

- **Código entregue na PR #297:** `/studio/conta/dados` compõe Profile Form API somente com nome e WhatsApp privado. `/studio/perfil/apelido` entrega editor isolado do campo original `field_nickname` com as regras da ACULTA User. Não cria schema, alias nem editor de dados privados no Perfil.
- `/conta-legada` permanece Minha Conta original; `/dados` legado preserva o formulário misto para comparação e `/` continua hub do Studio.
- **Sem execução de testes novos:** PHPUnit Unit, Kernel/Functional, lint, PHPCS, validadores de rotas, Domain, aliases, cache, CSRF, sessão anônima/autenticada, CPF/CNPJ opcional, checkout, segurança/OAuth, CEP/Número/S-N, nickname inválido/duplicado/concorrente, no-JS, Firefox ESR/X11 e HTTP dependem de checkout e Runtime autorizados.
- **Próxima fase S-R3-B3 após gates:** incluir pedidos, apoio e preferências na composição de Conta com owners originais; preparar S-R5 comparação funcional e visual da interface permanente.

## S-R3-B1 — Documentos da Conta, implementação dev.17

- **Feito no código:** `/studio/conta/documentos` via o mesmo
  Portal form proprietário de CPF/CNPJ, com disponibilidade opcional
  e retorno contextual; nenhuma duplicação de armazenamento ou
  exibição no Meu Perfil.
- **Contrato da raiz:** hub público na raiz `/` do ACCOUNT; caminho
  `/studio` interno e 404 em acesso direto; Minha Conta original
  em `/conta-legada`, mantida permanentemente.
- **Não validado nesta etapa:** novos Unit, Kernel/Functional, HTTP
  autenticado/anônimo, serviço CPF/CNPJ disponível/ausente,
  submit e retorno de checkout, navegador e no-JS.
- **Próxima fase S-R3-B2:** separar dados cadastrais privados do campo
  apelido público (owner Portal/User); integrar formulário Conta sem
  carregar o campo de apelido; preparar editor isolado de apelido em
  Perfil, sem mudar schema nem quebrar o form legado.
- Após isso, avançar preferências, pedidos e apoio com os serviços
  proprietários e comparar Conta Studio x `/conta-legada` antes
  de aprovar a nova interface.


## S-R3-A — composição de Conta por Portal, código dev.14

**Implementado:** endereço, segurança/e-mail, senha/OAuth e conexões em
rotas canônicas de `/studio/conta`. Controller Studio apenas compõe
render arrays e navegação; dados e operações permanecem no Portal.
A regra de Endereço da Conta legada foi estendida para a nova rota do
Studio sem criar entidades ou lógica de endereço duplicadas. Submit
volta à interface correspondente. Testes Unit e gate estático escritos.

**Pendências reais antes de homologar:** PHPUnit, Kernel/Functional,
testes HTTP e Firefox ESR/X11 no Domain ACCOUNT, submissão de CEP/Número/S-N,
endereço refletido em checkout, e-mail/primeira senha OAuth e ausência
de duplo shell. O cadastro em `/dados` e outros destinos ficam legados
até se separar `field_nickname` e concluir S-R3-B.

**Regra permanente:** o legado na raiz `/` não pode ser removido
depois da comparação, e `/minha-conta` direto permanece 404.
Não criar aliases, duplicar storage nem ampliar o escopo do Perfil.

## S-R2 concluído no código: experiências independentes (dev.13)

- **Feito:** providers independentes `conta` e `perfil`, rotas
  `/studio/conta`, `/studio/perfil`, rotas do User em
  `/studio/perfil/{bio,privacidade,redes/{rede}}`, controle de acesso,
  testes Unit e roteamento atualizados. A experiência `pessoal` e suas
  rotas desapareceram, **sem alias temporário**.
- **Preservação obrigatória:** Minha Conta legada continua em `/`
  (Domain ACCOUNT), com `/minha-conta` técnico direto respondendo 404.
  **Não há fase futura de desativação do legado**, mesmo após aceite
  da nova interface.
- **S-R3 próxima:** extrair a composição das seções Conta do Portal sem
  invadir Meu Perfil e sem aninhar dois shells. Hoje os links iniciais
  ainda abrem os formulários do Portal no contexto original.
- **S-R4:** editor isolado de apelido, aperfeiçoamento do Perfil e
  testes de upload e Form API no-JS.
- **S-R5:** comparação e aceite visual/funcional entre Conta legada
  e Studio, preservando a antiga.
- **Testes pendentes:** Unit, Kernel, Functional, cache/access, Drush,
  mobile/Firefox ESR-X11 e validação de rotas no Runtime.

**Documento prioritário:**
[Conta e Perfil separados](../../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
As fases antigas P0–P7 abaixo são históricas; qualquer menção a
remoção final do shell antigo é revogada.


## Incremento STUDIO-P2-A — editor privado parcial (dev.12, 2026-10-24)

**Implementado no código:** `/studio/pessoal/perfil` com leitura
exclusiva da conta corrente, renderização reutilizada de
`AccountBioCard`/`SocialLinksCard` do User, modal de foto atual, link
ao formulário de apelido e serviço oficial de perfil público de
`aculta_user`. Entrada `/studio/pessoal` inclui navegação ao editor.
As operações continuam nos serviços/formulários proprietários; não
há novos campos, entities, handlers, aliases nem copias de perfil.

**P2-B pendente:** integrar apelido e privacidade no contexto Studio
sem sobrepor o `AccountShellBuilder` e sem reescrever o User,
melhorar fallback sem JavaScript (o acesso GET direto aos modais de
bio/redes hoje é bloqueado pelo User), preservar erros, CSRF e uploads.
**Gates P2-A pendentes:** Unit e Kernel/Functional, acesso entre contas,
privacidade, nome inválido/perfil privado, ajax modal, foto/recorte,
cache/Domain e Firefox ESR X11. Não declarar Runtime homologado.

## Incremento dev.11 — remover compatibilidade temporária (2026-10-24)

A home canônica `/studio` conserva somente o route ID
`aculta_studio.hub`; `/studio/marley` permanece o início editorial
e as áreas/subáreas reais estão em `/studio/marley/{aba}/...`.
Foram eliminadas as rotas de prévia, capitalização, árvore editorial antiga
e os redirects legados, inclusive os aliases estudantis do LMS.
O contrato de acesso do Studio, MarleyTabManager, providers, Conta e perfil
público não mudou. P2 (editor de perfil) continua o próximo trabalho
funcional, condicionado à homologação de rotas, Unit/Kernel e Runtime.
`scripts/validate-no-temporary-route-aliases.php` evita reintrodução
de aliases. As fases dev.7–10 descritas abaixo são históricas.



**Versão STUDIO 0.1 · 2026-10-22**. GanjaLab e o entrypoint inicial WE SATIVA existem em `aculta_studio`; detalhes de cobertura e pendências abaixo distinguem o que está implementado do que segue em planejamento.

Fonte de verdade: [arquitetura ACULTA Studio](../../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md).

## Incremento STUDIO-P1-C — hub canônico e Marley (dev.10, aguardando gates)

**Implementado no Git:** `/studio` renderiza o hub autorizado; Marley
mantém `aculta_studio.home` em **`/studio/marley`**, e todas as suas
subrotas canônicas continuam minúsculas. `/studio/Marley` e
`/studio/inicio` são redirecionamentos temporários 302 apenas GET/HEAD.
A filtragem de catálogo e as áreas reais do `MarleyPresenter` permanecem.
Os testes Unit de rotas e do hub foram atualizados para essa decisão.

**Não executado nesta edição:** PHPUnit, Kernel/Functional, reconstrução
de cache de rotas, teste autenticado no purpose `account` e Firefox ESR/X11.
Sem evidência de homologação do Runtime. Preservar P2/P3 para editor pessoal
e formulários; não mover dados, `AccountShellBuilder`, login, pagamentos,
mensageria ou `/u/{apelido}`.

**Critérios de validação antes do próximo incremento:** testar usuário comum
sem Marley, editor com Marley, módulo Wiki/LMS ausente, links canônicos e
redirects GET/HEAD; confirmar /studio apenas como hub, a home
/studio/marley, o logout, cache/acesso e fallback sem JavaScript.

## Incremento STUDIO-P1-B — código dev.9, validação pendente

- **Implementado em Git:** provider de metadados Marley com acesso vinculado
  às áreas visíveis do `MarleyPresenter`; enumeração ordenada de providers;
  registro de Marley; controller e rota de **prévia** `/studio/inicio` com
  filtro por disponibilidade, autorização e existência de rota.
- **Preservado:** `/studio` ainda é a entrada histórica do Marley;
  `/studio/marley`, `/studio/ganjalab`, `/studio/we-sativa`,
  `/studio/pessoal`, home `/` da Conta e `/u/{apelido}` permanecem
  com seus donos. Não houve alteração de formulários ou shell.
- **Gates pendentes:** executar PHPUnit Unit, Kernel/Functional de registry,
  container e rotas, e smoke Firefox ESR/X11 autenticado em ambiente de teste
  com permissionamento de usuário comum/editor, módulos opcionais habilitados
  e ausentes. O cache da ponte Marley é intencionalmente `max-age: 0`.
- **Próximo incremento:** implantação controlada do hub canônico em
  `/studio` após revisar os consumidores e a compatibilidade de `legacy_home`;
  depois P2/P3 para formulários proprietários. Não avançar estados Dev→RC
  sem evidências de testes e Runtime.

## STUDIO-P0–P7 — migração futura de Minha Conta e Meu Perfil (decisão 2026-10-24)

**P0 documental concluída; P1-A–P1-C implementadas em código até a dev.10, sem homologação Runtime.**
Foi registrada a experiência `pessoal` e sua rota `/studio/pessoal`, com
controller autorizado, links para a Conta e testes Unit adicionados. **Nenhum
teste PHPUnit/Kernel/Runtime foi executado nesta edição pelo GitHub**;
P1-A requer gates antes de ser considerada homologada. O editor pessoal e a navegação contextual unificada seguem pendentes;
a ponte Marley e a prévia `/studio/inicio` já existem em código.
A arquitetura de referência está em
[Hub e Meu Espaço](../../../../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md).
Os incrementos editoriais/LMS/Wiki registrados abaixo não são revogados nem
renomeados; continuam com seus gates próprios e status histórico.

| Fase | Ordem | Entrega prevista | Aceite mínimo |
| --- | --- | --- | --- |
| **P0** | Agora; docs | Contrato, identidade, ownership, rotas atuais/alvo, menu e dependências Studio/Portal/User/Messenger/tema. | Documentação alinhada; nenhum código/Runtime modificado. |
| **P1** | Depois de P0 | Hub `/studio`, provider `pessoal`, ponte de metadados Marley para registry; experiências autorizadas. | Sem colisão com Marley/Ganjalab, cache/access, fallback full-page. |
| **P2** | Após P1 | Editor privado de Meu Perfil usando formulários User; perfil público fica `/u/{apelido}` em `main`. | Foto, avatar, bio, redes, visibilidade, duas contas e cache. |
| **P3** | Após P1 | Minha Conta: dados, endereço/CEP/S-N, documentos opcionais, segurança, OAuth e confirmação. | Form API/POST/CSRF e checkout intactos; segurança testada. |
| **P4** | Após P1 | Avisos e mensagens como atalhos, atividades, apoio, pedidos e preferências. | Ownership correto, sem storage, SMTP ou inbox duplicados. |
| **P5** | Após P2–P4 | Um shell, menu unificado/contextual, links canônicos e aliases GET/HEAD seguros. | Sem `AccountShellBuilder` aninhado, URLs e forms legados íntegros. |
| **P6** | Após P5 | Unit, Kernel, Functional, Runtime e Firefox ESR/X11 autorizado; mobile/teclado/no-JS. | Gates proporcionais, privacidade, Domain, WCAG e temas. |
| **P7** | Depois do aceite P6 | Retirar só a apresentação antiga duplicada; conservar entidades/APIs e rollback. | Aprovação operacional, equivalência e nenhuma perda de dados. |

**Bloqueadores já identificados:** `aculta_studio.legacy_home` usa `/studio`
para Marley; `AccountShellBuilder` e `account-navigation.js` produzem o
shell da Conta; a raiz `/` do purpose `account` continua a home da conta;
`/minha-conta` é bloqueado; formulários de endereço, foto e segurança
têm políticas e handlers próprios. **A definição futura de navegação não
autoriza redirects ou alterações de HTTP/POST nesta fase.**

### Incremento Studio dev.6 — início WE SATIVA

Adicionada a rota `/studio/we-sativa/inicio`, purpose `account`, com um provider
de página inicial opcional. O provider encaminha o render ao serviço Wiki,
responsável por dados, autorização e cache. O Kernel integrado validou a rota e
os links condicionados a acesso e HTML renderizado no ACULTA420 (2 testes / 208
asserções junto ao owner Wiki).
Teste Unit do controller inclui retorno owner, rota sem o contrato de início e
acesso negado (3/3); a suíte Studio completa passou em 20 testes / 61 asserções.
Runtime e Firefox ESR/X11, incluindo 320px e Olivero, permanecem pendentes.

### Incremento Studio dev.4 — disponibilidade da aba Wiki

`WikiTab::isAvailable()` exige o módulo proprietário `aculta_wiki` além do
bundle `wiki_entry`. A configuração do tipo de conteúdo pode permanecer após
desativar o módulo; nesse caso, Studio não apresenta uma aba sem serviços do
owner. Unit cobre o módulo desligado com bundle retido, o par habilitado e o
bundle ausente (focal 3/10; suíte Studio Unit completa 13/41). Runtime intocado.

### Incremento Studio dev.5 — separar autoria WE SATIVA do GanjaLab editorial

`create wiki_entry content` sozinho não dá acesso à área Wiki do GanjaLab nem
exibe seu inventário compartilhado. A autoria continua na WE SATIVA. GanjaLab
abre com permissão de edição ampla, revisão de revisões ou moderação de
propostas; a seção de verbetes exige `edit any wiki_entry content`. Teste Unit
focal: 7 testes / 27 asserções. A suíte Firefox ESR visível foi iniciada, mas
ficou aguardando o Drush `uli` sem produzir URL de login/artefatos; foi encerrada
após cerca de dois minutos. Runtime e visual seguem sem homologação.

## Prioridades

| Marco | Trabalho | Gate |
| --- | --- | --- |
| 0.1-A | inventariar infra já presente: StudioTab, StudioPresenter, listing, GanjaLab, rotas e consumidores projetados WE SATIVA/GanjaLab420 | inventário existente versus planejado |
| 0.1-B | ADR de contrato genérico de experiência separando purpose, navegação, provider de ações e ACL | não acoplar WE SATIVA ao StudioTab de conta |
| 0.1-C | endurecer cache e revision/entity access de StudioListingBuilder; corrigir queries auxiliares e paginação; Unit/Kernel | nenhum vazamento entre usuários, LMS, Wiki, Forum/Revista |
| 0.1-D | WE SATIVA: interface de autoria no purpose wiki usando adapters aculta_wiki, Core SDC/UI Suite e Form API | criar/enviar/acompanhar sem acesso de moderador |
| 0.1-E | GanjaLab: seção Wiki/Moderação na WikiTab existente; provider/POST de aculta_wiki | content_editor e transições oficiais, concorrência e feedback |
| 0.1-F | GanjaLab420 e futuras interfaces: inventário de consumidores reais e adaptação, sem inventar rotas | contratos testados por consumidor |
| 0.1-R | homelab, acessibilidade, HTTP sem JS, AJAX e cache por purpose | evidência real |

### WE SATIVA 0.1-D0 — entrypoint e Meu trabalho (Studio dev.3 / Wiki dev.39)

Implementado `/studio/we-sativa` no purpose `account`, com provider Studio que
delega ao `aculta_wiki.contributor_workspace`. O serviço Wiki consulta verbetes
e propostas próprios por cursor independente, revalida Entity Access e compõe
Meu Trabalho com SDCs. Criação, edição, contribuição e status usam as rotas,
serviços e Form API da Wiki; Studio não duplica armazenamento nem ACL.

O Kernel Wiki/Studio combinado foi ampliado desde a entrada inicial; no HEAD
Wiki dev.77, a classe completa de ciclo da Wiki passou em MariaDB (23 testes /
1.039 assertions) e inclui regressões de rota, propósito, autoria, paginação e
acesso. Os formulários Wiki têm retorno cacheável ao workspace quando o
provider/acesso estão disponíveis. A interface visual da experiência WE SATIVA ainda não foi homologada em
Firefox ESR/X11, 320 px, Olivero ou Runtime; a suíte atual do browser Studio
exercita principalmente GanjaLab. O smoke WE SATIVA foi adicionado em
`tests/browser/aculta-wiki/we-sativa.py`; a execução de 2026-10-24 não encontrou
rota/provider no container ativo do Runtime, apesar de Studio e Wiki constarem
habilitados. Reconstrução do container segue pendente de autorização; não
declarar a validação visual aprovada.

## Regras para agentes

**Não criar novo módulo ou renomear machine name.** ACULTA Studio é o submódulo presente. Não copiar cabeçalhos nem criar design system paralelo. `aculta420` fornece visual via Core SDC/UI Suite; `aculta_studio` fornece orquestração e contratos de UI; User/Wiki/Forum/LMS/Messenger mantêm sua lógica. Preservar branch e arquivos de outros agentes. Abrir fases por commits pequenos; não alegar validação em homelab sem executá-la.

### GanjaLab Wiki — integração inicial (Studio dev.2 / Wiki dev.19)

A `WikiTab` existente agora consome a fila autorizada de revisões e propostas fornecida por `aculta_wiki`. Propostas abrem o Form API de decisão; revisões abrem o formulário nativo do Node após checagem de rota no purpose Wiki. Kernel combinado cobre gates de permissão e listagens. Runtime e revisão visual Firefox/X11 ainda não foram homologados; a Wiki segue desabilitada no Runtime.

## Estado D-A.2-T

Primeira etapa realizada: cache de listagens por usuário, checagem explícita de revisão, teste Unit escrito, ainda sem execução Runtime. **Pendentes**: consulta de content_moderation_state, paginação, isolamento entre usuários/Group/purpose e provider da moderação Wiki. Não implantar fila antes dos gates.

## Atualização STUDIO 0.1-B / D-A.2-T2

STUDIO 0.1-B: contrato de providers de experiência definido no ADR da plataforma. StudioTab permanece do GanjaLab; WE SATIVA terá provider no purpose Wiki. D-A.2-T2: removida a consulta global content_moderation_state accessCheck(FALSE) do StudioListingBuilder e passado o estado pela última revisão visível. Pendências: paginação correta, grants e testes Kernel, provider Wiki, nenhuma fila ativada.

## GanjaLab420 — migração para ACULTA Studio (G420-A)

[Contrato e inventário de migração](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). GanjaLab420 é **a experiência LMS do estudante** dentro de ACULTA Studio. Os entrypoints de `/ganjalab420` e `/minha-aprendizagem` foram transferidos para `aculta_studio.ganjalab420_controller`, que delega ao controlador LMS; o LMS preserva dados, progresso, matrículas, certificados, controle de acesso e cache. Não confundir com GanjaLab editorial nem com WE SATIVA. A extração da composição visual para Studio será a G420-C; não declarar concluída. Antes do deploy habilitar Studio se necessário e executar testes Function/Kernel e smoke de rotas.

## G420-B — Provider de experiência implementado (código, validação pendente)

Contrato `StudioExperienceProviderInterface`, registry `StudioExperienceManager` e provider `GanjaLab420ExperienceProvider` registrados por DI. Entry point `GanjaLab420Controller` migrou da dependência direta do controller LMS para o manager Studio; render do LMS preservado. `StudioTab` permanece exclusivo de GanjaLab editorial; WE SATIVA não ganhou provider fictício. Foram escritos testes Unit de negação e registry, **não executados aqui**. G420-C: extrair composição visual, mantendo estado acadêmico, Group e autorização no LMS. O contrato completo está em [migração GanjaLab420](../../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md).

## G420-C — Dashboard no Studio e domínio de conta

`LearnerDashboardComposer` agora é responsável pela composição do dashboard; `LearningDashboardManager` continua dono de dados/cache/regras LMS. O provider do estudante usa o composer e as antigas chamadas `LearningDashboardController::student()` delegam a ele. Namespace canônico: `/studio/ganjalab`, `/studio/ganjalab420`, sob `_aculta_domain_purpose: account` (host `conta.aculta.*` definido pelo Portal). Aliases temporários mantidos. Documentação de arquitetura consolidada em `docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md`. **Implementação sem gate Runtime:** precedência de rotas, DI, Kernel, HTTP, SDC responsivo e testes anteriores precisam de verificação antes do aceite. Próxima G420-C2: concluir compatibilidade/testes do compositor, separar view models e elevar cartões para SDC/UI Suite; não duplicar dados ou serviços acadêmicos.

## G420-C2 — cartões SDC do painel estudante (2026-10-22)

**Implementado em código, não homologado no Runtime.** O `LearnerDashboardComposer` passou a compor as listas de cursos e certificados com o componente já existente `aculta420:card`, distribuído pelo `aculta420:content-grid` (duas colunas responsivas). Mantidos `aculta420:context-header` e `aculta420:empty-state`. A progressão permanece em elemento HTML nativo `progress` com texto e `aria-label`, dentro do slot do card; não se criou CSS, Twig ou design system paralelo.

Os dados e sua cacheabilidade continuam provenientes do `LearningDashboardManager` no LMS, inclusive autorização, notas e certificados. Cursos mantêm URLs fornecidas pelo Portal/LMS, certificados usam rota de verificação existente; nenhum ACL novo. `#cache.max-age: 0` conservado até aferição em ambiente real. Teste Unit existente atualizado para preservar header e estados vazios SDC; execução PHP lint, PHPUnit, inspeção UI Suite/SDC, contraste dark/light, 320–1440px, navegação por teclado e smoke de domínio conta ainda **pendentes**.

**Próxima C2-T:** fixture de curso matriculado, avaliação pendente e certificado ativo/revogado, testes Kernel/Functional de renderização com SDC e regressão de URLs no purpose account. Após homologação, avaliar um componente de progresso próprio do ACULTA420 apenas se houver segundo consumidor real.

## Replanejamento transversal — 2026-10-22 · STUDIO 0.1-C2-R

**Prioridade ativa: GanjaLab editorial.** G420-C2 permanece implementada estaticamente e não homologada. Não bloquear o desenvolvimento editorial pela construção paralela de componentes LMS/tema. A homologação G420-C2-T fica condicionada a fixtures reais, layout em ACULTA420, contratos dos dados LMS, acessibilidade, mobile, light/dark e gates Domain/HTTP. `aculta420:card`, `content-grid`, `context-header`, `empty-state` são componentes existentes, mas existência de YAML/Twig/story não é evidência de consumidor homologado, comportamento responsivo ou UI Suite validada. Progresso, avaliação pendente, estados de certificado e contexto de turma seguem acoplados aos dados/provedores LMS; não declará-los componentes de design maduros. Não criar um novo componente só para passar o gate.

### Matriz de dependências e frentes

| Frente | Código constatado | Não concluído / dependência | Política |
| --- | --- | --- | --- |
| G420-C2 dashboard | Composer no Studio, cards/grid/empty/context-header SDC existentes, dados em `LearningDashboardManager` | fixtures matriculado/não matriculado, certificados válidos/revogados, UI Suite real, Kernel/Functional, browser, mobile/dark, propósito account | congelar novas abstrações e acompanhar trabalho LMS/tema |
| G420-D salas e turmas | LMS, Group e recursos especializados existentes | adapters e UX integrada, avaliações e materiais/certificados em desenvolvimento paralelo | não iniciar pela UI sem consumidores e autorização confirmados |
| ACULTA420 | SDC card/grid/header/empty e stories estáticas | verificar render real, contratos de slots, tokens, 320–1440px, dark/light e gates UI Suite | tema é dono do visual; Studio não cria CSS concorrente |
| GanjaLab editorial | StudioTab/Presenter/Controller/AJAX, plugins Revista/Wiki/Forum/Cursos/Galerias | hardening de consultas, ações reais, jornadas próprias, editor e fila | prioridade imediata |
| Wiki moderação | proposta e WikiTab existentes | provider autorizado/paginado, grants por revisão, Kernel, Content Moderation | bloqueado para produção |
| WE SATIVA | provider e `/studio/we-sativa`; workspace Wiki com paginação, SDCs, Entity Access e links para fluxos Form API do dono | validação visual/browser de autoria a 320 px, Olivero, fallback sem JS e Runtime | avançar visualização sem criar storage ou ACL no Studio |

### Frente prioritária: GANJALAB E-0.1 — Editor Studio

- **E-A inventário/contrato:** conferir consumidores de cada plugin, rotas e forms dos donos, ownership por área, URLs canônicas `conta.aculta.*/studio/ganjalab`, links e dependências; limpar README/docs obsoletos sem remover rotas legadas.
- **E-B Meu trabalho:** padrão default `Meus conteúdos`, acessos autor/equipe/moderador distintos, filtros autorizados, paginação depois dos grants e render access/cache por item. Prioridade segurança antes de disponibilizar listagens novas.
- **E-C Jornada editorial Revista:** criar, salvar rascunho, editar, pré-visualizar, enviar para revisão e acompanhar pelo Form API e transições existentes do dono; Studio apenas navegação, composição e feedback.
- **E-D Editor shell:** layout mobile-first e progressive enhancement, foco, erro, aviso de alterações não salvas e ações contextualizadas via Core SDC + UI Suite do ACULTA420, preservando Institution/Domain Header.
- **E-E adapters:** estender a Wiki/Fórum/Galerias/Cursos somente após cada dono confirmar forms, ACL e estados; moderação Wiki em microfase própria com provider e testes.
- **E-R aceite:** testes Unit/Kernel/Functional de dois autores e editor, revisão privada, grants Domain e Group, 403/404, cache quente, no-JS/AJAX, 320–1440px, contraste e teclado.

**Não habilitar nova fila nem ampliar visibilidade de estados no E-A.** Cada microfase terá commit próprio na PR corrente, sem misturar trabalho paralelo. Testes de runtime não executados nesta reclassificação documental.

## GANJALAB E-A.1 — Entrada simples de publicação (código em 2026-10-22)

`StudioController::welcome()` renderiza na entrada do GanjaLab o SDC `context-header`, cartões `card` e grade `content-grid` com ações filtradas pelos providers `StudioTab`/Presenter e acesso às rotas do dono. Nenhuma entidade ou workflow migrou. Prioridade imediata **E-A.2 testes reais de ações/domain/visual** e **E-B Meu trabalho seguro**, antes de implementar editores inline ou moderação. Auditoria: [MARLEY-E-A-EDITOR-AUDIT.md](MARLEY-E-A-EDITOR-AUDIT.md). Sem PASS de Runtime, mobile ou PHP lint nesta sessão.

## GANJALAB E-B.1 — Meu trabalho (2026-10-22)

**Implementação parcial, não homologada:** área Revista usa seção padrão `Meu trabalho` com aba inicial `Meus conteúdos` e demais filtros pessoais. Todas as abas dessa seção declaram `scope: mine`, e o `StudioListingBuilder` impõe `uid = conta atual` na entity query antes da paginação Drupal de 20 itens. A tabela continua verificando o acesso da entidade e da última revisão individualmente; o cache varia por usuário e parâmetros do pager. Foi criado teste de caracterização por código, sem execução PHPUnit/Kernel/HTTP nesta sessão.

**Limitação fundamental:** os filtros rascunho/revisão/publicado ainda são aplicados após a paginação, porque dependem da última revisão visível. Assim, páginas filtradas podem aparecer incompletas; o pager conta candidatos por autor, não apenas os que correspondem ao estado. Não anunciar paginação de estados pronta nem ativar listas de equipe/moderação. Para E-B.2, implementar provider proprietário com consulta paginada coerente com workflow/revision grants ou refinar indexação segura, com testes Kernel de dois autores, drafts privados, revisão não-default, cache quente, domínio e mudança de estado. Nenhum bypass de `accessCheck(TRUE)` ou da revisão é permitido.

**UX alvo:** a home GanjaLab mantém convite para criar; `Meu trabalho` oferece continuar, revisar pendências e localizar itens próprios. Equipe e moderação são seções distintas, com autorização explícita do dono, nunca a aba `Todos` genérica. E-B.3 converte listagens autorizadas em cartões SDC mobile-first do ACULTA420 quando o contrato de dados estiver estável, com link real, foco, estado acessível e feedback. Continuar com E-C apenas após aceite dos grants das jornadas de publicação.

## GANJALAB E-B.2 — paginação pós-ACL da última revisão (2026-10-22)

**Implementado em código, não homologado.** Para `scope: mine`, o Studio faz consulta de nós com `accessCheck(TRUE)`, bundle e `uid` antes de examinar revisões; varre candidatos do autor em lotes de 100, valida `view` do nó e `view` + `view revision` da última revisão, determina o estado e só então cria o pager Drupal sobre os IDs correspondentes (20/página). Uma revisão privada não deve fornecer estado ou posição de paginação; sem revisão autorizada o item pessoal é omitido. As demais listagens genéricas continuam com limite histórico de 50 e **não estão homologadas como moderação**. O serviço `pager.manager` é injetado no `StudioListingBuilder`; teste estático de caracterização atualizado.

**Gate de escalabilidade pendente:** varrer todos os IDs acessíveis do autor a cada pedido pode ser caro em acervos grandes; requer benchmarks, Kernel com múltiplos autores e revisões, teste de acesso/cache e futuro provider paginado/indexado com garantias equivalentes. Não criar indexação de estados com `accessCheck(FALSE)` nem ignorar grants. O estado e o count podem variar durante edições concorrentes; sem snapshots transacionais nessa microfase.

**Próxima E-B.2-T:** homelab `drush cr`, PHPUnit, Kernel com 2 autores e revisões default/não-default, pagers com muitos registros, rota `conta.aculta.*/studio/ganjalab/revista/publicacoes/rascunhos`, evitar leakage em cache quente. E-B.3 só após validar o contrato de visualização e conteúdo.

## GANJALAB E-B.2-PERF — cursor de volume elevado (2026-10-22)

A varredura integral de IDs do autor por request foi substituída por **paginação progressiva por cursor (keyset/seek)** em `StudioListingBuilder`. Em `scope: mine`, a entidade é buscada com `accessCheck(TRUE)`, `uid`, `bundle`, ordem `changed DESC, nid DESC` e cursor `studio_cursor` validado como posição, não autorização. Cada consulta busca até 100 candidatos e há limite de dez lotes por request; cada candidato exibido exige `view` no nó e `view` + `view revision` na revisão mais recente, com filtro de estado antes da exibição. A navegação passa a usar link real “Carregar mais publicações” (não pager numérico); sem JS funciona. `url.query_args:studio_cursor` participa do cache. Serviços usam `request_stack` em vez de `pager.manager`.

**Escalabilidade e precisão:** custo por requisição limitado a até 1000 candidatos, sem `count(*)` condicionado por permissões de revisão e sem ler toda a coleção pessoal; porém estados muito raros podem demandar vários cliques de continuidade, inclusive páginas sem registros. O cursor é uma posição textual e **não é uma garantia de snapshot**: concorrência e alteração de `changed` podem mover itens entre páginas. Não há contagem total nem paginação numerada exata. O código ainda precisa de Kernel/Functional e benchmarks com milhares de nós/revisões. Para exigência futura de contagem precisa/ordenação estável sob edição concorrente, especificar índice derivado revision-aware com invalidação e grants corretos — nunca índice público de metadados de revisões privadas.

**Status:** implementação com limite de custo, sem homologação Runtime; não declarar “otimização definitiva” no sentido de contagem exata ou isolamento transacional. Próximo gate E-B.2-T: simular muitos candidatos sem resultado, acesso negado e última revisão privada, mudanças concorrentes, URLs inválidas e DOM no purpose account, mais testes de performance e verificação de DBCache.
# Identidade vigente — 2026-10-24

ACULTA Studio Marley (`marley`) é a experiência editorial e ACULTA Studio
Ganjalab (`ganjalab`) é a experiência de aprendizagem. `aculta_studio` continua
sendo a plataforma compartilhada. As seções abaixo de G420/GanjaLab preservam
o histórico das fases anteriores; nomes e caminhos atuais estão no
[contrato de identidade](../../../../../../../docs/portal/ACULTA-STUDIO-IDENTITY.md).


## OC315 - complementar experiência, sem misturar Conta, Perfil e Organização (2026-10-29)

Referência: [auditoria 3.1.5](../../../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). **Planejamento aprovado**, após gates S-R4/S-R5 já definidos.

- **OC-ST1:** "Meus salvos" combina flags existentes respeitando dono e purpose; `bookmark-toggle` **já possui** consumer em tópico do Fórum. Estado visual do botão ≠ lista privada concluída. Testar contas A/B e cache por UID.
- **OC-ST2:** "Minhas recomendações" terá storage/flag/autoridade separados de favoritos e seguidores; não inferir vínculo organizacional de bookmark.
- **OC-ST3:** "Minha organização" usa vínculo de representação aprovado pela moderação, nunca credencial de **Conta** ou papel global `organizador` por si. **Perfil** público continua entidade/experiência distinta; mudanças organizacionais exigem aprovação e trilha de auditoria.
- **OC-ST4:** primeiro acesso em cartões existentes; sem depender de Tours do OpenCulturas, sem JS obrigatório e sem duplicar onboarding.
- Gates: grants Entity/Domain, acesso negado em cross-account/cross-purpose, cache A/B, Firefox ESR e Olivero.

## OCS-F0 — inventário visual (2026-10-29)

**Registro da primeira captura, atualizado pelos resultados posteriores no fim
deste documento.** A captura ampliada inicialmente foi adiada por falha de ULI;
ela foi executada depois e encontrou Hub vazio, conteúdo inesperado em GanjaLab
e acesso negado ao Green Stash para a conta de teste. Consulte a atualização
mais recente antes de avaliar o estado da fase.

Inventário upstream e matriz inicial adicionados em
[`aculta420/docs/studio-visual/`](../../../../../../themes/custom/aculta420/docs/studio-visual/upstream-inventory.md)
e [matriz de paridade](../../../../../../themes/custom/aculta420/docs/studio-visual/visual-parity-matrix.md).
O tarball oficial 3.1.5 foi obtido e seu SHA-256 confere com a auditoria
registrada; o CSS servido pelo site público também tem o mesmo hash do CSS do
pacote. A página pública serve como referência geral, mas não substitui uma
captura do dashboard autenticado OpenCulturas. Em 2026-10-29, o script
WebDriver BiDi capturou homepage OpenCulturas e `/studio/marley` autenticado em
1440×1000 e 390×844 CSS px; as quatro imagens foram inspecionadas manualmente e
estão fora do Git em `/home/piradopirata/suite-out/aculta-studio-ocs-f0/`.
Isso estabelece baseline inicial em duas larguras, não valida todas as telas
nem os breakpoints do gate. Boundary e schema SDC passaram na execução anterior;
o gate do índice de status permanece vermelho por tabela raiz desatualizada.
**F0 segue parcial**: falta comparação equivalente ao dashboard autenticado
upstream, cobertura visual de toda área real e resolução/registro dos gates. A
tentativa seguinte de ampliar para oito entradas do Studio ficou `DEFERRED`
antes do login quando Drush não descobriu
`aculta_document_viewer_group:course_document`; a evidência está separada em
`baseline-deferred.json` no diretório externo da suíte. Em tentativa posterior,
o script capturou as oito rotas; o resultado e os bloqueios constam no registro
mais recente abaixo.

## Complemento OC-C - OpenCulturas 3.1.5 (2026-10-29)

- [ ] Vínculo verificável e revogável entre conta, perfil público e organização. Área "Minha organização" por escopo de `content_editor`, com reivindicação moderada e estado inicial fechado. **Não** criar papel genérico organizador (D-01) nem confundir conta com perfil.
- [ ] "Meus salvos" e "Recomendações" com cache por UID e testes Kernel intercontas A/B. `bookmark-toggle` já existe e tem consumidor no Fórum; reusar, não duplicar.
- [ ] Isolamento por purpose, acesso, WCAG, teclado, no-JS, 320 px e zoom 200%.

Backlog aprovado sem reclassificar S-R4/S-R5 como homologadas. Referência: `docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md`.
## Retificação vigente — THEME-MF0–MF3, OCS-F0–F11 e OCS-A1–A4 (2026-10-29)

Esta seção substitui a numeração resumida das propostas visuais históricas
abaixo, sem alterar seus registros de implementação ou homologação. O trabalho
é sequencial: auditar e tornar todo ACULTA420 mobile-first (MF0–MF3), concluir
a fidelidade OpenCulturas Next de todas as áreas reais do Studio (F0–F11), e
só então aplicar a identidade Aculta aos mesmos SDCs (A1–A4). **A1–A4 não
iniciam antes de MF3 e F11 aprovados.** Nenhuma fase é considerada concluída
somente porque há documentação.

- **THEME-MF0:** inventário de todos os estilos, scripts, templates, consumers,
  larguras/overflow e purposes. Estado estático parcial, sem captura completa:
  [`mobile-first-audit.md`](../../../../../../themes/custom/aculta420/docs/mobile-first-audit.md).
- **THEME-MF1–MF2:** fundação mobile desde 320 CSS px e migração progressiva,
  preservando Bootstrap, semântica, shell e owners dos módulos.
- **THEME-MF3:** gate transversal para todos os purposes e breakpoints definidos
  no prompt vigente; ainda não executado.
- **OCS-F0–F11:** baseline, tokens, navegação/composição, SDCs e Hub, Marley,
  GanjaLab editorial/LMS, WE Sativa, Green Stash integrado, Conta/Perfil e
  demais áreas reais. A matriz inicial e os `Pede-A` estão em
  [`studio-visual`](../../../../../../themes/custom/aculta420/docs/studio-visual/).
- **OCS-A1–A4:** identidade Aculta após ambos os gates, mantendo props/slots,
  render arrays e contratos SDC da Etapa 1.

**Green Stash é a área comercial integrada ao ACULTA Studio.** O registro
técnico `GreenStashExperienceProvider` não cria experiência de produto
independente. Operações de Commerce, ACL, formulários, dados e transições
permanecem com seus owners; a apresentação só pode promover capacidades
confirmadas no código. O inventário identificou Produtos e Envios no código;
Pedidos/Estoque/Promoções/Clientes/Relatórios/Configurações ainda exigem
verificação individual e não devem ser anunciados como ativos.


## OCS-F0 / THEME-MF0 — evidência de execução (2026-10-29)

Inventário estrutural e matriz de paridade foram produzidos; os gates continuam
**parciais**, sem aceite. Firefox ESR visível capturou homepage OpenCulturas e
oito URLs Studio em 1440/390: `/studio` respondeu documento vazio;
`/studio/ganjalab` renderizou Minha Conta em vez do dashboard LMS;
`/estudio/loja` negou acesso à conta sintética sem permissão; Marley, WE Sativa,
Conta, Perfil e Planos Mercado Pago renderizaram. Scroll width coincidiu com
viewport nessas capturas, sem constituir gate mobile-first. Evidências estão em
`$SUITE_OUT/aculta-studio-ocs-f0`; `mobile-first-audit.md` e
`studio-visual/visual-parity-matrix.md` detalham limites e Pede-A. MF0/F0 não
foram aprovadas; não iniciar F1/MF1 até resolver o Hub e obter fixture
autorizada/comprovação da rota LMS. Todos os purposes ainda carecem de baselines
runtime. Uma repetição instrumentada do baseline confirmou `Institution Bar`
presente nas sete rotas que renderizaram, mas `.aculta-header` ausente em todas;
apenas Marley expõe marcadores `[data-aculta-studio]`. A disponibilidade do
Domain Header em `account` e a política de marcadores das outras áreas ficam
registradas como Pede-A, não como workaround da Frente B.

### Atualização visual F0 — grade responsiva expandida

Em 2026-10-29, Firefox ESR visível capturou as oito URLs Studio nas nove
larguras do gate de viewport (72 capturas Studio, mais nove da referência
pública OpenCulturas). Nas rotas que renderizaram, scroll width coincidiu com
o viewport; `/studio` ficou vazio, `/studio/ganjalab` continuou mostrando Minha
Conta e `/estudio/loja` negou acesso à conta sintética em todos os tamanhos.
Institution Bar apareceu nas sete páginas com conteúdo; Domain Header faltou
nas oito URLs; marcadores `data-aculta-studio` apareceram só em Marley. O
servidor estava no head `f2708e60f`, com arquivos de tema/Studio/Commerce
idênticos à PR então em `5734bed87`, mas tinha mudanças locais de Eventos. As
capturas e limitações estão em `studio-visual/upstream-inventory.md`; esta
execução aumenta a evidência responsiva, mas não fecha F0/MF0 nem permite F1/MF1.

O probe adicional de foco por teclado percorreu 12 Tabs em Marley, Conta e
Perfil a 320/1440 px (72/72 `:focus-visible`, outline calculado presente). A
captura Marley/320 mostrou foco visível no skip link. É evidência parcial e não
substitui percurso integral, Shift+Tab, modal/widgets, WCAG ou o gate F7.

A repetição ampliada do probe usou os nove viewports e Tab/Shift+Tab nas mesmas
três rotas: 648 observações, 621 alvos focáveis com `:focus-visible` e outline,
27 retornos ao `body` ao terminar a volta. Capturas em `$SUITE_OUT` e detalhes
no inventário visual. A amostra não fecha o gate WCAG/F7.

A prova de modos também cobriu claro, escuro e `prefers-reduced-motion` nas
rotas Marley, Conta e Perfil em 320/1440 px. O modo escuro alterou os tokens
computados e confirmou as media queries dark/reduced; o modo auto refletiu a
preferência padrão clara do Firefox. Capturas e limites estão na matriz F0; não
são aprovação de contraste WCAG nem cobertura global.

Os modos claro, escuro + reduced motion e auto foram revalidados em toda a
grade de nove viewports para Marley, Conta e Perfil (81 capturas). As media
queries e tokens esperados foram observados em 27/27 capturas por modo, sem
overflow medido. Isso aumenta a evidência das três rotas; não cobre todos os
componentes/purposes, não aprova WCAG nem encerra F0/MF0.

A auditoria global THEME-MF0 também capturou anonimamente as raízes dos dez
purposes em nove larguras (90 telas, sem overflow medido). Esse dado não cobre
Studio autenticado além das rotas registradas no baseline OCS; a raiz ACCOUNT
leva ao login e o Domain Header faltou em oito purposes públicos, apesar da
Institution Bar presente em todos. Ver `mobile-first-audit.md` para Pede-A,
limites por purpose e estados vazios.

OCS-F1 iniciou a camada visual de fidelidade com tokens de cor OpenCulturas
3.1.5 e acabamento das abas nos seletores de apresentação Studio já existentes.
O SDC `context-tabs` foi reutilizado; nenhum contrato funcional foi alterado.
Firefox no checkout servido passou 29 verificações, 1 SKIP e zero falhas na
rota Marley, com contraste mínimo 7,05:1 claro e 11,51:1 escuro. Tipografia
aguarda licença verificável e outras áreas Studio precisam de captura; F1
permanece parcial. Referência e limites em `studio-visual/upstream-inventory.md`.

### THEME-MF2 — layout responsivo interno do Studio (2026-10-29)

`studio.css` empilha sidebar e painel como base e restaura a navegação lateral
em `min-width: 48rem`, sem alterar os SDCs, markup, providers, links, ACL ou o
shell multidomínio. O smoke visual passou a injetar a CSS candidata e verificar
320/390/767/768/1024/1440px. Stylelint e sintaxe JS PASS; Firefox/Runtime
DEFERRED por sessões concorrentes. Esta alteração é somente responsividade do
tema: não conclui F0/F1 nem homologa OCS-F11 ou THEME-MF3.


### OCS-F0 — recontagem dos providers no HEAD 3c1691fc5 (2026-10-30)

A revarredura do registry encontrou sete providers registrados diretamente em
`aculta_studio.services.yml` (Conta, Perfil, Marley, GanjaLab estudante, WE
Sativa, Planos Mercado Pago e Meus Salvos) e Green Stash registrado
dinamicamente pelo módulo Commerce. Meus Salvos tem controller e tabs para
salvos/seguindo/recomendações, com query transversal `SavedContentQueryInterface`;
a existência desses fluxos no código não encerra os testes ARQ-07B nem o
baseline visual/A-B no Runtime. Green Stash segue área comercial integrada:
o provider de entrada ainda declara apresentação preparatória, enquanto
produtos/envios têm fluxos proprietários a validar por loja. Os detalhes por
rota, owner, disponibilidade e estado visual foram adicionados à matriz
`studio-visual/visual-parity-matrix.md` e ao inventário upstream. OCS-F0 segue
parcial; Hub/LMS, acesso à loja, Domain Header no purpose ACCOUNT e capturas
pareadas continuam pendentes.

Após o inventário, a apresentação do Domain Header foi ajustada no contrato do
Portal para todos os dez purposes, inclusive ACCOUNT. O teste Kernel cobre
identidade e URL por Domain, mas a alteração ainda aguarda entrega ao checkout
servido e verificação visual de duplicação nas rotas Studio. OCS-F0 e OCS-F11
permanecem parciais.

## OCS-F12 - Residência das Suites por domínio (ADR-038)

**Estado: paths implementados; homologação funcional parcial.** As rotas canônicas das cinco Suites, paths de Conta/Perfil/salvos/Mercado Pago, entrada da Conta e 404 global de `/studio*` estão no código. `aculta_studio` continua framework/registry sem hub. A matriz HTTP verificou 275 respostas e Firefox ESR capturou 20 páginas em 1440/390 px; grants de escrita, testes Kernel e acessibilidade ampla permanecem pendentes. Wiki usa o Domain `wiki420` sem mudança de host planejada.

- [x] F12-A registrar a correspondência de rotas e ownership inicial em ADR-038; consumidores/docs restantes seguem no inventário de trabalho.
- [x] F12-B definir paths de Conta, Perfil, Salvos e planos Mercado Pago fora de `/studio`, sem fundir Conta/Perfil nem trocar owners.
- [x] F12-C migrar declarações e providers Marley e Ganjalab; Ganjalab passa de `account` para `courses`.
- [x] F12-D mover entradas WE SATIVA, Green Stash e Culturas a `wiki`, `shop` e `eventos`.
- [x] F12-E confirmar o Domain Wiki canônico (`wiki420`) e retirar do escopo a referência equivocada a `wiki.aculta.*`, conforme correção do responsável em 2026-10-30.
- [x] F12-F retirar rewrite `account` para `/studio`, implementar entrada da Conta e retirar as rotas públicas `/studio*`; cache de rotas reconstruído e caminhos antigos verificados como 404.
- [x] F12-G auditar consumidores de rotas ativas e atualizar mapas, canonical/cache/SEO e documentação de rotas; manter links absolutos pelo `DomainPurposeManager`.
- [x] F12-H1 verificar por HTTP os cinco Domains, paths canônicos, purpose errado para GET/POST/AJAX e `/studio*` 404 (275/275 respostas esperadas em 2026-10-30).
- [ ] F12-H2 homologar grants e formulários de escrita das Suites, especialmente Green Stash, e ampliar acessibilidade/zoom/teclado/mobile além do baseline 1440/390.

Pendências residuais: F12-H2 e testes Kernel de integração para métodos/AJAX permanecem sem execução porque `SIMPLETEST_DB` não está configurado. O usuário de teste não tem grants de Green Stash, portanto a captura visual não homologa escrita na Loja. Não declarar homologação funcional integral antes de validar essas permissões e fluxos.


### OCS-F1/F5–F7/F9 — campos e botões Fidelity (2026-10-30)

O tema agora traduz o tratamento de Form API do OpenCulturas 3.1.5 em tokens e
seletores restritos a `.studio__main form` e `.aculta-profile-editor form`.
Firefox ESR visível passou 42/42 checks em 320/390/768/1440px, claro/escuro,
com botão padrão, primário, reset e destrutivo, foco visível e isolamento do
formulário fora do Studio. Stylelint e Design Foundations passaram. Capturas
320px foram inspecionadas; artefatos estão em
`/home/piradopirata/aculta-suite-out/studio-form-fidelity-final2-20261030/`.
Não valida Drupal/Form API real, AJAX/no-JS, ACL nem fluxos dos owners; F5–F7/F9
continuam parciais e não há gate F11.

### Reteste OCS-F1/F5–F7/F9 — alvos de formulário (2026-10-30)

Campos e botões dentro de `.studio__main form` e `.aculta-profile-editor form`
agora têm altura mínima de 44px. A mesma fixture Firefox ESR passou 50/50 em
claro/escuro e 320/390/768/1440px; o estilo continua isolado de formulários
externos ao Studio. Stylelint passou. Evidências em
`/home/piradopirata/aculta-suite-out/studio-form-touch-target-20261030/`.
Permanece validação de fixture CSS/markup, sem homologar Drupal/Form API, owners,
AJAX/no-JS, ACL ou gates OCS-F11/THEME-MF3.

### OCS-F6 — apresentação candidata do dashboard GanjaLab (2026-10-30)

A área de aprendizagem real em `/ganjalab` usa o dashboard do estudante e os
SDCs existentes (`context-header`, `card`, `content-grid`, `empty-state`). A
camada do tema agora estende tokens, geometria do container, headings, cards e
estado vazio somente a `body.path-ganjalab .aculta-main`; a navegação da Conta,
Institution Bar e Domain Header ficam fora do escopo Fidelity. Nenhum controller,
serviço LMS, matrícula, dado ou regra de acesso foi alterado.

Firefox ESR visível capturou a conta sintética autorizada em nove viewports
(320, 360, 390, 599, 600, 768, 1024, 1280 e 1440 px), com CSS candidata injetada
no navegador e sem overflow horizontal. As imagens foram revisadas. A amostra
não cobre zoom, WCAG, ACL entre estudantes, aulas/turmas com dados,
no-JS/AJAX ou regressão dos outros purposes; F6 permanece parcial. O coletor
agora aguarda Montserrat e registra os estados de cada `FontFace`; Firefox
confirmou a fonte carregada em 320/390/768/1440 px. A medição encontrou Inter
no estado vazio nas larguras 768/1440 px, e a regra Fidelity foi corrigida. Um
segundo reteste confirmou Montserrat no estado vazio em 320/390/768/1440 px,
sem overflow. Reteste escuro forçado no documento confirmou tokens de texto e
superfície em 320/1440 px. Dez invariantes (fonte, headings, dois estados
vazios, componentes, overflow e isolamento do shell) passaram em 320/390/768/
1440 px no modo claro. Onze checks passaram em 320/1440 px no modo escuro,
incluindo o token de texto escuro. Isso não é auditoria de contraste WCAG.

O Runtime observado no HEAD `2493d7535` serve `/ganjalab`, mas a rota oficial
WE SATIVA `/studio/we-sativa` retorna documento vazio: esse Runtime ainda
registra `/wesativa`. É divergência de versão/roteamento fora do escopo CSS;
não se altera roteamento nem se declara F7/OCS-F11 homologado com essa
superfície incompleta. Evidências e procedimento reproduzível estão em
[`tests/browser/aculta-studio/README.md`](../../../../../../../tests/browser/aculta-studio/README.md)
e em `$SUITE_OUT/ganjalab-fidelity-candidate-20261030`.

### OCS-F7 — consumer Wiki WE SATIVA, estilo Fidelity candidato (2026-10-30)

Reutilizado o wrapper real `.we-sativa__workspace` e os SDCs atuais
`context-header`, `content-section`, `context-tabs`, `content-grid`, `card` e
`empty-state`; sem alteração dos serviços Wiki nem criação de outro contrato.
A folha escopada traz heading e seções tipográficas, superfícies/cartões e
estados vazios Fidelity inspirados nos tokens já auditados do OpenCulturas Next
3.1.5. O tarball de referência foi novamente baixado de Drupal.org e validado
pelo SHA-256 `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`;
nenhum CSS, Twig, ícone, imagem ou fonte upstream foi copiado.

Firefox ESR visível, CSS candidata e conta sintética autorizada: path legado
`/wesativa`, 320/1440px, 14/14 invariantes em cada largura; Montserrat carregada,
duas seções e estados vazios, filtros com `aria-current` único, wrapping, links
46px, zero overflow e shell preservado. Capturas em
`/home/piradopirata/aculta-suite-out/wesativa-legacy-route-final-20261030/`.
O Runtime `2493d7535` ainda registra `/wesativa`; `/studio/we-sativa` segue
sem página. Não homologar o path oficial, autoria com verbetes, estados de
revisão/moderação, grants A/B, no-JS/AJAX, zoom ou WCAG. F7 parcial;
THEME-MF3 e OCS-F11 continuam sem aprovação.

#### OCS-F7 — modo escuro no consumer servido (2026-10-30)

O reteste escuro de `/wesativa` passou 15/15 invariantes em 320/1440px: texto
`rgb(239, 239, 239)`, Montserrat, superfícies, quatro filtros, shell isolado e
zero overflow. Evidência visual e JSON em
`/home/piradopirata/aculta-suite-out/wesativa-dark-legacy-20261030/`. Snapshot
pré-ULI `/home/piradopirata/aculta-suite-out/runtime-before-wesativa-dark-20261030.sql`,
SHA-256 `635b80adb3aaff58be98a539c52aeddaf9f133a14bf3336f4a7bd6e5b707ca33`.
Essa amostra do path legado não fecha F7, ACL editorial, dados reais, WCAG ou
validação do path canônico.
