# ACULTA Messenger - incorporação direta de Private Message

**Status em 15/11/2026:** P0/P1 de preparação do código, sem ativação ou migração do Runtime. Destino: `aculta_messenger`, sem segundo módulo. Fonte auditada: `private_message-4.0.0-alpha5.tar.gz`, SHA-256 `488090c43d270ea76f42440cb800575ab5f9f830d9456dd81153236f5c308925`. O módulo contrib continua sendo o provider ativo até a janela validada.

## Resultado inicial verificável

- `dev` consultada a partir do HEAD `6407701e35678c347e90dae4999f9014b6c2a93c` (o plano original examinava `dcff10c...`).
- `aculta_messenger.info.yml` ainda depende de `private_message:private_message`.
- `AcultaMessengerServiceProvider` substitui a implementação de `private_message.mapper` por `MessengerPrivateMessageMapper`.
- A implementação atual registra políticas de bloqueio, antiflood, privacidade e retenção, drawer multidomínio e aviso de e-mail sem corpo. Não substituir esses contratos.
- `aculta_messenger.routing.yml` já contém `/painel-administrativo/aculta/mensagens` e rotas de mensagens sob `/mensagens`.
- O upstream tem hooks/entidades/serviços/JS/Twig/testes que requerem descoberta nativa, e uma referência inválida a `private_message/browser_notification` na biblioteca `message_form`.
- O upstream `private_message_uninstall()` apaga configuração, e a entidade/serviço `PrivateMessageUninstaller` tem operações de deleção. **Não portar desinstaladores como funções habilitadas.**
- O arquivo `private_message.install` declara `pm_thread_history` com PK `uid, thread_id` e colunas `access_timestamp, delete_timestamp`. O histórico deve sobreviver integralmente ao handover.

## Ferramenta reproduzível para P1

```bash
python3 scripts/prepare_private_message_port.py \
  --tar /caminho/private_message-4.0.0-alpha5.tar.gz \
  --output var/private-message-port \
  --module-root web/modules/custom/aculta_portal/modules/aculta_messenger
```

A ferramenta não modifica a árvore ativa. Valida SHA-256 e a segurança dos caminhos do arquivo, adapta apenas namespaces de classes, preserva IDs persistidos e emite `PORT-MANIFEST.json` com hashes por arquivo, colisões e número de substituições. Separa `ready/` (somente staging), `manual/` (mesclagens e itens perigosos) e `reference/` (licença/proveniência). Exclui funcionalmente o submódulo `private_message_notify`.

**Execução sobre o anexo fornecido, fora do Runtime:** 191 arquivos; 111 preparados, 34 para mesclagem, 28 referências e 18 excluídos do submódulo opcional; 230 substituições de namespace. Lint com PHP 8.4 nos arquivos PHP preparados: sem erros sintáticos. Isto NÃO demonstra bootstrap, APIs, schema, autorização ou compatibilidade de testes.

## Ordem de integração obrigatória

