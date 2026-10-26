# Execução contínua ACULTA — PR #298

**Branch:** `chore/aculta-m0-integridade`  
**HEAD retomado:** `d55c4301e7ffde6022fd6c7b3651df1de927e619`  
**HEAD estável:** `72fff2a3f` (Config Sync dependency repair and audit notes are uncommitted)
**Estado:** PARTIAL — H5P, Portal Unit/Kernel e auditorias focais CPF/User/CEP concluídos; auditoria M-0 e frentes M-1 a M-5 em andamento.
**Runtime:** `toca`, Drupal 11.4.10, PHP 8.4.27, URI padrão `http://default`, banco `aculta_runtime`. Consultas somente leitura; nenhum write foi executado.

## Ponto de retomada

1. Commits pendentes: corrigir o harness `AccountOtherDataStudioReturnTest`, documentar Portal Unit 350/878 e Kernel 53/831 e publicar o resultado.
2. Prosseguir M-1 por Mailer, Confirmador e Messenger; auditar consentimento, identidade pública e integração de checkout em ambiente isolado.
3. Executar os gates administrativos e de configuração com bootstrap e URIs explícitas contra o checkout candidato quando viável.
4. Completar inventário Runtime read-only, Config Sync e auditoria individual dos demais componentes, preservando os oito módulos do agente 2.

## Evidência desta retomada

- `6daa7542f test(h5p): repair course access kernel contract`: formatter de acesso e test harness usam o plugin Group real. PHPCS Drupal/DrupalPractice, PHP lint e diff check passam nos dois arquivos H5P alterados.
- Kernel isolado H5P: 3 testes/134 assertions PASS; 41 deprecações Core/contrib. Foram validados Group membership, educador, estudante, usuário sem vínculo, referência de recurso e revogação; nenhum serviço de produto fictício foi adicionado.
- PHPCS Drupal/DrupalPractice passou nos três arquivos H5P alterados após corrigir o widget e documentar duas exceções pontuais para os nomes de classe `aculta_H5P*`, preservados por contrato de plugin/cache.
- Gate Domain administrativo: `php vendor/drush/drush/drush.php --uri=http://aculta.toca.net.br php:script scripts/validate-admin-domain-policy.php` PASS (3498 checks). O bootstrap usou o checkout compartilhado `main`; isso valida o Runtime/código atual, não o diff da PR.
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
| P0 PHPCS | Parcial | Arquivos H5P alterados passam; widget legado conserva violações preexistentes e precisa de correção focal após analisar contratos |
| P0 H5P | PASS focal | Kernel isolado 3/134; suíte integral do módulo não repetida nesta etapa |
| P0 admin Domain | PASS no checkout compartilhado | 3498 checks; repetir/validar contra a branch quando runner bootstrapped estiver disponível |
| M-0 inventário individual | Parcial | Inventário 41 itens preservado; faltam Runtime/Config Sync completo e evidência por componente |
| M-1 a M-5 | Em execução | Avançar em Mailer/Confirmador/Messenger; CPF update aguarda validação isolada e autorização para Runtime; auditorias User/Portal/CEP e homologações visuais continuam parciais |

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
- Delta restante ativo versus Sync: 218 módulos ativos, 198 versionados; 20 ausentes são `aculta_editor`, `aculta_index`, `aculta_lms_messenger`, `google_tag` e 16 módulos CKEditor Plugin Pack. A intenção de habilitação desses componentes não está documentada por propósito; uma importação pode desabilitá-los. Classificado como bloqueador de reconciliação. Sem `cim` ou write no Runtime.
- A comparação ativa também mostra `aculta_editor` habilitado no Runtime enquanto ausente do export; tarefa encaminhada ao agente 2 por ser módulo sob seu ownership. `validate-module-theme-map.php` passou com 196 checks após completar sete linhas documentais ausentes, incluindo os seis custom modules e o provider contrib Entity Print.

## Restrições / deferred

- Nenhuma escrita no Runtime; sem migração, importação de config ou processamento de filas.
- Operações financeiras externas, deploy, merge e remoção de adaptadores permanecem fora da autorização.
- Mudanças que alterem ownership de dados, membership persistida ou política de produto serão isoladas e submetidas ao responsável quando houver decisão necessária.

## Atividade do agente 2

- Ownership concedido exclusivamente: Melhor Envio, Deployer, Patcher, Sitemap, QR Code, Links, Activity e Editor. Commits incorporados: `6483dddbe`, `81b03581a`, `85db52888`.
- Agente não operou no Runtime. Os diretórios devem permanecer sob esse ownership em novas correções até coordenação explícita.
