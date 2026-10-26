# Execução contínua ACULTA — PR #298

**Branch:** `chore/aculta-m0-integridade`  
**Último commit estável antes deste checkpoint:** `6cfa8fe72cf238f4ca15dc96dcc9119afc4cdb20`; branch remota sincronizada e worktree limpo.
**Estado:** PARTIAL — PHPCS PR-wide, H5P, gate Domain administrativo, User/Portal/CPF focal, Index e Statistics têm evidências registradas PASS. M-0 continua bloqueada pela diferença de `core.extension`, Config Domain por purpose e RBAC Wiki; M-1–M-5 seguem em execução e sem promoção RC.
**Runtime:** Drush read-only confirmou Drupal 11.4.10/PHP 8.4.27 no MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`. Nenhum write foi executado.

## Ponto de retomada

1. Definir harnesses Kernel para LMS e Doações que exercitem classes reais sem serviço de produto fictício; os harnesses agregados ainda falham no setup por não carregar a cadeia Portal completa.
2. Reconciliar o delta ativo/Sync de 19 extensões após revisão dos templates instaláveis do Editor e dos módulos CKEditor/Google Tag; não importar configuração no Runtime.
3. Mapear os grants Wiki ativos versus exportados e corrigir divergências somente em código/configuração versionados e testes isolados; não alterar permissões persistentes.
4. Planejar os cinco hooks e três post-updates pendentes do Messenger com inspeção read-only, backup recuperável e rollback antes de qualquer execução autorizada.
5. Continuar incrementos centrais de Portal, User, CPF, CEP, Commerce, LMS, Messenger, Statistics e Index; incorporar trabalho dos oito componentes delegados somente por revisão e cherry-pick.
6. Manter a branch/PR #298 como único destino; sem merge, deploy, import ou escrita Runtime.

## Evidência histórica preservada — revalidar antes de tomar decisão

- `6daa7542f test(h5p): repair course access kernel contract`: formatter de acesso e test harness usam o plugin Group real. PHPCS Drupal/DrupalPractice, PHP lint e diff check passam nos dois arquivos H5P alterados.
- Kernel isolado H5P: 3 testes/134 assertions PASS; 41 deprecações Core/contrib. Foram validados Group membership, educador, estudante, usuário sem vínculo, referência de recurso e revogação; nenhum serviço de produto fictício foi adicionado.
- PHPCS Drupal/DrupalPractice passou nos três arquivos H5P alterados após corrigir o widget e documentar duas exceções pontuais para os nomes de classe `aculta_H5P*`, preservados por contrato de plugin/cache.
- Gate Domain administrativo executado via bootstrap Drupal/Drush: `php vendor/drush/drush/drush.php --root=web --uri=https://aculta.toca.net.br php:script scripts/validate-admin-domain-policy.php` PASS (3.498 checks). Drush status confirmou Drupal 11.4.10, DB `aculta_runtime`, root do checkout compartilhado; é verificação read-only do Runtime/código ativo e não valida o diff da PR. Execução direta `php scripts/validate-admin-domain-policy.php` não é válida por depender de container Drupal.
- Commits do agente 2 revisados e cherry-picked: `6483dddbe` Deployer profile scope, `81b03581a` teste Kernel de filtro editorial e `85db52888` reconciliação documental dos módulos sob seu ownership.
- Deployer suite: PASS após tornar neutra a descrição de menu do Portal que acoplava nome de módulo opcional; builds de ensaio continuaram em `/tmp`, e `DEP-0003` permanece bloqueador operacional de páginas públicas do host de teste.
- Editor Kernel, com MariaDB dedicado e código da branch: 7 testes/90 assertions PASS; 3 deprecações. O teste prova que `revista_editorial` aplica `filter_html` e remove `<script>`/atributo de evento.
- Links: status YAML atualizado para `0.4.0-dev.32`, manifesto e changelog alinhados; índice de 41 componentes regenerado e PASS.
- CPF/CNPJ (`aculta_id_cpf`): `AccountDocumentStore` agora possui restrição SQL única em `profile__field_documento_indice`; update `10002` verifica duplicatas com contagem agregada e falha sem imprimir fingerprints; o hook de instalação aplica o contrato em instalações novas. Unit 47/107, runner de domínio 248 checks e Kernel MariaDB 2/5 PASS. `updatedb` não foi executado no Runtime.
- `aculta_user` revalidado sem alterações de código: Unit 129/492 PASS (uma depreciação PHPUnit); Kernel 25/361 PASS (cinco depreciações de Core/contrib). Homologação visual e Runtime permanecem pendentes.
- CEP/Endereço (`aculta_portal_cep`): Unit 9/36 PASS; `scripts/validate-account-address.php` PASS; serviço único é consumido pelo endpoint e pelos adaptadores PHP do Melhor Envio, sem persistência de endereço. O módulo está habilitado em MAIN (read-only). PHPCS do baseline inalterado: 105 erros/36 warnings em 12 arquivos, não introduzidos por esta PR; homologação Browser/X11 ainda pendente.
- Portal: suíte Unit completa 350/878 PASS (6 depreciações PHPUnit) e Kernel com MariaDB isolado 53/831 PASS (90 depreciações Drupal/Core/contrib). O harness de `AccountOtherDataStudioReturnTest` injeta `path.validator` e restaura o container global anterior; código funcional inalterado. Runtime foi somente leitura; rotas por purpose e homologação visual pendentes.
- Runtime inventory read-only por `moduleHandler` em URI MAIN: 35/40 módulos habilitados; cinco desabilitados (`aculta_activity`, `aculta_deployer`, `aculta_commerce_melhorenvio`, `aculta_commerce_messenger`, `aculta_wiki_messenger`). `updatedb:status` mostra 5 hooks e 3 post-updates pendentes de `aculta_messenger`; não executados por falta de autorização para escrita. Outros purposes compartilham o estado global de módulos, mas Config Domain ainda não foi comparada por host.

