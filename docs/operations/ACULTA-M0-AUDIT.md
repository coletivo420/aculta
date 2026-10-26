# Registro de auditoria ACULTA M-0

Data: 2026-10-25. Base remota consultada: `805fb7c510baeccac22f12986743245219ce3e49`. Checkout de execução: branch isolada `chore/aculta-m0-integridade`.

| ID | Severidade | Evidência | Solução / resultado | Estado |
|---|---|---|---|---|
| M0-001 | Alta | `scripts/validate-project-status-index.php` aceitava `version_note` substituir a versão do manifesto; quatro divergências preliminares indicadas pela missão | Índice agora compara campo estruturado `version` com o manifesto, `progress_note` separado e versão CLI Deployer preservada como nota. Teste negativo temporário rejeitou versão adulterada | Corrigido; índice PASS com 41 componentes |
| M0-002 | Média | Gate `validate-own-module-packages.php` classificava fixtures Messenger/Wiki em `tests/modules/` como pacotes distribuídos | Gate limita escopo a componentes de produto | Corrigido; 41 checks PASS |
| M0-003 | Alta | Config Group `lms_course` e permissões usam `aculta_document_viewer_group:course_document`, mas Index/LMS Resources/H5P consultavam `aculta_pdf_viewer_group:course_document`; provider de Index declarado no módulo PDF | Relação permanece propriedade de Document Viewer Group. Provider transferido para owner correto. Consumidores atualizados. PDF Group permanece proprietário temporário do bundle Media e configuração; sem migração/desinstalação | Corrigido no código; migração de armazenamento/plugin não foi executada nem é necessária ao contrato verificado |
| M0-004 | Média | `aculta_lms` não declarava Document Viewer Group apesar de consumir sua relação; H5P e Resources também usavam contrato inconsistente | Dependências e IDs de plugin alinhados aos proprietários reais; versões/changelogs ajustados | Corrigido; Kernel Index 1/28, LMS 4/208, Owner Integration 2/88 e H5P focal 3/134 PASS. O Kernel H5P usa fixture mínima de Group e não instala configuração LMS alheia ao contrato; deprecações restantes vêm de Core/contrib |
| M0-005 | Média | Fallbacks e Wiki search form continham classes/tokens da apresentação; Wiki referenciava diretamente tema ACULTA420 | Removidos fallbacks/classes diretas; Wiki passa estado genérico por opção de rota, tema consome a opção; fronteira documentada | Corrigido; gate módulo–tema PASS (2880 checks) |
| M0-006 | Baixa | Exemplos/fixtures Messenger e Wiki usavam domínios de ambiente | Substituídos por `example.com` reservado a exemplos/testes | Corrigido; endereços PASS (4239 arquivos) |
| M0-007 | — | `aculta_forum`/Document Viewer e relação Group consultados antes desta auditoria poderiam divergir em config exportada/runtime | HEAD atual e leitura do Runtime indicaram módulos e plugin alinhados; nenhuma discrepância atual reproduzida | Não reproduzido no estado atual |
| M0-008 | Alta | Runtime habilitava 35 módulos próprios conforme leitura realizada, mas inventário por módulo/ambiente e instalação limpa não foram registrados de forma confiável nesta execução | Nenhuma operação persistente executada. Registrar `drush status` antes de qualquer uso e mapear cada extensão por URI/ambiente | Pendente; exige nova auditoria read-only por ambiente |
| M0-009 | Alta | Cobertura de políticas Domain/Entity Access, config ativa/exportada, rotas, hooks, entidades e testes não foi completada para todos os módulos | Matriz individual registra campos não verificados como pendentes; concluir por ondas antes de RC | Pendente; dividir por M-1–M-5 |

## Gates globais executados

- `validate-project-status-index.php`: PASS (41 componentes).
- `validate-own-module-packages.php`: PASS (41 checks).
- `validate-module-theme-boundary.php`: PASS (2880 verificações).
- `validate-aculta-portal-drupal11.php`: PASS (1160 checks).
- `validate-no-temporary-route-aliases.php`: PASS (25 arquivos de rota).
- `validate-public-slugs.php`: PASS (380 rotas; 17 violações contrib pertencem à baseline documentada).
- `validate-environment-addresses.php`: PASS (4239 arquivos).
- `git diff --check`: PASS.

Runtime foi consultado somente em leitura; não houve `cim`, `updatedb`, instalação/desinstalação, limpeza, mudança de permissões, nem mutação de entidades.

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