1. **P0 - Snapshot e inventário:** registrar HEAD, lockfile, versões PHP/Drupal, extensão instalada, contagens e UUIDs por entidade, tabelas e índices, locks, cron/filas, permissões, providers e configurações dependentes. Fazer backup verificável em armazenamento privado e **ensaiar restauração** para `aculta_runtime_copia`. Não registrar PII.
2. **P1 - Porte do código em checkout de ensaio:** integrar classes descobertas sob `Drupal\aculta_messenger`, templates e assets aprovados; mesclar `*.services.yml`, rotas, plugins, UI Suite, tradução, permissions, menus, bibliotecas, config e schemas. Preservar os nomes históricos de services, permissões e entity IDs necessários, mas adaptar o class namespace. Ajustar imports do código ACULTA. Não carregar as duas implementações do mesmo entity type. Substituir `MessengerPrivateMessageMapper` somente preservando seu filtro de bloqueios.
3. **P2 - Hooks e mensageria:** implementar hooks procedurais sob `aculta_messenger_*` ou `#[Hook]`; preservar o hook **emitido** `private_message_new_message` exatamente uma vez. Não duplicar fluxo de notificação, corpo de e-mail ou inbox. Reconciliar `private_message/browser_notification` sem dependência fictícia.
4. **P3 - Migração em clone isolado:** planejar `preflight`, `dry-run`, `apply`, `verify` e `abort` idempotentes. Suspender tráfego/cron/escritas, capturar backup, migrar `pm_thread_history` para tabela com owner novo **ou provar alternativa segura equivalente** e atualizar todas as consultas. Transferir metadados instalados e providers das entidades `private_message`, `private_message_thread`, `private_message_ban` e respectivos field storages usando APIs do Drupal da versão instalada. Adaptar dependencies de config e `core.extension` apenas com a propriedade transferida. Examinar código real do `ModuleInstaller` e do repositório de schema; não fazer `applyUpdates()` indiscriminado.
5. **P4 - Corte apenas após gates:** provar contagens/UUIDs/índices/campos/histórico e referências iguais antes/depois; `pm_thread_history` intacto e sem risco de hook_schema legado; provider novo para todos os entity types e field storages; config dependency sem `private_message`; rollback testado. Somente então retirar `private_message` do `core.extension`, `aculta_messenger.info.yml` e `drupal/private_message` do Composer com lockfile consistente. **Proibido `drush pm:uninstall private_message` isoladamente**.
6. **P5 - Segurança e UI:** conferir permissões legadas sem ampliação, acesso a thread e grupo, bloqueio bilateral, privacidade da primeira mensagem, flood, CSRF, XSS, IDOR, caches por usuário, retenção e workers, sem conteúdo de mensagem em e-mail/log. Portal `account` para membros, `main` para administração. Não reintroduzir páginas nativas concorrentes nem dependência de jQuery.
7. **P6 - Testes e deploy:** PHP lint, phpcs, PHPStan nível 10 com baseline congelado do ACULTA, PHPUnit Unit/Kernel/Functional/FunctionalJavascript, Playwright, Semgrep, teste E2E nos dez site_keys, e evidências dos pré/pós gates. Conferir notificações de Fórum, Wiki, LMS, Loja, Eventos, Revista, Doações e Game. Atualizar README/ARCHITECTURE/ROADMAP, skills e changelog. Entregar commit/push em `dev` apenas com revisão R2/R3 adequada.

## Gates de bloqueio

**NO-GO** se não houver clone restaurado e comparações reproduzíveis, se as entidades aparecerem duplicadas, se `pm_thread_history` puder ser removida, se faltar owner/config dependency, se houver divergência de contagens ou UUIDs, se os testes de autorização falharem, ou se existirem remessas com corpo de mensagens. Nunca converter uma falha do Runtime em um sucesso documental.

## Atribuição/licença

Preservar a licença upstream GPL-2.0-or-later, autorias, o `LICENSE.txt` original e hashes individuais do manifesto como arquivo de proveniência no código final. O tarball é insumo para o código, não autorização para ativar seu submódulo de notificação.

## Estado de execução

**Concluído:** conferência do pacote, classificação e preparação automática do código, inventário direcionado do módulo em `dev`, script de staging e runbook. **Concluído após o corte (branch `pm-corte-direto`):** código portado para `src/` ativo; entidades `private_message`, `private_message_thread` e `private_message_ban` sob `aculta_messenger`; rota `entity.private_message_thread.canonical` (`/mensagens/conversa/{private_message_thread}`) apontando para `MessagesController::drawerConversation`; permissão `use private messaging system` definida em `aculta_messenger.permissions.yml` e concedida somente a `email_confirmed` (não a `authenticated`); `drupal/private_message` retirado de `composer.json` e `composer.lock`; ajustes de tipagem e docblocks para PHPStan, com baseline reduzida de 786 para 4 erros na última medição.

**Pendente:** resolver os 3 erros de injeção do H5P (`aculta_lms_h5p`, controllers `aculta_H5PAJAX`, `aculta_H5PEditorAjaxController`, `aculta_H5PFileController`), cuja causa não foi localizada; decidir o tratamento de `new static` em `PrivateMessageConfigFormBase::create`; manter o PHPCS sem erros (0 erros; restam 110 avisos, sobretudo de comprimento de linha); rodar Functional; rebase sobre `origin/dev` e fast-forward só após Unit, PHPCS, PHPStan, Kernel e Functional verdes. Acesso GitHub não equivale a acesso ao banco do servidor.


## Preflight automático somente leitura

Em um clone devidamente bootstrapped, sem escrita, registre o inventário pré/pós:

```bash
vendor/bin/drush scr scripts/private_message_preflight.php --uri=https://dominio-do-clone.exemplo > var/private-message-preflight-before.json
```