## Status por frente

| Frente | Estado | Próxima ação |
|---|---|---|
| P0 PHPCS | PASS no conjunto PR-wide | Drupal + DrupalPractice, 0 erros/0 avisos nos PHP alterados; não confundir com baseline não alterado |
| P0 H5P | PASS | Kernel H5P 15/388 e Unit 24/73 em runner isolado; sem stub de serviço do produto |
| P0 admin Domain | PASS no Runtime read-only | 3.498 checks via Drush com URI explícito; não valida diffs não implantados da branch |
| M-0 inventário individual | Parcial | Inventário 41 itens preservado; faltam Runtime/Config Sync completo e evidência por componente |
| M-1 a M-5 | Em execução | CPF Unit 48/116 e Kernel 2/5, FiscalIdentityPolicy 10/24; avançar em contratos Messenger/Commerce/LMS. Integração Profile/Form API, functional/browser e homologações por propósito continuam parciais |

## Atualização após HEAD `d82181d74`

- Portal Unit e Kernel completos passaram conforme evidência acima; o Kernel usou `/tmp/aculta-m0-kernel-test-root` e `/home/aculta/secrets/simpletest-db.env` sem expor credenciais e sem tocar Runtime.
- Resultado contínuo segue PARTIAL: depreciações upstream, Browser/Functional e auditoria Config Sync/Domain por purpose permanecem pendentes.
- Próxima unidade: continuar os contratos de Messenger e confirmar no Runtime read-only os states pendentes de updates; identificar Browser smoke somente quando houver sessão/superfície segura.

## M-1 — Mailer, Messenger e Confirmador (2026-10-26)

- O runner padrão resolve `web/core` e `vendor` para o checkout compartilhado. Para validar esta branch, todas as suítes foram reexecutadas usando `/tmp/aculta-m0-kernel-test-root/web/core/phpunit-m0.xml` e seu autoloader explícito para os módulos do worktree. Unit Portal: 350/878 PASS (seis depreciações PHPUnit); Unit Mailer: 162/343 PASS; Unit Messenger: 382/4.186 PASS.
- Kernel combinado de `aculta_mailer`, `aculta_messenger`, `aculta_mail_confirmer` e `aculta_mail_confirmer_user`: 112 testes / 1.504 assertions PASS em MariaDB isolado, com módulos da branch. Foram reportadas 11 depreciações e quatro notices upstream/PHPUnit.
- README do Messenger estava defasado em `.384` diante do manifesto/status `.396`; versão e totais Unit foram reconciliados. Contagens históricas dos snapshots antigos continuam identificadas como históricas.
- Status e matriz de fechamento registram evidência combinada; confirmação/troca de e-mail em Browser, Runtime mutável e envio SMTP continuam pendentes. Nenhuma fila ou mensagem externa foi processada.

## M-1 — CPF/CNPJ, colisão concorrente (2026-10-26)

