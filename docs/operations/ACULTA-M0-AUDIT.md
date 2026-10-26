# Registro de auditoria ACULTA M-0

Data: 2026-10-25 a 2026-10-26. Base remota consultada: `805fb7c510baeccac22f12986743245219ce3e49`. Checkout de execução: branch isolada `chore/aculta-m0-integridade`.

| ID | Severidade | Evidência | Solução / resultado | Estado |
|---|---|---|---|---|
| M0-001 | Alta | `scripts/validate-project-status-index.php` aceitava `version_note` substituir a versão do manifesto; quatro divergências preliminares indicadas pela missão | Índice agora compara campo estruturado `version` com o manifesto, `progress_note` separado e versão CLI Deployer preservada como nota. Teste negativo temporário rejeitou versão adulterada | Corrigido; índice PASS com 41 componentes |
| M0-002 | Média | Gate `validate-own-module-packages.php` classificava fixtures Messenger/Wiki em `tests/modules/` como pacotes distribuídos | Gate limita escopo a componentes de produto | Corrigido; 41 checks PASS |
| M0-003 | Alta | Config Group `lms_course` e permissões usam `aculta_document_viewer_group:course_document`, mas Index/LMS Resources/H5P consultavam `aculta_pdf_viewer_group:course_document`; provider de Index declarado no módulo PDF | Relação permanece propriedade de Document Viewer Group. Provider transferido para owner correto. Consumidores atualizados. PDF Group permanece proprietário temporário do bundle Media e configuração; sem migração/desinstalação | Corrigido no código; migração de armazenamento/plugin não foi executada nem é necessária ao contrato verificado |
| M0-004 | Média | `aculta_lms` não declarava Document Viewer Group apesar de consumir sua relação; H5P e Resources também usavam contrato inconsistente | Dependências e IDs de plugin alinhados aos proprietários reais; versões/changelogs ajustados | Corrigido; Kernel Index 1/28, LMS 4/208, Owner Integration 2/88 e H5P focal 3/134 PASS. O Kernel H5P usa fixture mínima de Group e não instala configuração LMS alheia ao contrato; deprecações restantes vêm de Core/contrib |
| M0-005 | Média | Fallbacks e Wiki search form continham classes/tokens da apresentação; Wiki referenciava diretamente tema ACULTA420 | Removidos fallbacks/classes diretas; Wiki passa estado genérico por opção de rota, tema consome a opção; fronteira documentada | Corrigido; gate módulo–tema PASS (2880 checks) |
| M0-006 | Baixa | Exemplos/fixtures Messenger e Wiki usavam domínios de ambiente | Substituídos por `example.com` reservado a exemplos/testes | Corrigido; endereços PASS (4239 arquivos) |
| M0-007 | — | `aculta_forum`/Document Viewer e relação Group consultados antes desta auditoria poderiam divergir em config exportada/runtime | HEAD atual e leitura do Runtime indicaram módulos e plugin alinhados; nenhuma discrepância atual reproduzida | Não reproduzido no estado atual |
| M0-008 | Alta | O inventário Runtime anterior não listava cada extensão de forma verificável | Consulta read-only em URI MAIN confirmou 35/40 módulos habilitados e cinco desabilitados; tema default `aculta420`; `aculta_messenger` tem 5 hook updates e 3 post-updates pendentes. Negociação explícita de DomainPurposeManager resolveu os nove hosts ao purpose esperado (9/9) | Parcial; revisão de overrides de Domain Config, Entity Access e Config Sync por purpose ainda pendente |
| M0-009 | Alta | Cobertura de políticas Domain/Entity Access, config ativa/exportada, rotas, hooks, entidades e testes não foi completada para todos os módulos | Matriz individual registra verificações focais e limites; continuar por ondas antes de RC | Parcial; auditorias por componente ainda pendentes |
| M0-010 | Alta | `core.extension.yml` não listava módulos exigidos por dependências de componentes/configuração ativa: Document Viewer, Document Viewer Group, Entity Print e LMS Resources | Adicionadas as quatro extensões ao Config Sync. Runtime MAIN read-only confirmou os quatro habilitados; 1.425 YAMLs de configuração agora têm providers presentes no Config Sync | Correção versionada; não importada no Runtime. Na revisão seguinte, 19 módulos ativos ainda estavam fora do export; `google_tag` foi reconciliado no M0-011 |
| M0-011 | Média | `google_tag` estava ativo no Runtime e previsto no roadmap de Statistics, mas ausente do Config Sync; o delta poderia removê-lo numa importação | Comparação read-only confirmou que não há contêiner configurado, `use_collection` está desativado e os dois valores coincidem com `google_tag.settings` instalável do contrib. A extensão e esse settings vazio foram adicionados ao Sync | Corrigido no repositório; delta ativo/Sync reduzido de 19 para 18. Sem `cim`, alteração Runtime ou emissão de tags |
| M0-012 | Alta | Domain Config ativo e versionado não tinha comparação efetiva por purpose; a home da Conta no Sync era `/studio` apesar do contrato atual | Negociação DomainPurpose passou em nove URIs explícitas. As seis coleções Domain têm os mesmos nomes ativos/exportados; `domain.conta_aculta_org/system.site.page.front` foi alinhado a `/minha-conta`. A Revista ainda diverge em `domain.revista420_aculta_org/system.site.name` e em `domain.revista420_aculta_org/metatag.metatag_defaults.front` (`tags.title`, `tags.og_title`); o Sync segue o contrato de apresentação `Revista 420` | Correção de Conta versionada e gates PASS. A divergência da Revista permanece DEFERRED para avaliação do owner; nenhum override ativo foi alterado |
| M0-013 | Alta | O Config Sync não provava que o formato `full_html`, usado pelo tipo `aculta_adm`, era restrito à administração | Runtime MAIN read-only encontrou `use text format full_html` somente no papel `administrator`; novo Kernel Editor carrega os papéis do Sync e confirma permissão/FilterFormat access apenas para administrador | Teste focal integrado (`8/127` PASS); nenhum grant Runtime alterado. Config Sync do Editor e homologação Browser continuam pendentes |
| M0-014 | Média | `aculta_document_viewer` e `aculta_pdf_viewer` distribuíam cópias byte-a-byte idênticas do viewer PDF.js, wrapper HTML/JS e CSS | `aculta_document_viewer` agora declara dependência do owner PDF e anexa sua biblioteca; assets duplicados removidos. Builder Document mantém configuração e checagens próprias de Media/File | Unit Document 42/225 PASS; Kernel atual falha em setup (5 erros/0 assertions) na closure de Portal/Search API; instalação limpa fica DEFERRED até corrigir o harness |
| M0-015 | Alta | `aculta_lms_h5p` removia assets gerenciados usando verificações locais de File Usage/Entity Usage e chamava o Image remover diretamente, sem o coordenador Portal que conhece referências globais | H5P agora libera seus próprios markers e delega imagens e assets não-imagem a `aculta_portal.unused_file_cleaner`; recusa manter o fluxo se o resultado não for `Deleted`. Kernel usa `FileReferenceInspector` e `UnusedFileCleaner` reais num harness isolado | Corrigido; H5P Unit 25/81 e Kernel 15/388 PASS (41 depreciações upstream); PHPCS focal PASS. PHPCS do módulo inteiro tem erros legados em arquivos não modificados; nenhuma limpeza Runtime ocorreu |
| M0-016 | Alta | READMEs/manifests e `EMAIL-CONFIRMER-INCORPORACAO.md` diziam que o Confirmador bloqueia login não confirmado; `EMAIL-CONFIRMATION-POLICY.md` e código criam conta ativa, permitem login e restringem ações; a seção 7 também dizia que o hash não expira, embora `hash_expiration` seja 86400 | Alinhados manifestos, READMEs, changelogs, política atual, mapa módulo–tema e execução contínua ao comportamento real. Prova focal: user novo fica ativo e sem marca/papel; validators permitem autenticação de conta ativa; expiração de 24 h e troca continuam no Kernel | Corrigido em configuração/documentação; Confirmador + troca 7/53 Kernel, validators de login 9/22 Unit e policy 3/16 Kernel PASS. Browser autenticado segue pendente; sem SMTP externo ou write Runtime |
| M0-017 | Alta | Config Sync declara quatro grants de proposta nos papéis `email_confirmed` e `content_editor`, mas a leitura do Runtime Wiki encontrou os grants ausentes; o acesso permanece negado pelo handler | Adicionado `WikiProposalRolePermissionsConfigTest` para fixar os quatro grants e a dependência do módulo nos papéis versionados | Proteção de Config Sync PASS (1/8); grants Runtime ainda DEFERRED, sem alteração de permissões nem importação |

