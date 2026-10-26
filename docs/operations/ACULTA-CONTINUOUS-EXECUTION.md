# Execução contínua ACULTA — PR #298

**Branch:** `chore/aculta-m0-integridade`  
**Último commit:** `af3a05f27` (`docs(growroom): set test runtime import as target`), enviado por push normal a `origin/chore/aculta-m0-integridade`. Commit anterior `d3357b474` corrigiu a suíte Kernel parcial LMS para 29/835.
**Estado:** PARTIAL — PHPCS PR-wide, Kernel H5P, gate Domain administrativo e suíte Kernel parcial LMS têm evidências PASS. M-0 continua parcial por instalação/importação de acervo em ambiente efêmero ainda não exercida, divergência ativa dos grants Wiki e Config Sync por purpose. Melhor Envio foi confirmado como extensão fora do Sync. Messenger tem preferências ativas sem contrato canônico decidido e updates operacionais pendentes. M-1–M-5 seguem em execução; nenhum componente foi promovido.
**Runtime:** Drush read-only confirmou Drupal 11.4.10/PHP 8.4.27 no MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`. Nenhum write foi executado.

**Domain/purpose:** em 2026-10-26, `DomainPurposeManager::getCurrentPurpose()` resolveu os nove hosts MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP, COURSES, FORUM e LINKS ao purpose correspondente (9/9 PASS). As seis coleções Domain ativas têm nomes idênticos ao Sync; cinco também coincidem em conteúdo. O front da Conta foi corrigido no Sync de `/studio` para `/minha-conta`, conforme Runtime e contrato; o acesso HTTP direto ao path segue 404. Na coleção `domain.revista420_aculta_org`, o Runtime ainda diverge em `system.site.name` e em `metatag.metatag_defaults.front` (`tags.title`, `tags.og_title`); o Sync mantém o contrato editorial `Revista 420`. Nenhum valor ativo foi alterado. Entity Access por purpose não está homologado.

## Ponto de retomada

1. LMS: os harnesses Kernel parciais agora passam com classes reais dos owners; permanece separado um teste de instalação limpa da closure e de toda a configuração Portal/Messenger/Studio.
2. Editor/CKEditor: 17 IDs e nove configs reconciliados; delta de módulos permanece só `aculta_lms_messenger`, shell que requer uninstall controlado. Importação isolada e Browser seguem pendentes.
3. Wiki: leitura de `wiki420` confirmou os quatro grants de proposta ausentes dos papéis ativos `email_confirmed` e `content_editor`, apesar do Sync correto; Entity Access falha fechado. Só aplicar depois de autorização de escrita, com rollback dos dois roles.
4. Manter bloqueados os cinco hooks e três post-updates pendentes do Messenger até a janela/autorização; o plano read-only registra cinco filas de conquista sem dados pessoais e snapshot/rollback integral em `ACULTA-MESSENGER-UPDATE-PLAN.md`.
5. Próxima unidade independente: projetar exportação do acervo Growroom e importador Drupal idempotente usando fixtures sintéticas e banco efêmero, sem dados privados no Git. O Runtime de testes é o destino desejado, mas a importação persistente aguarda prévia, backup/restauração e validação isolada. Revisão das nove configurações Messenger divergentes continua condicionada ao contrato de preferências pendente; não exportar globalmente.
6. Manter os resultados delegados nos oito componentes atualizados na matriz. O agente das oito frentes concluiu seu trabalho; comparação por caminhos confirmou que todos os incrementos já estão na PR sob SHAs equivalentes. Não fazer cherry-pick duplicado; nenhum desses diretórios foi alterado neste turno.
7. Manter a branch/PR #298 como único destino; sem merge, deploy ou escrita Runtime. Import persistente no ambiente de testes somente após pipeline, prévia e rollback reproduzíveis. Último checkpoint remoto: `af3a05f27`.

## M-0 — revalidação de Config Sync Messenger (2026-10-26)

- `drush status` read-only confirmou novamente Drupal 11.4.10/PHP 8.4.27 e conexão MAIN `aculta_runtime` no checkout compartilhado `/home/piradopirata/projetos/aculta/web`; nenhum comando de escrita foi executado.
- Comparação focal entre a configuração ativa e o `config/sync` desta branch imprimiu somente nomes/caminhos divergentes (sem valores): `aculta_messenger.settings` tem 12 caminhos sob `email_modes`; os metadados `description` de `field_aculta_messenger_attempts` e `label`/`description` de `field_aculta_messenger_source_fr` também diferem. Config Sync possui cinco controles estruturais enquanto o default instalável define ainda políticas de e-mail, eventos da Revista e âncora de digest.
- Não foi inferido que as políticas ativas sejam defaults ou customização operacional. A decisão sobre a fonte canônica de `email_modes`/`magazine_events` foi solicitada ao responsável; nenhuma configuração foi alterada. Os metadados dos campos ficam em revisão separada e não justificam importação global. A pergunta interativa sobre essa política continua aberta.
- Inspeção estrutural read-only de capturas privadas confirmou formatos distintos: IPS4 usa blocos `post_block`/`post_body`/`entry-content`; no THW antigo, o permalink aparece como query `postid=` em `<a href>` dentro de uma tabela sem classes semânticas (linha com duas células e ancestrais `table/tr/td/font`). `postid` não é atributo HTML; a anotação anterior foi corrigida. Os scripts atuais não exportam corpos/posts; ainda é necessário um parser versionado com fixtures sintéticas e saída privada antes de qualquer importação. Nenhum conteúdo ou dado pessoal foi impresso.
- A amostra estrutural não foi convertida em código de importação. A decisão sobre 616 tópicos internos e 17 títulos comerciais em revisão foi solicitada; a preparação independente pode prosseguir, mas não define seleção nem escreve no Drupal.
- No HEAD `dc5561f23`, `php scripts/validate-project-status-index.php` passou (41 componentes) e `php scripts/validate-own-module-packages.php` passou (41 verificações). Foram executados no worktree da PR; os resultados não implicam aprovação dos demais gates nem homologação Runtime.

## M-4 — fechar o harness Kernel focal de Doações (2026-10-26)

- `DonationPortKernelTest` foi reproduzido inicialmente com 4 erros de setup/0 assertions: o teste instalava `aculta_donation` sem o Messenger exigido pelo contrato. Instalar o módulo Messenger inteiro expôs sua closure transitiva e não é necessário para este escopo focal.
- O fixture agora injeta, no container de teste, as implementações reais `MessengerNoticePublisher` e `OrderTypeCheckoutFlows`; também resolve `DonationNoticeEventPublisher` e `DonationCheckoutProvider` reais. A configuração completa do módulo não é instalada porque instancia panes externos ao cenário e requer serviços de recorrência; a suite cobre discovery dos plugins/blocos, criação do pedido de valor inicial zero e negação de estorno.
- Kernel com código da branch e MariaDB dedicado: 5 testes / 193 assertions PASS, com 88 depreciações upstream. PHPCS Drupal/DrupalPractice e PHP lint do teste PASS. Nenhum stub de produto, acesso Runtime, SMTP ou operação financeira externa.
- `aculta_donation` avançou `0.1.0-dev.25` → `.26`; README, CHANGELOG, status e matriz atualizados. Continua Dev; Runtime, sandbox financeiro, revisão integral e RC pendentes.
- A suíte Unit completa do submódulo foi executada no root isolado com código da branch: 24 testes / 84 assertions PASS, sem depreciações. O Kernel 5/193 também foi reexecutado antes da atualização de versão e passou com 88 depreciações upstream.

## M-1 — reclassificar Config Sync operacional do Messenger (2026-10-26)

- Runtime MAIN foi consultado somente por Drush read-only. No arquivo ativo, `aculta_messenger.settings.email_modes` existe e difere dos defaults instaláveis em 15 caminhos; o Sync não contém essa árvore. `magazine_events` e `private_message_digest_anchor` existem nos defaults, mas não estão nem no ativo nem no Sync.
- `updatedb:status` listou cinco hooks (10008–10012) e três post-updates pendentes; entre eles `private_message_email_preferences`, que migra preferências legadas para Messenger. Nenhum update foi executado. Essa migração e as diferenças operacionais tornam inseguro exportar defaults ou copiar o ativo para o Sync sem decidir a fonte canônica.
- Metadados de `field_aculta_messenger_attempts` e `field_aculta_messenger_source_fr` no ativo correspondem aos defaults instaláveis; somente o Sync estava diferente. Os dois arquivos Sync foram corrigidos seletivamente sem alterar campos, mensagens ou preferências ativas. `aculta_messenger` avançou `.400` → `.401`; decisão sobre políticas de e-mail/config split permanece aberta.

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
| P0 PHPCS | PASS para erros; avisos baseline permanecem | Seis PHP focais: `origin/main` 143 erros/54 avisos; após correção, 0 erros/49 avisos. Avisos restantes são linhas longas antigas e chamadas de tradução dinâmica. Não se afirma que o repositório inteiro esteja sem avisos. |
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
- Revisão read-only repetida em 2026-10-26 confirmou 218 módulos ativos e 199 no Sync. `google_tag` estava ativo sem contêiner; no Runtime, `use_collection=false` e `default_google_tag_entity` vazio coincidiam com os defaults instaláveis. A extensão e o settings vazio foram versionados; após isso restam 18 módulos ativos fora do Sync: `aculta_editor`, `aculta_lms_messenger` e 16 módulos CKEditor Plugin Pack. A ausência do shell é intencional para novas instalações; a cópia ativa no Homelab aguarda uninstall operacional autorizada. Sem `cim` ou write no Runtime.
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
- Donation Kernel: quatro testes / zero assertions seguem DEFERRED por erro de compilação. Reprodução atual: sem aculta_messenger, a compilação acusa ausência de aculta_messenger.notice_event_publisher; com o módulo real habilitado, avança e acusa a dependência real private_message.service ausente. São etapas da closure de módulos do harness, não defeitos nos testes de autorização; nenhum stub de produto foi criado e nenhum Runtime foi alterado.
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

- Reconfirmado em consulta read-only: host `toca`, checkout Runtime `/home/piradopirata/projetos/aculta` (branch compartilhada `docs/aculta-forum-0.1.0-dev.1`; branch da PR separada em `/tmp/aculta-m0-integridade`); Drush conectou Drupal 11.4.10, URI MAIN, banco `aculta_runtime`. Nenhuma operação de escrita ocorreu.
- `updatedb:status` mostra hooks 10008–10012 (marcador de entrega privada, marcador de amizade, estado de digest, estado de leitura de amizade e bundle de aviso de conquista) e post-updates `private_message_email_preferences`, `shared_email_preferences` e `game_achievement_notices` pendentes.
- A suíte Kernel combinada de Messenger/Mailer/Confirmador passou 112/1.504 na MariaDB isolada; testes focais de migração de preferências e de conquistas existem. Isso valida código e fixtures, não o conteúdo real de dados pendentes no Runtime.
- Estado operacional **DEFERRED**: não executar `updatedb`. Próxima ação requer snapshot/backup recuperável, inventário privado somente leitura das contagens/estados afetados, plano de rollback e autorização específica; filas externas/e-mail permanecem sem processamento.
- Em 2026-10-26, contagem agregada read-only encontrou cinco filas legadas de conquistas em cinco contas e zero linhas nas quatro chaves antigas de preferências consultadas. Sem UID ou valores individuais. O plano de preflight, execução isolada, janela e rollback integral está em [`ACULTA-MESSENGER-UPDATE-PLAN.md`](ACULTA-MESSENGER-UPDATE-PLAN.md); a atualização no Runtime permanece bloqueada por autorização operacional.

## M-4 — cobertura de acesso da Loja (2026-10-26)

- A suíte Unit `aculta_commerce` passou 25 testes / 160 assertions (9 depreciações PHPUnit) com o runner isolado da branch. `GreenStashProductAccessTest` usa as interfaces reais Commerce e verifica acesso de entidade, acesso de atualização à única loja, produto de outra loja, store sem permissão `update` e configuração ambígua.
- O teste alterado passou PHPCS Drupal/DrupalPractice sem violações e PHP lint. Versão avançou `0.1.0-dev.5` → `0.1.0-dev.6`; README, CHANGELOG, status e matriz foram reconciliados. Nenhum pedido, produto, loja, Runtime ou serviço externo foi alterado.
- Ainda faltam Form/Kernel/Functional para criação e operações Green Stash, fluxos comerciais completos e homologação visual; não há promoção RC.


## M-3/M-5 — auditoria de ACULTA Game (2026-10-26)

- Unit do Game passou 11 testes / 51 assertions. Kernel `GameAchievementNoticePersistenceKernelTest` no Messenger passou 3/30 no MariaDB dedicado: revalida fonte contra concessões revogadas, migra fila legada uma vez e conserva o cursor/fila quando publisher falha. Duas depreciações de `@EntityType` vêm de Message/Message Template no Core/contrib.
- Documentação identifica o corte de ownership dev.17 (Game publica, Messenger persiste/lê, Mailer transporta), mas corrige uma lacuna: o Kernel Fórum testa elegibilidade do `ForumParticipationPolicy`, não a chamada de concessão no `BadgeEvaluationHooks::forumPostSave`. Essa cobertura direta permanece próxima tarefa; nenhuma conquista foi concedida no Runtime. README, CHANGELOG, execução, status e matriz foram reconciliados, sem mexer na feature ou subir versão.

## Retomada PR #298 — integração dos commits delegados (2026-10-26)

- Branch local `chore/aculta-m0-integridade`; commits revisados e cherry-picked do agente 2: `5c1b86fe0` (Sitemap README), `50e83290b` (Patcher impede symlink escape), `4deedb366` (status Sitemap/QR/Links/Deployer), `e38f48a1e` (limpeza segura de fixtures Deployer). Nenhum merge ou operação de Runtime ocorreu.
- Revisão focal própria: `vendor/bin/phpcs --standard=Drupal,DrupalPractice` nos três PHP alterados (Patcher exporter/test e runner Deployer) PASS; runner Sitemap PASS (13 verificações); gates Patcher 2 testes/9 assertions PASS, índice 41 componentes PASS após regenerar README, pacotes 41 PASS e Config Extension Dependencies 1.560 checks/1.426 arquivos PASS. O runner Deployer do agente concluiu PASS sem avisos; suíte combinada dos quatro componentes reportada pelo agente: 91 testes/566 assertions PASS. Esta execução não repetiu a suíte combinada.
- O README raiz foi regenerado pelo gerador de status após os novos status estruturados; essa alteração gerada ainda precisa ser incluída no próximo commit. PHPCS aqui cobre somente arquivos PHP alterados pelos commits incorporados, não o baseline integral dos módulos delegados.
- Próxima tarefa sob ownership central: revisar o resultado do Kernel H5P já registrado em 15/388 com Unit 24/73, retomar o harness LMS/Portal sem serviço fictício, então avançar os contratos centrais independentes (Portal, User, CPF, CEP, Messenger, Commerce, LMS, Statistics e Index). P0-H5P e gate Domain já têm resultados PASS documentados; M-0 global continua PARTIAL por Config Sync/Runtime, autorização e homologações pendentes.

## M-5 — dependência da instalação limpa Statistics/Portal (2026-10-26)

- Functional Statistics reproduzido no MariaDB dedicado: falha com 2 erros/0 assertions ao instalar o dependency closure real porque `aculta_portal/config/install/search_api.server.aculta_database.yml` requer `search_api_db`, mas o manifesto Portal não o requeria. O Core e as extensões Search API/Search API DB estão disponíveis no código; `search_api_db` declara Search API como dependência transitiva.
- Corrigido o contrato no proprietário: Portal `.33` declara `search_api:search_api_db`. Runner de Functional em raiz efêmera, com Composer autoload e `index.php` apontando ao test root, passou da instalação do Search API; a cadeia Portal exige `aculta_user` na mesma instalação devido aos serviços de consentimento, apesar de User depender de Portal. Com User + fixture Domain MAIN, houve 2 testes/11 assertions e 2 falhas no helper de login antes das verificações do dashboard; isso permanece FAIL/deferred, sem classificar o dashboard como defeituoso. O router usado inicialmente sem `index.php` também produziu 404 de ambiente; corrigido no runner local. Sem operação Runtime.


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

## M-4 — regressões do publisher e cursor Commerce (2026-10-26)

- Reexecutar a Unit Commerce com o autoloader explícito do worktree (o PHPUnit padrão usa Core/vendor compartilhados e carregava código Commerce do checkout comum). A regressão de paginação tinha fixture incoerente: o candidato gerenciável estava dentro do lote varrido e o probe apontava para outro ID. Ajustados os IDs para 500 candidatos inacessíveis seguidos de um produto mais antigo; nenhum código de produção foi alterado.
- `CommerceOrderNoticePublisherTest` agora confirma que pedidos de visitantes mantêm o recibo nativo e que falha ao publicar no Messenger não marca `skip_order_receipt`; o logger recebe apenas a classe da exceção, nunca seu texto. PHP lint/PHPCS nos dois testes PASS; suíte Unit Commerce 29 testes / 183 assertions PASS, 10 depreciações PHPUnit.
- Nenhum pedido, pagamento, fila, Runtime ou serviço externo foi alterado. Commerce continua Dev até Kernel/Functional, checkout e homologações de loja única.

## M-0 — Config Domain e acesso `full_html` (2026-10-26)

- O Runtime MAIN foi consultado read-only, com host/URI e banco confirmados. `DomainPurposeManager::getCurrentPurpose()` resolveu os nove hosts dos purposes (9/9). As seis coleções Domain têm os mesmos nomes de configuração ativos/exportados; cinco coleções tinham conteúdo idêntico. A home de Conta estava divergente (`/studio` no Sync, `/minha-conta` no Runtime e contrato) e foi corrigida no Sync. Na Revista, permanecem três campos ativos divergentes (`system.site.name`, `metatag.front.tags.title`, `og_title`); o Sync mantém `Revista 420`, coerente com o contrato de apresentação. Nenhum valor da Revista foi sobrescrito e nenhum `cim` ocorreu.
- `validate-domain-front-paths.php` PASS (16), `validate-public-slugs.php` PASS (395, 17 baseline contrib), aliases PASS (25), dependências Config Extension PASS (1.559/1.426), índice status PASS. `validate-domain-presentation-contract.php` direto no PHP falhou sem bootstrap; reexecutado corretamente via Drush no MAIN: PASS (162). A falha direta foi harness, não produto.
- Commit delegado Editor `80dfe0549` revisado: adiciona apenas um teste Kernel de autorização. Cherry-pick local `261b52bc6`; Kernel Editor 8/127 PASS, PHPCS Drupal/DrupalPractice e lint PASS, três depreciações contrib. No Runtime, apenas `administrator` tem `use text format full_html`; o Kernel confirma os papéis de Config Sync e acesso efetivo ao FilterFormat. Nenhuma permissão Runtime alterada.
- Wiki RBAC read-only foi repetido em `wiki420`: os cinco grants de proposta não aparecem nos papéis ativos `email_confirmed` e `content_editor`; o Config Sync contém os quatro grants desses papéis. Kernel focal de submissão/revisão privada passou 1/216 em MariaDB dedicado (256 depreciações upstream); portanto o bloqueio observado é drift ativo-versus-Sync, não falha do handler. Aplicar os grants exige diff prévio e autorização operacional; nenhum papel ativo foi alterado.

## M-2 — compartilhar assets do motor PDF (2026-10-26)

- Comparação byte-a-byte confirmou cópias idênticas de `pdf-viewer.mjs`, `pdf-viewer-wrapper.mjs`, `viewer-wrapper.html` e CSS nos módulos PDF e Document Viewer. Document Viewer passou a depender do módulo PDF e anexar sua biblioteca canônica; seu builder, configuração e verificações de Media/File continuam próprios. Os quatro assets duplicados foram removidos sem alterar o arquivo upstream PDF.js.
- Unit Document Viewer: 42/225 PASS. Kernel da branch foi executado em MariaDB isolado: 5 erros antes de assertions ao instalar a closure obrigatória Portal, por dependências `search_api.server.aculta_database` (`search_api_db`, `search_api`) e `views.view.courses_admin` (`system.menu.admin`) ausentes no harness. Resultado DEFERRED, sem declarar instalação limpa aprovada. PHP lint, dependências Config Extension (1.560/1.426), pacote próprio (41), índice status (41) e diff check PASS. PHPCS completo ainda reporta baseline em linhas não alteradas (builder 31/1, Kernel 4 FQN); nenhuma violação recai nas linhas modificadas.
- Runtime não foi alterado. Próxima ação é corrigir o harness de instalação limpa com closure Drupal real e confirmar a integração de formatter/Media Embed; manter Browser/Office e homologações Runtime pendentes.

## P0 — PHPCS e regressões focalizadas (2026-10-26)

- No HEAD `cccca20a9`, o scan inicial dos seis PHP com findings registrou 143 erros/54 avisos; comparação dos findings com `origin/main` confirmou zero erros/avisos novos. PHPCBF e correções focais removeram todos os erros e cinco avisos: PHPCS atual tem 0 erros/49 avisos. Os avisos restantes são linhas longas preexistentes no builder PDF e no gateway, além de traduções dinâmicas do Mercado Pago; nenhuma limpeza ampla de formatação foi feita.
- H5P: nomes dos testes alinhados à convenção Drupal; os quatro round-trips dos formulários passam `allowed_classes` com somente a classe concreta de Form API, preservando a cobertura de restauração de dependências. Unit 24/73 PASS; Kernel completo 15/388 PASS, 41 depreciações Core/contrib.
- Document Viewer: Unit 42/225 PASS e PHPCS sem erros (um aviso de linha longa preexistente). Kernel no MariaDB dedicado: 5 erros de setup/0 assertions. Portal requer `aculta_user.aculta_user_agreement_ledger`, mas o harness não habilita User; habilitá-lo experimentalmente revelou `path_alias` e dependência `system.menu.admin` em configuração LMS. A fixture experimental foi removida; não foi criado serviço fictício, e a instalação limpa permanece DEFERRED.
- Mercado Pago: Unit 11/22 PASS; Kernel 1/50 PASS, 75 depreciações Core/contrib. PHPDoc documenta os parâmetros reais e a exceção de `createPaymentMethod`; removida variável de iteração sem uso. Nenhuma transação ou chamada externa executada.
- PHP lint dos seis arquivos PASS. Nenhuma operação Runtime ocorreu. Os resultados antigos de PHPCS listados acima neste documento são históricos e ficam supersedidos por esta medição.

## M-3 — teste direto do gatilho Game/ForumPost (2026-10-26)

- A lacuna do status `aculta_game` foi reproduzida: o Kernel do Fórum cobria apenas `ForumParticipationPolicy::isEligiblePost()`, sem invocar `BadgeEvaluationHooks::forumPostSave()`.
- Novo teste focal chama o hook real em duas passagens (insert/update), com `ForumParticipationPolicy`, `DomainPurposeManager`, `BadgeService`, `AchievementDefinitions` e `BadgeCatalog` reais; entidades, config e storage são doubles Unit. O ID de Domain usado é o canônico `forum_aculta_org`. O serviço faz uma única gravação do badge existente após segunda passagem idempotente.
- Unit Game completa: 12 testes / 73 assertions PASS. PHPCS Drupal/DrupalPractice e PHP lint dos dois arquivos novos PASS. Status YAML, README, CHANGELOG e índice de 41 componentes foram reconciliados.
- Limite: não equivale a Kernel com `ForumPost` real, nem homologa privacidade de perfil, visual, Runtime ou concessões persistentes. Nenhuma configuração ou dado Runtime foi alterado; módulo continua Dev `0.1.0-dev.17`.

## M-0 — reconciliação Config Sync do Editor (2026-10-26)

- Antes da mudança, Drush status foi confirmado read-only no host de testes `toca`, banco `aculta_runtime`, Drupal 11.4.10/PHP 8.4.27; as consultas usaram URI MAIN explícita. Nenhuma escrita ocorreu.
- Ativo versus `config/sync` da PR: 218 extensões ativas e 200 no Sync; após o ajuste, 217 IDs coincidem e o único ID ausente do Sync é `aculta_lms_messenger`, shell legado cuja desinstalação requer janela/autorização. Não se removeu o shell nem se alterou Runtime.
- As 17 extensões ativas de Editor/CKEditor foram adicionadas ao `core.extension.yml`. Os oito templates têm conteúdo igual aos `config/install` locais (campos de conteúdo normalizados; UUID/hash runtime ignorados); o nono objeto `ckeditor5_plugin_pack.settings` confere com seu default contrib versionado. Os 9 objetos ativos de `editor.*`, `filter.*` e `ckeditor5_plugin_pack*` agora existem no Sync. Arquivos de módulos/contrib não foram alterados.
- Core `ConfigImporter::processExtension()` redireciona `ConfigInstaller` para a fonte Sync; por isso os defaults foram versionados junto com as extensões. O import não foi executado no Runtime. `validate-config-extension-dependencies.php` PASS, 1.589 checks/1.435 arquivos; índice 41 e pacotes 41 PASS.
- Permanece DEFERRED: ensaiar o ciclo de instalação/importação em banco efêmero e fazer Browser autenticado dos formatos/templates. `full_html` continua permitido somente a `administrator`. O shell Messenger requer desinstalação controlada e rollback antes de qualquer retirada.


## M-0 — verificação read-only de grants Wiki (2026-10-26)

- Consultados os papéis ativos em `wiki420.aculta.toca.net.br` por Drush (`config` read-only): os quatro grants de proposta (`submit wiki proposals`, `view own wiki proposals`, `view any wiki proposals`, `moderate wiki proposals`) estão ausentes dos papéis `email_confirmed` e `content_editor`; os YAML versionados declaram os grants esperados.
- O `WikiProposalAccessControlHandler` retorna acesso negado quando faltam grants; o efeito observado é indisponibilidade das propostas para contribuintes e editores, sem evidência de exposição indevida. Kernel focal existente de submissão/revisão privada: 1/216 PASS.
- Nenhuma permissão Runtime foi alterada. Corrigir o drift requer operação persistente/importação e revisão do diff por purpose, portanto permanece DEFERRED até autorização operacional; depois, repetir verificação por papéis e fluxos em ambiente isolado.


## M-1 — reconciliar estado de entrega do Messenger (2026-10-26)

- A comparação read-only de 1.435 arquivos Sync e 129 defaults Messenger encontrou o campo `field_aculta_msg_delivery` com três valores no Sync, mas cinco (`pending`, `sent`, `failed`, `failed_permanent`, `cancelled`) no default e no Runtime ativo. Os workers já persistem os dois estados terminais.
- Atualizado somente o Config Sync do campo para incluir `failed_permanent` e `cancelled`; nenhuma fila, mensagem, configuração Runtime ou update hook foi executado. Messenger avançou `0.1.1-dev.396` → `.397`; README/status/matriz foram reconciliados.
- Validação de escopo: três testes Unit dos workers passaram 32/608; comparação da configuração ativa/default, YAML, dependências Config Extension (1.589/1.435), status index (41), pacote próprio (41) e `git diff --check` PASS. A importação fica DEFERRED, embora a alteração seja aditiva e reflita os valores já ativos.
- Auditoria global encontrou 28 configs ativos iguais aos defaults e ausentes do Sync; 30 configs ativos sem Sync com diferenças frente aos defaults; 9 configs presentes no Sync com conteúdo diferente do Runtime. Não houve export global; essas famílias exigem avaliação individual. `flag.flag.seguir_publicacao` existe no Sync e não está ativo. Omitidos/antigos do tema Olivero e integrações contrib requerem classificação por owner.


## M-1 — ocultar evento técnico nos avisos de amizade (2026-10-26)

- A comparação de `core.entity_view_display.message.aculta_msg_friendship.mail_body` e `mail_subject` confirmou que o campo `field_aculta_friendship_event` (`requested`/`accepted`) é oculto no Runtime MAIN e em `config/install`, mas não nos dois arquivos Sync. O texto de template é genérico; permitir o campo no display poderia expor o estado técnico no corpo/assunto.
- Sync passou a declarar a dependência do campo e ocultá-lo nos dois displays, alinhando-os com o Runtime e o default. Nenhuma mensagem, preferência ou configuração ativa foi alterada. Messenger avançou `.397` → `.398`.
- YAML, Config Extension Dependencies (1.589/1.435), status index (41), pacote próprio (41) e `git diff --check` PASS; PHPCS não aplicável (nenhum PHP foi alterado). Os testes focais de amizade/entrega passaram 35/644.
- Permanecem 9 configs Messenger ativos, presentes no Sync e com diferenças de conteúdo após normalizar UUID/hash; `aculta_messenger.settings` e `message.template.aculta_msg_friendship` exigem a decisão pendente sobre fonte canônica. As 32 configs ativas fora do Sync que divergem dos defaults também não foram sobrescritas.


## M-1 — ocultar controles Entity Print em displays (2026-10-26)

- Runtime MAIN read-only confirma `entity_print` ativo e os campos extras EPUB/PDF/DOCX ocultos nos sete displays de avisos/e-mails. Defaults Messenger e Sync não os ocultavam.
- Corrigi os sete `config/install` e sete arquivos Sync para manter os três controles ocultos; teste Unit novo compara ambos os conjuntos e também verifica o evento de amizade oculto e sua dependência. `MessengerMailDisplayConfigTest` 2/68 PASS; conjunto focal de amizade/entrega 37/712 PASS; PHPCS/PHP lint do teste PASS.
- Nenhum usuário, mensagem, configuração ativa ou saída Entity Print foi alterado. Messenger avançou `.398` → `.399`; status, changelog, README, índice e matriz reconciliados.
- Reclassificação determinística ativa/default/Sync: 9 configs Messenger ainda divergentes (settings, templates e campos do evento de amizade); Config Sync global agora tem 81 nomes ativos ausentes e um nome exportado inativo.


## M-1 — schema e Config Sync do contador de tentativas (2026-10-26)

- Auditoria de `field_aculta_enqueue_attempts` encontrou propriedades de instância (`min`, `max`, `prefix`, `suffix`) no YAML de field storage Integer. Drupal espera `unsigned` e `size` no storage; `field.field` conserva settings da instância. O `FieldStorageConfig` ativo no MAIN já havia normalizado o storage para `unsigned: false`, `size: normal`.
- Corrigi o default instalável e versionei seletivamente no Sync os dois objetos ativos (storage e field instance), preservando UUID/dependencies exportados; nenhum conteúdo de Message foi lido ou alterado. Update 10009 continua pendente e é idempotente pela instalação de defaults ausentes.
- Testes focal: `MessengerMailDisplayConfigTest` 3/80 e suite amizade/entrega 38/724 PASS; PHPCS Drupal/DrupalPractice, PHP lint, YAML, Config Extension Dependencies, status, pacotes e diff check PASS.
- Inventário global atualizado: 81 configs ativos ausentes do Sync, uma config exportada inativa; Messenger tem 30 defaults ativos fora do Sync que divergem em conteúdo e 9 configs versionadas com diferenças ativas. A transição `string`→`list_string` de `field_aculta_friendship_event` continua sem mudança até decisão e plano de migração.


## M-1 — cache de consulta CEP separado por provedor (2026-10-26)

- Revisão do contrato de fontes configuráveis mostrou que a chave de cache externa usava somente CEP. ViaCEP poderia preencher a chave que depois seria consultada ao selecionar BrasilAPI (ou vice-versa), ignorando a fonte selecionada.
- A chave agora inclui o ID do provedor. Unit focal `CepLookupServiceTest`: 10/43 PASS; caso novo comprova lookup e cache da BrasilAPI sem chamar ViaCEP. PHP lint, PHPCS Drupal/DrupalPractice focal e `git diff --check` PASS.
- `aculta_portal_cep` avançou `0.3.0-dev.10` → `.11`; atualização refletida no CHANGELOG, README/contrato de cache, status e índice gerado. Sem chamadas externas reais ou alteração no Runtime.


## M-1 — evidência de unicidade do apelido (2026-10-26)

- Leitura somente leitura no MAIN confirmou 14 valores atuais em `profile__field_nickname`, sem valor nulo/vazio nem colisões por `LOWER(TRIM(value))`; a coluna usa collation `utf8mb4_general_ci` e não possui índice único. Nenhum nome/UID foi listado.
- A política atual exclui perfis do próprio UID nas verificações de colisão; o contrato permite manter o mesmo apelido em múltiplos bundles Profile da mesma pessoa. O Runtime consultado não tem hoje UID com múltiplas linhas de apelido, mas isso não define o contrato futuro.
- Garantia por índice único do valor seria transacional, mas também proibiria duplicar o mesmo apelido entre bundles do mesmo usuário. Uma chave composta (UID + slug canônico) não cabe nos dados atuais do field table sem novo índice/storage. A implementação fica sem alteração até decisão sobre esse contrato; teste/fix de escrita concorrente será definido em seguida.


## M-4 — corrigir AccessResult na criação Green Stash (2026-10-26)

- Revisão de `GreenStashProductCreateForm::assertCreateAccess()` encontrou uso de objetos `AccessResultInterface` como booleanos nos checks de acesso à experiência e `commerce_product.createAccess()`. Objetos de resultado negado são truthy, então os checks não interrompiam a criação.
- Ambos os resultados agora exigem `isAllowed()`. `GreenStashProductCreateAccessTest` prova que negação da experiência para antes do handler Commerce e negação de `createAccess()` para antes de resolver a loja. Suíte Unit Commerce completa: 31 testes / 192 assertions PASS; 11 depreciações PHPUnit. PHPCS Drupal/DrupalPractice e PHP lint nos dois arquivos PHP alterados PASS.
- Commerce avançou `0.1.0-dev.8` → `.9`; CHANGELOG, README, status e matriz atualizados. Nenhuma chamada ao Runtime ou persistência externa.


## M-0 — regressão de gates e setup Kernel (2026-10-26)

- Gates reexecutados no HEAD `9e707b6df`: status 41 componentes, packages 41, Config Extension 1.590/1.437 arquivos, fronteira módulo–tema 2.887 verificações, Drupal 11+ 1.160, slugs 396 rotas, aliases 25 arquivos, endereços 4.254 arquivos, contrato de segredos, papéis (53 permissões editoriais), hierarquia, aceite único, links de 24h, Profile Links 1.835 arquivos, mapa Domain front 16 checks, cross-domain 18, admin Domain 3.498 e payment Domain 44 — PASS. Scripts que usam Drupal foram executados por `drush php:script` com `--root=web` e `--uri=https://aculta.toca.net.br`; invocação PHP direta não é válida para esses gates.
- `validate-portal-commerce-security.php` não foi executado contra o Runtime: inspeção do script confirmou que ele altera temporariamente o status do gateway de pagamento via Config API. Fica DEFERRED até haver Runtime efêmero/isolado com restauração garantida.
- DonationPortKernelTest no MariaDB dedicado aculta_simpletest: 4 setup errors / 0 assertions; cinco depreciações Core/contrib. Após um experimento temporário que incluiu aculta_messenger real, a falha avançou para private_message.service ausente. O harness ainda precisa carregar a closure de dependências real; alteração de experimento revertida.