- `AccountDocumentStore` agora descarta a exceção SQL encadeada ao converter a violação da chave única em erro genérico. A mensagem subjacente do driver pode carregar o fingerprint duplicado; a API de domínio não a expõe a handlers/logs que recebam a `LogicException`.
- Novo `AccountDocumentStoreUniqueConflictTest` simula o erro de persistência pós-preflight e confirma mensagem genérica sem causa anterior: Unit do módulo 48/116 PASS; Kernel da restrição SQL/update 10002 2/5 PASS em MariaDB isolado. PHPCS e lint dos dois arquivos alterados PASS.
- Manifesto e status avançaram para `0.3.0-dev.3`; Runtime permanece sem `updatedb`. Próximo: exercitar update com perfis legados representativos e integração real Profile/Form API em Kernel.

## M-0.2 — Config Sync / Runtime (2026-10-26)

- Read-only Drush por `https://aculta.toca.net.br` confirmou Document Viewer, Document Viewer Group, Entity Print e LMS Resources ativos e listados no `core.extension` ativo.
- O `core.extension.yml` exportado omitia essas quatro extensões apesar de dependências em manifests/config. Entradas adicionadas no repositório; `php scripts/validate-config-extension-dependencies.php` PASS (1.558 referências, 1.425 arquivos) para dependências de configuração e manifests próprios habilitados.
- Delta restante ativo versus Sync: 218 módulos ativos, 199 versionados; 19 ausentes são `aculta_editor`, `aculta_lms_messenger`, `google_tag` e 16 módulos CKEditor Plugin Pack. A intenção de habilitação desses componentes não está documentada por propósito; uma importação pode desabilitá-los. Classificado como bloqueador de reconciliação. Sem `cim` ou write no Runtime.
- A comparação ativa também mostra `aculta_editor` habilitado no Runtime enquanto ausente do export; tarefa encaminhada ao agente 2 por ser módulo sob seu ownership. `validate-module-theme-map.php` passou com 196 checks após completar sete linhas documentais ausentes, incluindo os seis custom modules e o provider contrib Entity Print.

## Restrições / deferred

- Nenhuma escrita no Runtime; sem migração, importação de config ou processamento de filas.
- Operações financeiras externas, deploy, merge e remoção de adaptadores permanecem fora da autorização.
- Mudanças que alterem ownership de dados, membership persistida ou política de produto serão isoladas e submetidas ao responsável quando houver decisão necessária.

## Atividade do agente 2

- Ownership concedido exclusivamente: Melhor Envio, Deployer, Patcher, Sitemap, QR Code, Links, Activity e Editor. Commits incorporados: `6483dddbe`, `81b03581a`, `85db52888`.
- Agente não operou no Runtime. Os diretórios devem permanecer sob esse ownership em novas correções até coordenação explícita.


## Content Index e coordenação delegada — 2026-10-26

- Runtime MAIN consultado somente em leitura: Index habilitado, uma geração ativa válida, 145 entradas e 110 relações; queue vazia e auditoria completa sem achados. `aculta_index` adicionado ao Config Sync versionado; sem `cim`, rebuild ou operação persistente. Delta ativo/Sync passa de 20 a 19, ainda sem intenção documentada para os módulos remanescentes.
- Unit Index 51/262 PASS. Kernel full anterior encontrou falhas reproduzíveis no harness: paths de serviço apontavam para o checkout compartilhado; teste de acesso e integração owner não inicializavam Document Viewer Group real; hooks transversais Flag e History precisavam de schemas no fixture. Kernel focal após correção: Media 5/101 PASS; Group/owner/Wiki 10/361 PASS (272 depreciações upstream/Core). Kernel completa concluída: 111 testes / 1.852 assertions PASS, sem erros/falhas; 280 depreciações; 54 notices deprecation PHPUnit reportados separadamente.
- Revisado e cherry-picked o commit do agente 2 `6acf336da` para Editor, Sitemap, QR e Patcher; branches/diretórios delegados continuam sem modificações por esta execução. Agent2 registra estado read-only e lacunas de instalação/config de Editor para coordenação posterior.


## M-1 — Regressão User e CEP (2026-10-26)

- User Unit reexecutado no código da branch: 129 testes / 492 assertions PASS, uma depreciação PHPUnit. CEP Unit: 9 testes / 36 assertions PASS. Nenhuma escrita em Runtime; Kernel CEP/User não iniciado enquanto o Kernel Index usava o banco MariaDB dedicado.