## Gates globais executados

- `validate-project-status-index.php`: PASS (41 componentes).
- `validate-own-module-packages.php`: PASS (41 checks).
- `validate-config-extension-dependencies.php`: PASS (1.558 referências de dependência em 1.425 arquivos YAML; manifests próprios habilitados e `dependencies.module` versionadas resolvem no `core.extension.yml`). O delta ativo/Sync passou de 19 para 18 após reconciliar `google_tag`; 17 extensões do Editor/CKEditor e o shell `aculta_lms_messenger` continuam exigindo decisão por owner antes de importar.
- `validate-domain-front-paths.php`: PASS (16 checks); `validate-domain-presentation-contract.php` executado via Drush bootstrapped no MAIN: PASS (162 checks); `validate-public-slugs.php`: PASS (395 rotas; 17 baseline contrib); `validate-no-temporary-route-aliases.php`: PASS (25 routing files). O primeiro gate direto de PHP sem bootstrap (`validate-domain-presentation-contract.php`) falhou com `Class Drupal not found` e foi repetido pelo mecanismo Drush correto.
- `validate-module-theme-map.php`: PASS (196 checks) após adicionar as linhas ausentes do mapa para seis submódulos e registrar `entity_print`; o gate anterior falhava em sete omissões de documentação.
- `validate-module-theme-boundary.php`: PASS (2880 verificações).
- `validate-aculta-portal-drupal11.php`: PASS (1160 checks).
- `validate-no-temporary-route-aliases.php`: PASS (25 arquivos de rota).
- `validate-public-slugs.php`: PASS (380 rotas; 17 violações contrib pertencem à baseline documentada).
- `validate-environment-addresses.php`: PASS (4239 arquivos).
- `git diff --check`: PASS.