## M-4 — negar AccessResult no formulário Green Stash (2026-10-26)

- `GreenStashProductCreateForm` avaliava `AccessResultInterface` como booleano nos gates da experiência e do Commerce; resultado negado continuava truthy. Corrigido para `isAllowed()` em ambos os checks e adicionados dois testes que asseguram parada antes do handler/lookup subsequente.
- Unit Commerce completa: 31/192 PASS, com 11 depreciações PHPUnit; PHPCS e PHP lint focais PASS. `aculta_commerce` dev.8 → dev.9. Sem Runtime ou pagamentos externos.


## M-2 — cobertura fail-closed do FileRemover (2026-10-26)

- Adicionado `FileRemoverFailClosedTest` para confirmar que referências File API e Entity Usage bloqueiam exclusão; falha na consulta Entity Usage propaga antes de acessar a storage de arquivos. O teste não opera sobre arquivos reais.
- Teste focal: 3/23 PASS; suíte Unit Image: 25/103 PASS (9 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint PASS. `aculta_image` avançou `0.1.0-dev.31` → `.32`; README, CHANGELOG, status e matriz reconciliados.
- A auditoria não executou `ImageCleanup` em Runtime nem alterou arquivo/conteúdo; limpeza real continua fora do escopo desta missão.

## M-2 — Image: quarentena usa coordenador global — 2026-10-26

- A auditoria confirmou que ImageQuarantine fazia a verificação local somente em File API Usage e Entity Usage e removia diretamente pelo FileRemover, sem a inspeção Portal de referências em campos/revisões/texto formatado.
- ImageQuarantine agora só move um arquivo depois de UnusedFileCleaner avaliar o estado como Available e só apaga item vencido via removeIfUnused; sem o serviço coordenador, falha fechada. Image continua proprietário da remoção física pelo contrato já existente. Nenhuma operação de arquivo foi executada.
- ImageQuarantineOwnershipTest: 2 testes/8 assertions PASS; suíte Unit Image: 27/111 PASS (9 depreciações PHPUnit); UnusedFileCleaner Portal: 5/22 PASS (1 depreciação). PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Gate aculta_image PASS (25 estilos), pacotes próprios PASS (41), dependências Config Extension PASS (1590/1437), fronteira módulo-tema PASS (2889).
- PHPUnit foi executado com bootstrap temporário que prioriza classes do worktree porque vendor/core são compartilhados e o autoloader padrão seleciona cópias do checkout principal. Um primeiro run sem essa priorização carregou a assinatura antiga de ImageQuarantine; esse erro de harness não foi contado. Bootstrap temporário está em /tmp, fora do repositório.
- Manifesto, README, changelog, plano e status de Image atualizados para dev.33. Estado permanece Dev; Kernel/install-clean e homologação operacional ainda pendentes. Nenhuma alteração no Runtime.

## M-1 — Portal: caracterizar inspetor global de referências — 2026-10-26

- FileReferenceInspectorTest cobre campos de referência estruturada, embeds em texto formatado, pesquisa em todas as revisões de entidades revisionáveis e falha fechada como Unverifiable quando uma query não pode ser concluída.
- Suíte Unit completa do Portal: 353 testes / 914 assertions PASS, seis depreciações PHPUnit; teste focal novo 3/36 PASS; PHPCS Drupal/DrupalPractice e PHP lint do teste PASS.
- Gates após bump documental de Portal 0.4.1-dev.34: índice 41 PASS, pacotes próprios 41 PASS, Config Extension 1590 checks/1437 YAML PASS, Drupal 11+ 1160 PASS. Runtime permaneceu inalterado.
- A verificação encontrou cobertura direta ausente do FileReferenceInspector, não um defeito de produto reproduzido. Instalação limpa e Functional continuam pendentes conforme status anterior.


## M-1 — CPF/CNPJ: cobertura completa do update de unicidade — 2026-10-26

- A inspeção read-only do Runtime MAIN foi precedida por Drush status (Drupal 11.4.10, root compartilhado /home/piradopirata/projetos/aculta/web, banco aculta_runtime). O código ativo em /home/aculta é aculta_id_cpf 0.3.0-dev.1, com schema 10001; a branch da PR tem update 10002 e não foi executada no Runtime.
- Consulta agregada sem selecionar dados pessoais: Profile.associado tem 1 documento cifrado e 1 fingerprint, zero documentos sem índice, zero grupos duplicados e nenhum índice SQL único. Config ativa não possui bundle participante; campo atual é associado.
- O update 10002 agora falha fechado se faltar documento de origem ou fingerprint de algum documento ativo associado; só cria a restrição depois de cobertura e duplicatas serem verificadas. A checagem não exibe CPF, ciphertext ou fingerprint.
- Kernel MariaDB isolado: 3 testes / 8 assertions PASS (idempotência, rejeição de grupos duplicados e de documento sem fingerprint); Unit: 48/116 PASS; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS.
- Manifesto/README/CHANGELOG/status/matriz reconciliados para 0.3.0-dev.4 e bundle associado. O update não foi aplicado; permanece dependente de ensaio com dados sintéticos, plano de rollback e autorização operacional.


## M-1 — dispensa fiscal de curso LMS gratuito (2026-10-26)

- Corrigida a política compartilhada: Loja exige documento; LMS exige para variações `curso` de preço base maior que zero. Todos os itens precisam ser reconhecidos como cursos gratuitos para dispensar; pedido vazio, item ausente ou composição ambígua permanece fail-closed. Descontos não tornam curso pago gratuito para esta regra.
- `FiscalIdentityPolicyTest`: 13 testes / 30 assertions PASS. `FiscalIdentityPaneKernelTest`: 12 testes / 343 assertions PASS em MariaDB dedicado (72 depreciações upstream). PHPCS Drupal/DrupalPractice e PHP lint dos dois arquivos PHP PASS. Uma invocação anterior do Kernel sem `SIMPLETEST_DB` terminou em setup (12 errors/0 assertions); repetição com o ambiente isolado passou.
- Atualizados manifesto `aculta_portal` 0.4.1-dev.35, README, changelog, contratos CPF/checkout, roadmaps Commerce/LMS, status e matriz. README raiz regenerado; índice 41 componentes PASS. Nenhuma operação Runtime/checkout foi executada.
- Próxima ação: revisar diff, retomar decisão do harness Portal/User para LMS/Doações e auditar o próximo contrato central independente.


## M-2 — limpeza de imagem sempre coordenada pelo Portal (2026-10-26)

- `ImageCleanup` ainda chamava `FileRemover` diretamente quando `aculta_portal.unused_file_cleaner` estava indisponível, divergindo do contrato em `aculta_image/docs/PLANO.md`. Removido o fallback: agora retorna `Unverifiable`, não deleta e não remove o item da fila; o lease expira e permite tentativa posterior. Dependência direta de `FileRemover` removida do serviço/construtor. Quarentena já exigia coordenador.
- Novo `ImageCleanupCoordinatorTest` verifica ausência de exclusão física e retenção da fila sem Portal: Unit Image completa 28/118 PASS (9 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS; `validate-aculta-image.php` PASS (25 estilos catalogados, zero usos externos).
- Manifesto/status avançaram para Image `0.1.0-dev.34`; README/changelog/AGENTS, matriz e execução atualizados. Nenhum arquivo real foi limpo e nenhum Runtime foi alterado. Próxima ação: completar Kernel/instalação limpa e seguir consumers de cleanup do Portal.


## M-1/M-2 — limpeza de avatar integrada ao coordenador (2026-10-26)

- `AvatarAssetCleanup` do User excluía temporários e avatares órfãos diretamente via `FileRemover`, após checagem local de `user_picture`/File Usage; isso não cobria referências estruturadas e embeds globais. Migrado para `aculta_portal.unused_file_cleaner`, preservando a retenção e retornando contadores apenas para status `Deleted`. O User já requer Portal; não foi criada dependência circular nem storage paralelo.
- `AvatarAssetCleanupCoordinatorTest` usa UnusedFileCleaner real, com banco, File Usage e ReferenceInspector simulados: 1 teste/8 assertions PASS. User Unit completo: 133/512 PASS (1 depreciação PHPUnit). Nenhum arquivo real ou Runtime alterado.
- User avançou `0.4.0-dev.77`; status, README, CHANGELOG e matriz atualizados. PHPCS Drupal/DrupalPractice e PHP lint nos dois arquivos User PASS; User Kernel completo 25/361 PASS (5 depreciações contrib); YAML PASS; index 41, pacotes 41, Config Extension 1.590, User Roles, Drupal 11+ 1.160 e fronteira módulo-tema 2.891 PASS. Próxima ação: commit e push normal.


## M-1 — preflight de unicidade de apelido (2026-10-26)

- Drush status read-only reconfirmou Host `toca`, MAIN `https://aculta.toca.net.br`, DB `aculta_runtime`, Drupal 11.4.10/PHP 8.4.27 e root `/home/piradopirata/projetos/aculta/web`. Consultas agregadas à `profile__field_nickname` não leram valores pessoais: 14 linhas ativas/14 perfis, zero linhas deletadas, nenhum grupo duplicado entre UIDs ou repetição entre perfis do mesmo UID, sem índice único. Nenhum write ocorreu.
- Não aplicar unique key simples: a tabela Field API não contém UID e `NicknamePolicy` exclui perfis do mesmo UID, permitindo apelidos repetidos entre perfis legados da mesma conta. A opção priorizada para protótipo é lock distribuído por apelido normalizado em hooks de persistência, com reconsulta protegida; fallback é índice derivado por UID se algum caminho não puder ser coberto. Evidência, riscos, teste concorrente e rollback estão em `aculta_user/docs/NICKNAME-UNIQUENESS.md`.
- Status M-1 segue PARTIAL; próxima tarefa é protótipo Kernel/MariaDB com dois processos concorrentes, sem execução no Runtime.


## M-4 — remoção H5P integrada à limpeza global (2026-10-26)

- `aculta_H5PManagedFileStorage` removia assets depois apenas dos marcadores H5P/Image; agora entrega assets de imagem e não-imagem a `aculta_portal.unused_file_cleaner` após liberar os próprios markers. Status diferente de `Deleted` falha fechado, preservando os arquivos que ainda tenham referência.
- Unit H5P completo: 25 testes / 81 assertions PASS. Kernel completo em MariaDB dedicado: 15 testes / 388 assertions PASS, 41 depreciações Core/contrib. O harness registra classes de limpeza reais do Portal e do Image sem instalar o Portal inteiro, mantendo os Kernel focais isolados. PHPCS focal e lint PASS.
- PHPCS sobre todo `aculta_lms_h5p/src` e `tests` continua FAIL por erros preexistentes em múltiplas classes portadas, PHPDoc e estilo; nenhum desses arquivos alheios ao incremento foi formatado. Nenhuma operação de limpeza ou Runtime ocorreu.
- Manifesto/status H5P avançaram para `0.4.0-dev.150`. Próximo: fechar os gates focais e seguir a auditoria de componentes centrais independentes.


## M-0 — varredura PHPCS das alterações da PR (2026-10-26)

- `git diff origin/main...HEAD -- '*.php'` selecionou 78 arquivos PHP; PHPCS Drupal/DrupalPractice terminou com zero erros e 49 warnings. Os warnings listados estão nos arquivos tocados de Document Viewer e Mercado Pago (linhas extensas e traduções dinâmicas), sem falhas bloqueantes de erro; a revisão de causa permanece ligada aos módulos owners. A varredura não equivale a PHPCS limpo de todo o código legado de cada módulo.
- Os sete PHPs tocados no incremento H5P também passaram PHPCS sem erros nem warnings. Nenhum warning foi suprimido ou gate enfraquecido.


## M-1 — normalização no Profile legado (2026-10-26)

- `ProfileNicknameValidationHooks` aparava o valor para validar formato e colisão, mas a extração do widget continuava contendo o texto original. Agora o valor aparado aprovado é gravado em Form API antes de `ContentEntityForm` copiá-lo à entidade; entrada vazia ou valor legado inalterado preserva o comportamento anterior.
- Teste focal: 4/18 PASS; User Unit completo 134/518 PASS (uma depreciação PHPUnit); Kernel completo 25/361 PASS (cinco depreciações Core/contrib). PHPCS e lint dos arquivos alterados PASS. Runtime somente leitura, sem alteração.
- User avançou a `0.4.0-dev.78`; a corrida de unicidade permanece aberta. A próxima ação dessa trilha é provar uma garantia concorrente compatível com perfis múltiplos do mesmo UID, sem constraint simples incompatível com a tabela Field API.


## M-1/M-0 — reconciliar contrato do Confirmador e login (2026-10-26)

- Auditoria cruzada confirmou que a política vigente permite login à conta ativa ainda não confirmada e restringe ações específicas; `verify_mail: false`, `EmailConfirmationPolicy` e `PortalFormCallbacks` sustentam essa decisão. O `user_login` do submódulo registra confirmações configuradas, não bloqueia o login. Confirmação de cadastro não inicia sessão; confirmação de troca pode iniciar sessão somente para visitante anônimo/conta ativa.
- Corrigidos textos errados “bloqueio de login” e “hash sem expiração” em manifestos, READMEs, changelogs, política, mapa módulo–tema e relatório de incorporação. O hash usa TTL de 24 h (`hash_expiration: 86400`). Histórico do comportamento do contrib foi preservado e marcado como substituído pelo contrato ACULTA.
- Testes no runner isolado: Confirmador + troca 7 testes/53 assertions PASS (uma depreciação Core/contrib); `LoginBlockedAccountTest` + `LoginValidatorSwapTest` 9/22 PASS; `EmailConfirmationPolicyKernelTest` 3/16 PASS, agora afirmando que o usuário sem marca permanece ativo. Nenhum SMTP externo, Browser/Runtime ou operação persistente foi usado.
- `aculta_mail_confirmer` e submódulo avançam para `0.1.0-dev.3`; Browser autenticado para confirmação/troca e capacidades restritas permanece pendente. A prova automatizada é parcial e não substitui homologação.


## Checkpoint M-1 — confirmação de e-mail e próxima análise de perfil (2026-10-26)

- Commit `35da56a09` reconciliou o contrato do Confirmador: conta ativa pode autenticar antes da confirmação, ações específicas continuam restritas e o token expira em 24 h. Inclui texto de formulário e asserção Kernel; PHPCS Drupal/DrupalPractice e lint focais passaram; `EmailConfirmationPolicyKernelTest` passou 3/16. Índice de status (41) e pacotes próprios (41) passaram.
- Kernel foi executado com as credenciais dedicadas em `/home/aculta/secrets/simpletest-db.env`. Uma tentativa anterior com credencial genérica falhou no setup; não conta como resultado de produto. Nenhuma operação Runtime persistente, SMTP externo ou Browser foi realizada.
- **Registro histórico substituído por F-D44 em 2026-10-26:** a hipótese inicial de sufixar rotas de perfil legado foi rejeitada. Autores Growroom não possuem perfil individual/rota `/u/`; apenas cartões de autoria em conteúdo do Fórum exibem `-gr`. Perfis por papel, rotas privadas `/user/{uid}` e `/identidade/{user}` permanecem fora dessa regra.
- Drush status read-only no URI MAIN reconfirmou Drupal 11.4.10, DB `aculta_runtime` e root compartilhado `/home/piradopirata/projetos/aculta/web`.


## Regressão de gates e investigação do Kernel Document Viewer (2026-10-26)

- Na HEAD `2901b16d4`, a varredura PHPCS Drupal/DrupalPractice dos PHPs da PR continua em 0 erros e 49 avisos, concentrados em um aviso preexistente do PDF Viewer e 48 no CheckoutPro de Mercado Pago. Fronteira módulo–tema (2.892), Drupal 11+ (1.160), aliases (25), slugs (396; 17 violações contrib baseline), endereços (4.260), status (41) e próprios packages (41) passaram.
- Kernel completo `aculta_document_viewer` reproduz 5 erros de setup/0 assertions: o Portal ativa serviços que exigem `aculta_user` e a cadeia declarada pelo Portal; o harness atual não habilita essa closure. Tentativa focal com `aculta_user` e seus módulos-base reduziu o primeiro erro, mas expôs `key.repository` ausente. Não foi mantida alteração de harness, não foi criado serviço fictício e nenhuma instalação Runtime ocorreu. A instalação isolada completa deve carregar a closure real de 72 módulos declarada transitivamente, ou o harness precisa ser redesenhado com contratos reais mínimos; classificar como falha de setup, sem alegar defeito funcional do viewer.
- O worktree de missão foi restaurado sem alterações após a investigação. Próximo passo para esse Kernel: avaliar a viabilidade de instalar a closure Portal real em test root efêmero; manter Unit 42/225 PASS como evidência separada, sem promover o componente.

## M-3 — proteger grants de propostas Wiki no Config Sync (2026-10-26)

- O Runtime Wiki consultado read-only mantém ausentes os grants de proposta de `email_confirmed` e `content_editor`, então submissão e moderação permanecem indisponíveis pelo handler fail-closed. Nenhuma configuração ativa foi alterada.
- Adicionado `WikiProposalRolePermissionsConfigTest`, que verifica a distribuição dos quatro grants entre os dois papéis versionados e a dependência de `aculta_wiki`: 1 teste / 8 assertions PASS. PHPCS Drupal/DrupalPractice, lint, índice de status (41), pacotes próprios (41) e `git diff --check` PASS.
- Wiki avança para `0.1.0-dev.118`; README, roadmap, changelog, status, matriz e índice foram atualizados. Importação dos grants ativos e homologação dos fluxos permanecem DEFERRED até autorização operacional.


## M-1 — autoria histórica Growroom (2026-10-26)

- Decisão do responsável: autores importados do Growroom não recebem User/Profile nem página individual `/u/`; `/u/lendashistoricas` é o memorial coletivo e exibe nomes sem sufixo. `-gr` é reservado ao nome de exibição do cartão histórico no conteúdo do Fórum.
- `aculta_user 0.4.0-dev.80`: `AuthorCardBuilder::buildLegacyForum()` aplica o marcador apenas nos adapters Fórum; o método genérico mantém o nome e comentários de contextos não-Fórum não recebem `-gr`; `#profile_url` continua `NULL`. Unit User completa 135/519 PASS (uma depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint nos arquivos alterados PASS. Sem alteração Runtime. Código em `db389cd5e`; evidência e documentação atualizadas em `80aff8803`, ambos publicados em `origin/chore/aculta-m0-integridade`.
- F-D43/F-D44 foram registrados. A revisão de consumidor encontrou que o builder genérico também atende Comments compartilhados; dev.80 restringe o sufixo aos adapters de Fórum. O documento de importação agora substitui como desatualizado o bloqueio da remodelagem #297 e a decisão de manter o acervo ausente como estado final. O mapeamento dry-run atual não é importação; o repositório ainda não contém um exportador final/importador Drupal idempotente e o mapa não mantém IDs estáveis de autoria. Próxima tarefa: desenhar/testar exportador versionado com fixtures sintéticas, preservando IDs e hashes, sem copiar dados privados ao Git. Importação persistente no Runtime fica DEFERRED até pipeline, banco efêmero, prévia, backup e rollback.

- Regressão dev.80: suíte Unit completa User 135 testes/519 assertions PASS, uma depreciação PHPUnit. O Kernel isolado anterior não existe mais em `/tmp`, portanto não foi repetido; nenhum Runtime foi usado.


## M-3 — manifesto estável de autoria Growroom (2026-10-26; commit `83c6f1856`)

- Decisão F-D45: autores sem ID de origem recebem chave `nome-<sha256>` do nome normalizado; IDs IPS4/THW são prioritários. Homônimos normalizados serão agrupados. Autoria sem nome utilizável fica com estado `revisar`, não pode ser importada.
- `scripts/growroom/build_mapping.py --so-mapeamento` regenerou os mapas no storage privado Homelab, fora do Git e do Drupal Runtime. `autor-origem.json`: 9.608 registros (741 IPS4, 2.751 THW, 6.116 por nome), sem pendências; 211.388 referências a tópicos. Dez e-mails continuam omitidos.
- `verificar_importacao.py --rapido`: C1–C10 PASS; C10 validou IDs, nomes e URLs em tópicos selecionados; C8 sem e-mails. C1/C3 em modo rápido verificaram existência/tamanho, não hashes/marcador final. A integridade completa dos bytes do acervo não foi recalculada.
- Testes sintéticos `python3 -m unittest tests/scripts/test_growroom_origin_ids.py`: 10 testes PASS; `py_compile` PASS. Nenhum importador Drupal de posts/assets existe ainda. Próxima unidade: projetar exportação versionada dos posts/arquivos, seguida por importação idempotente em banco efêmero; importação no Runtime não ocorreu.


## M-4 — regressão focal Commerce, Doações e Mercado Pago (2026-10-26)

- Commerce Unit: 31 testes / 192 assertions PASS; 11 depreciações PHPUnit. Mercado Pago Unit: 11/22 PASS. Nenhum pagamento, sandbox externo ou credencial foi usado.
- Kernel da branch, MariaDB `aculta_simpletest`: `CheckoutProTest` 1/50 PASS, com 76 depreciações Core/contrib e 2 PHPUnit. Um primeiro runner carregou código do checkout compartilhado e foi descartado; a execução válida usou o caminho absoluto do worktree e autoloader prioritário da PR.
- Registro histórico supersedido: uma tentativa anterior do `DonationPortKernelTest` tinha 4 erros de setup / 0 assertions sem publisher; na dev.26 o harness passou a registrar as implementações reais `MessengerNoticePublisher` e `OrderTypeCheckoutFlows` sem instalar configuração/closure irrelevante. Resultado atual documentado pelo proprietário: 5 testes / 193 assertions PASS em MariaDB isolado, 88 depreciações upstream. Sem Runtime ou integração financeira externa.

## M-2 — validar referências físicas em revisões e rich text (2026-10-26)

- Unit focais existentes de `UnusedFileCleaner`/`UnusedMediaCleaner`: 8 testes / 31 assertions PASS, com duas depreciações PHPUnit.
- Novo `FileReferenceInspectorKernelTest` usa Node, File e campos reais em MariaDB isolado: 2 testes / 19 assertions PASS. O Entity Query com `allRevisions()` encontrou um arquivo que só permanece em revisão antiga; a consulta UUID encontrou outro referenciado por campo text_long. Nenhuma exclusão foi invocada.
- PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. `aculta_portal` avançou `.35` → `.36`, com README, changelog, status, índice e matriz atualizados. Group/Media compartilhada, tradução/revisão multilingue e validação operacional do cleaner permanecem em auditoria; Runtime continua somente leitura.


## M-5 — revalidação Functional de Statistics (2026-10-26)

- Fixture da `StatisticsDashboardFunctionalTest` explicita `aculta_user`, `search_api` e `search_api_db`: serviços do Portal dependem do ledger User e o backend da entidade Search API instalada exige esses providers. Domain MAIN `aculta_org` foi configurado como `127.0.0.1`; o PHP server deve ser iniciado com cwd no `web/` Drupal para resolver serviços relativos.
- BrowserKit passou a primeira assertion de acesso anônimo (403). Login e a segunda rota falham no helper `drupalLogin()`; painel autenticado, exportação e 404 não foram exercitados. Um experimento não versionado criou Domains `main.localtest.me` e `account.localtest.me` com cookie compartilhado no site efêmero; ainda falhou no helper, então a causa de sessão/login não foi isolada. Resultado agregado: 2 testes / 11 assertions / 2 falhas. Uma tentativa Selenium/Firefox iniciou, mas esse driver não fornece status HTTP e não valida esta classe. Nenhuma execução é PASS funcional ou revisão visual.
- PHP lint e PHPCS Drupal/DrupalPractice do teste PASS após corrigir estilo legado. Nenhum produto, Runtime ou dado persistente foi alterado.
- Próxima ação: observar a resposta da submissão de login e o cookie/registro de sessão no banco SimpleTest isolado, sem imprimir credenciais nem dados pessoais; corrigir a fixture ou o contrato real conforme evidência, preservando `403/200/404/CSV`. Statistics permanece Dev.

## Growroom — bloquear inclusão automática de itens em revisão (2026-10-26)

- Auditoria do conjunto encontrou divergência comprovada: o classificador marcava 17 URLs como `revisar`, mas o gerador filtrava somente `comercial_forte`. O conjunto anterior incluía sem seleção manual itens nessa revisão, em conflito com o roteiro de triagem. `build_mapping.py` agora conserva níveis de marcação e exige seleção explícita para `comercial_forte` e `revisar`; C9 aplica e reporta o mesmo contrato.
- Testes `python3 -m unittest tests.scripts.test_growroom_origin_ids`: 13/13 PASS, cobrindo bloqueio de revisão sem seleção, aceitação após seleção e regressão no C9. `py_compile` e `git diff --check` PASS.
- A geração foi executada somente no storage privado documentado (`sudo -n python3 -B -I scripts/growroom/build_mapping.py --so-mapeamento`). Conjunto: 31.585 tópicos; 28.134 de fóruns incluídos e 1.946 exceções temáticas; 651 marcados excluídos por falta de seleção. Nenhuma ampliação foi feita.
- Verificadores rápido e completo passaram C1–C10 após regeneração. Completo: 68.666 registros sem arquivo ausente/SHA divergente; 2.151 grupos duplicados sem problema; 228 truncamentos sem problema; 3.941 URLs de perfil permitidas com captura; C9 sem violações; C10 9.607 autorias sem duplicatas, inválidos, pendências, referências ausentes/fora do conjunto ou e-mails. Relatório do acervo permanece privado.
- Sem escrita no Runtime e sem importação de conteúdo. Próxima tarefa: desenvolver exportação a partir do conjunto aprovado e testar o pipeline de importação idempotente em banco efêmero; a importação persistente no Homelab exige prévia, destino e backup/rollback validados. Ver `aculta_image/docs/IMPORTACAO-GROWROOM.md` para decisão e critérios operacionais atualizados.

## Runtime — nova leitura por purpose e adaptação do inventário (2026-10-26)

- Antes da consulta, `drush status --uri=https://growroom420.aculta.toca.net.br` identificou host `toca`, Drupal 11.4.10, DB `aculta_runtime` e root compartilhado `/home/piradopirata/projetos/aculta/web`. O checkout publicado por `public_html -> web` é `/home/aculta`, não o worktree `/tmp/aculta-m0-integridade`; nenhum código da branch foi servido ou testado pelo Apache.
- `moduleHandler()->moduleExists()` confirmou 36/40 módulos próprios habilitados e quatro desabilitados: `aculta_activity`, `aculta_deployer`, `aculta_commerce_messenger`, `aculta_wiki_messenger`. O shell `aculta_lms_messenger` está habilitado. `aculta_commerce_melhorenvio` aparece habilitado, ao contrário do snapshot MAIN anterior; motivo não verificado. Tema/entidades foram consultados em leitura; o Fórum tem 5 tópicos, 5 posts e zero autorias legadas.
- Sem importação ou mutação no Runtime. Para testar código da PR, servir um docroot efêmero da branch ligado a banco isolado; não usar o symlink `public_html` nem presumir que o Runtime executa este worktree.

## Config Sync — decisão sobre Melhor Envio (2026-10-26)

- Leitura read-only no Runtime Growroom: `core.extension` possui 219 módulos; `config/sync/core.extension.yml` da PR lista 217. Os dois runtime-only são `aculta_commerce_melhorenvio` e `aculta_lms_messenger`.
- O config ativo `aculta_commerce_melhorenvio.settings` contém caminhos adicionais (`contact_email`, `environment`, `keys`, `redirect_uri`, `scopes`) que não existem no schema/default versionado desta branch. Valores não foram lidos nem registrados; só `origin.country_code` foi identificado como não vazio na seção de origem. Nenhum conteúdo operacional foi exportado.
- Decisão do responsável: manter Melhor Envio fora do Sync. Nenhum arquivo `core.extension` ou config do módulo foi alterado. Isso não autoriza desinstalar a extensão ativa; qualquer `cim` futuro exige preview explícito da remoção da extensão/config e rollback aprovado. A comparação funcional de código/schema fica encaminhada ao owner sem edição do diretório.

## M-4 — compatibilizar Mercado Pago com Webhooks assinados (2026-10-26)

- Auditoria cruzada reproduziu contradição: `RedirectCheckoutForm` acrescentava `source_news=ipn` à URL, enquanto o `WebhookGuard` do Portal aceita notificações Webhook assinadas e rejeita payload IPN legado (`topic`/`id`). A documentação oficial identifica esse parâmetro como seleção exclusiva de IPN, que não permite validar origem por assinatura.
- Removido `source_news=ipn`; o endpoint de Commerce passa a receber Webhooks modernos para validação/normalização pelo Portal antes de `onNotify()`. Unit `MercadoPagoWebhookFailClosedTest` + `MercadoPagoSubscriptionWebhookTest`: 13 testes / 19 assertions PASS, incluindo assinatura correta, assinatura forjada, recusa de IPN e adaptação das notificações suportadas. Nenhuma chamada de rede, credencial, pagamento ou Runtime.
- `aculta_pagamentos_mercadopago` avançou `0.1.0-dev.8` → `.9`; README, CHANGELOG, status e matriz reconciliados. PHP lint PASS; PHPCS Drupal/DrupalPractice no formulário: zero erros e seis avisos antigos de linhas longas. Sandbox externo, checkout, estorno e homologação visual permanecem pendentes.

## M-4 — corrigir setup da suíte Kernel LMS (2026-10-26)

- Kernel agregado inicialmente reproduziu erros de setup: testes parciais ativavam LMS sem sua closure, primeiro faltava `aculta_portal.checkout_settings`, depois a relação obrigatória com Messenger e a rota referenciada `aculta_studio.ganjalab_controller`. Nenhuma assertion de segurança havia falhado; a causa era a composição incompleta do container de teste.
- `AcultaLmsDependencyHarnessTrait` fornece somente definições para classes reais de Portal, Messenger, LMS e Studio com os argumentos do wiring de produção. Não cria classes fictícias nem instala configuração alheia aos cenários. `AcultaLmsInstallTest` também verifica a classe real CheckoutSettings, o padrão `curso` e o controller de rota real.
- Kernel LMS agregado no MariaDB dedicado: 29 testes / 835 assertions PASS. PHPCS Drupal e DrupalPractice, PHP lint dos arquivos alterados e `git diff --check` PASS. Uma depreciação upstream vem de `webform_score` contrib. Isso não prova a instalação limpa com a configuração completa da closure.
- `aculta_lms` avançou para `0.1.0-dev.91`; README, CHANGELOG, status e matriz de fechamento atualizados. Runtime permaneceu somente leitura.

## M-1 — corrigir os links do rodapé de avisos (2026-10-26)

- `MessengerBarHooks` gerava `all_url` das gavetas de avisos e mensagens com `canonicalRouteUrl('account', ...)`, que força URL absoluta pelo hostname de produção. Ambos agora usam `Url::fromRoute()` sem a opção `absolute`, preservando caminho relativo e retirando a dependência do hook em `DomainPurposeManager`.
- Messenger avançou `.401` → `.402`; README, CHANGELOG, status, matriz de fechamento e índice raiz foram sincronizados. `PersistentNoticeAccessTest`: 5 testes / 11 assertions PASS com autoloader prioritário do worktree. PHP lint, DrupalPractice, índice de 41 componentes, pacote de 41 componentes e `git diff --check` PASS.
- PHPCS Drupal nos dois arquivos alterados ainda aponta erros antigos de PHPDoc/comentários/uso não relacionado e warnings de linhas longas; o teste focal passou. Nenhuma validação foi declarada para a interface no navegador. Não houve operação Runtime.
- Próxima ação: retomar Config Sync do Messenger com a decisão de versionar defaults estruturais e preservar overrides ambientais; provar o mecanismo de override sem importar configuração no Runtime.

## M-1 — versionar defaults estruturais do Messenger (2026-10-26)

- Conforme decisão do responsável, `config/sync/aculta_messenger.settings.yml` agora inclui `email_modes` e `magazine_events` com valores idênticos a `config/install`. `private_message_digest_anchor` continua fora do Sync por ser operacional. A documentação descreve overrides em `settings.php`/`settings.local.php` e exige mapeamento das diferenças ativas antes de importação.
- `MessengerModuleInstallConfigTest` agora compara ambas as árvores instaláveis/exportadas e protege a ausência da data operacional no Sync. Em runner/autoloader da branch, o teste de contrato e `MessengerSettingsFormTest` passaram: 5 testes / 53 assertions. YAML, PHP lint, PHPCS Drupal/DrupalPractice no teste, Config Extension (1.590 checks/1.437 arquivos), pacote próprio (41) e índice (41) PASS.
- Messenger avançou `.402` → `.403`; README, CHANGELOG, status, matriz e README raiz foram sincronizados. Nenhuma configuração ativa foi lida em valores, modificada ou importada. O Runtime tem diferenças ativas já observadas somente por nomes de caminhos; preservar essas políticas requer override local conferido antes de qualquer `cim`.

## M-3 — corrigir autoria da última atividade do Fórum (2026-10-26)

- A revisão de F-D44 encontrou `PublicForumLastActivityAuthorHooks` usando `buildLegacy()` enquanto cartões de tópicos e posts usam `buildLegacyForum()`. Corrigido para exibir `-gr` também no cartão da última atividade. Autores históricos continuam sem User/Profile e sem rota individual; `/u/lendashistoricas` mantém os nomes sem sufixo.
- A omissão contradiz o contrato de apresentação do candidato RC.2, portanto o status foi reclassificado para Dev `0.1.0-dev.323`; RC.2 permanece histórico. README, CHANGELOG, roadmap, checklist RC, status, matriz e índice raiz atualizados. `LegacyAuthorDisplayNameTest` 1/1 PASS; PHPCS Drupal/DrupalPractice, PHP lint, índice de 41, pacote de 41 e diff check PASS. Não houve migration, Runtime write ou revisão visual.
- Próxima verificação: teste renderizado/Firefox ESR com fixture de autoria histórica em ambiente local isolado; em paralelo, completar o importador idempotente e ensaio efêmero Growroom antes de qualquer importação no Homelab.

## M-0 — retirar exceções contrib resolvidas do gate de slugs (2026-10-26)

- Reexecução no HEAD `e6ecf899` indicou que as 17 rotas contrib listadas na baseline DT-P20 deixaram de violar a política; o gate exige que cada correção seja removida da lista. As 17 entradas foram eliminadas sem alterar regras/rotas contrib.
- `validate-public-slugs.php`: PASS, 44 rotas, zero violações contrib na baseline. `validate-environment-addresses.php`: PASS, 4.265 arquivos. Status (41) e pacotes (41) também PASS; diff check PASS. Esse resultado substitui os snapshots históricos que ainda listavam 17 exceções.

## M-5 — isolar o bloqueio Functional de Statistics (2026-10-26)

- O primeiro ensaio BrowserKit serviu por engano a raiz do worktree, que não inclui `web/core`, e retornou 500; foi descartado como harness inválido. O teste foi repetido com Drupal 11.4.10 físico em docroot efêmero, código da PR por symlinks e banco `aculta_simpletest`; esse ambiente não usa o checkout `public_html` nem o Runtime.
- Ambos os métodos do `StatisticsDashboardFunctionalTest` falham durante compilação do container, antes de assertions: `aculta_commerce_melhorenvio.shipment_address_binder` injeta `MelhorEnvioAddressMapper`, mas o submódulo só registra o serviço `aculta_commerce_melhorenvio.address_mapper`. Evidência enviada ao owner Melhor Envio; não modifiquei diretórios de ownership delegado. Statistics permanece sem PASS Functional; README/status/matriz/CHANGELOG atualizados. Server PHP local encerrado, sem arquivo criado no `sites/simpletest` compartilhado e sem Runtime write.

## M-1 — fechar PHPCS nos arquivos alterados do Messenger (2026-10-26)

- Após corrigir os links relativos das gavetas, o PHPCS Drupal apontou PHPDoc e comentários longos preexistentes nos dois arquivos tocados. Os docblocks e comentários foram regularizados sem alteração funcional. Drupal e DrupalPractice passaram sem erros ou avisos nos arquivos; `PersistentNoticeAccessTest` passou com 5 testes / 11 assertions e PHP lint/diff check passaram.
- Commits `8b5e31043` (estilo) e `b7c806859` (links relativos) publicados em `origin/chore/aculta-m0-integridade`. Runtime não foi alterado.

## M-4 — corrigir injeção do endereço Melhor Envio (2026-10-26)

- O erro reproduzido ao compilar o container para Statistics vinha de um alias ausente: `ShipmentAddressBinder` solicita `MelhorEnvioAddressMapper` por tipo, mas o serviço de produção tinha apenas o ID `aculta_commerce_melhorenvio.address_mapper`. O owner adicionou alias de classe para o ID existente e teste que compila o YAML de serviços real; sem serviço fictício e sem mudança de Config Sync.
- Commit do owner `0f6e61ad5` foi revisado e incorporado como `4ae4229e4`. Na branch integrada, a suíte Unit completa Melhor Envio passou: 30 testes / 79 assertions; o teste novo de autowiring passou 1/1; PHP lint e PHPCS Drupal/DrupalPractice do novo teste passaram. Nenhuma chamada externa ou escrita Runtime.
- A Functional Statistics ainda não foi reexecutada depois do alias; o status permanece pendente até testar o harness isolado e todas as rotas/assertions. OAuth, cotação, contratação e etiqueta permanecem fora do incremento funcional executado.

## M-1 — rejeitar CEPs com caracteres contaminados (2026-10-26)

- `CepLookupService::normalize()` anteriormente removia qualquer caractere não numérico e aceitava entradas como `abc01310100xyz`. Agora aceita oito dígitos contínuos ou a forma com hífen/espaços prevista e rejeita letras/separadores extras; o teste cobre essas regressões.
- `CepLookupServiceTest`: 10 testes / 45 assertions PASS; gate `validate-account-address.php`, PHP lint, PHPCS Drupal/DrupalPractice focal, status index (41), packages (41) e diff check PASS. `aculta_portal_cep` avançou `0.3.0-dev.11` → `.12`; README, CHANGELOG, status, índice e closeout foram sincronizados. Sem consulta externa ou alteração Runtime.
- Commit `66e9d4d06` foi publicado em `origin/chore/aculta-m0-integridade`.

## M-5 — reexecutar Functional Statistics após alias do Melhor Envio (2026-10-26)

- O teste de wiring corrigido foi integrado; a primeira repetição com Core físico duplicado no docroot voltou a falhar antes de assertions por dupla declaração de `system_hook_info()`. A tentativa seguinte usou o Core compartilhado read-only, mas o PHPUnit ficou sem avançar por mais de 40 segundos após exibir o cabeçalho; servidor e runner foram encerrados. Não houve resultado funcional. A documentação de Statistics e a matriz agora distinguem o erro de serviço resolvido do bloqueio atual do harness, e não registram PASS.
- Não houve uso de Firefox ou validação visual. Banco dedicado isolado apenas; nenhum Runtime, dado de produção ou configuração persistente tocados.

## M-1 — revalidar o Confirmador com transporte de teste (2026-10-26)

- `AcultaMailConfirmerKernelTest` foi reexecutado no MariaDB dedicado: 5 testes / 38 assertions PASS. A suíte usa o mail collector do Core e confirma estados pendente/confirmado/cancelado/expirado, hash inválido, texto aprovado de e-mail e fila de reenvio sob cooldown; nenhuma mensagem foi entregue externamente.

## M-1 — proteger a rota de reenvio de confirmação privada (2026-10-26)

- A rota `/aculta-mail-confirmer/reenviar/{confirmation}` tinha apenas `_permission: access aculta mail confirmer`, sem invocar o handler de acesso da entidade. Isso permitia à permissão genérica acionar IDs que não pertencem à conta. A rota agora exige `_entity_access: confirmation.update` e continua exigindo `_csrf_token`; o handler de confirmações privadas limita ao proprietário ou administrador.
- Kernel lê a definição YAML da rota, cria a Route/RouteMatch e executa o checker genérico de Entity Access com entidades reais: titular permitido e outra conta negada. Confirmador 6 testes/45 assertions e User Change 2/15 PASS no MariaDB dedicado; PHPCS Drupal/DrupalPractice, PHP lint, YAML, `validate-no-temporary-route-aliases.php`, status index (41), packages (41) e diff check PASS.
- `aculta_mail_confirmer` avançou `0.1.0-dev.3` → `.4`; README, CHANGELOG, status, closeout e índice sincronizados. Nenhum SMTP externo ou Runtime foi usado.

## M-1 — revalidar CPF/CNPJ com autoload da branch (2026-10-26)

- Uma primeira Unit executada com bootstrap genérico carregou classes pelo checkout compartilhado e apresentou erro no teste de colisão SQL; não foi classificada como falha do produto. Novo bootstrap efêmero priorizou os namespaces Portal/CPF da PR e manteve Core 11.4.10 e contrib instalados: Unit CPF 48 testes / 116 assertions PASS e Kernel CPF 3 / 8 PASS em MariaDB dedicado. Nenhuma alteração de código, atualização 10002 ou Runtime write.

## Checkpoint de integridade pós-correções (2026-10-26)

- No HEAD após as correções de Messenger, Melhor Envio, CEP, Confirmador e documentação Statistics: status index 41 PASS; pacotes 41 PASS; fronteira módulo/tema 2.897 PASS; Drupal 11+ 1.160 PASS; aliases transitórios 25 arquivos PASS; slugs públicos 44 rotas/0 violações PASS; endereços 4.266 arquivos PASS; Config Extension Dependencies 1.590 checks/1.437 configs PASS.
- Esse checkpoint não encerra M-0: comparações completas de Config Sync/Runtime, RBAC Wiki, Functional Statistics, Browser e homologações por propósito continuam abertas. Módulos permanecem Dev quando suas evidências RC não estão completas.

## M-2 — revalidar a política de referências File/Media (2026-10-26)

- Com bootstrap PSR-4 explícito da branch, a suíte Unit de `aculta_portal/tests/src/Unit/Cleanup` passou 11 testes / 67 assertions (2 depreciações PHPUnit) e `FileReferenceInspectorKernelTest` passou 2 / 19 no MariaDB dedicado. A verificação cobre referências estruturadas em revisões antigas e UUID de File em rich text. Não foi chamada rotina de exclusão; Runtime permaneceu somente leitura.

## M-1 — reservar `-gr` para cartões históricos de Fórum (2026-10-26)

- Decisão do responsável: o apelido é identidade por UID; UIDs distintos não compartilham apelido; perfis por papel do mesmo UID exibem o mesmo apelido; renomear libera o anterior imediatamente; referências internas permanecem por UID; nenhum apelido de conta pode terminar em `-gr`.
- `AccountNames::isValid()` e `publicRouteSlug()` agora recusam `-gr` em qualquer caixa. O marcador continua apenas nos adapters de autoria de conteúdo do Fórum; autores Growroom não recebem User/Profile nem rota própria. `aculta_user` avançou `.80` → `.81`. User Unit 136/523 PASS (uma depreciação); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. O Kernel foi encerrado antes de concluir porque o banco SimpleTest estava em uso pelo agente do Index; portanto Kernel fica pendente.
- A garantia concorrente foi separada: o responsável escolheu índice SQL derivado slug→UID, preservando Profile como fonte de verdade; implementação e testes concorrentes ainda não estão feitos. Não houve alteração Runtime.


## M-1 — convenção de apelido automático e slug público (2026-10-26)

- O responsável confirmou que colisões automatizadas podem exibir `nome#2` com slug `/u/nome-2`. Também esclareceu que `#N` pode ser digitado manualmente, desde que o slug esteja disponível; o formulário valida e rejeita colisão, sem gerar sufixo em nome do usuário. O mapeamento só reconhece sufixo numérico final (`#2` ou maior), nunca deixa `#` literal na rota. `nome#2` e `nome-2` ocupam o mesmo identificador público.
- `AccountNames`, `NicknamePolicy`, geração inicial de Profile e lookup da rota foram alinhados; OAuth recupera a parte local do e-mail quando o Social Auth precisou numerar o username. Os nomes Growroom `-gr` seguem proibidos para conta.
- AccountCreationHooks também confirma conversão de username OAuth `nome-2` em apelido `nome#2`; testes focais: 9 testes / 67 assertions PASS; suíte Unit User completa: 140 testes / 542 assertions PASS, com uma depreciação PHPUnit. PHPCS Drupal/DrupalPractice e PHP lint dos arquivos tocados PASS. O Runtime não foi alterado. A projeção SQL escolhida para concorrência, sincronização de Profiles de um UID e Kernel/MariaDB seguem pendentes; não alegar unicidade concorrente até essa fase.


## M-1 — projeção única de apelidos por UID (2026-10-26)

- `aculta_user` dev.83 adiciona tabela owner-managed `uid PRIMARY KEY, slug UNIQUE`, hook Profile transacional, sincronização de Profiles por UID, liberação imediata ao renomear, leitura de slug pela rota pública e update/rebuild 10013 validado antes de substituir o índice. Rebuild bloqueia conflitos e publica somente contagens agregadas. Runtime não foi alterado.
- User Unit completo: 140 testes / 543 assertions PASS (uma depreciação PHPUnit); PHPCS Drupal/DrupalPractice, PHP lint, gates de pacote/status, fronteira módulo/tema, Drupal 11+, aliases, slugs, endereços e dependências Config Sync PASS.
- Kernel `NicknameRegistryKernelTest`: 6 testes / 44 assertions PASS em SQLite `:memory:` e 6 / 44 PASS em MariaDB dedicado. Cobertos claims, rename/liberação imediata, chave SQL única, rebuild idempotente/conflitos e sync no mesmo UID. O fixture foi ajustado para habilitar `filter` após o primeiro erro de setup. Falta cenário de dois escritores simultâneos em processos separados e rollback transacional de falha no save. Update 10013 não foi executado em Runtime.


## M-1 — corrida concorrente da claim de apelido (2026-10-26)

- O Kernel `NicknameRegistryKernelTest` foi ajustado para carregar explicitamente os fontes do módulo no worktree isolado e evitar resolver classes de uma versão antiga do checkout compartilhado. Fixture de processo carrega o Core e o driver MariaDB instalados; não usa SQLite.
- O teste abre dois processos, ambos leem que o slug está livre, e só então libera as inserções concorrentes. Resultado MariaDB dedicado: **7 testes / 55 assertions PASS**, incluindo exatamente uma claim vencedora e uma rejeitada pela constraint. PHPCS Drupal/DrupalPractice, PHP lint dos dois arquivos e `git diff --check` PASS. As 11 depreciações reportadas vêm de Core/contrib já carregados pelo Kernel.
- `aculta_user` avançou `0.4.0-dev.83` → `0.4.0-dev.84`. O update 10013 não foi aplicado a Runtime; rollback de falha de save e instalação efêmera do update permanecem pendentes.
- A primeira invocação antes do ajuste do harness falhou porque a branch carregava classes ausentes no checkout principal e o subprocesso procurava Core no caminho errado; não é contada como resultado de produto.


## M-1 — revalidação do lookup de CEP (2026-10-26)

- O autoloader PHPUnit foi montado para apontar o namespace `Drupal\aculta_portal_cep` aos fontes do worktree. `CepLookupServiceTest`: **10 testes / 45 assertions PASS** em PHP 8.4.27; `scripts/validate-account-address.php`: **PASS** (23 checks).
- PHPCS Drupal/DrupalPractice do módulo inteiro continua **FAIL**, 144 erros/28 warnings em 11 arquivos (inclui 72 erros no JS); a varredura só de `src`/`tests` encontra 72/28 em 10 arquivos. Nenhum arquivo do módulo foi alterado por esta verificação; não reduzi cobertura nem reformatei baseline legado.
- O script Browser/X11 existente usa login por link único e submete o formulário, gravando endereço na conta. Como não foi confirmado um usuário descartável em banco/Runtime isolado, a homologação visual fica DEFERRED. Nenhuma mudança ou gravação no Runtime.
- Uma tentativa inicial de chamar `tests/run.php` no módulo CEP foi inválida: esse arquivo não existe nesse componente; não é contabilizada como teste.


## M-1 — conformidade PHP do CEP (2026-10-26)

- Os dez arquivos PHP de `aculta_portal_cep` foram revisados após a varredura PHPCS identificar PHPDocs ausentes/incompletos e formatação de arrays. A alteração preserva os contratos e a lógica; a descrição do formulário foi encurtada para caber no padrão sem fragmentar texto traduzível.
- PHPCS Drupal/DrupalPractice com extensões PHP explícitas: **PASS**, sem erros ou warnings em todo o módulo. PHP lint: PASS. `CepLookupServiceTest`: **10/45 PASS** no autoloader do worktree. `validate-account-address.php`: **PASS, 24 checks**.
- Correção da medição anterior: a varredura genérica que percorreu o módulo inteiro incluiu `cep-address.js` como PHP e gerou falsos erros (72 ocorrências); não é um resultado válido de PHPCS PHP. O arquivo JS continua coberto pelo gate funcional de endereço.
- `aculta_portal_cep` avançou `0.3.0-dev.12` → `0.3.0-dev.13`; README do proprietário não precisava de alteração. Sem Runtime write. Browser visual permanece DEFERRED: o script existente salva o endereço da conta e requer identidade descartável em ambiente isolado.

- Smoke Firefox ESR visível (`DISPLAY=:0`): `https://example.com/` carregou; `https://conta.aculta.toca.net.br/entrar` retornou erro WebDriver. Consulta read-only do resolver local e `curl` confirmou que o hostname não resolve (curl exit 6); sem sessão autenticada ou escrita. Isso não é reprovação do módulo nem aprovação visual; Browser fica DEFERRED por DNS indisponível e falta de identidade descartável.


## M-1 — rollback da projeção de apelidos (2026-10-26)

- `NicknameRegistryKernelTest::testFailedProfileSaveRollsBackNicknameSynchronization` executa a sincronização sob transação, injeta uma falha simulada depois do presave e verifica a claim anterior, ausência da nova claim e persistência antiga de ambos Profiles. Resultado agregado MariaDB: **8 testes / 65 assertions PASS**, com 11 depreciações Core/contrib. PHPCS Drupal/DrupalPractice e PHP lint focais PASS.
- `aculta_user` avançou `0.4.0-dev.84` → `0.4.0-dev.85`. Update 10013 não executado no Runtime; validar instalação limpa/rebuild com conflito e sem conflito em ambiente efêmero permanece pendente.