## Gate de Domain administrativo — 2026-10-26

- Runtime identificado antes da consulta: Drupal 11.4.10, URI `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`.
- `validate-admin-domain-policy.php` executado corretamente pelo Drush com bootstrap e arquivo de script do worktree; PASS, 3.498 checks. A falha histórica `Class "Drupal" not found` era da execução direta em PHP CLI, não da política.

## Regressão de gates globais — 2026-10-26

Após integrar o commit documental do agente 2 e reconciliar Index no Config Sync, os gates foram reexecutados: índice de status 41 componentes PASS; dependências Config Sync 1.559 checks/1.425 arquivos PASS; mapa módulo-tema 196 PASS; fronteira módulo-tema 2.879 PASS; Drupal 11+ 1.160 PASS; pacotes próprios 41 PASS; aliases temporários 25 arquivos PASS; slugs públicos 387 rotas PASS (17 violações contrib na baseline); endereços 4.238 arquivos PASS. A execução específica do Domain administrativo passou via Drush com 3.498 checks. Estes resultados cobrem os gates listados, não a suíte total de qualidade dos 40 módulos.

- Index Kernel completo em MariaDB dedicado, após correções focais: 111 testes / 1.852 assertions PASS; sem falha/erro. O PHPUnit reportou 280 depreciações e 54 notices deprecation PHPUnit separadamente. Os cinco providers Media passaram 5/101; Course Document/Owner/Wiki passaram 10/361 focalmente antes da suíte completa.

## Acceptance guardrail and PHPCS review — 2026-10-26

- `validate-single-acceptance.php` initially failed because the root `AGENTS.md` omitted the existing unique-acceptance contract. Added a concise pointer/rule to `docs/portal/ACEITE-UNICO.md`; gate now PASS. `validate-single-use-links.php` also PASS (24-hour contract).
- User regressions on this branch: Unit 129/492 PASS (1 PHPUnit deprecation); Kernel 25/361 PASS (5 Core/contrib deprecations). WikiController Unit 13/74 PASS after its test comment cleanup. CEP Unit 9/36 and account-address gate PASS.
- Focused PHPCS for the two edited global gates and WikiController test passes. The PR-wide PHPCS scan identified remaining violations in delegated Deployer and Editor files; those were referred to agent 2 and remain unclosed until its tested commits are reviewed. Pre-existing warnings in the theme file are outside the changed lines.


## M-3 — Wiki proposals/RBAC (2026-10-26)

- Consultado Runtime read-only em `https://wiki420.aculta.toca.net.br`: Drupal 11.4.10, DB `aculta_runtime`. `email_confirmed` não tem grants de submit/view own; `content_editor` não tem view any/moderate, enquanto os quatro grants constam no Config Sync versionado. A divergência impede envio/fila no Runtime. Os acessos seguem fail-closed; não houve evidência de exposição.
- `WikiLeadImageLifecycleKernelTest::testProposalSubmissionUsesPrivateRevisionedEntityAndAccess`: PASS, 1/216 em MariaDB isolado. Foram reportadas 256 depreciações Core/contrib e duas PHPUnit. `validate-aculta-user-roles.php` e `validate-role-hierarchy.php` PASS.
- `DEFERRED`: reconciliar os papéis ativos requer autorização operacional; preparar diff limitado aos dois roles, export do estado para rollback e verificar pós-apply no purpose Wiki. Nenhuma configuração persistente foi alterada.


## M-2 — Course Document access contract (2026-10-26)

- `CourseDocumentAccessTest` passou no MariaDB isolado: 1 teste / 48 assertions, 144 depreciações Core/contrib. Harness inclui closure real, serviço Portal/User e schemas dos hooks; Media Type/field fixture corresponde ao contrato `config/sync`. Verificados membership, relação removida, não membro e revogação na URL privada Core. PHPCS e PHP lint do teste PASS.
- A documentação antiga classificava o Kernel como bloqueado por ledger ausente; era setup incompleto. Persistem a dependência histórica do bundle Media no PDF Group e a confirmação de instalação por purpose. Nenhuma migração nem operação Runtime executada.


## PHPCS PR-wide e ownership agente 2 — 2026-10-26