Runtime foi consultado somente em leitura; não houve `cim`, `updatedb`, instalação/desinstalação, limpeza, mudança de permissões, nem mutação de entidades.

Inventário Runtime de 2026-10-25 foi executado em `http://aculta.toca.net.br` com o Drush do checkout compartilhado: `moduleHandler()->moduleExists()` confirmou 35/40 módulos habilitados e cinco disponíveis desabilitados (`aculta_activity`, `aculta_deployer`, `aculta_commerce_melhorenvio`, `aculta_commerce_messenger`, `aculta_wiki_messenger`). `aculta_messenger` tem cinco hook updates (10008–10012) e três post-updates pendentes em `updatedb:status`; nenhum foi aplicado. A leitura não verifica Config Domain em cada purpose nem código da branch M-0; esses itens permanecem na matriz.

## Dependências de fechamento — direção

A seta `A → B` significa “B consome contrato/serviço fornecido por A”.

- Mailer → Messenger → publishers de Wiki/LMS/Commerce/Magazine.
- Image + Portal Cleanup → Gallery/Forum/Wiki/Magazine e recursos de LMS.
- User + CPF + CEP + Portal Checkout → Commerce/LMS/Doações (validar dependências por manifesto antes da execução de onda).
- PDF Group (bundle/config Media) + Document Viewer Group (relação Group) → LMS Resources/H5P/Forum.
- Content owners → Content Index → Statistics e consumidores do catálogo.


