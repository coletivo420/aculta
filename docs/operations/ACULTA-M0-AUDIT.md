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
| M0-008 | Alta | O inventário Runtime anterior não listava cada extensão de forma verificável | Consulta read-only em URI MAIN confirmou 35/40 módulos habilitados e cinco desabilitados; tema default `aculta420`; `aculta_messenger` tem 5 hook updates e 3 post-updates pendentes. Nenhuma operação persistente foi executada | Parcial; matriz registra estado global da instalação. Revisão de configuração Domain e Config Sync por purpose ainda pendente |
| M0-009 | Alta | Cobertura de políticas Domain/Entity Access, config ativa/exportada, rotas, hooks, entidades e testes não foi completada para todos os módulos | Matriz individual registra verificações focais e limites; continuar por ondas antes de RC | Parcial; auditorias por componente ainda pendentes |
| M0-010 | Alta | `core.extension.yml` não listava módulos exigidos por dependências de componentes/configuração ativa: Document Viewer, Document Viewer Group, Entity Print e LMS Resources | Adicionadas as quatro extensões ao Config Sync. Runtime MAIN read-only confirmou os quatro habilitados; 1.425 YAMLs de configuração agora têm providers presentes no Config Sync | Correção versionada; não importada no Runtime. Ainda há 20 módulos ativos fora do export e diferença global pendente de decisão/ownership |

## Gates globais executados

- `validate-project-status-index.php`: PASS (41 componentes).
- `validate-own-module-packages.php`: PASS (41 checks).
- `validate-config-extension-dependencies.php`: PASS (1.558 referências de dependência em 1.425 arquivos YAML; manifests próprios habilitados e `dependencies.module` versionadas resolvem no `core.extension.yml`). O delta de 20 módulos ativos sem export permanece fora desse gate e bloqueia importação até decisão por componente.
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
- `validate-admin-domain-policy.php` foi tentado sem bootstrap Drupal e terminou com `Class "Drupal" not found`; não contar como PASS. Outros gates dependentes de bootstrap e testes Browser/visual não foram executados nesta fase.

## Evidência Portal adicionada em 2026-10-25

- `AccountOtherDataStudioReturnTest`: harness Unit agora inicializa `path.validator` para o caminho de validação usado por `Url::fromUserInput()` e restaura o container Drupal pré-existente no `tearDown()`.
- Suíte Unit completa do Portal: 350 testes / 878 assertions PASS, seis depreciações PHPUnit.
- Suíte Kernel completa do Portal em MariaDB de testes isolado: 53 testes / 831 assertions PASS, 90 depreciações Core/contrib. Nenhuma gravação no Runtime.
- Isso melhora a evidência automatizada do Portal; Functionals, Browser/X11 e comparação de configuração ativa por purpose continuam pendentes.

## Reconciliação Config Sync — 2026-10-26

- `core.extension.yml` não listava `aculta_document_viewer`, `aculta_document_viewer_group`, `entity_print` e `aculta_lms_resources`, embora manifests/config exportados os exijam e todos estejam ativos no Runtime MAIN. As quatro entradas foram adicionadas, sem executar importação.
- Varredura de 1.425 configurações versionadas: toda dependência `dependencies.module` tem provider no `core.extension`. Varredura dos manifestos dos módulos ACULTA habilitados também passou.
- Comparação read-only de `core.extension` ativa (218 extensões) versus Sync (198): permanecem 20 módulos ativos não listados no export — `aculta_editor`, `aculta_index`, `aculta_lms_messenger`, `google_tag` e 16 módulos `ckeditor5_plugin_pack*`. O baseline de drift já inclui `core.extension`, mas não explica a intenção por extensão. Nenhum deles foi removido ou adicionado nesta correção; a importação pode alterar habilitação, então a decisão continua pendente e nenhum `cim` foi executado.