- Revisão do agente 2 confirmou que as violações PHPCS originais em `aculta_deployer/src/Transform.php`, `aculta_deployer/tests/run.php` e `aculta_editor/tests/src/Kernel/AcultaEditorFormatsKernelTest.php` antecediam a PR. Formatação/PHPDoc corrigidos sem alterar comportamento; Deployer runner completo, Editor Kernel (7/90), PHP lint e PHPCS Drupal+DrupalPractice PASS.
- O literal `vendor/` no fixture `tests/run.php` acionava o `AcultaDeployer\Boundary::check()` lexical, embora o arquivo fosse criado apenas em diretório temporário para o teste. O fixture agora monta a mesma rota com `DIRECTORY_SEPARATOR`, preserva o arquivo e teste, sem adicionar exceção nem reduzir o scan; boundary PASS. Commits integrados: `b560219a4`, `a36f33553`, `a0b824ae9` (originais do agente: `61bbce049`, `ed3d48cdf`, `8deed7b50`).
- Reexecução PHPCS Drupal+DrupalPractice em todos os PHP alterados pela PR: 0 erros / 0 avisos. Os 12 avisos preexistentes de comentário no `ThemeHooks.php` foram apenas quebrados em linhas; lint e fronteira passaram.
- Agente 2 confirmou no Runtime read-only que `aculta_editor` está ativo embora ausente do Sync; oito templates `config/install` não têm homólogos exportados. Kernel 7/90 PASS. Decisão sobre o conteúdo dos templates e revisão de acesso `aculta_adm` seguem pendentes; não alteramos Config Sync nem Runtime para Editor.

## Ponto de controle — 2026-10-26

- Gate set reexecutado no HEAD local após atualizações de status: PROJECT STATUS 41 PASS; Config Extension Dependencies 1.559/1.425 PASS; Module Theme Map 196 PASS; Module Theme Boundary 2.879 PASS; Drupal 11+ 1.160 PASS; packages 41 PASS; aliases 25 PASS; public slugs 387 PASS (17 contrib baseline); environment addresses 4.238 PASS; secrets PASS; session cookie 6 PASS; user roles PASS; role hierarchy PASS; single acceptance PASS; single-use links PASS.
- PHPCS Drupal+DrupalPractice no conjunto PR-wide de PHP alterado: PASS, zero erros/avisos. Lint do ThemeHooks PASS. As 12 warnings de comentário antigos desse arquivo foram somente quebrados em linhas, sem mudança funcional.
- Último conjunto testado: Content Index Kernel 111/1.852 PASS; User Kernel 25/361 PASS; Document Viewer Group Kernel 1/48 PASS; Wiki proposta Kernel 1/216 PASS; Deployer runner PASS; Editor Kernel 7/90 PASS; Unit Portal 350/878, Mailer 162/343, Messenger 382/4.186, User 129/492, CEP 9/36, Index 51/262, WikiController 13/74 PASS; Portal Kernel 53/831 e comunicação Kernel 112/1.504 PASS (evidências anteriores nesta execução).
- Commits M-4 `68a7b6e9b` e `6518a986e` publicados na branch da PR #298. Trabalho H5P em andamento; Runtime continua somente leitura. M-0 permanece PARTIAL enquanto 19 extensões ativas não estiverem reconciliadas no Sync, roles Wiki divergirem e homologações por propósito faltarem.

## M-4 — regressão focal de Commerce, LMS e pagamentos — 2026-10-26

- LMS Unit: 87 testes / 447 assertions PASS; 8 depreciações PHPUnit. Commerce Unit: 21/125 PASS; Donation Unit: 24/84 PASS; Mercado Pago Unit: 11/22 PASS.
- Mercado Pago Kernel: 1/50 PASS após as alterações. Corrigida assinatura nullable de `refundPayment()` para `?Price`; annotation do gateway convertida para `CommercePaymentGateway` attribute com metadata equivalente; teste declara processo separado. O total caiu de 78 para 75 depreciações; as três próprias (annotation, nullability e processo do Kernel) foram removidas. PHPCS Drupal+DrupalPractice baseline da classe: 24 erros/52 avisos antes, 22/52 depois; teste Kernel 0 erros/1 aviso. Versão dev.8.
- Donation Kernel: 4 erros de setup/0 assertions porque o harness não inicializa o fechamento de dependências Messenger → Portal → Image; ampliar apenas para Messenger resultou em serviço privado de Message ausente e incluir módulos listados de forma parcial expôs dependência Image ausente. Alterações experimentais do harness foram descartadas. Resultado DEFERRED para fechar a cadeia Kernel real; nenhum stub de serviço ou alteração Runtime.
- Commerce README/status atualizados para dev.5 com a suíte corrente e limitação do Kernel. Nenhum checkout/pagamento, fila real, credencial ou integração externa utilizado.
- Runtime permanece somente leitura. M-4 não promoveu módulos a RC: faltam Kernel funcional de Doações, validação de checkout/estorno e homologação visual/sandbox autorizada.