## Testes proporcionais e limitações observadas

- Unit Wiki: 20 testes / 104 assertions PASS.
- Kernel Index — acesso ao documento de curso: 1 / 28 PASS.
- Kernel LMS — acesso ao índice de conteúdo: 4 / 208 PASS.
- Kernel Index — integração dos owners: 2 / 88 PASS.
- Unit Content Index service map: 1 / 3 PASS.
- Composer validate: PASS com avisos de constraints exatas já existentes.
- YAML parse: PASS (49 arquivos alterados/não versionados inspecionados).
- PHP lint: PASS (30 arquivos PHP alterados na execução).
- PHPCS Drupal/DrupalPractice: FAIL em arquivos tocados que já contêm violações legadas de nomenclatura, estrutura de comentários e `unserialize()` sem lista de classes; o gate não foi enfraquecido. O script `validate-own-module-packages.php` foi corrigido para 0 erros (restam avisos de linhas longas/constante global).
- Kernel H5P focal: PASS (3 testes/134 assertions) em bootstrap isolado com código da branch; usa o plugin real `aculta_document_viewer_group:course_document` e verifica membros, educador, estudante, não membro, referência e revogação. A tentativa anterior que instalava a configuração LMS completa falhava antes das assertions por ausência de `aculta_studio.ganjalab_controller`; a fixture foi reduzida ao contrato do teste sem serviço de produto fictício. Persistem 41 deprecações upstream.
- `validate-admin-domain-policy.php`: execução direta em PHP CLI falhou por ausência do bootstrap Drupal; execução correta via `drush php:script` no Runtime identificado passou com 3.498 checks, em modo read-only. Browser/visual e outros gates dependentes de bootstrap continuam pendentes quando aplicáveis.

## Evidência Portal adicionada em 2026-10-25

- `AccountOtherDataStudioReturnTest`: harness Unit agora inicializa `path.validator` para o caminho de validação usado por `Url::fromUserInput()` e restaura o container Drupal pré-existente no `tearDown()`.
- Suíte Unit completa do Portal: 350 testes / 878 assertions PASS, seis depreciações PHPUnit.
- Suíte Kernel completa do Portal em MariaDB de testes isolado: 53 testes / 831 assertions PASS, 90 depreciações Core/contrib. Nenhuma gravação no Runtime.
- Isso melhora a evidência automatizada do Portal; Functionals, Browser/X11 e comparação de configuração ativa por purpose continuam pendentes.

## Reconciliação Config Sync — 2026-10-26

- `core.extension.yml` não listava `aculta_document_viewer`, `aculta_document_viewer_group`, `entity_print` e `aculta_lms_resources`, embora manifests/config exportados os exijam e todos estejam ativos no Runtime MAIN. As quatro entradas foram adicionadas, sem executar importação.
- Varredura de 1.425 configurações versionadas: toda dependência `dependencies.module` tem provider no `core.extension`. Varredura dos manifestos dos módulos ACULTA habilitados também passou.
- Comparação read-only de `core.extension` ativa (218 extensões) versus Sync (199): permanecem 19 módulos ativos não listados no export — `aculta_editor`, `aculta_lms_messenger`, `google_tag` e 16 módulos `ckeditor5_plugin_pack*`. O baseline de drift já inclui `core.extension`, mas não explica a intenção por extensão. Nenhum deles foi removido ou adicionado nesta correção; a importação pode alterar habilitação, então a decisão continua pendente e nenhum `cim` foi executado.