Este script consulta os providers atuais/instalados de três tipos de entidade, field storage providers, contagens de tabelas, UUIDs distintos quando presentes, nomes de configurações dependentes do contrib e a presença do token de corpo no modelo legado. **Não inclui corpos ou dados pessoais no relatório e não executa migração.** Rodar somente após confirmar que Drush aponta para o banco clonado correto, e comparar com relatório pós-corte. Dados operacionais e backups ficam fora do Git e do docroot.

## Execução do corte (15/11/2026)

Janela única autorizada. Aplicada em `aculta_runtime` e ensaiada antes em `aculta_runtime_copia` (clone `clone-pm`, `--uri=http://clone-pm`).

**Feito e verificado:**

- Backups restauráveis antes de qualquer escrita: `aculta_runtime_pre_corte.sql.gz` (628 tabelas) e `aculta_runtime_copia_pre_clone.sql.gz` (628 tabelas); restauração da cópia validada por contagens iguais às do Runtime.
- Inventário e dry-run somente leitura. Antes: `private_message` 15, `private_message_thread` 5, `private_message_ban` 0, `pm_thread_history` 13, `private_message_thread__members` 13, `message` 76 (sendo 1 aviso com `field_aculta_messenger_source_pm`, alvo 516, e 58 com `source_fr`).
- Purge por Entity API (filtro: template `aculta_msg_private_notification` com alvo em `private_message`; `pm_thread_history` truncada; preferências `users_data` do módulo `private_message`). Depois: PM 0, threads 0, bans 0, histórico 0, `message` 75, `source_fr` 58 (preservados).
- Desinstalação do contrib pelo caminho suportado do Core (`pm:uninstall private_message`), sem cascata (única dependência direta era `aculta_messenger`, removida).
- Motor sob provider `aculta_messenger`: `private_message`, `private_message_thread`, `private_message_ban` com provider `aculta_messenger`; tabela `aculta_pm_thread_history` criada vazia; configs de display/settings criadas; `hook_update_10019` instala tudo (idempotente; recusa se `private_message` estiver habilitado).
- `composer.json` e `composer.lock` sem `drupal/private_message` (única mudança no lock); `composer validate` válido.
- Testes unitários: `aculta_messenger` 403 testes OK (1 deprecação herdada do upstream em `PrivateMessageMapperInterface`); `aculta_user` Social 4 testes OK.

**Verificações posteriores ao corte:**

- Kernel no MariaDB `aculta_simpletest` (fora do Runtime e sem SQLite, conforme `BANCO-DE-TESTE.md`): última rodada completa com 63 testes, 1646 asserções, 0 erros e 0 falhas (somente deprecações). A rota passa a ser testada com o membro autenticado, porque o módulo `flag` decora `access_check.csrf` e permite anônimo sem token; por isso o teste de acesso à rota roda como membro.
- Unit `aculta_messenger`: 403 testes, 4386 asserções, OK.
- Rodada completa de Kernel após os últimos commits de PHPStan: em andamento no momento desta atualização; o resultado deve ser registrado aqui somente depois de concluída.

**Pendente (não executado):**

- Gates ainda não concluídos: Functional, Playwright e Semgrep. PHPCS do módulo sem erros. PHPStan completo mede 5 erros na última medição (4 de injeção do H5P fora do módulo e 1 de `new static`).
- Testes de navegador e smoke HTTP nos dez site keys (o Runtime não foi acessado via HTTP nesta janela).
- Hooks de formulário de usuário e de perfil do PM não foram portados (UI paralela; plano P1.4).
- Hooks `aculta_messenger_install` e `aculta_messenger_private_message_thread_view` tiveram a assinatura alterada por decisão do responsável (remoção de `$is_syncing` e `$view_mode`); conferir chamadores antes de fechar.
- README/ARCHITECTURE/CHANGELOG do módulo: ainda sem o estado pós-corte.
- Remoção de `captcha.captcha_point.private_message_add_form` (mantida; config do captcha para o formulário portado).
- Credenciais de cópia de banco ficam em `~/.config/aculta-clone-pm/db.php` (modo 0600, fora do repositório); `web/sites/clone-pm/` e `web/sites/sites.php` são locais e estão excluídos via `.git/info/exclude`. Nada disso deve ser versionado.