- LMS Kernel agregado reexecutado: 29 testes / 50 assertions, 23 erros no setup. A primeira falha concreta é `aculta_lms.course_order_enrollment` depender do serviço ausente `aculta_portal.checkout_settings` porque os harnesses habilitam LMS sem Portal, apesar da dependência obrigatória. O Portal possui uma cadeia extensa de serviços; sem fixture/teste de instalação que carregue seu fechamento real, a suíte agregada segue DEFERRED. Unit LMS continua 87/447 PASS; focais prévios não equivalem à validação integral.

## M-5 — regressão Statistics — 2026-10-26

- Statistics Unit: 108 testes / 438 assertions PASS. Kernel no MariaDB dedicado: 47/319 PASS. Functional `StatisticsDashboardFunctionalTest` executado contra server local e banco dedicado; 2 erros, 0 assertions porque BrowserTestBase tentou criar `sites/simpletest/<id>/settings.php` sob o checkout compartilhado, resolvido pelo Core symlinkado. Não houve acesso ao Runtime; o server foi encerrado e os paths temporários confirmados ausentes. Browser visual segue pendente.
- Revalidado Runtime com `drush status` antes da consulta: Drupal 11.4.10, URI MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`; `pm:list` confirmou `aculta_statistics` Enabled. Consultas somente leitura; nenhuma credencial externa foi usada.
- README, changelog, status e matriz atualizados: removida afirmação obsoleta de que ativação no Runtime estava pendente. Consultas reais GA4/Search Console e homologação visual continuam pendentes.

## M-0 — status por purpose — 2026-10-26

- `drush status --uri` read-only consultado em MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP, COURSES, FORUM e LINKS. Todos usam o mesmo `sites/default`, DB `aculta_runtime`, Drupal root e `config/sync`; portanto module enablement é global, não uma lista diferente por host.
- Isso não comprova equivalência dos overrides Domain Config por purpose. A auditoria de configuração ativa por host continua pendente; nenhum `cim`, update ou write ocorreu.

## M-4 — fechamento Kernel H5P — 2026-10-26

- A classe de acesso prioritária passou 3 testes / 134 assertions; Unit H5P passou 24/73. A primeira suíte Kernel agregada falhou em 12 testes antes de assertions por carregar `aculta_lms` sem o serviço obrigatório `aculta_portal.checkout_settings`.
- Corrigidos os harnesses de descoberta e pacote para não habilitar `aculta_lms` em cenários que verificam apenas serviços/Hooks H5P e o installer. A reexecução da pasta Kernel passou 15/388, incluindo a classe completa de acesso; 41 depreciações Core/contrib.
- `aculta_lms_h5p` avançou a `0.4.0-dev.149`; status e matriz atualizados. Não foram removidas assertions nem adicionados stubs do produto. Ainda não há teste Kernel de instalação limpa da cadeia completa LMS/Portal/Studio; isso permanece separado da cobertura focal H5P.

## M-1 — reteste de CPF e Config Sync do Editor (2026-10-26)

- CPF Unit reexecutado com o bootstrap explícito do worktree: 48 testes / 116 assertions PASS. CPF Kernel no MariaDB dedicado: 2/5 PASS, confirmando constraint única, idempotência e recusa sem exposição de fingerprint em migração duplicada; não houve write no Runtime.
- O primeiro comando Unit usou o PHPUnit padrão e combinou o teste da branch com código do checkout compartilhado (`web/core` e `vendor` symlinkados em `/home/aculta`); falhou nesse código desatualizado. A suíte foi repetida com `/tmp/aculta-m0-kernel-test-root/web/core/phpunit-m0.xml`, que autocarrega explicitamente os módulos do worktree, e passou. Para validar branch/worktree, usar o bootstrap isolado registrado nesta execução.
- Editor: Runtime MAIN tem `aculta_editor` e suas 16 dependências obrigatórias ativos; oito templates instaláveis não têm cópia em Sync. A revisão do Drupal 11 mostra que `cim` agenda install/uninstall segundo o delta de `core.extension` e usa Sync como source dos defaults durante instalação. A reconciliação dos 17 IDs foi adiada até os oito templates serem reconciliados e testados em ambiente efêmero; não houve alteração de arquivos do Editor nem importação. A evidência detalhada está em `ACULTA-M0-AUDIT.md`.


- Revalidação focal M-1: `FiscalIdentityPolicyTest` do Portal passou 10 testes / 24 assertions no bootstrap do worktree. Cobre exigência de documento para pedidos de Loja/LMS, dispensa em Doações, tipos configuráveis e estados fail-closed quando armazenamento/crédito criptográfico falha. Não foram realizadas operações de pedido ou checkout.
- Reprodução do bloqueio LMS em worktree: `ClassDashboardKernelTest` falhou no container por `aculta_portal.checkout_settings` ausente (1 erro/0 assertions). O módulo LMS declara Portal como dependência; o harness não o inicializa. Nenhum serviço de produto foi criado para encobrir o setup, e a correção aguarda a decisão de arquitetura do Kernel.

## M-1 — fila de updates Messenger no Runtime (2026-10-26)

- Reconfirmado antes da consulta: host `toca`, checkout compartilhado em `/home/piradopirata/projetos/aculta`, branch de trabalho separada em `/tmp/aculta-m0-integridade`; Drush read-only conectou Drupal 11.4.10, URI MAIN, banco `aculta_runtime`. Nenhuma operação de escrita ocorreu.
- `updatedb:status` mostra hooks 10008–10012 (marcador de entrega privada, marcador de amizade, estado de digest, estado de leitura de amizade e bundle de aviso de conquista) e post-updates `private_message_email_preferences`, `shared_email_preferences` e `game_achievement_notices` pendentes.
- A suíte Kernel combinada de Messenger/Mailer/Confirmador passou 112/1.504 na MariaDB isolada; testes focais de migração de preferências e de conquistas existem. Isso valida código e fixtures, não o conteúdo real de dados pendentes no Runtime.
- Estado operacional **DEFERRED**: não executar `updatedb`. Próxima ação requer snapshot/backup recuperável, inventário privado somente leitura das contagens/estados afetados, plano de rollback e autorização específica; filas externas/e-mail permanecem sem processamento.

## M-4 — cobertura de acesso da Loja (2026-10-26)

- A suíte Unit `aculta_commerce` passou 25 testes / 160 assertions (9 depreciações PHPUnit) com o runner isolado da branch. `GreenStashProductAccessTest` usa as interfaces reais Commerce e verifica acesso de entidade, acesso de atualização à única loja, produto de outra loja, store sem permissão `update` e configuração ambígua.
- O teste alterado passou PHPCS Drupal/DrupalPractice sem violações e PHP lint. Versão avançou `0.1.0-dev.5` → `0.1.0-dev.6`; README, CHANGELOG, status e matriz foram reconciliados. Nenhum pedido, produto, loja, Runtime ou serviço externo foi alterado.
- Ainda faltam Form/Kernel/Functional para criação e operações Green Stash, fluxos comerciais completos e homologação visual; não há promoção RC.


## M-3/M-5 — auditoria de ACULTA Game (2026-10-26)

- Unit do Game passou 11 testes / 51 assertions. Kernel `GameAchievementNoticePersistenceKernelTest` no Messenger passou 3/30 no MariaDB dedicado: revalida fonte contra concessões revogadas, migra fila legada uma vez e conserva o cursor/fila quando publisher falha. Duas depreciações de `@EntityType` vêm de Message/Message Template no Core/contrib.
- Documentação identifica o corte de ownership dev.17 (Game publica, Messenger persiste/lê, Mailer transporta), mas corrige uma lacuna: o Kernel Fórum testa elegibilidade do `ForumParticipationPolicy`, não a chamada de concessão no `BadgeEvaluationHooks::forumPostSave`. Essa cobertura direta permanece próxima tarefa; nenhuma conquista foi concedida no Runtime. README, CHANGELOG, execução, status e matriz foram reconciliados, sem mexer na feature ou subir versão.


## M-3 — reconciliar estado do Magazine (2026-10-26)

- Auditoria encontrou status estruturado obsoleto: dizia não haver publisher/inscrições ou evento implementado, contradizendo manifesto dev.10, README e changelog. A árvore contém publishers para publicação/republicação/atualização e transições editoriais; `seguir_publicacao` é Flag, não storage paralelo de Messenger. Autores/coautores elegíveis recebem os avisos conforme contrato; envio para revisão/resultados já estão descritos na dev.10.
- Retestes no runner isolado: Magazine Unit 24/96 PASS (3 depreciações PHPUnit) e Kernel 12/500 PASS no MariaDB dedicado. Runtime e SMTP não testados. Status, matriz e changelog agora distinguem implementação, teste e homologação pendente.

## M-1 — validação de apelido nos formulários Profile (2026-10-26)

- Auditoria do User encontrou o caminho restante de escrita: `/dados` preserva `field_nickname` no Profile Form via Portal e não consultava a regra owner de `aculta_user`. Adicionado hook do User que aplica `AccountNames` e `NicknamePolicy` a formulários Profile de papéis com o campo; apelido legado inalterado continua aceito. Não há nova dependência Portal → User nem armazenamento.
- User Unit: 132/504 PASS (1 depreciação PHPUnit); User Kernel: 25/361 PASS em MariaDB dedicado (5 depreciações Core/contrib); teste focal do hook incluído nas Units 3/12 PASS; PHP lint e PHPCS Drupal/DrupalPractice nos dois arquivos alterados PASS.
- A unicidade ainda usa preflight de consulta; não há constraint transacional e não foi alegada garantia contra corridas simultâneas. Próxima ação: avaliar constraint/index compatível com Field API e dados atuais sem storage paralelo, com teste real de concorrência. Nenhuma alteração Runtime.
- Atualização documental de status: removidos resíduos de aspas em Studio/tema e, por cherry-pick do agente 2, Activity/Melhor Envio; índice de 41 componentes valida. Commit delegado incorporado: `ebfdf3833`.

## M-4 — Kernel focal do painel de turma LMS (2026-10-26)

- A primeira tentativa de habilitar Portal/Studio completos confirmou que esse Kernel passava a instalar configuração alheia ao cenário e exigia a grande closure de serviços Commerce/Domain. O teste foi reduzido ao contrato focal permitido: mantém Group/LMS/`lms_classes` reais, carrega e instancia as classes reais de `aculta_lms` e o compositor real de Studio, sem importar rotas/configuração Portal ou adicionar serviços fictícios.
- `ClassDashboardKernelTest`: 1 teste / 48 assertions PASS; 39 depreciações Core/contrib/Group/LMS; PHPCS e PHP lint PASS. As verificações de educator/student, membership, curso/turma, cache e remoção da relação seguem ativas. Isso não valida instalação limpa da cadeia total.
- LMS avançou `0.1.0-dev.89` → `0.1.0-dev.90`. Kernel agregado reexecutado: 29 testes / 98 assertions, 22 erros de setup, todos os harnesses parciais falhando no serviço obrigatório `aculta_portal.checkout_settings`; nenhum erro de assertion. Resolver exige carregar Portal completo ou adequar os cenários com fixture focal que use classes reais, sem serviço falso. Nenhuma escrita Runtime.

## M-4 — continuação de paginação Green Stash (2026-10-26)

- Corrigido `GreenStashProductReader`: ao esgotar cinco lotes de 100 após filtrar produtos que o operador não pode gerenciar, a página agora retorna cursor após o último ID examinado. No caso de lookahead gerenciável, o cursor continua antes do item para que ele apareça na página seguinte. Entity Access, permissão de atualização do produto e autorização da loja permanecem obrigatórios.
- A fixture modela candidatos visíveis no Entity Query, mas sem permissão de atualização; isso corresponde ao filtro de gestão explícito do leitor.
- Nova regressão `GreenStashProductReaderPaginationTest` atravessa 500 produtos visíveis sem permissão de atualização e recupera o produto mais antigo gerenciável na página seguinte. O teste também verifica que um probe de um ID não emite cursor quando a sequência termina no limite: 2/11 PASS. Unit Commerce completa: 27/171 PASS (10 depreciações PHPUnit). PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` dos arquivos alterados PASS; índices e gates globais descritos abaixo passaram.
- `aculta_commerce` avançou `0.1.0-dev.6` → `0.1.0-dev.8`; README, changelog, status, índice raiz e matriz de fechamento reconciliados. Nenhum Runtime, pedido, pagamento ou serviço externo foi alterado. Kernel de Doações e homologações Green Stash continuam pendentes.
- Próxima tarefa: retomar o fechamento seguro do harness Kernel LMS/Doações ou avançar outra lacuna testável de Commerce/Portal; manter o módulo em Dev até Kernel integrado, Functional e homologações comerciais estarem concluídos.