- Revisão read-only do ownership de Editor: manifesto e README declaram `aculta_editor` e suas 16 dependências CKEditor obrigatórias; Runtime MAIN confirma todas ativas. O owner confirmou que Auto Image é exceção intencional e Premium/WProofreader ficam desabilitados. Oito templates em `config/install` não têm equivalentes em `config/sync`.
- Drupal 11 `ConfigImporter::createExtensionChangelist()` agenda instalação de extensões presentes apenas no source `core.extension`; `ConfigImporter::processExtension()` configura a instalação para usar a própria storage de Sync. Como os oito templates do Editor não estão exportados, adicionar agora os 17 módulos ao `core.extension` pode instalar as extensões sem esses defaults. O resultado exige reconciliar os templates e testar instalação/import em banco efêmero antes de alterar Sync. Nenhum arquivo do Editor ou Runtime foi alterado.
- A investigação do Editor restringe o delta global: as 17 extensões do Editor têm owner/necessidade identificados; permanecem separadas `google_tag` (intenção de ambiente não registrada) e `aculta_lms_messenger` (shell legado que requer uninstall controlado). Ainda não é seguro preparar `cim` nem classificar todo delta como resolvido.


## Content Index — verificação read-only e Kernel focal (2026-10-26)

- Drush read-only em MAIN confirmou `aculta_index` habilitado, geração ativa válida, 145 entradas e 110 relações; queue sem itens e auditoria completa sem achados. A entrada foi adicionada a `config/sync/core.extension.yml`; não houve importação, rebuild ou escrita no Runtime.
- Unit do módulo: 51 testes / 262 assertions PASS. A suíte Kernel full anterior falhava por paths do checkout compartilhado, dependência de plugin owner ausente no harness e schemas `flagging`/`history` faltantes. Esses pontos foram corrigidos nos testes para usar caminhos da branch, inicializar o owner `aculta_document_viewer_group` e instalar schema necessário. Após as correções, os Kernel focais passaram: Media 5/101 e Group/owner/Wiki 10/361; há 272 depreciações upstream/Core. A suíte completa está em execução para confirmação agregada.

- Atualização 2026-10-26: gate Domain administrativo executado com Drush e URI explícito `https://aculta.toca.net.br`: PASS (3.498 checks). Consulta de status confirmou somente leitura no Runtime `aculta_runtime`; não houve alteração persistente.

- Acceptance guardrail: `validate-single-acceptance.php` identified the missing root AGENTS pointer; corrected the instruction to reference `docs/portal/ACEITE-UNICO.md`. Single Acceptance and Single-use Links gates now pass.
- PHPCS scope review: the global status/package scripts and WikiController test edited in this continuation now pass focused PHPCS. PR-wide findings remain in delegated Deployer/Editor PHP and are being corrected by their owner; do not classify the aggregate as PASS until re-run.


## Wiki — RBAC de propostas no Runtime (2026-10-26)

- `status` Drush identificou Drupal 11.4.10, DB `aculta_runtime`, URI Wiki `https://wiki420.aculta.toca.net.br`; consulta de permissões foi read-only. Runtime: `email_confirmed` não tem `submit wiki proposals` nem `view own wiki proposals`; `content_editor` não tem `view any wiki proposals` nem `moderate wiki proposals`.
- A configuração versionada `user.role.email_confirmed.yml` e `user.role.content_editor.yml` contém os quatro grants. É uma divergência ativa-versus-Sync que bloqueia envio e fila de propostas no Runtime. A ausência desses grants mantém o fluxo negado; os handlers exigem Entity Access e permissões explícitas. O teste `testProposalSubmissionUsesPrivateRevisionedEntityAndAccess` passou em MariaDB isolado: 1 teste / 216 assertions; 256 depreciações Core/contrib e duas PHPUnit. Não foi observado vazamento de propostas.
- Não corrigido no Runtime: qualquer `cim` ou mudança de grants ativos é operação persistente sem autorização. Próxima ação: autorização operacional para reconciliar somente esses papéis, prévia de diff de permissões e plano de rollback com export dos papéis ativos; validar envio/fila após apply em URI Wiki. Alternativa segura até lá: manter propostas indisponíveis no Runtime, sem fallback de autorização.

## User — validação de apelido no formulário legado (2026-10-26)

