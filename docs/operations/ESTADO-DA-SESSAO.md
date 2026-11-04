# Estado da sessão

## Handoff — alerta Upgrade Status em ACULTA MCP — 2026-11-03

- Corrigida a propriedade injetada `entityTypeManager` em `McpAccessSettingsForm`: `private readonly` passou a `protected readonly`, para ficar visível ao `DependencySerializationTrait` herdado de `FormBase`.
- `php -l` e `git diff --check` passaram. `upgrade_status:analyze aculta_mcp` reexecutado após `drush cr`: “No known issues found.” `updatedb:status` sem pendências e `config:status` sem diferenças.
- Publicação: commit `97524b75466ad8d0ada75315eb4be086d1c87652` enviado por fast-forward a `origin/dev`; Runtime é o checkout compartilhado, no mesmo HEAD, e já executou a reanálise.

## Handoff — Text With Summary 2.0.x-dev — 2026-11-03

- Adicionado `drupal/text_with_summary:2.0.x-dev@dev` ao Composer; lockado no commit upstream `89c4282cf25259564ce7fa41b9dd70f7fe7f496d` em `web/modules/contrib/text_with_summary`.
- Após `drush cr`, `extension.list.module` resolveu `text_with_summary` para `modules/contrib/text_with_summary`; módulo já habilitado. Nenhuma atualização de banco/configuração pendente.
- `composer validate --no-check-publish` válido, com avisos preexistentes de constraints exatas; `git diff --check` passou.
- Commit `4b176c0e72` enviado a `origin/dev`; Runtime compartilha o checkout e está no mesmo código.

## Handoff — remoção do módulo Search — 2026-11-03

- Desinstalado `search` do Runtime `aculta_runtime`; removido de `core.extension.yml` e excluídos `search.page.user_search` e `search.settings` do Config Sync. `search_api` e `search_api_db` continuam habilitados.
- `composer.json` não tinha requisito direto `drupal/search`; `composer.lock` registra o pacote como substituído por `drupal/core`. Nenhuma remoção Composer era aplicável.
- Validações: `updatedb:status` sem pendências, `config:status` sem diferenças, Search ausente da lista de módulos ativos, `composer validate --no-check-publish` válido com avisos preexistentes de constraints exatas.
- Commit `20d5a2041a175a0cdc1bf88780b8ab918b5d0fbb` enviado por fast-forward a `origin/dev`; Runtime no mesmo checkout/HEAD. Próximo passo: nenhuma ação pendente para Search.

## Handoff — LMS, pagamentos e estado do Drupal 12 — 2026-11-03

- Fluxo de revisão de respostas concluído no código: listagem paginada e
  limitada à turma/estudantes ativos, verificação de curso-turma/matrícula,
  nota validada (0–100), feedback via Comment API e arquivo privado protegido.
  A permissão `administer lms` agora tem serviço de hook registrado.
- Harnesses Kernel ajustados para os tipos Group/Comment e relação LMS atuais,
  sem carregar `lms`/`lms_classes` contrib legados.
- Validações: Unit LMS 234/828 e Kernel LMS 33/996 PASS; Unit Mercado Pago
  258/1.156 e Kernel Checkout Pro + update de cartão 4/152 PASS. PHPCS Drupal,
  PHP lint e `git diff --check` PASS. O Kernel LMS emite 62 depreciações
  upstream de Core/contrib; alerta de diretório `browser_output` não gravável.
- Não houve chamada de pagamento real nem mensagem externa. A homologação com
  comprador sandbox continua pendente do desafio de segurança do provedor.
- Runtime `aculta_runtime`: checkout compartilhado em `5d6f14ad4b`, Drupal
  11.5.0-beta1, PHP 8.5.12. Após a sincronização dos commits, `drush cr` PASS;
  `updatedb:status` sem pendências e `config:status` sem diferenças.
  A migração para D12 ainda não foi feita.
- O dry-run Composer para D12 continua bloqueado por vários contrib. A release
  Bootstrap 8.x-3.45 declara Drupal 12 no `bootstrap.info.yml`, mas o requisito
  no `composer.json` do pacote continua `drupal/core ^10.5 || ^11.2`; Commerce
  3.3.x declara apenas `^10.3 || ^11`. Não trocar a linha de Bootstrap.
- Search foi removido no commit `71a86fa9fa`; o módulo substituto
  `text_with_summary` 2.0.x-dev (`^11.5 || ^12`) foi instalado no commit
  `4b176c0e72`. `core:requirements` deixou de reportar módulos depreciados.
  Restam avisos de Claro,
  traduções disponíveis, 7 mídias duplicadas, filtros de status redundantes
  nas Views Forum/Revista/Conteúdo, cache de desenvolvimento desabilitado,
  Security Review e metadados de atualização do Core ausentes. Conteúdo e
  configuração do Runtime ficaram intactos nesta auditoria.
- Arquivos desta entrega: módulo `web/modules/custom/aculta_portal/modules/aculta_lms/`
  (controllers, formulário, acesso, membership reader, services/routing,
  testes e changelog), metadados/changelog do `aculta_portal` e este handoff.
- Próxima etapa do objetivo: resolver os bloqueios Composer upstream para
  Drupal 12 e continuar tratando os avisos de requisitos sem apagar conteúdo;
  homologação de pagamento real depende do acesso do comprador de teste.

## Handoff — espaçamento Studio e escopo de Próximos eventos, 2026-11-03

- Dashboard Studio recebeu intervalo de 12 px e reset de margens nos títulos internos (`studio.css`); com CSS candidato em Firefox ESR, todas as verificações específicas passaram em 320/390/768/1280 px, grade 1/2 colunas e sem overflow. A suíte ampla teve 126 PASS, 2 FAIL (Montserrat candidata não carregou; verificação de moldura das abas secundárias) e 21 SKIP condicionais; os FAILs foram preservados no relatório externo `$SUITE_OUT/studio-spacing-dev36-20261103/results.json`.
- `block.block.aculta420_aculta_events_upcoming.yml` agora exige purpose `main` e rota `<front>`, mantendo a caixa somente na landing principal e removendo-a do Perfil público no mesmo domínio.
- Versão do tema `0.7.0-dev.36` publicada no commit `4bc2bb508558d0dad1d1e4c7b5f93c642906054c`. Runtime `toca` / Drupal 11.4.10 / `aculta_runtime`: preflight sem updates de banco e somente a configuração do bloco diferente; snapshot pré-import com 270.104.476 bytes, SHA-256 `f1461615bbec2363ab70ee762cac15b5d50115c2c80537fef5272c6500261a0f`; importou somente `block.block.aculta420_aculta_events_upcoming`; `drush cr` PASS, `updatedb:status` sem updates e `config:status` limpo.
- Smoke HTTP anônimo pós-import: landing principal `200`, bloco `.aculta-events-upcoming` presente; perfil público `/u/pessoa-5` `200`, bloco ausente. `extension.list.theme` após reset confirma dev.36. Firefox ESR visível com CSS real do Runtime: 106/106 PASS, 21 SKIP condicionais, 0 FAIL; capturas/JSON em `$SUITE_OUT/studio-spacing-runtime-dev36-20261103/`. A preferência clara da conta foi restaurada pelo runner.
- Captura de configuração/snapshot e teste não alteraram conteúdo do site. Snapshot e respostas HTML ficam fora do Git.

## Handoff — compactação da escala de espaçamento ACULTA420, 2026-11-03