- A auditoria dos pontos de escrita confirmou que o cadastro usa `AccountCreationHooks`/`NicknamePolicy`, `/studio/perfil/apelido` usa `NicknameForm`/`NicknamePolicy`, mas `/dados` mantém o widget `Profile.field_nickname` diretamente via `PortalHooks::formAlter` e não chamava a política compartilhada.
- `ProfileNicknameValidationHooks` agora aplica a gramática de `AccountNames` e a consulta de colisão em formulários Profile de papéis que expõem o campo. Um valor legado inalterado é aceito para permitir editar outros dados. Portal continua sem depender de User e o armazenamento permanece único.
- User Unit 132/504 PASS (1 depreciação PHPUnit); Kernel 25/361 PASS (5 depreciações Core/contrib); teste focal de hook 3/12 incluído nas Units; PHP lint/PHPCS dos arquivos alterados PASS. Não houve alteração Runtime.

## Revalidação do inventário de módulos por Runtime (2026-10-26)

- `php vendor/drush/drush/drush.php status --uri=https://growroom420.aculta.toca.net.br` confirmou host Homelab `toca`, Drupal 11.4.10, banco `aculta_runtime` e root compartilhado `/home/piradopirata/projetos/aculta/web`. A consulta de `moduleHandler()->moduleExists()` nos 40 componentes encontrou 36 habilitados e quatro desabilitados: Activity, Deployer e os adaptadores `aculta_commerce_messenger`/`aculta_wiki_messenger`. O shell `aculta_lms_messenger` está habilitado.
- `aculta_commerce_melhorenvio` está habilitado nesta leitura de 2026-10-26, embora o snapshot MAIN de 2026-10-25 o registrasse desabilitado. Como habilitação é global à instalação, a causa temporal da diferença não foi determinada; não houve instalação, alteração ou escrita para investigá-la.
- Contagem read-only já registrada nesta URI: 5 tópicos, 5 posts e zero entidades `aculta_legado_autor`. O acervo Growroom ainda não foi importado no Runtime; o mapa de preparação fica fora do checkout em storage privado. Nenhuma operação persistente foi executada.
- Limite conhecido: a consulta é preflight, sem constraint transacional no armazenamento existente. Não se afirma unicidade sob concorrência; testar/desenhar uma garantia sem storage paralelo é próxima ação do User.

## LMS — painel de turma em fixture Kernel focal (2026-10-26)

- `ClassDashboardKernelTest` inicialmente não compilava porque habilitava `aculta_lms` sem a dependência Portal que a configuração de rota/serviço requer. Habilitar Portal/Studio integralmente revelou uma closure ampla e config irrelevante (Commerce/Domain), além de schemas adicionais não usados pelo contrato testado.
- O harness agora mantém os módulos reais Group, LMS e `lms_classes`; registra os namespaces dos módulos para instanciar diretamente classes reais `LearningDashboardManager`, access check, controller, prazo e compositor Studio. Não instala o Portal, não cria serviço fictício e não remove assertions do contrato de membership/autorização.
- Kernel focal PASS: 1 teste / 48 assertions; 39 depreciações Core/contrib/Group/LMS. PHPCS/PHP lint PASS. Kernel agregado reexecutado: 29 testes / 98 assertions, 22 erros de setup e nenhuma assertion failure; todos os 22 harnesses que compilam `aculta_lms` sem Portal falham por `aculta_portal.checkout_settings` ausente. O teste focal não homologa a compilação dos serviços nem instalação limpa da cadeia inteira Portal/Messenger/Studio.


## Document Viewer Group — Kernel de acesso (2026-10-26)

- `CourseDocumentAccessTest` passou em MariaDB isolado: 1 teste / 48 assertions, 144 depreciações Core/contrib. O harness resolve a closure real de dependências e cria Media `course_document`/field privado equivalentes ao Config Sync, sem importar configuração ativa. Membro, relação removida, não membro e revogação negam/acessam como esperado no endpoint privado Core.
- A falha anterior por serviço/ledger e schema ausente foi harness: corrigido ao carregar módulos reais e instalar schemas usados pelos hooks. Não houve escrita no Runtime. A dependência histórica do bundle/config de Media no PDF Group ainda exige plano de migração antes de qualquer consolidação.

- Delegação PHPCS: commits do agente 2 foram revisados e integrados (Deployer/Editor). O scan PR-wide agora passa com 0 erros e 0 avisos; teste completo do Deployer e Kernel do Editor passam. O fixture Deployer monta o caminho de autoload sem literal de dependência, mantendo o teste e gate lexical sem exceção.
- Editor permanece bloqueador Config Sync: módulo ativo no Runtime, ausente no `core.extension` versionado e seus oito config/install templates não têm cópia em Sync. O status foi registrado; o acesso `aculta_adm` requer revisão antes de qualquer homologação. Nenhuma configuração ativa alterada.

- Gate set de 2026-10-26 foi reexecutado e passou: status 41 componentes; Config Extension 1.559 referências; mapa módulo-tema 196; fronteira 2.879; Drupal 11+ 1.160; próprios packages 41; aliases 25; slugs 387; endereços 4.238; segredos; cookies; papéis/hierarquia; aceite e links únicos. O PHPCS PR-wide terminou com zero erros e zero avisos. Isso não fecha a M-0: permanecem 19 extensões ativas ausentes do Sync, divergência de grants Wiki e validações/homologações por propósito pendentes.
## Atualização — Editor/CKEditor no Config Sync (2026-10-26)

Consulta read-only do Runtime MAIN confirmou 218 módulos ativos. Comparados à branch da PR, havia 18 extensões ativas fora do `core.extension`: `aculta_editor`, suas 16 dependências CKEditor e `aculta_lms_messenger`. Os oito `ckeditor5_plugin_pack_templates.ckeditor5_template.*` ativos coincidem em conteúdo com os defaults instaláveis do Editor, e `ckeditor5_plugin_pack.settings` coincide com o default do módulo contrib; ambos permaneciam fora do Sync. As 17 extensões do Editor e nove configurações foram adicionadas ao Sync da PR. A comparação agora resulta em 217/218 IDs iguais; somente o shell `aculta_lms_messenger` fica fora do Sync, aguardando uninstall controlado/autorizado. Nenhum arquivo Core/contrib foi editado e nenhuma configuração Runtime foi importada.

O Core instala módulos pelo delta `core.extension` e usa a própria storage de Sync como fonte de defaults durante a importação; por isso módulos e seus nove objetos foram versionados juntos. Gates: Config Extension Dependencies PASS (1.589 checks/1.435 arquivos), status index PASS (41) e own packages PASS (41). Kernel Editor 8/127 continua como evidência focal; instalação limpa/importação e Browser permanecem DEFERRED. A informação histórica abaixo sobre 17 IDs e templates pendentes fica supersedida por esta atualização.



## Atualização — varredura de nomes Config Sync (2026-10-26)

A comparação read-only geral do MAIN encontrou 81 configs ativos ausentes do Sync e uma config Sync ausente da storage ativa (`flag.flag.seguir_publicacao`). Entre os ativos, 28 defaults Messenger são iguais aos instalados, 30 diferem dos defaults e 9 configs que já existem no Sync divergem do Runtime; não foi feito export global. Foi corrigido de forma focal `field.storage.message.field_aculta_msg_delivery`: Sync tinha três valores, enquanto config/install, Runtime e workers comprovam também `failed_permanent` e `cancelled`. A mudança aditiva consta na dev.397; importação Runtime permanece bloqueada. Em dev.398, os displays de amizade ocultaram `field_aculta_friendship_event`; em dev.399, defaults e Sync ocultaram Entity Print EPUB/PDF/DOCX. Em dev.400, o contador de tentativas passou a ter settings Integer corretos e seus dois configs foram versionados no Sync. Testes de contrato 3/80 PASS; nenhum conteúdo ativo foi alterado. A lista geral contém também resíduos de Olivero, CAPTCHA, Entity Print e campos/profile; cada grupo precisa de classificação pelo proprietário antes de edição.