- **Objetivo:** pesquisar padrões compactos e reduzir/padronizar espaçamentos globais e nas telas Studio sem alterar paleta/cabeçalho.
- **Decisão:** escala semântica de 4/8 px em tokens; seções agora 24–40 px; conteúdo principal usa 16 px na base e 16–24 px em desktop. Studio usa gutters/row gaps menores em todos os propósitos que compartilham o token; Conta reduz os intervalos da navegação e mantém links com 44 px. Perfil e Conta Portal foram alinhados aos tokens. O conteúdo continua progressivo por viewport.
- **Pesquisa:** Carbon, GOV.UK Design System e USWDS; links e resumo estão em `web/themes/custom/aculta420/CHANGELOG.md` e em [Spacing](https://www.carbondesignsystem.com/building-blocks/foundations/spacing/overview), [GOV.UK](https://design-system.service.gov.uk/styles/spacing/) e [USWDS](https://designsystem.digital.gov/design-tokens/spacing-units/).
- **Arquivos do ajuste:** `tokens.css`, `layout.css`, `studio.css`, `portal-account.css`, `components/patterns/profile-editor/profile-editor.css`, `content.css`, `docs/design-system.md`, `CHANGELOG.md`, `docs/roadmap.md`, `aculta420.info.yml` (dev.35), e `tests/browser/aculta-studio/visual.mjs` (dev.34, contrato visual responsivo).
- **Validações:** Stylelint PASS; Foundation 354 PASS; Design Foundations 2462 PASS; token-only claro/escuro PASS; SDC schema 764 PASS; `git diff --check` PASS. Firefox ESR visível após dev.35: Marley/Dashboard/Perfil 62/62 checks aplicáveis PASS, 21 SKIP; Conta 102/102 aplicáveis PASS, 21 SKIP e zero falhas, com colunas 2/1 responsivas, AJAX/histórico, retry, timeout, Form API e sessão expirada. Capturas/JSON de todas as telas e matriz Conta: `$SUITE_OUT/studio-spacing-dev35-20261103/`. Medição de caixa anterior: `$SUITE_OUT/studio-spacing-account-dev34-20261103/`. Preferência clara original restaurada.
- **Pesquisa visual e limite:** capturas reais pós-mudança em 1440/320px conferidas; não foi feita uma nova comparação pareada com a prancha nesta rodada. Os passos pendentes de senha e persistência do avatar permanecem conforme o handoff Studio logo abaixo.
- **Git/Runtime:** espaçamento dev.32 enviado em `70c677550fdba05255da9db3d73f78d756508a9e`; correção de harness e retestes em `4c139219d29caaeb594b675097e9ed0ecc4be1d0`; ajuste do título global dev.35 em `0ed970d168688661523d16d304f9264f7ea75dce`; documentação desses resultados em `f6f95a81eda0d99c305d5875fadfe0d61ad9aea4`. `origin/dev` avançou paralelamente até `0965aa5123733b13aaab1c3e72b6ec605f6f9844` (LMS), sem conflito. Runtime é o mesmo checkout (`toca`, Drupal 11.4.10, `aculta_runtime`), `updatedb:status` sem updates, `config:status` sem diferenças, `drush cr` PASS; após `ExtensionList::reset()` confirmou ACULTA420 dev.35. Sem snapshot/update/import.
- **Retomar lendo:** tokens/layout/componentes acima, `web/themes/custom/aculta420/docs/versioning.md`, e os handoffs Studio nesta página.

## Handoff — estabilidade de UID na exportação ICS, 2026-11-03

- **Objetivo:** manter o UID ICS estável quando atributos editáveis de uma entidade de calendário mudam, sem alterar o Runtime nem publicar.
- **Implementação local:** `IcsEventBuilder` agora deriva o UID somente da chave estável e de `$uidDomain`; início, título, local, recorrência e reagendamento não participam do hash. O adaptador LMS deixou de usar título/início: prazos usam o ID da relação e eventos usam o ID de `aculta_event_date`, preservando o UID da série.
- **Arquivos da entrega:** `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Ics/IcsEventBuilder.php`; seu teste Unit; `web/modules/custom/aculta_portal/modules/aculta_lms_agenda/src/AgendaEntriesProvider.php`; `Calendar/LmsCalendarItemProvider.php`; `Calendar/PersonalCalendarSeries.php`; teste Unit de `PersonalCalendarSeries`; este handoff.
- **Validações:** PHP lint dos cinco arquivos PASS; PHPCS focal PASS; `git diff --check` PASS; Calendar + LMS Agenda Unit: 106 testes/317 asserções PASS; foco ICS/LMS: 14 testes/41 asserções PASS. PHPUnit exibiu apenas o aviso preexistente de diretório de saída de browser não gravável.
- **Git:** branch `dev`, HEAD inicial `cf286b7258ad2fa5c846eceb2631902004f812ab`; arquivo não relacionado `tests/browser/aculta-studio/avatar-write.mjs` permaneceu untracked e fora do staging. Commit desta entrega ainda pendente; não houve push, merge, PR ou alteração do Runtime.
- **Retomar lendo:** `IcsEventBuilder.php`, `IcsEventBuilderTest.php`, `AgendaEntriesProvider.php`, `PersonalCalendarSeries.php`, `git status --short --branch`.

## Handoff — complementos de validação Studio, 2026-11-03

- **Base:** `dev`, HEAD inicial `f5ae2bf16d7b1f6da7a7a656e2f4e2f929b2a602`, igual a `origin/dev`; trabalho LMS paralelo `ad4b42e01e` já estava publicado e foi preservado.
- **Ajuste local:** harness `tests/browser/aculta-studio/orca-speech.mjs` passou a usar `BASE`, `drush uli --no-browser`, validar labels/headings realmente presentes, exigir artefatos externos via `$SUITE_OUT`, proteger diretório/arquivos e evitar interpolação insegura do caminho em Python. `node --check` passou; Orca + Firefox ESR passou 5/5 em Marley, com três abas disponíveis, heading `Meu trabalho` e áudio não silencioso. Relatórios/capturas permanecem fora do repositório.
- **Validações adicionais:** `teste.comum` atualmente tem papel `content_editor`, com permissão real de moderação; uma conta sintética sem esse grant não recebe a aba. Um PNG sintético chegou ao formulário de corte de avatar; não foi aplicado/salvo e o estado de avatar permaneceu idêntico. Nenhuma troca de senha ocorreu: a conta descartável exige aceite individual dos Termos, não realizado; a fixture comum proíbe troca sem decisão. Credencial sintética permanece em arquivo externo modo 0600.
- **Referências:** cotejo real feito com `docs/portal/studio-visual/referencias/image(20261103-123227).png` e capturas Firefox existentes para Dashboard, Conta, Perfil e Marley. Cinco PNGs passaram `file` e `identify`; ZIP ausente, então `unzip -t` não executado. Não se declara igualdade pixel a pixel.
- **Documentos atualizados:** auditoria AJAX, plano visual geral, P1 pessoal/editorial, roadmap Studio, README do harness e `docs/operations/TESTE-COMUM.md` receberam estado/limites atuais. Senha e persistência final de avatar continuam pendentes; teste Orca cobre Marley somente.
- **Publicação/Runtime:** commit `b7d0207336633f68532d565ab49a30e68d5a3585` enviado por fast-forward a `origin/dev`; `git ls-remote` confirmou o mesmo hash. O checkout servido está nesse HEAD, Drupal 11.4.10/banco `aculta_runtime`; `updatedb:status` sem updates e `config:status` sem diferenças. Não houve alteração de grants, dados das contas existentes, Termos ou configuração; a conta sintética e o upload temporário registrados acima permanecem no Runtime.
- **Retomar lendo:** os cinco documentos acima; `tests/browser/aculta-studio/orca-speech.mjs`; auditoria de Conta Studio.

## Handoff — revisão visual Studio, 2026-11-03

### Reteste da navegação em caixas da Conta — commit `963ab7c6b10a375819e9bb0651bbb1a8e43540da`

- Cotejo da captura real apontou que `studio-account-contextual-nav` ainda herdava `nav-tabs`/`studio__subtabs` do Marley, mantendo itens em linha e sem caixas separadas. ACULTA420 passou a usar lista vertical própria e painéis de navegação/conteúdo com tokens de superfície, borda, raio e sombra; `<details>`, Form API e owners foram preservados. Cabeçalho global e suas cores não mudaram.
- Push fast-forward confirmado em `origin/dev`; Runtime `toca`/`aculta_runtime` sincronizado no mesmo HEAD. `drush cr` PASS; tema `0.7.0-dev.31`; `updatedb:status` sem updates; `config:status` limpo.
- Firefox ESR visível: 97/97 PASS aplicáveis, 22 SKIP; menu vertical/ativo e caixas computadas verificados, nove rotas Conta HTTP 200, navegação AJAX, histórico, retry, timeout, Form API inválido/válido, teclado, claro/escuro e 320–1440px. No-JS 14/14 PASS. Capturas: `$SUITE_OUT/studio-dashboard-final-20261103/account-panels-dev31-final/` (Dashboard, Conta 1280/320, Perfil, Marley); no-JS: `$SUITE_OUT/studio-dashboard-final-20261103/final-nojs-retry/`.
- Tema Unit 50/358, Design Foundations 2441, SDC schema 764, Portal Drupal 11+ 1202 e status index 53 PASS; Stylelint, PHP lint e `node --check` PASS. `validate-portal-ui-docs.php` mantém 14 falhas preexistentes em tokens/atributos LMS/Eventos/uploader; fronteira mantém 3 classes preexistentes de `aculta_paths`.
- Dados e Endereço foram submetidos validamente via Firefox uma vez cada com os valores já exibidos na conta sintética; mensagem de sucesso, zero erros e valores iguais antes/depois. Hash dos quatro campos de perfil comparado a snapshot externo 0600 e inalterado. Upload/avatar e senha não foram submetidos. Módulos opcionais, conteúdo editorial da conta e leitor de tela seguem DEFERRED. Cinco PNGs atuais foram reconhecidos por `file`/`identify`, incluindo a prancha 1536×1024 inspecionada; ZIP original ausente, então `unzip -t` não foi executado.
- Retomar lendo: `docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md`, `docs/portal/ACULTA-STUDIO-VISUAL-PLANNING.md`, `docs/portal/STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md`, `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`.

- Branch de trabalho `dev`. Commits de código desta continuação: `1fb7231447` corrige ações do Perfil comprimidas; `39c6f4035c` alinha “Criar” ao título “Meu trabalho”. O Runtime de aplicação está no código do segundo commit. O commit paralelo LMS `ad4b42e01e` já era ancestral de `origin/dev` e estava publicado; não foi republicado nem reescrito.
- Cinco referências PNG válidas permanecem em `docs/portal/studio-visual/referencias/`; `image(20261103-123227).png` (1536×1024) foi inspecionada. O ZIP não estava disponível. A imagem desktop/mobile não está no remoto atual e não foi restaurada.
- Composição aplicada: Dashboard com identidade, perfil público condicionado ao owner, uma entrada de Conta e experiências reais; Conta em Grid com AJAX próprio; Perfil separado em duas áreas e ações legíveis; Marley com abas owner, botão Criar junto ao título e listagem em scroll interno. Não houve mudança de cores do cabeçalho nem novo SDC; `aculta420:card` (`studio_fidelity`) e `profile-editor`/`profile-identity` foram reutilizados.
- Firefox ESR visível no Runtime: `post-push` 94/94 verificações aplicáveis PASS (21 SKIP, 0 falhas); após Marley dev.44/tema dev.30, `marley-header-dev30-retest` 97/97 PASS (21 SKIP, 0 falhas). Nove rotas Conta HTTP 200; no-JS 4/4; navegação AJAX/histórico, falhas 403/404/retry, timeout/resposta tardia, sessão expirada, Form API inválido/válido, Dashboard, Perfil, Marley, 320–1440px e zoom 200/400% verificados. Capturas externas em `$SUITE_OUT/studio-dashboard-final-20261103/post-push/` e `$SUITE_OUT/studio-dashboard-final-20261103/marley-header-dev30-retest/`.
- PHPUnit Studio + tema: 114 testes/989 assertions PASS, incluindo 65/634 do Studio e 49/355 do tema. PHP lint/PHPCS focais, Design Foundations 2441, SDC schemas 764, SDC asset audit 0 findings, Portal Drupal 11+ 1202 e status index 53 passaram. `validate-module-theme-boundary.php` ainda acusa apenas três classes preexistentes em `aculta_paths` (`button`, `button--small`, `superficies-dns`).
- Runtime `toca`, Drupal 11.4.10, banco `aculta_runtime`: atualizado por fast-forward ao hash acima; `drush cr` PASS, sem DB updates e sem config drift; `aculta_studio=0.1.0-dev.44`, `aculta420=0.7.0-dev.30`. A lista de extensões precisou de `reset()` para descartar metadado de tema antigo; nova execução Drush informa `.30`.
- DEFERRED: envios válidos de dados pessoais/endereço, upload/avatar, alteração de senha, ausência de módulos opcionais, conteúdo editorial privado aguardando moderação e leitor de tela. Preferência sintética foi submetida via Form API AJAX e restaurada pelo harness. A conta comum não tinha conteúdo próprio, então as ações de edição foram SKIP; não houve escrita pessoal/endereço.
- O seletor do harness do cabeçalho usa `section-action`, conforme o contrato real. O ajuste e a atualização documental desta rodada são uma entrega de teste/documentação sem alteração funcional adicional.

## Handoff — implementação Conta Studio e Dashboard, 2026-11-03

### Reteste adicional no-JS — 2026-11-03

- Runtime confirmado em `toca`, `aculta_runtime`, tema ACULTA420; `updatedb:status` sem pendências e `config:status` limpo. Nenhuma alteração de conteúdo foi feita.
- Firefox ESR visível com a conta sintética `teste.comum`: `tests/browser/aculta-studio/nojs.mjs` passou 14/14. Cobriu autenticação temporária, shell/Form API em Dados, link HTML nativo para Endereço, voltar e as nove rotas diretas com região de conteúdo e item ativo. Relatório em `$SUITE_OUT/studio-audit-20261103/nojs-aculta-final2/nojs-results.json`.
- Seis PNGs do commit `d732e95c` em `references/studio-visual/` estão truncados/corrompidos (IDAT incompleto/CRC inválido); comparação pareada continua pendente de reenvio íntegro. Sem cópia de conteúdo fictício.
- O harness no-JS agora recebe o redirect `/conta/dados` no ULI, confirma a sessão antes das rotas privadas e não depende de classes de layout do tema. Atualizados auditoria Conta AJAX, plano visual, plano P1 e roadmap.
- `config:status` anteriormente mostrou Olivero durante validação paralela; ao retomar, Runtime já estava restaurado em ACULTA420 e configuração limpa. O estado paralelo LMS foi preservado; esta entrega será publicada separadamente sobre o HEAD atual de `origin/dev`.

- **Objetivo:** concluir Dashboard, shell AJAX de Conta, preservar Perfil público independente e ajustar a hierarquia editorial Marley, conforme planos e auditoria.
- **Base/entrega Studio:** trabalho iniciado em `50050dc4dbf07a46dadb861fba45cce32ec194b4`; `8ccbcb0618` e o reparo autowired `bf731e8789` publicados em `origin/dev`. O pedido de reset foi cumprido preservando o patch fora do repositório.
- **Implementação local:** biblioteca Conta própria com `Drupal.Ajax`, histórico, metadados de título/breadcrumb, foco, estado e falha/retry; detalhes nativos para menu móvel; wrappers Grid próprios. Reteste corrigiu a construção `Drupal.Ajax(null, null, ...)` para não associar listener de clique Core duplicado ao handler delegado. Dashboard consulta providers e acesso de destino em `DomainPurposeManager`; identidade usa resolver público e falha fechada. Culturas registrado pelo owner Eventos. Marley remove destino Studio quebrado de “Criar” e aponta para a rota owner; moderação permanece gated.
- **Verificações:** números da validação atual estão no item de retomada abaixo (Unit Studio 72/659 e Firefox comum 92/92 aplicáveis, 22 SKIP). Gates Portal Drupal 11+ 1202, slugs 410, admin routes 172 e status index 53 passaram; `node --check`, PHP lint e PHPCS focal passaram. Os números anteriores deste handoff são históricos.
- **Runtime:** hostname `toca`, Drupal 11.4.10, PHP 8.4.27, banco `aculta_runtime`. Pós-push: HEAD local e `origin/dev` em `27e8d0626353bdedfff2d962ac1b27b43b76af6a`; mesmo checkout serve o Runtime. `updatedb:status` sem updates, `config:status` limpo, módulo em `0.1.0-dev.41`; `drush cr` PASS após ajuste do JS. Sem import nem escrita de dados nesta retomada.
- **Entrega desta retomada:** corrigido o listener perdido após `replaceWith` movendo a delegação para `document`, única e guardada pelo behavior; `Drupal.Ajax` não recebe alvo/evento Core e não acumula handlers. Ampliado `tests/browser/aculta-studio/visual.mjs` e adicionado `nojs.mjs`. No Firefox ESR visível, Conta fechou 92/92 verificações aplicáveis PASS, 22 SKIP; rotas Conta 9/9 HTTP 200, AJAX/histórico/mobile, 403/404+retry, timeout/resposta tardia, sessão expirada, Form API inválido/válido, Dashboard/Perfil, Marley, 320–1440px, zoom 200/400%; no-JS 4/4. Unit Studio 72/659 PASS; Gates Drupal 11+ 1202 PASS, slugs 410 PASS, admin routes 41 arquivos/170 rotas PASS e índice 53 PASS. Atualizados auditoria, planos, roadmap, CHANGELOG, versão `0.1.0-dev.41`, status e README. A prancha original não estava disponível localmente para cotejo; uploads/senha/escrita pessoal, opcionais ausentes, conteúdo pendente de moderação e screen reader seguem DEFERRED. O trabalho LMS paralelo foi publicado em `8fccd52da2`; não há alterações LMS pendentes no status atual.
- **Git:** commit de código, harness e planos `27e8d0626353bdedfff2d962ac1b27b43b76af6a` enviado por fast-forward a `origin/dev`. O commit metadata `a29db64627` e o registro LMS `139aea1f12` ocorreram em paralelo antes dele; nenhum arquivo LMS entrou no commit Studio. Worktree limpo após a publicação.
- **Pendências de entrega:** nenhuma. Permanecem somente os itens DEFERRED funcionais/visuais enumerados abaixo e na auditoria.
- **Pendências:** módulos opcionais ausentes no Runtime, upload/avatar/senha/anexos, escritas pessoais/endereço, conteúdo aguardando moderação, leitor de tela e comparação visual integral com a prancha; não marcar homologação integral.
- **Leitura para retomar:** `docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md`; `docs/portal/ACULTA-STUDIO-VISUAL-PLANNING.md`; `docs/portal/STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md`; `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`; `git status --short --branch`.

## Handoff — medição de carga MariaDB para xAPI, 2026-11-03

- **Objetivo:** medir carga sintética de escrita para `aculta_lms_xapi_statement` em banco separado no Homelab, sem escrita em `aculta_runtime`.
- **Entrega:** `docs/operations/CARGA-MARIADB-XAPI.md`; commit `8efb3af9b600a25217c5cf5c3aa45a06eae8ce18` publicado em `origin/dev`.
- **Medição:** 5.000 linhas em 50 lotes, 6.389 linhas/s, p95 SQL individual 0,118 ms, p95 transação de lote 14,843 ms; tablespace 81.920 → 22.024.192 bytes. Limites de 5.000 linhas, 64 MiB e 30 s; encerrou pelo limite de linhas após 0,783 s.
- **Descarte e Runtime:** `aculta_carga` removido, verificação de zero schema/tabelas. Contagens xAPI, progresso, aula, atividade e resposta no `aculta_runtime` permaneceram em zero. A soma `information_schema` variou +16.384 bytes durante o intervalo; causa não atribuída. Nenhum comando de escrita foi direcionado ao Runtime.
- **Verificação:** carga feita por conexão local socket Unix, só dados sintéticos; sem usuário de teste, senha ou segredo criado. Sem importação de configuração. `git diff --check` passou; nenhum teste de aplicação foi executado.
- **Pendências:** medição de persistência, não de validação, HTTP ou capacidade production-like. Para fechar ADR-041, repetir em ambiente production-like representativo antes de ativar conteúdo xAPI em produção.
- **Retomar lendo:** `docs/operations/CARGA-MARIADB-XAPI.md`; `docs/decisions/ADR-041-xapi-2-lrs-proprio.md`; este handoff.

## Handoff anterior — Conta Studio AJAX, 2026-11-03

- Objetivo: aprofundar comparação Conta/legado e preparar execução da correção.
- Entrega: docs/portal/STUDIO-CONTA-AJAX-AUDITORIA.md, integração nos planos ACULTA-STUDIO-VISUAL-PLANNING.md e STUDIO-VISUAL-P1-PESSOAL-EDITORIAL.md, e no roadmap do aculta_studio.
- Evidências: rotas distintas; AccountShellBuilder anexa AJAX legado; StudioAccountSectionController emite links comuns; JS Studio editorial limitado por seletores e histórico Marley; PortalUiHooks mapeia conta para layout flex; riscos de histórico mobile/timeout legados documentados.
- Verificação: leitura de fontes e revisão documental; nenhum teste de aplicação executado. Código funcional não alterado. Publicação via API GitHub sobre dev em commit único; sem checkout local.
- Runtime: sem acesso nesta sessão; sincronização e homologação DEFERRED. Próximo agente deve cumprir preflight operacional antes de sincronizar.
- Retomar: auditoria C0-C5 e P1.2; registrar DOM/rede/estilos computados antes de corrigir. Handoffs de outras frentes abaixo preservados.


## Handoff mais recente — ADR-042 P5 concluído, 2026-11-03

- **Objetivo:** corte P5 do LMS contrib concluído. P6 e reescrita de histórico não executados.
- **Commits publicados em `origin/dev`:** `baeb4339a` prepara configuração e campo do Fórum; `28f8bf887` torna seguro o update quando falta tabela de revisões; `d328c658b` remove `drupal/lms` do Composer/lock e conclui P5. Versões: `aculta_lms` 0.1.0-dev.172; `aculta_forum_lms` 0.1.0-dev.33.
- **Runtime:** checkout `dev` em `origin/dev`, Drupal 11.4.10, PHP 8.4.27, banco `aculta_runtime`. Snapshot anterior ao P5: SHA-256 `37947c05fe3d523fbce0ff42c78f61f5d90f56a2a566de7c06f1869774bcd595`. Updates 10012 (LMS) e 10001 (Fórum) aplicados; cache rebuild e Composer install passaram; `updatedb:status` sem pendências. Módulos contrib desinstalados na ordem `lms_answer_comments`, `lms_answer_plugins`, `lms_classes`, `lms`; `lms_file_upload` não era pacote Composer e suas cópias manuais saíram dos caminhos de descoberta.
- **Descarte explicitamente autorizado em P5:** 8 aulas, 17 atividades, 3 respostas, 3 status de aula, 2 status de curso e 2 relacionamentos de turma `[TESTE P1]`; nenhum conteúdo migrado. Os cinco Groups permaneceram. A relação de turma ACULTA continua configurada.
- **Verificações:** LMS Unit 218/758 PASS; Fórum Unit 33/329 PASS; `AcultaLmsInstallTest` 6/172 PASS (35 depreciações); Kernel focal Fórum 1/25 PASS (38 depreciações); Portal 1.172 checks PASS; public-slugs 410 rotas PASS; composer validate PASS com avisos preexistentes de constraints fixas; busca literal por imports `Drupal\\lms` em `web/modules/custom` retorna zero. `composer show drupal/lms` confirma pacote ausente.
- **Configuração Runtime:** `config:status` foi lido antes/depois e não houve importação. Permanecem diferenças de `aculta_portal.superficies`, config de Groups e tópicos, `core.extension`, papel `lms_teacher` e Views de cursos. Detalhes e causa da tentativa inicial falha/retry do update 10001 estão no CHANGELOG do Fórum.
- **Retomar lendo:** `docs/decisions/ADR-042-remocao-dependencias-lms-contrib.md`; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `web/modules/custom/aculta_portal/modules/aculta_lms/CHANGELOG.md`; `docs/operations/status/aculta_lms.yml`; `git status --short --branch`.

## Handoff anterior — continuação ADR-042 P5, 2026-11-03

- **Tarefa:** continuar a troca de consumidores LMS para entidades próprias e concluir P4/P5 quando não houver perda funcional sem contrato aprovado. Usuário autorizou commit/push e intervenção em outros módulos/tema. P6 e reescrita de histórico continuam proibidos.
- **Commits publicados:** `.159` `9c998f1ec` adiciona payload JSON imutável por tentativa em `aculta_lms_answer`; `.158` `1b0333f89` migra agenda; `ecd5daf3f`, `afc1689d8`, `381eff801`, `9d6bf2fa3` e `bc99862a9` registram gate, Runtime, handoff, desenho P2 e índice. `.155` `20d56c7f3` (ciclo de certificados usa progresso ACULTA), `.156` `4f7ea82bc` (relação Group de turmas própria), `31915dbe9` (README de status), `.157` `536994ba1` (calculador e configuração ACULTA de permissões) e `fa9a5cf5a` (registro Runtime). `.158` foi publicado em `1b0333f89`; o ajuste do baseline do gate Portal em `ecd5daf3f`. Após novo avanço remoto, ambos foram rebaseados com segurança sobre `e9935d948` e enviados por fast-forward. A agenda usa entidades P2, corrige a negação de acesso entre turmas e corrige a sintaxe inválida herdada em `EventClassAccess.php`.
- **Arquivos `.155`/`.156`:** `tests/src/Kernel/CertificateLifecycleTest.php`; `src/Plugin/Group/Relation/CourseClassRelation.php`; `src/Hook/CourseClassRelationHooks.php`; `tests/src/Kernel/ClassRosterVisibilityTest.php`.
- **Arquivos `.157` preparados:** `src/Access/ClassPermissionCalculator.php`; `aculta_lms.services.yml`, `.info.yml`, `.install`; `config/install/aculta_lms.settings.yml`, `config/schema/aculta_lms.settings.schema.yml`; `config/sync/aculta_lms.settings.yml`; Kernels de instalação/permissões; CHANGELOG, status e README.
- **Arquivos `.158` publicados:** `aculta_lms_agenda/src/AgendaEntriesProvider.php`, `EventClassAccess.php`, `Hook/EventAccessHooks.php`, services/module, teste Kernel e docs; `config/sync/group.relationship_type.lms_class-lms_class_activity_due.yml` remove dependência do módulo `lms`; versões `aculta_lms` .158 e agenda .2, status e changelogs.
- **Verificações `.160`–`.164` e purpose da rota:** commits `.160` `8905e21bc`, `.161` `e62791b04`, `.162` `42c9684af`, `.163` `637e0ed1f`; a rota ACULTA `/resposta/{answer}/arquivo` recebeu purpose `courses` em `b0bd84dc9`, enviado por fast-forward. O harness Kernel de `AttemptArchiveLifecycleTest` foi corrigido para instalar `comment_body` antes de `aculta_lms`; Kernel conjunto `AcultaLmsInstallTest` + `AttemptArchiveLifecycleTest` 16/603 PASS (57 depreciações), Unit LMS 236/829 PASS (1 depreciação). Comentário Core ACULTA fechado por padrão, AnswerPayload valida referência managed file, e a rota própria exige owner matriculado ou grade students, tentativa enviada/avaliada e arquivo permanente `private://`. Unit focal de autorização 3/30 PASS; Portal `DomainRouteSubscriberLocalizedPathsTest` 1/30 PASS e gate 1.172 checks PASS. Runtime `10009` após snapshot SHA-256 `d44704f1…84bcfe9a4`; `10010` após `a382cb02…8400b0bf`; `updatedb:status` sem updates, `drush cr` PASS e rota confirmou `/resposta/{answer}/arquivo | courses`. Config Sync mantém somente diferenças anteriores em `aculta_portal.superficies` e `core.extension`, sem import global.
- **Verificações após rebase:** Unit LMS 228 testes/786 assertions PASS (1 depreciação PHPUnit); Kernel ClassRoster 3/66 PASS (41 depreciações Core/contrib); Kernel AcultaLmsInstall 6/143 PASS (28 depreciações); PHPCS focal PASS; gate Portal 1.172 checks PASS; índice 49 componentes PASS; `git diff --check` PASS.
- **Verificações `.158`:** Agenda Kernel 4/168 PASS (94 depreciações Core/contrib); Unit agenda 8/18 PASS; PHPCS dos arquivos alterados PASS; gate Portal 1.172 PASS após atualizar baseline para o subscriber registrado de superfícies (interruptor desligado); `validate-public-slugs.php` 423 rotas/0 violações PASS. Inventário atual: 14 arquivos PHP ainda importam `Drupal\lms`, nenhum no submódulo agenda; Unit LMS reexecutada 228/786 PASS (1 depreciação PHPUnit). O teste Kernel segue com LMS habilitado por dependência no manifesto de `aculta_lms`; a fixture já não importa entidades contrib nem instala seus schemas.
- **Runtime:** hostname `toca`, Drupal 11.4.10, DB `aculta_runtime`. A entrega .157 já foi atualizada no Runtime: não há updates pendentes e o cache/config estão limpos. Após publicar .158, preflight mostrou `updatedb:status` sem updates e duas diferenças de Config Sync: `aculta_portal.superficies` adiciona `leitura_ativa: false`, e o relacionamento de prazo remove a dependência `lms`. Snapshot anterior ao import: SHA-256 `d1bd180e6883f5efccd12ad9a65a094cb7f22dc681f6b09595e3c0c06ec72848`. Import das duas configs e `drush cr` concluídos; status posterior sem updates e sem diferenças. Snapshot anterior ao update 10007: 79.373.653 bytes, SHA-256 `624bb5573a378f556600fabdc4ab2d679f8730990973acf661ecdfacc6c6e424`. Update 10007 concluiu; cache rebuild PASS; pós-verificação sem updates e sem diferenças de config. Para `.159`, snapshot anterior ao update 10008 SHA-256 `8feef18cc9fd459b6cb6c92a47d1b6db67d4c1b5440323edd386b888ee10ca3e`; update e cache rebuild PASS, status final limpo.
- **Nota operacional:** `config:import --diff` mostrou a diferença e também executou a importação. Criou `aculta_lms.settings` com `view group_node:aculta_lms_topic entity` → `grade students` e `aculta_portal.superficies` com dez superfícies ativas em modo subdomínio, trazida pelo upstream `aculta_superficies`. Nenhum config existente foi alterado ou removido; estados finais estão limpos. A segunda criação não foi antecipadamente reportada e fica registrada como desvio operacional.
- **Inventário que bloqueia P4/P5 sem mudança de contrato:** `AttemptArchiveManager` e hooks arquivam texto/comentários/anexos e encadeiam estados de tentativa ausentes em `CourseAnswer`; upload/download e autorização (`Plugin/ActivityAnswer/FileUpload.php`, `AnswerFileDownloadController`, `UploadedAnswerAccessCheck`) dependem do payload e uso de arquivo contrib; revisão dissertativa (`FreeTextReviewFeedback`) está fora do escopo de P3; `CourseLinkHooks` depende do hook, rota/componente de início e configurações de revisita/avaliação do LMS, sem rota ACULTA equivalente. O Kernel da agenda agora cria LessonEntity/ActivityEntity e não importa Drupal\lms. O inventário global tem 14 arquivos PHP com imports restantes. A relação `lms_classes` já tem plugin ACULTA, mas o P4 sem o contrib ainda não foi verificado.
- **Limite:** responsável aprovou em 2026-11-03 payload JSON, tentativas imutáveis, comentários Core vinculados e referências a arquivos seguros, mantendo `CourseAnswer` como projeção de estado/nota. `.163` adiciona rota própria de download, mantendo a rota contrib enquanto os plugins forem portados. Ainda faltam revisão, UI/rota de início, inventário de 14 imports PHP, revisão de 71 YAML de Config Sync e dependência `lms:lms` no manifesto `aculta_lms_h5p`. `ActivityEntity` não armazena enunciado/gabarito; aguarda decisão sobre `activity_data` JSON para portar os plugins objetivos. P4/P5 incompletos; P6 e reescrita de histórico não executados.
- **Runtime desta continuação:** hostname `toca`, banco `aculta_runtime`, Drush 13.8.0. Após `10010`, `updatedb:status` sem pendências; `config:status` mostra somente `aculta_portal.superficies` (`leitura_ativa` true ativo/false sync) e `core.extension` (`aculta_superficies` ativo/ausente sync). A importação global permanece fora desta entrega porque desativaria superfícies. Snapshots: antes de `10009`, SHA-256 `d44704f1c612c95b2854bdfb5401196d9293a896dd1dfde33d49c5684bcfe9a4`; antes de `10010`, SHA-256 `a382cb025e0593ecaee741e1f285fb509995fb2b467990defa240fa68400b0bf`.
- **Próximos passos:** portar os consumidores restantes; após decisão sobre `activity_data`, ligar os plugins objetivos e remover módulos contrib na ordem P5. Responsável autorizou desinstalação no Runtime durante P5 mesmo com descarte de dados de teste; não executar P6 nem remover dados fora dessa ordem.
- **Retomar lendo:** ADR-042; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `src/Plugin/Group/Relation/CourseClassRelation.php`; `src/Entity/AnswerEntity.php`; `src/Plugin/ActivityAnswer/`; `src/AttemptArchiveManager.php`; `src/Hook/CourseLinkHooks.php`; `aculta_lms_agenda/tests/src/Kernel/AgendaEntriesProviderKernelTest.php`; `rg -l 'Drupal\\lms' web/modules/custom --glob '*.php'`; `git status --short --branch`.

## Handoff anterior — superfícies de domínio (planejamento), 2026-11-02

- **Tarefa:** planejar o controle de superfícies do aculta_portal (modo subdomínio ou diretório, slug e ativar/desativar), mantendo o módulo Domain para hosts e ambientes. Autorizado commit e push em `dev`.
- **Commits:** `c5333a1` (planejamento do submódulo e ADR, enviado a `origin/dev`, fast-forward a partir de `93b638a`); commit seguinte desta entrega (ADR-043 movido para `docs/decisions/`, roteiro 3.0, status do submódulo, handoff).
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_superficies/` (info.yml, README, CHANGELOG, `docs/PLANO-FINAL.md`); `docs/decisions/ADR-043-superficies-dominio.md` e anexo; `docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md`; `docs/architecture/rotas-por-purpose-dev-93b638a.csv`; `docs/operations/status/aculta_superficies.yml`.
- **Verificações:** `validate-environment-addresses.php` FAIL preexistente em `aculta_lms/tests/src/Unit/XapiBatchIngestorTest.php:134` (fora desta entrega, não corrigido). `validate-public-slugs.php` e `validate-project-status-index.php` não executados: exigem `vendor/`, ausente nesta sessão.
- **Pendências:**
  1. ~~Corrigir `CANONICAL_STUDIO_PATHS`~~: não é conflito. A retificação do responsável (2026-10-30, `web/modules/custom/aculta_portal/AGENTS.md`) aprova `/studio/we-sativa` e `/studio/green-stash`, e o código está conforme. Pendente: o corpo do ADR-038 ainda cita `/wesativa` e `/greenstash`; corrigir é decisão do responsável.
  2. Regenerar a linha do `aculta_superficies` na tabela de status do README raiz com `php scripts/validate-project-status-index.php --write`, em ambiente com `vendor/`.
  3. ADR-043 aceito pelo responsável em 2026-11-02 como direção de modelo; implementação na Fase 3.
  4. Fase 3.0: testes de rota no Runtime (DEFERRED).
- **Limites e riscos registrados:**
  - O commit `c5333a1` tem trailer `Co-Authored-By`, proibido pelo `AGENTS.md`. Não foi reescrito por causa da regra de não reescrever histórico compartilhado. Decisão do responsável.
  - O remoto `origin` respondeu que o repositório foi movido para `coletivo420/aculta`. Atualizar a URL do remoto depende de autorização explícita.
  - A permissão de escrita da sessão foi marcada como recusada pelo classificador em uma troca de remoto, mas o push para `dev` foi aceito.
- **Runtime:** sem acesso nesta sessão. Sincronização pós-push (D-70) não executada; registrar como DEFERRED.
- **Retomar lendo:** `docs/decisions/ADR-043-superficies-dominio.md`; `web/modules/custom/aculta_portal/modules/aculta_superficies/docs/PLANO-FINAL.md`; `docs/decisions/ADR-038-aculta-studio-suites-por-dominio.md`; `git status --short --branch`.

## Handoff anterior — contexto do fórum sobre entidades LMS próprias, 2026-11-02

- **Tarefa:** continuar leitores/consumidores em P5 da ADR-042. Checkout `dev` iniciou em `1034dbb0c9679b786b5448e618a8ab94ae689edb`; `origin/dev` estava alinhada. Usuário autorizou commit/push e intervenção em outros módulos/tema. Sem P4, P5 de remoção de pacotes ou P6 executados ainda.
- **Trabalho desta etapa:** Forum LMS passou a usar `GroupInterface` e entidades P2 ACULTA para resolver discussões por atividade; seleção H5P e acesso de membros usam permissões Group. Configuração de instalação do fórum referencia `aculta_lms_activity`. Removido o hook antigo que alterava formulário de resposta contrib inexistente na UI ACULTA. `config/sync` permaneceu intacta e conserva `lms_activity`, portanto a diferença e a estratégia de atualização dessa configuração ainda precisam de revisão antes de P5.
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/`; `src/Plugin/EntityReferenceSelection/Courseaculta_H5PContentSelection.php`; `config/install/field.*.field_lms_activity.yml`; testes Unit e Kernel sob `tests/src/`; manifesto/CHANGELOG e `docs/operations/status/aculta_forum_lms.yml`. Versão preparada `0.1.0-dev.31`.
- **Verificações:** Unit Forum LMS 33 testes / 323 asserções PASS. Kernel focal 1/27 asserções PASS. Kernel completo 27 testes / 1.348 asserções PASS, 58 depreciações Core/contrib. Gate Portal PASS (1.159 checks); índice regenerado oficialmente e validado PASS (48 componentes). `git diff --check` PASS.
- **Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, DB `aculta_runtime`; antes da sincronização `updatedb:status` sem updates e `config:status` sem diferenças. Nenhuma atualização ou import de config executada. A sincronização de código/cache após o push ainda precisa ser registrada.
- **Pendências P5:** migrar consumidores restantes em `aculta_lms`, agenda, Messenger e demais módulos; avaliar relacionamentos `lms_classes` e a configuração `field_lms_activity` sincronizada, sem apagar/migrar dados sem estratégia aprovada; só então remover imports/dependências conforme ADR. Ainda não verificado: P4 sem contrib e grep global zero. P6 e reescrita de histórico não executados.
- **Retomar lendo:** ADR-042; `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `docs/operations/status/aculta_forum_lms.yml`; `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsForumDependencyHarnessTrait.php`; `git status --short --branch`; inventário via `rg -n 'Drupal\\\\lms' web/modules/custom`.

## Handoff mais recente — P2 e plugins objetivos P3 do LMS, 2026-11-02

- **Tarefa:** implementar as quatro entidades próprias do `aculta_lms` segundo ADR-042 e `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; P5/P6 e remoção de dados não executados.
- **Referência:** branch `dev`, HEAD de referência `cd5e4c818b9443bbc088c4fbf7e0669ffa4dadbe`; `composer install` concluído. Sem commit/push conforme instrução explícita.
- **P2:** `LessonEntity`, `ActivityEntity`, `AnswerEntity`, `ProgressEntity`; handler único interno de acesso negado; `LearningEntityStorageSchema` define índices compostos, inclusive chave única `course_id + uid`; updates idempotentes `aculta_lms_update_10005` e `10006` (colunas requeridas e tamanhos exatos). Campos/validações seguem o desenho; nenhuma rota/formulário.
- **P3:** plugins ACULTA `TrueFalseAnswerPlugin`, `SelectAnswerPlugin` e `FillBlanksAnswerPlugin`, resolvidos pelo tipo estável de `CourseActivity` e usando `ObjectiveAnswerScorer` injetado. Os plugins de LMS contrib continuam ativos; não há formulário, persistência, dissertativa, upload ou troca de leitor nesta fase.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/{Access,Entity}/`; `aculta_lms.install`; `tests/src/Unit/LearningEntitiesTest.php`; teste Kernel em `tests/src/Kernel/AcultaLmsInstallTest.php`; versão/CHANGELOG, `docs/operations/status/aculta_lms.yml` e README gerado.
- **Verificações P2:** Unit 211/673 PASS; Kernel focal `AcultaLmsInstallTest` 5/126 PASS, 27 depreciações Core/contrib. O Kernel chama os dois updates duas vezes e verifica os comprimentos SQL e índice único. Gate Portal Drupal 11+ PASS, 1.159 checks; public-slugs PASS (423 rotas, 0 violações); status index PASS, 48 componentes.
- **Verificações P3:** Unit focal `ObjectiveAnswerPluginsTest` 5/13 PASS; Unit completa 216/686 PASS, 1 depreciação PHPUnit; Kernel focal `AcultaLmsInstallTest` 5/128 PASS, 27 depreciações, incluindo DI e resolução por tipo; PHP lint e `git diff --check` PASS. Gate Portal e validadores de tema não aplicáveis a P3.
- **Git:** P2 publicado em `2e27589c6896c84a42be7927c861f01aa8bd7def`; o patch produzido a partir de `cd5e4c8` bate integralmente com esse diff e passou verificação de aplicação e reversão em clone limpo. P3 publicado em `535cd54fdd4552aeb69705836d052f35f2b1696d`, com trailer Claude-Session solicitado. O commit P2 preexistente não foi reescrito para alterar sua mensagem/trailer.
- **Runtime:** hostname `toca`, Drush confirmou Drupal 11.4.10 e banco `aculta_runtime`. Snapshot P2 SHA-256 `d8be1298ddbc961a1397ffc22db74a3aa044d74edc2d1caeaafffcef2de0526e`; aplicados somente updates P2 `10005` e `10006`. Após P2 e após push P3: `updatedb:status` sem pendências e `config:status` sem diferenças. `drush cr` após o push P3 PASS; nenhum import de configuração. Tabelas P2 criadas vazias, sem migração de legado.
- **Pendências e limites:** instalação limpa total da closure Portal não exercitada; PHP 8.5 não verificado (Runtime/CLI usam 8.4.27). Não executar P4, P5 ou P6 nesta missão.
- **Retomar lendo:** `docs/modules/P2-ENTIDADES-PROPRIAS-LMS.md`; `src/Assessment/ObjectiveAnswerScorer.php`; `src/Plugin/ActivityAnswer/`; serviço `aculta_lms.objective_answer_plugins`; testes `ObjectiveAnswerPluginsTest` e `AcultaLmsInstallTest`; changelog e status.


## Handoff mais recente — adaptadores ACULTA do LMS contrib, 2026-11-02

- **Tarefa:** concluir os adaptadores P1 de aula, atividade e resposta do `aculta_lms`, seguindo o ADR-042; P5/P6 não executados.
- **Referência e publicação:** iniciou no commit informado `ea7a74788d3442333ce448b90ffd16d5c9f599d0`; remoto avançou para `892d1a71a` durante a tarefa. Entrega `c0a0ca40f` foi rebaseada sem conflitos e enviada por fast-forward a `origin/dev`. `composer install` concluído.
- **Implementação:** adicionados `ContribCourseLessonAdapter`, `ContribCourseActivityAdapter` e `ContribCourseAnswerAdapter`, seus aliases por interface e cobertura Unit. Atualizados manifest, CHANGELOG, status do componente e README gerado.
- **API contrib verificada:** `LessonInterface.php` expõe métodos adicionais de randomização/navegação/handler; ID, label e campos vêm das interfaces Drupal base. Aula/posição vêm de `CourseInterface::LESSONS`; publicação é o campo `status`. `ActivityInterface.php` não acrescenta métodos; tipo vem do bundle, aula e pontuação máxima vêm das referências em `LessonInterface::ACTIVITIES`. `AnswerInterface.php` oferece `getActivity`, `getLessonStatus`, `isEvaluated`, `getScore`, `getData` e `getUserId`; `TrainingManager.php` oferece `getActivityMaxScore` e operações de tentativa, sem estado textual. O adaptador normaliza pontos para percentual usando o máximo da atividade e recusa resposta avaliada sem nota ou máximo.
- **Arquivos alterados:** `README.md`; `docs/operations/status/aculta_lms.yml`; `web/modules/custom/aculta_portal/modules/aculta_lms/CHANGELOG.md`; manifesto e serviços do módulo; três adaptadores em `src/Learning/`; teste Unit dos adaptadores e asserções dos aliases em `tests/src/Kernel/AcultaLmsInstallTest.php`.
- **Verificações:** Unit completa PASS, 204 testes/666 assertions, 1 deprecação PHPUnit; conversão `4/5 = 80%`, placeholder zero como NULL e recusa sem máximo cobertos. Kernel `AcultaLmsInstallTest` PASS, 4 testes/95 assertions e 27 depreciações upstream; confirma os três aliases. `LearningDashboardKernelTest` PASS, 1/41, 43 depreciações upstream no banco `aculta_simpletest`. Gate Portal PASS (1.159 verificações), índice PASS (48 componentes), `git diff --check` PASS. `AccountCoursesReaderTest` continua UnitTestCase; não foi convertido para Kernel.
- **Runtime:** Drush confirmou o Runtime de desenvolvimento no checkout servido conforme `docs/architecture/environments.md`. `drush cr` PASS e os três aliases resolveram para seus adaptadores. `updatedb:status`: sem updates; `config:status`: sem diferenças. `aculta_lms_update_10004` não está pendente; nenhum update/import foi aplicado e não foi necessário snapshot.
- **Pendências:** estado textual desconhecido não é representável na API contrib, que expõe booleano; o adaptador mapeia os dois valores válidos e recusa valor inválido. Nenhuma alteração de P5/P6.
- **Leitura para retomar:** `docs/decisions/ADR-042-remocao-dependencias-lms-contrib.md`; `vendor/drupal/lms/src/Entity/{LessonInterface,ActivityInterface,AnswerInterface}.php`; `vendor/drupal/lms/src/TrainingManager.php`; adaptadores em `web/modules/custom/aculta_portal/modules/aculta_lms/src/Learning/`.

## Handoff mais recente — xAPI F3/Homelab e sincronização, 2026-11-02

- **Tarefa:** concluir a implementação F3 do LRS xAPI, sincronizar com `origin/dev`, validar no checkout Homelab e publicar. O usuário autorizou push e o teste real ZIP/H5P na sessão anterior.
- **Git:** remoto avançou para `1be85100e`; os commits locais foram reaplicados por rebase sem conflitos. Push fast-forward confirmado em `origin/dev` até `fe2e9c6f4`; F3 está em `5d2c53b04` (`feat(aculta_lms): conclui ingestão e retenção xAPI`).
- **Entrega:** `aculta_lms` 0.1.0-dev.124; endpoint para emissão de token da tentativa e `POST /xapi/statements`, validação/idempotência/limite Flood por token, retenção mensal em lotes e cobertura Unit/Kernel. ADR-041, plano xAPI, CHANGELOG, status e README foram atualizados.
- **Runtime:** Drush confirmou `toca`, Drupal 11.4.10, PHP 8.4.27 e banco `aculta_runtime`. `updatedb:status`: sem updates pendentes; `config:status`: sem diferenças. Snapshot criado anteriormente para a validação de config/Olivero: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-xapi-olivero-20261102/runtime.sql`, SHA-256 `385f539d6a8f82e12dfe2c8e293bab691f3242e00b5545b33913453ff2868153`.
- **Validações executadas nesta retomada:** PHPUnit oficial Unit de XAPI, AttemptRule, Mailer e H5P: 97 testes/190 asserções PASS; Kernel xAPI retention, Mailer e H5P installer no `aculta_simpletest`: 22 testes/365 asserções PASS, 27 depreciações de Core/contrib; gate Portal Drupal 11+: 1.159 checks PASS; gate MAIN: 39 arquivos/163 rotas PASS; status index: 48 componentes PASS; PHP lint e `git diff --check` PASS.
- **Validações Homelab executadas anteriormente nesta tarefa:** Firefox ESR visível com ACULTA420 e Olivero aceitou e persistiu statement em ambos; formulário de configuração somente no MAIN e 404 em `cursos`; Flood aceitou as primeiras 60 submissões inválidas e respondeu 429 à 61ª. Artefatos fora do repositório: `/home/piradopirata/aculta-suite-out/xapi-browser-20261102/`.
- **ZIP/GZIP da tarefa predecessora:** resultados reais e achados foram registrados sem reescrever os achados em `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; inclui execução autorizada de ZIPs de bomba pelo instalador H5P. Portal/Revista funcional por tema ficaram DEFERRED pelas dependências de instalação/configuração descritas naquele documento; Fórum passou em ACULTA420 e Olivero.
- **Próximos passos:** nenhum bloqueio de implementação. Uso em produção depende de medir a carga de escrita MariaDB e provisionar o segredo HMAC pelo deploy, conforme ADR-041.
- **Leitura para retomar:** commit `5d2c53b04`; `docs/modules/PLANO-XAPI-2-SUBSTITUI-SCORM.md`; `docs/decisions/ADR-041-xapi-2-lrs-proprio.md`; `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`.

Este é o ponto único de handoff para as próximas sessões e agentes. Atualize este documento antes de trocar de sessão, encerrar uma fase ou transferir o trabalho para outro agente/ferramenta, conforme `AGENTS.md`.

## Handoff mais recente — validação Homelab concluída parcialmente, 2026-11-02

- **Tarefa e contexto:** validar no Homelab as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`; usuário autorizou depois a bomba ZIP real e pediu o relatório completo.
- **Checkout/publicação:** `dev`, limpo e sincronizado com `origin/dev` no momento do handoff. Commits desta validação enviados: `3a1a8569e` (fixture H5P), `9dddc1fff` (fixtures Mailer) e `5e73f2279` (bombas ZIP reais). Commit documental da consolidação final ainda precisa ser criado/enviado.
- **Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. Atualização sem pending updates; import somente de `config/sync`, que removeu a configuração DB-only `aculta_file_uploader.settings`; verificações finais de status limpas.
- **Resultados executados:** Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; Mailer Kernel 7/33 PASS; H5P instalador Kernel 13/310 PASS; casos reais de bomba ZIP 2/78 PASS, rejeitando entrada de 33 MiB e entrada de 2 MiB com razão >100:1 antes da extração. Gates Portal Drupal 11+ 1.159 checks PASS; índice de status 48 componentes PASS. Fórum funcional: ACULTA420 6/33 PASS e Olivero 6/33 PASS.
- **Limite:** não há testes `BrowserTestBase`/`FunctionalJavascriptTestBase` para Portal e Revista. Smoke temporária da Revista falhou antes da execução por dependências de Views (`node.type.revista`, `field.field.node.revista.field_aculta_discussion`) ausentes em instalação limpa sem `config/sync`; logo Portal/Revista não foram validados em nenhum dos dois temas. O achado e motivo estão no relatório abaixo.
- **Documentação a finalizar:** atualizar e publicar `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` com os resultados e este handoff. Não alterar o texto dos achados da revisão.
- **Referências:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

## Handoff mais recente

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** alinhar o checkout Homelab a `dev`, validar as entregas `6f978b5`, `42ed25f`, `7fe8bae`, `7adb5fc`, `313c227` e `fac3d58`, corrigir falhas das suítes e publicar fases concluídas em `origin/dev`.
- **Checkout e publicação:** branch `dev` no checkout servido, sincronizada com `origin/dev`; HEAD verificado `904bdcbc5` após a publicação de duas fases. Commits da tarefa enviados: `3a1a8569e` (`test(aculta_lms_h5p): corrige fixture do instalador`) e `9dddc1fff` (`test(aculta_mailer): registra uploader nos Kernels`).
- **Runtime:** hostname `toca`, Drupal 11.4.10, PHP CLI 8.4.27, Drush 13.8.0, banco `aculta_runtime`. Snapshot pré-import em `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-homelab-validation-20261102/runtime.sql`, 102.039.549 bytes, SHA-256 `f49e3b6a72f9cbec3d34cec1edb51fcdf464d2c25bc8695a06f1f9f1c413f68`. `updatedb`: sem updates; `config:import` apenas de `config/sync` removeu `aculta_file_uploader.settings` DB-only. Verificação final após novas mudanças remotas: `updatedb:status` sem updates e `config:status` sem diferenças.
- **Verificações finais:** gates Portal Drupal 11+ PASS (1.159 checks) e índice PASS (48 componentes). PHPUnit oficial: Mailer Unit 15/40 PASS; LMS `AttemptRuleTest` 11/11 PASS; H5P Unit 6/40 PASS; H5P instalador Kernel 11/232 PASS; Mailer Kernels 7/33 PASS, estes quatro Kernels/Units no banco correto conforme aplicável (`aculta_simpletest` para Kernel). H5P foi corrigido para habilitar `aculta_file_uploader` e instalar seu schema; Mailer para habilitar `file` e `aculta_file_uploader`.
- **Documentação:** `SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md` recebeu resultados executados e versões atualizadas, mantendo pendentes o teste real de bomba (aguarda autorização explícita) e os testes Portal/Revista/Fórum com Olivero. `QrCodeBatchController` foi verificado como exportação ZIP de saída; fluxo PPT Opigno permanece fora do escopo conforme plano.
- **Arquivos alterados nesta fase:** commits acima cobrem H5P e Mailer. Permanecem alterações documentais locais neste handoff e no relatório de segurança, ainda não commitadas. Preservada fora do escopo a edição de `web/modules/custom/aculta_portal/modules/aculta_studio/src/Form/StudioUploadEmbedForm.php`.
- **Próximos passos:** confirmar autorização antes da bomba real; decidir/rodar os testes de Portal, Revista e Fórum com Olivero; então finalizar e publicar a fase documental em `dev`, sem incluir a edição alheia de Studio.
- **Leitura para retomar:** `docs/operations/SECURITY-REVIEW-ZIP-INPUTS-2026-11-02.md`; `docs/operations/BANCO-DE-TESTE.md`; `docs/architecture/environments.md`; `docs/operations/AGENT-OPERATIONS.md`; `git status --short --branch`.

- **Data/hora:** 2026-11-02, America/Sao_Paulo.
- **Tarefa e contexto:** resolver todas as pendências de `php scripts/validate-project-status-index.php` em `dev`, por pedido explícito do responsável.
- **Concluído nesta sessão/fase:** alinhadas as versões dos 12 status files aos manifestos, criado o status e README ausentes do `aculta_file_uploader`, e README raiz regenerado pelo script oficial. Commit `4ac8f1d0a` foi enviado e confirmado em `origin/dev`. Validador final PASS para 48 componentes.
- **Arquivos alterados nesta tarefa:** `README.md` (índice gerado); `docs/operations/status/aculta_{document_viewer,file_uploader,forum,gallery,image,lms,lms_h5p,magazine,portal,qrcode,studio,user,wiki}.yml`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este handoff.
- **Verificações executadas e resultados:** `php scripts/validate-project-status-index.php --write` regenerou o índice; `php scripts/validate-project-status-index.php` PASS (48 componentes, incluindo todos os status e links `next_phase`); parse YAML de todos os status e do manifesto do Uploader PASS; `git diff --check` PASS.
- **Pendências e bloqueios:** nenhum bloqueio no índice. O checkout primário `/home/piradopirata/projetos/aculta` está separado da branch `dev` e pode conter estado de outras frentes; não foi alterado. O trabalho ocorre no checkout existente da branch `dev`.
- **Próximos passos:** nenhum para o índice global; `4ac8f1d0a` está publicado em `origin/dev`, branch limpa, validador PASS. Gates autenticados/externos da tarefa Contributions anterior continuam descritos no handoff anterior e no plano correspondente.
- **Leitura para retomar:** `scripts/validate-project-status-index.php`; `docs/operations/status/`; `web/modules/custom/aculta_portal/modules/aculta_file_uploader/README.md`; este arquivo.

### Handoff anterior — execução do plano Contributions, 2026-11-02

Commits publicados em `origin/dev`: `6a0f0d072`, `7495f7a3d` e `2445c89b2`. A tarefa do plano, testes e gates operacionais pendentes estão registrados nas seções acima e em `docs/architecture/PLANO-ACULTA-CONTRIBUTIONS.md`.

Não inclua credenciais, tokens ou outros dados sensíveis. Atualize este handoff ao transferir para um novo ponto de retomada, mantendo referências anteriores apenas se ainda forem necessárias para entender o estado atual.

## Handoff — superfícies e caminhos públicos, 2026-11-03

- **Entrega:** validação do Runtime para as superfícies concluída no commit `8263f1776`, enviado a `origin/dev`; `git ls-remote origin dev` confirmou `8263f17767fbe3910ac335bbcc381b375771bcc4`.
- **Arquivos:** teste Kernel de superfícies, changelog/README/manifesto/status do `aculta_superficies`, matriz HTTP, revisão de fases e registro de dívidas.
- **Runtime:** hostname `toca`, PHP 8.4.27, Drupal 11.4.10, banco `aculta_runtime`; `composer install`, `updatedb:status`, `config:status`, `config:import --diff` e `cr` passaram. `leitura_ativa` está TRUE somente no banco do Runtime; `config/sync/aculta_portal.superficies.yml` continua FALSE.
- **Testes:** Unit focal 38/38 e 311 asserções; Kernel `SurfaceRoutesKernelTest` 2/2 e 22 asserções, com depreciações de Core/contrib. Matriz HTTP antes/depois registrada em `docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md`.
- **Gates:** admin routes, environment addresses, browser validators, public slugs, tema ACULTA420, boundaries e suíte do deployer PASS. Índice/Portal Drupal 11 falham por alteração simultânea do LMS: manifest `.160`, status `.159` e README fora deste commit.
- **DEFERRED:** revisão autenticada da interface `/painel-administrativo/aculta/caminhos` e das Suites; resolução inbound de `/slug` no MAIN (3.5); exportação de `leitura_ativa: true` para `config/sync` somente no RC.
- **Estado do checkout:** alterações não staged do `aculta_lms` pertencem ao trabalho paralelo e foram preservadas; não incluir no próximo commit de superfícies.

- **Continuação:** expectativas antigas de `/wesativa` e `/greenstash` foram corrigidas para `/we-sativa` e `/green-stash` nos testes de Studio e Commerce. A suíte combinada passou 257 testes/1.642 asserções; commit `52c52301b` está publicado em `origin/dev`.
- **Interface administrativa:** rota, permissão restrita, parent de menu ACULTA e purpose MAIN conferidos nos YAML e no teste estático. A requisição anônima retorna 404; validação autenticada segue DEFERRED por falta de sessão de teste.
- **Interface administrativa no Runtime:** `aculta_superficies` foi habilitado para a validação. `/painel-administrativo/aculta/caminhos` retorna 403 no MAIN para anônimo e 404 em subdomínio; a rota expõe path, purpose `main`, permissão `administrar superficies` e `_admin_route: true`. `config:status` mostra `core.extension` e `aculta_portal.superficies` como diferenças esperadas.

## Handoff — ADR-042 P5, histórico de tentativas ACULTA, 2026-11-03

- **Objetivo ativo:** concluir P4/P5 da ADR-042. P6 e reescrita de histórico permanecem fora.
- **Publicados:** dev.165 `af2c0352f` migra histórico para AnswerEntity; dev.166 `ad1135bce` cobre idempotência do update 10011; dev.167 `534ef1b3b` remove feedback legado; dev.168 `acdc05d0e` remove FileUpload contrib, access/controller/rota antigos e respectivos testes/configs.
- **Runtime/update 10011:** snapshot SHA-256 `a344c5509ee02a565b4bd72790da51dd541d86298f75ba42eb7ba5f91c1fa076`. Tentativa de alterar `score` inteiro falhou sem alteração; update ajustado instalou `score_precise` ao lado do inteiro e passou. `drush cr` passou; sem updates pendentes. `config:status` mantém apenas `aculta_portal.superficies` e `core.extension` diferentes; sem import.
- **Local dev.169:** removidos `ActivityDeadlineFormHooks` e `CourseLinkHooks`, presos ao formulário/rota do contrib. Unit 218/758 PASS; `AcultaLmsInstallTest` 6/196 PASS (44 depreciações); `AttemptArchiveLifecycleTest` 5/201 PASS (50 depreciações); gate Portal 1.172 checks PASS. `rg -F 'Drupal\lms' web/modules/custom -g '*.php'` retorna zero.
- **Pendências:** dev.169 ainda local. Dependências `lms*` seguem no manifesto; `composer.json`/lock, config sync e módulos Runtime não foram cortados. O formulário ACULTA de submissão de arquivo segue pendente. Configuração `activity_data` para os plugins objetivos aguarda definição; não inventar formato de enunciado/gabarito.
- **Próximos passos:** publicar dev.169; preparar P4 removendo deps do manifesto e retirando lms do harness; executar testes limpos; então P5 Composer/config/Runtime com snapshot e ordem autorizada. P6 não executar.
## Renomeação LinkWeed — 2026-11-03

- Objetivo: renomear `aculta_links` para `aculta_linkweed`, mantendo purpose `links` e Domain `links_aculta_org`.
- Feito: código, namespaces, entidades, tabelas, config, rotas administrativas e referências customizadas renomeados; título da superfície virou LinkWeed; Runtime atualizado e cache reconstruído.
- Entidades/tabelas: `aculta_link` → `aculta_linkweed`, `aculta_link_revision` → `aculta_linkweed_revision`, `aculta_link_item` → `aculta_linkweed_item`, `aculta_link_item_revision` → `aculta_linkweed_item_revision`, `aculta_link_page` → `aculta_linkweed_page`, `aculta_link_page_revision` → `aculta_linkweed_page_revision`.
- Contagens Runtime antes/depois: 1/1, 1/1, 0/0, 0/0, 0/0, 0/0, na ordem acima.
- Verificações: `updb`, `cr`, `pm:list`, config `aculta_linkweed.social_links`; gates administrativos, endereços, browser validators, slugs, status index e Drupal 11 passaram. Unit do módulo: 8 testes/65 assertions.
- Entrega: commit `f07f922b9c44d77a5ad93b82189b717cc17eb1bf` enviado a `origin/dev`; `ls-remote` confirmou o mesmo hash. Smoke HTTP no MAIN simulado: `/painel-administrativo/linkweed` respondeu 403 anônimo (rota presente e protegida) e `/painel-administrativo/links` respondeu 404. `config:status` ainda mostra divergências LMS preexistentes e `core.extension`; não foram exportadas por segurança de escopo.
- DEFERRED: resultado final do Kernel focal não foi capturado pelo runner longo; não houve falha explícita registrada. Navegador visual não foi necessário após smoke HTTP e gates.

## Handoff — remover imports LMS reintroduzidos pelo PR #301 — 2026-11-03

- **Objetivo:** substituir os usos de `Drupal\\lms` em sete arquivos de código e quatro testes, conforme ADR-042, com escopo ampliado autorizado para fechar as dependências arquiteturais identificadas.
- **Estado Git:** worktree isolado em `bf731e8789795f987a9bbc7246b3e7a17a31a933`; durante a tarefa, `origin/dev` avançou a `9c2e64018b8603ba8d3b6e178e5b1728211243c4` (três commits à frente). Alterações ainda locais, sem commit/push; rebase pendente.
- **Implementação:** formulário ACULTA próprio de submissão (`AnswerSubmissionForm`) com acesso por matrícula, armazenamento de tentativas via payload ACULTA, resposta livre com feedback formativo, tipos objetivos do P3 e upload privado; configuração `answer_config` em `ActivityEntity` com update idempotente 10013. Hooks de prazo, imagem/upload e discussão agora operam no formulário ACULTA. O portal deixou de manter slugs duplicados; ficou a implementação em `aculta_lms`, conforme escolha do responsável. Atualizado teste de notice stale answer e fixture antiga do dashboard.
- **Arquivos/versões:** alterações em `aculta_lms` (0.1.0-dev.175), `aculta_forum_lms` (0.1.0-dev.35), `aculta_portal` (0.1.0-dev.68), status yml, CHANGELOGs, README gerado e este handoff. Removidos os quatro arquivos de implementação de slug duplicados do portal e seu teste; equivalentes e teste permanecem em `aculta_lms`.
- **Testes:** `aculta_lms` Unit 224 testes/773 assertions PASS; `aculta_forum_lms` Unit 33/331 PASS; `LearningNoticeStaleAnswerTest` 1/8 PASS. `AcultaLmsInstallTest` Kernel 6/178 PASS (35 depreciações upstream). `LmsActivityDiscussionResolverKernelTest` Kernel 1/25 PASS (38 depreciações upstream). Aviso de diretório browser_output não gravável nas execuções Unit.
- **Gates:** `validate-aculta-portal-drupal11.php` PASS (1202 checks); `validate-public-slugs.php` PASS (410 routes, 0 violações); índice de status PASS (53 componentes); `git diff --check` PASS; busca de `Drupal\\lms` em `web/modules/custom` sem resultados. Gates auxiliares: `validate-module-theme-map.php` ainda falha em cinco módulos sem linha no mapa (`aculta_links`, `aculta_lms_messenger`, `aculta_paths`, `aculta_commerce_messenger`, `aculta_wiki_messenger`); `validate-portal-ui-docs.php` ainda reporta 13 tokens/atributos existentes de Studio, Events e File Uploader sem documentação. O novo código não adiciona tokens ao tema.
- **Runtime:** sem alterações. Não houve atualização/import/caches. Sincronização de código no Runtime fica DEFERRED para respeitar a instrução específica de não alterar dados/runtime nesta tarefa.
- **Próximos passos:** rebasear sobre `origin/dev`, rerodar Unit/gates, revisar diff staged, commitar em português com trailer Claude-Session e sem coautoria; push fast-forward em `origin/dev`; depois avançar `origin/main` somente se continuar ancestral e conforme ordem explícita. Confirmar hashes remotos. Não executar P4/P5 adicionais, P6, nem operações de dados.
- **Leitura para retomar:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/AnswerSubmissionForm.php`; `src/Plugin/ActivityAnswer/FreeTextAnswerPluginBase.php`; `src/Access/AnswerSubmissionAccessCheck.php`; `src/Hook/ActivityDeadlineFormHooks.php`; `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsActivityDiscussionHooks.php`; este handoff.

## Handoff — validação Runtime LMS interrompida — 2026-11-03

- **Ordem e referência:** validar `aculta_lms` 0.1.0-dev.175 no Homelab, sem produção. Checkout em `dev`, sincronizado a `775295ac3d1c8c21524a9b15696515b70182b1b2`; branch remota `main` estava no mesmo commit.
- **Snapshot:** íntegro via `drush sql:dump`, 54.968.655 bytes, modo 0600, SHA-256 `374e09c05e47de60cc42a01d14c0e0d4186c1fa222a1c787acce00f1e92dd5a9`. Caminho lógico: `$SUITE_OUT/lms-runtime-validation-20261103/aculta_runtime-before-775295ac3.sql`.
- **Runtime:** `composer install` sem mudanças de pacotes; lockfile sem `drupal/lms*`. `drush cr` PASS. Antes do update: somente `aculta_lms_update_10013` pendente, configuração sem diferenças. Update 10013 aplicado. Depois: sem updates, sem diferenças de configuração; nenhum import de config/sync.
- **Dados sintéticos criados:** Group curso ID 88, Group turma auxiliar ID 89, duas aulas ACULTA publicadas e cinco atividades (true_false, select, fill_blanks, free_text, file_upload), além de uma conta aluna e uma educadora sintéticas. Nenhuma resposta ou arquivo foi submetido. Dados ficam para revisão; não remover sem ordem própria.
- **Parada funcional:** primeira requisição anônima à rota de resposta retornou HTTP 500, com corpo `O site encontrou um erro inesperado. Tente novamente mais tarde.` O smoke runner não interrompeu o loop após o primeiro 500: consultou também as outras quatro rotas (todas 500) e `/user/login` em Conta (404), sem autenticar nem escrever. Depois, uma única requisição GET anônima repetiu a primeira rota para capturar o corpo exato. Encerrei o fluxo sem POST/upload; Watchdog não registrou mensagem correspondente.
- **Gates estáticos:** Portal 1202 checks, slugs públicos 410 rotas, rotas administrativas MAIN 41 arquivos/170 rotas e índice 53 componentes PASS. Firefox ESR 153.5.1 e geckodriver 0.37.1 estão disponíveis; `DISPLAY=:0` responde, mas o navegador não foi iniciado devido à parada.
- **Entrega documental:** versão `aculta_lms` 0.1.0-dev.176 registra update e falha; este relatório e o gate estático compõem a entrega versionada.
- **DEFERRED:** respostas e upload autenticados, acesso do educador a respostas/avaliação discursiva, isolamento entre estudantes, screenshots 320/1440 em ACULTA420 e Olivero. O código atual não registra rota/tela de revisão de respostas; o painel de turma expõe métricas e prazos, sem payloads de resposta. Retomar após ordem para tratar o HTTP 500; qualquer revisão docente exige decisão/implementação separada. Não apagar dados sintéticos sem ordem.
- **Leitura para retomar:** `docs/operations/AGENT-OPERATIONS.md`; `docs/architecture/environments.md`; `web/modules/custom/aculta_portal/modules/aculta_lms/aculta_lms.routing.yml`; `AnswerSubmissionForm.php`; snapshot externo sob `$SUITE_OUT`.

### Continuação — correção do acesso e retomada dos testes, 2026-11-03

- **Correções locais:** `AnswerSubmissionAccessCheck` aceita slugs antes da conversão dos parâmetros; `LearningEligibility` usa os relacionamentos `lms_classes` do curso. Também foram corrigidos os serviços do formulário perdidos na serialização do Form API, o encadeamento `setPermanent()->save()` (o método retorna `void`) e a árvore de valores das lacunas. Causas 500 no watchdog: `Typed property ... $currentRoute must not be accessed before initialization` e `Call to a member function save() on null`.
- **Testes:** LMS Unit 226/788 PASS (4 depreciações PHPUnit); `AcultaLmsInstallTest` 7/193 PASS (35 depreciações upstream). `AnswerSubmissionAccessCheckTest` cobre slugs crus sem TypeError; `LearningEligibilityTest` cobre matrícula em turma relacionada/outsider; Kernel verifica serialização dos serviços. `ClassActivityDeadlineTest` não executa porque o harness ainda declara o módulo removido `lms` (`Unavailable module: 'lms'`); não foi reintroduzido.
- **Runtime:** snapshot pré-alterações em `$SUITE_OUT/lms-runtime-validation-20261103/aculta_runtime-before-lms-500-fix-9512e69.sql`, SHA-256 `ec8259784afe036efba985dcab557694530cb106dfa20fadbd31b7d9ff71bc88`. `updatedb:status` limpo e `config:status` sem diferenças; `drush cr` passou. Rotas anônimas: cinco HTTP 403. Conta sintética autenticada abriu e enviou os cinco tipos sem 500. Contagens: verdadeiro/falso 1, seleção 1, lacunas 2 (score 0 inicial e 100 após correção), resposta livre 1 `submitted` sem nota, upload 2 `submitted`. Upload tem dois arquivos referenciados: um permanente com uso registrado e um temporário da submissão que falhou após salvar a tentativa. Dados permanecem sem descarte.
- **Visual/limites:** Firefox ESR visível, ACULTA420, upload 10/10 em 1440/320; capturas em `$SUITE_OUT/lms-runtime-validation-20261103/firefox-upload/` e `$SUITE_OUT/lms-runtime-validation-20261103/firefox-answer-types/`. Painel docente no domínio MAIN responde HTTP 200 e mostra a turma, mas não existe revisão/avaliação de resposta. Isolamento entre duas estudantes não executado (uma estudante sintética autorizada); Olivero DEFERRED.
- **Versão/gates:** `aculta_lms` 0.1.0-dev.177. Portal 1202 checks, slugs 410 rotas e admin MAIN 41 arquivos/170 rotas PASS.
- **Retomar lendo:** `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/AnswerSubmissionAccessCheck.php`; `src/Notice/LearningEligibility.php`; `src/Form/AnswerSubmissionForm.php`; `tests/src/Unit/AnswerSubmissionAccessCheckTest.php`; `tests/src/Unit/LearningEligibilityTest.php`; este handoff.

### Continuação — isolamento entre estudantes e prazo sem contrib, 2026-11-03

- **Snapshot:** `$SUITE_OUT/lms-runtime-validation-20261103/final/aculta_runtime-before-isolation-review.sql`, SHA-256 `cd8ac930dcb95354074846929273253327045fd907f1cec0b1c53f04101c2616`.
- **Isolamento:** segundo aluno sintético autenticado abriu somente o próprio formulário (HTTP 200), recebeu HTTP 403 ao baixar a resposta de outro aluno e a rota de lista de respostas inexistente respondeu HTTP 404. Não houve vazamento de conteúdo nem 500.
- **Revisão docente:** DEFERRED. O painel docente existente mostra turma, métricas e prazos, mas o módulo não possui rota/formulário para listar, avaliar ou publicar feedback de respostas.
- **Prazo:** `ClassActivityDeadlineTest` foi ajustado para não habilitar `lms` ou `lms_classes` e usar as entidades ACULTA. Execução: 1 teste, 15 assertions, erro antes da conclusão: `The "entity:group_relationship:lms_course-group_membership" plugin does not exist`.
- **Gates:** Portal, slugs públicos e rotas administrativas passaram; a busca de `Drupal\\lms` permanece sem ocorrências. O índice global não passou porque já existe divergência não relacionada do `aculta_studio` entre status e manifesto.
- **Decisão:** Olivero dispensado pelo responsável em 2026-11-03, motivo: validação dispensada pelo responsável.

### Continuação — independência LMS fechada, 2026-11-03

- **Frente 1:** `ClassActivityDeadlineTest` usa somente os plugins de relação ACULTA e passa 1/29, com 39 depreciações upstream. Nenhum módulo `lms*` é habilitado no harness; as referências históricas de configuração contrib permanecem apenas em Config Sync/histórico e não são recriadas.
- **Frente 2:** criadas as rotas de lista e revisão docente. Educador teve HTTP 200 na lista e no detalhe e gravou nota 77, feedback e `state=graded`; aluno B teve HTTP 403 na lista e no detalhe. A resposta própria do aluno exibe a avaliação. Unit LMS 226/788 PASS e Kernel `AcultaLmsInstallTest` 7/193 PASS.
- **Frente 3:** manifesto, status e changelog do Studio alinhados em `0.1.0-dev.41`, versão evidenciada pelo manifesto/status e pela entrada de changelog existente. Nenhum código Studio foi incluído.
- **Runtime:** snapshot anterior ao update 10014 em `$SUITE_OUT/lms-runtime-validation-20261103/final/aculta_runtime-before-isolation-review.sql`, SHA-256 `cd8ac930dcb95354074846929273253327045fd907f1cec0b1c53f04101c2616`. Update `aculta_lms_update_10014` aplicado; depois não há updates nem diferenças de configuração; cache rebuild passou.
- **Gates:** Portal 1202 checks PASS, slugs 410 PASS, rotas administrativas 172 PASS e grep `Drupal\\lms` zero. Studio Unit 72/661 PASS. Olivero dispensado pelo responsável.

### Continuação — validação visual Olivero, 2026-11-03

- **Snapshot:** `$SUITE_OUT/lms-runtime-validation-20261103/olivero/aculta_runtime-before-olivero.sql`, SHA-256 `a1ddcb237d01e62c2eebbd531844df75ac3e573d92510fc8018e8c04b411c7f4`.
- **Firefox ESR visível:** Olivero passou 10/10 verificações do formulário de upload em 1440 px e 320 px, incluindo recusa `.exe`, aceite PNG, envio e ausência de erros JavaScript. Capturas e relatório em `$SUITE_OUT/lms-runtime-validation-20261103/olivero-final4/`.
- **Correção:** o harness passou a desconsiderar o menu móvel oculto do Olivero no cálculo de overflow; o formulário recebeu proteção de viewport para a navegação colapsada. O tema foi restaurado para `aculta420` e Olivero desinstalado.
- **Runtime final:** `drush cr` passou, sem updates pendentes e sem diferenças de configuração.

## Handoff — Bootstrap 8.x-3.45 — 2026-11-03

- Atualizado `drupal/bootstrap` para 8.x-3.45 (Composer 3.45.0); `ACULTA420` e Bootstrap confirmados ativos pelo Drush. O asset local `twbs/bootstrap` 5.3.8 foi mantido para as fixtures e verificações já existentes.
- Ajustados README, sincronizador de assets do Composer e gate Foundation. `composer validate`, `php -l` nos scripts alterados, gate Foundation (355 checks) e `git diff --check` passaram.
- Commit `fee0f65c11` enviado para `origin/dev`.
- Snapshot pré-update do `aculta_runtime`: `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-bootstrap-8x-20261103.sql.gz.gz`; SHA-256 `2468c0ac57ea21092a753cc6289b73df623ebe38e62d1467dce0ffa11b27a853`.
- **Bloqueio Runtime:** `updatedb` falhou em `system_update_11501`, pois o update procura o tipo de entidade `aculta_link`, ausente no Runtime. Nenhum `config:import` foi executado. Diagnosticar/alinha o estado do entity type antes de retomar `updatedb`.

## Handoff — preparação Drupal 12 e ImageMagick 5 — 2026-11-03

- Upgrade Status 5.0.0-alpha3 instalado/ativado e Rector 1.1.3 configurado para os módulos Portal/MCP e tema ACULTA420. 83 manifests customizados agora declaram `core_version_requirement: ^11 || ^12`.
- Rector aplicou os ajustes de APIs removidas/depreciadas no código customizado; a segunda execução em dry-run não encontrou transformações restantes. Depreciações PHP 8.5 de cURL, GD e Reflection foram tratadas; o shim público legado `UploadedAnswerAccessCheck` foi preservado por compatibilidade.
- ImageMagick atualizado para 5.0.2; PDFPreview está no branch `1.x-dev` (`6bdd374e`) porque a release estável 1.2.0 não aceita ImageMagick 5. Composer também remove Storybook. Drupal Core continua 11.5.0-beta1; isso é preparação de compatibilidade, não upgrade do Core.
- Verificações: Composer validate, gate Portal (1202 checks), gate ACULTA420 Foundation (355 checks), índice do README (53 componentes), PHP lint e Rector dry-run PASS. PHPUnit não executado.
- Upgrade Status registrou avisos manuais não impeditivos, incluindo o shim legado de compatibilidade e depreciações removidas só no Drupal 13.
- Snapshot pré-update do Runtime e bloqueio em `system_update_11501` estão descritos acima. `config:import` ficou pendente após a falha de `updatedb`.

## Handoff — compatibilidade ACULTA420 com Bootstrap 8.x — 2026-11-03

- ACULTA420 carrega agora `bootstrap5-framework` a partir de `/libraries/bootstrap`, depois das bibliotecas do Drupal Bootstrap 8.x-3.45 e antes dos CSS/JS do tema. Isso mantém as classes e APIs `data-bs-*` já usadas pelo tema.
- Versão do tema marcada `0.7.0-dev.38`; CHANGELOG, roadmap, gate Foundation, `AGENTS.md` do tema e índice gerado atualizados.
- Verificações estáticas: PHP lint, parsing de YAML, status index (53 componentes) e `git diff --check` PASS. Testes e revisão visual não executados.
- Cache do Runtime não reconstruído. A sincronização permanece bloqueada pelo `system_update_11501` que procura `aculta_link`; registro do snapshot e hash no handoff Bootstrap acima.

## Handoff — ACULTA420 com Bootstrap 8.x nativo — 2026-11-03

- **Objetivo:** usar somente Bootstrap 3.4.10 entregue pelo Drupal Bootstrap 8.x-3.45, sem carregar camada Bootstrap 5 no ACULTA420.
- **Implementação:** removida a biblioteca `bootstrap5-framework` do `aculta420/global`; gavetas de carrinho, avisos e mensagens usam o plugin modal Bootstrap 3 com apresentação lateral CSS própria. Dropdown, abas, menu colapsável, alertas e eventos AJAX usam atributos/classes/eventos nativos do Bootstrap 3/jQuery. Ajustada a grade do endereço, cartões de conteúdo e utilitários que `PortalUiHooks` entrega para layouts ACULTA com classes `aculta-u-*`.
- **Versão:** tema `0.7.0-dev.39`; atualizado CHANGELOG, roadmap, gate Foundation, instruções locais, status do componente e linha gerada do README.
- **Validações:** status index regenerado (53 componentes); `git diff --check`, PHP lint, `node --check` e parsing de 3 arquivos YAML PASS. Testes e inspeção visual não executados.
- **Runtime:** sincronização DEFERRED. `updatedb` permanece bloqueado por `system_update_11501` que procura o tipo de entidade `aculta_link`, ausente no Runtime; snapshot/hash constam no handoff Bootstrap 8.x acima. Não houve operação de escrita.
- **Retomar lendo:** `web/themes/custom/aculta420/aculta420.libraries.yml`, `components/patterns/{cart-offcanvas,alerts-trigger,messenger-trigger,product-tabs}`, `js/{navigation,messenger-drawers}.js`, `css/components/{portal-cart,utilities}.css`, `scripts/validate-aculta420-foundation.php` e esta seção. A validação visual e de comportamento no tema base ainda é necessária antes de declarar RC.

## Handoff — política de contrib até ACULTA 1.0 — 2026-11-03

- **Decisão D-72:** até a versão oficial 1.0, manter Drupal contrib em atualização ativa, aceitar alpha/beta para compatibilidade D12, adaptar módulos ACULTA e evitar constraints exatas no `composer.json` sem razão técnica. `composer.lock` permanece versionado como resolução reproduzível e deve avançar com cada atualização.
- **Documentação alterada:** decisão D-72 em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`, política em `docs/operations/AGENT-DEPENDENCIAS.md` e matriz em `docs/portal/MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md`.
- **Inventário Composer (lock atual):** upgrades diretos identificados: `geofield` 1.67.0 → 10.3.5 (estável, D12; major e constraint atual `^1` bloqueia); `commerce_cart` e `commerce_checkout` 3.3.10 → 3.3.14 (estável, mas ainda sem D12); CKEditor 5 Plugin Pack 1.5.6 → 2.0.0-beta1 e Premium Features 1.8.7 → 2.0.0-beta1 (D12, coordenar com Drupal 12; não compatíveis com D11). Bootstrap omitido conforme orientação do responsável.
- **Acompanhar upstream:** Simple OAuth 6.1.x-dev (patch D12 em revisão), Pathauto 8.x-1.x-dev (compatibilidade D12 fechada no issue; ainda sem release alpha/beta confirmado), Simple Sitemap 5.x-dev (issue D12 fechada) e Webform 6.x-dev (issue D12 precisa revisão). Não tratados como releases prontas.
- **Verificações:** Composer `outdated --all --locked 'drupal/*'`, `composer show --all` para Group/Premium Features e consultas das releases/issues oficiais Drupal.org; `git diff --check` PASS. Nenhuma dependência atualizada neste trabalho.
- **Estado do Runtime:** não sincronizado; pendências de `updatedb` e `config:status` já constam nos handoffs anteriores. Não executar Config Import.
- **Retomar lendo:** esta decisão, `docs/operations/AGENT-DEPENDENCIAS.md`, `docs/portal/MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md` e os manifests `composer.json`/`composer.lock`.

### Continuação — atualização contrib e branches solicitados — 2026-11-03

- **Composer:** atualizados `simple_oauth` para `6.1.x-dev`, `pathauto` para o branch Composer `1.x-dev` (branch upstream `8.x-1.x-dev`), `simple_sitemap` para `5.x-dev` e `webform` para `6.x-dev`. Também liberadas as constraints exatas antigas: Commerce Cart/Checkout `^3.3` (lock 3.3.14) e Geofield `^10.3` (lock 10.3.5).
- **Lock:** resolução regenerada; Composer instalou os quatro branches e as três versões estáveis acima. Update amplo `composer update 'drupal/*' -W` não encontrou outros upgrades Drupal permitidos pelas constraints atuais; atualizou PHPStan 2.3.5 e phpstan-phpunit 2.1.1.
- **Bootstrap/Core:** Bootstrap mantido conforme instrução do responsável. Drupal Core continua em 11.5.0-beta1; os branches escolhidos foram resolvidos no Core 11, mas isso não comprova compatibilidade efetiva desses módulos com Drupal 12.
- **Verificações:** `composer validate --no-check-publish` válido com avisos de constraints exatas preexistentes e dos branches `-dev`; `git diff --check` passou. Sem testes funcionais.
- **Runtime:** não sincronizado. Estado anterior registra pendências de updates e diferenças de configuração após falha em `text_post_update`; consultar `updatedb:status`/`config:status` antes da próxima troca de head. Nenhum import ou update de banco executado nesta tarefa.
- **Retomar lendo:** `composer.json`, `composer.lock`, a decisão D-72 e este handoff.

### Continuação — revisão de compatibilidade ACULTA — 2026-11-03

- **Regra de agentes:** `docs/operations/AGENT-DEPENDENCIAS.md` agora exige inspeção dos contratos e módulos ACULTA dependentes sempre que Core ou contrib forem atualizados, além de registrar evidências e bloqueios.
- **Achado Simple Sitemap 5:** removida a opção `xsl` obsoleta da configuração exportada. O plugin ACULTA continua descoberto pelo manager 5.x (atributos e anotações); hooks de links e variantes usam APIs mantidas. README e changelog do módulo e tabela da raiz alinhados às versões do lock.
- **LMS:** referências a módulos contrib indisponíveis `lms`/`lms_classes` removidas das fixtures Kernel de Agenda; `aculta_lms` permanece como módulo da suíte. A referência `lms` sem uso na fixture do Sitemap também foi removida. Outros identificadores `lms` no código são purpose/contrato de produto ou fixtures explícitas de migração, não dependências Drupal ativas.
- **Compatibilidade restante:** Pathauto 8.x-1.x-dev corrige APIs para Core 12; OAuth 6.1.x-dev não afeta a configuração MCP atual (usa scopes, não cria consumers); Webform 6.x-dev mantém as APIs chamadas pelo ACULTA, mas ainda emite depreciações upstream para Drupal 12. Core 12 não foi ativado.
- **Falhas de container corrigidas:** DomainNegotiator sem `setActiveDomain()` foi substituído pelo contexto público; fixture Sitemap inclui `aculta_file_uploader`, `crop` e `image_widget_crop` e injeta `surface_config_reader`; fixture Agenda remove módulos `lms`/`lms_classes` não instalados e prepara entidades Group a partir de `config/sync`. Definições antigas de relacionamento tinham `use_creation_wizard`, fora do schema Group instalado, também removido.
- **Validação:** DomainPurposeManager Kernel 8/47, Sitemap Kernel 4/15, Agenda Kernel 4/160 e `WebformScoreOwnershipTest` 1/11 PASS em PHP 8.5.12. Depreciações Core/contrib: 60, 36 e 42, respectivamente. PHPUnit avisou que o diretório padrão BrowserTest não é gravável, sem impedir os Kernels. `git diff --check`, quatro PHP lint e runner estático do Sitemap PASS.
- **Versão:** Portal avançado para 0.4.1-dev.70; Core 12 não foi ativado. Nenhuma sincronização/import no Runtime.
- **Runtime:** sem sincronização/import. Drush confirmou `aculta_runtime`, mas `updatedb:status` tem 5 pendências (Simple Sitemap 8409 e post-updates de Text/User/Views), e `config:status` mostra drift amplo. Resolver o procedimento D-70 de snapshot/update/import antes da próxima troca de head. Core continua 11.5.0-beta1.

## Handoff — remoção de módulos de IA e definições de entidade/campo — 2026-11-03

- **IA:** nenhum módulo de IA estava habilitado ou instalado. Removidos os pacotes Composer Drupal AI e clientes DeepSeek/OpenAI já ausentes do lock atual; quatro submódulos opcionais que vêm dentro de pacotes contrib (CKEditor AI, CKEditor AI Assistant, Tool AI Connector e VVJ Core AI) estavam desabilitados e foram removidos do checkout. `scripts/remove-optional-ai-modules.php` torna essa remoção persistente após `composer install`/`composer update`.
- **Definições:** os updates customizados de calendário, doação, LinkWeed, mailer, mensageria e UGC já constam aplicados no Runtime. Criados updates para os campos Card do Commerce PaymentMethod e para migrar autores de revisão do Group preservando os valores antes de remover `revision_user`.
- **Status read-only após as correções existentes:** restam apenas as mudanças de PaymentMethod (4 campos) e Group (`revision_user` a desinstalar). `updatedb:status` lista os dois novos updates e quatro post-updates upstream de Text/User/Views. `config:status` mostra 86 diferenças.
- **Snapshot anterior às alterações de banco:** `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-entity-status-fix-20261103.sql`, SHA-256 `5dfe8df21e1246166948636fd5a3d0a738641aad606203fe83e474252faa6074`.
- **Bloqueio conhecido:** um post-update de Text falhou anteriormente por configuração de Views que referencia o plugin ausente `course_card_row`; por isso não executar import de configuração até resolver esse estado e confirmar os updates pendentes. Ainda não foi executado `updatedb` desta entrega.

## Handoff — campos de cartão do Commerce no Runtime — 2026-11-03

- Commit `879c544f27` e o update `aculta_pagamentos_mercadopago_update_10002` instalaram os campos `card_type`, `card_number`, `card_exp_month` e `card_exp_year` como campos do bundle de pagamento Mercado Pago.
- `PaymentCardFieldsUpdateKernelTest`: 1 teste / 17 assertions PASS em `aculta_simpletest`, com 13 depreciações Core/contrib.
- Verificação final no Runtime `toca`/`aculta_runtime`: resumo de mudanças de entidade/campo vazio, `updatedb:status` sem pendências e `config:status` sem diferenças. Nenhum `config:import` foi necessário.
- O preflight do Drush ainda emite avisos upstream de versão do Simple OAuth e post-updates antigos do Group; eles não impediram este update. Não reexecutar updates relacionados sem revisar esses avisos.

## Continuação — corrigir a sala de aprendizagem ACULTA — 2026-11-03

- **Achado LMS:** `CourseJourneyPresentationHooks::preprocessLearningRoom()` ainda verificava a rota removida `lms.group.answer_form`; respostas agora passam por `aculta_lms.answer_submission`, então a sala Ganjalab não envolvia o formulário atual.
- **Correção:** hook ajustado para a rota ACULTA; fixtures do composer agora usam os route IDs atuais. Novo `CourseJourneyPresentationHooksTest` prova que a rota ACULTA recebe o shell e preserva o formulário.
- **Validações:** Studio Unit completo 66/639 PASS; PHPCS Drupal/DrupalPractice nos quatro arquivos alterados PASS; gate Portal Drupal 11+ 1202 checks PASS; índice 53 componentes PASS; `git diff --check` PASS. PHPUnit avisou que `sites/simpletest/browser_output` não é gravável.
- **Runtime:** antes da publicação, `updatedb:status` sem atualizações e `config:status` sem diferenças; nenhuma escrita de banco/configuração necessária. Próximo passo após push: confirmar Runtime, então continuar a auditoria LMS; pagamentos e demais requisitos Drupal ficam para as etapas seguintes do objetivo.
- Snapshot pré-update protegido: `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-mp-card-fields-20261103.sql`, SHA-256 `81715e89580503147ba03ea4f5fdc9454518fd7d2a2efa243ea10e83da1271ce`.
## Continuação — páginas individuais de aula LMS — 2026-11-03

- A jornada do estudante agora fornece link por aula publicada e a rota
  `/curso/{curso}/{aula}` renderiza atividades ordenadas com acesso às
  submissões ACULTA. A autorização exige usuário autenticado, curso e aula
  publicados e matrícula vigente; posições de URL preservam aulas não
  publicadas na ordenação original.
- Kernel confirma URL e renderização de aula sem atividades, incluindo aula
  não publicada antes da visível: 1 teste/35 assertions PASS. Unit LMS 230/805
  PASS; PHPCS e PHP lint focais PASS. Há seis depreciações upstream no Kernel e
  quatro depreciações PHPUnit na suíte Unit.
- Arquivos da entrega: `web/modules/custom/aculta_portal/modules/aculta_lms/`
  (rota, serviços, acesso, controller, jornada, testes, versão e changelog),
  `docs/operations/status/aculta_lms.yml`, linha gerada do `README.md` e este
  handoff.
- Ainda não sincronizado no Runtime. Antes do push, conferir `updatedb:status`
  e `config:status`; em seguida publicar commit isolado e validar o Runtime.
- Próxima etapa do objetivo: concluir auditoria LMS, depois pagamentos e então
  resolver item a item os problemas reportados por `updatedb`.
## Continuação — regressão Kernel de pagamentos — 2026-11-03

- Atualizada a suíte `CheckoutProTest` para habilitar `text_with_summary`,
  necessário ao schema de `commerce_product.default.body` no Drupal atual.
  Antes, os três cenários falhavam durante a instalação do fixture, antes de
  executar código do gateway.
- Validação pós-correção: Unit do Mercado Pago 258/1.156 PASS; Kernels
  `CheckoutProTest` + `PaymentCardFieldsUpdateKernelTest` 4/152 PASS, com 72
  depreciações upstream. PHPCS e PHP lint PASS.
- Runtime: campos de cartão instalados, `updatedb:status` sem pendências e
  `config:status` sem diferenças; nenhuma chamada externa ou cobrança criada.
- Entrega em preparação: `aculta_pagamentos_mercadopago` 0.1.0-dev.85, teste,
  changelog, status e índice do README. Próxima etapa: inventariar os itens
  reais do `updatedb:status`/requirements e corrigi-los um a um.
## Continuação — erro de dependência Simple OAuth no relatório — 2026-11-03

- `core:requirements` indicava erro de versão para `simple_oauth_21` porque o
  checkout Git Composer de `drupal/simple_oauth` não inclui `version` no
  `.info.yml`. `ComposerVersionMetadataHooks` no owner `aculta_mcp` injeta o
  branch `6.1.x-dev` consultando `Composer\InstalledVersions`, sem ampliar a
  faixa de Core permitida pelo upstream.
- **Validações:** Unit novo 3/6 PASS; PHPCS Drupal/DrupalPractice e PHP lint
  focais PASS. Após `drush cr`, o `.info` descoberto mostra `6.1.x-dev`,
  `core:requirements` não tem mais erros e `updatedb:status`/`config:status`
  seguem limpos. `MIGRACAO-DRUPAL-12-MATRIZ-CONTRIB.md` registra que isso não
  equivale a compatibilidade Drupal 12; os módulos OAuth upstream ainda
  declaram suporte até Drupal 11.
- Restam avisos de requisitos independentes (módulos/temas depreciados,
  tradução, mídia duplicada, filtros redundantes, cache/render debug, Search,
  Security Review e estado de atualização do Core); devem ser avaliados em
  escopo próprio, preservando dados e sem desinstalação automática.
- Arquivos: `web/modules/custom/aculta_mcp/src/Hook/` e `tests/src/Unit/Hook/`,
  README do módulo, matriz Drupal 12 e este handoff.
## Correção posterior — registro da sincronização LMS

- O texto “Runtime ainda não sincronizado” na entrada inicial do LMS e “entrega
  em preparação” na entrada de pagamentos foram redigidos antes dos pushes.
  Ambos foram publicados e sincronizados: LMS `808879f6e2`, pagamentos
  `a799f461c8`; `drush cr`, `updatedb:status` e `config:status` passaram após
  cada push.
## Handoff — remoção autorizada do tema Stable 9 — 2026-11-03

- Removido `stable9` de `config/sync/core.extension.yml`, commit `1f7ac3348c` (`config: remove Stable 9 do Config Sync`), enviado a `origin/dev`.
- Runtime `aculta_runtime` (Drupal 11.5.0-beta1): snapshot antes da importação em `$SUITE_OUT/runtime-snapshots/aculta_runtime-before-stable9-uninstall-20261103.sql`, 64,433,105 bytes, modo 0600, SHA-256 `3dd1d7f02621e813011b578b4104de32ad1a44535bb5615ccce0e149036f24e2`.
- Preview de `config:import --diff` continha somente a remoção de `stable9`; importação concluiu. `drush cr` PASS. `updatedb:status`: sem atualizações; `config:status`: sem diferenças; temas ativos restantes: Claro, Bootstrap 8.x-3.45 e ACULTA420.
- `core:requirements` deixou de reportar Stable 9; permanece o aviso de Claro depreciado. Search e Text With Summary Field também seguem como módulos depreciados instalados; não foram desinstalados nesta entrega.
- Próximo: prosseguir no trabalho aberto do ACULTA420 com arquivos locais já modificados, revisar diffs por escopo e validar antes de qualquer commit. Preservar as alterações alheias existentes.
## Handoff — inventário do próximo bloqueio Drupal 12 — 2026-11-03

- Estado do Runtime após Stable 9: `updatedb:status` sem atualizações; `config:status` sem diferenças. Nada foi escrito no Runtime nesta fase.
- O Core permanece em `11.5.0-beta1`, fixado junto com `drupal/core`, `core-recommended`, scaffold, project-message, recipe-unpack e `core-dev` no `composer.json`. PHP é 8.5.12; Composer local é 2.8.8.
- A consulta Composer para `drupal/core 12.0.0-beta2` identificou bloqueios em dependências contrib ainda limitadas a `^11`, entre elas Commerce/Address, Bootstrap, CKEditor5 Plugin Pack, Tool/MCP, Webform, Simple OAuth, Search/Media e várias outras. O Core 12 também requer Symfony 8.1, Guzzle 8 e outras versões transitivas que precisam ser resolvidas em conjunto. Não houve mudança no `composer.json` nem no `composer.lock`.
- O tema Bootstrap instalado é `drupal/bootstrap` 3.45.0 (`8.x-3.45` no Drupal); os metadados Composer dessa release não aceitam Drupal 12. A release 5.0.2 também ainda declara apenas Drupal 10/11; a branch 5.0.x-dev declara `>11.2`, mas representa outra linha de framework e não foi instalada, para preservar a decisão explícita do responsável sobre a base Bootstrap 8.x. Necessário validar compatibilidade real e adaptar conforme essa decisão antes de resolver Core.
- A documentação Drupal consultada lista `12.0.0-beta2` e recomenda PHP 8.5+ e Composer 2.9.3. Como o alvo ainda é beta, atualizar primeiro o conjunto Composer em ambiente isolado/dry-run e validar os módulos; sincronizar Core no Runtime somente após solver limpo, verificações e snapshot.
- Próximo passo: levantar versões `*-dev`/alpha/beta com metadados Drupal 12 para cada bloqueio direto, atualizar em lotes pequenos e executar `composer update --dry-run` até resolver o conjunto. Não desinstalar módulos ativos só para satisfazer o solver; avaliar substituição compatível e preservar funcionalidade.
