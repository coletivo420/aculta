# ACULTA - Porte Private Message com corte direto dos dados legados

Data: 2026-11-15
Base documental: plano anterior para Private Message 4.0.0-alpha5, snapshot Git informado `dcff10c9613fbf7241528be150141a63bd5d125b`.
Alvo: `web/modules/custom/aculta_portal/modules/aculta_messenger` na branch `dev`.
Status: plano revisado e pacote de fontes preparadas; **nenhum deploy, alteração de banco ou commit/push foi executado nesta revisão**.

## Decisão irrevogável de escopo

**Corte limpo dos dados legados DO MOTOR PRIVATE MESSAGE.** Não migrar conversas, mensagens, histórico de leitura, participantes ou bloqueios do contrib. Não manter entidades antigas para leitura, não criar compatibilidade de bancos legados, não fazer shadow tables nem handover do provider dos registros antigos. O objetivo é iniciar o novo motor de conversas do zero, integrado no único módulo `aculta_messenger`.

**Fora do corte:** avisos/notificações transversais do ACULTA que não pertençam ao Private Message, filas e eventos de outros produtores, dados de usuários e perfis, conteúdos e mensagens de Fórum, Wiki, LMS, Revista, Loja, Eventos, Doações, preferências compartilhadas e configurações gerais do Portal. Preservar configurações funcionais atuais do `aculta_messenger` sempre que não sejam dependências do contrib; reconstruir apenas o que estiver realmente ligado ao motor descartado.

## Auditoria do pacote recebido

- Arquivo anterior: `aculta_private_message_port_2026-11-15.zip` (181 entradas, com manifesto e fonte tar incorporada).
- `staging/ready/` tem classes e assets preparados para porte; NÃO está pronto para instalação.
- `staging/manual/` contém hooks, rotas, config, install/uninstall e arquivos perigosos que precisam ser mesclados manualmente.
- `staging/reference/` traz material de referência, não executável.
- `scripts/private_message_preflight.php` é **somente leitura** e continua útil como inventário pré-corte. Sua nomenclatura e campos de handover não são um roteiro de migração; não utilizá-lo para aplicar alterações.
- `scripts/prepare_private_message_port.py` prepara um staging, não modifica o Runtime.
- `staging/manual/src/Service/PrivateMessageUninstaller.php`, `AdminUninstallForm.php`, Drush commands do contrib e `private_message.install` **não podem ser copiados diretamente**: possuem operações destrutivas e APIs ligadas ao antigo provider.
- Lint registrado: 87 arquivos PHP, zero erros de sintaxe, PHP CLI 8.4.24; não comprova compatibilidade Drupal ou qualidade funcional.
- Fonte upstream SHA-256: `488090c43d270ea76f42440cb800575ab5f9f830d9456dd81153236f5c308925`.

## Modelo de dados após o corte

Usar IDs de entity types `private_message`, `private_message_thread`, `private_message_ban` **se necessário ao comportamento do motor**, mas com provider `aculta_messenger` desde a instalação limpa. IDs técnicos reaproveitados NÃO implicam preservar dados. Preferir as tabelas-base historicamente declaradas quando a definição incorporada as exigir, porém **criadas novamente e vazias**. `pm_thread_history` deve ser substituída, se funcionalmente necessária, por schema gerenciado por `aculta_messenger`, preferencialmente `aculta_pm_thread_history`, sem cópia dos registros antigos. Revisar referências em código, SQL, plugins e testes.

O novo instalador deve suportar instalação limpa e versão atualizada do ACULTA. Não tentar inscrever o novo provider sobre esquemas persistidos do antigo contrib. Remover definições instaladas antigas **no clone** por sequência suportada pelo Core, eliminando referências pendentes, antes do registro do novo provider. Se houver falha de consistência em qualquer etapa, abortar e restaurar o snapshot; nunca forçar alteração do repositório interno de definições instaladas via SQL manual sem justificativa/teste específico.

## Algoritmo operacional seguro

### P0 - Inspeção e baseline

1. Atualizar `dev` sem sobrescrever mudanças de outros agentes. Registrar HEAD, status, Composer, Drupal/Core, config sync, módulos ativos e patchset.
2. Escanear referências a `private_message`, serviços, hooks, schema, permissões, exports, integração `aculta_messenger`, avisos, correlações de notificação e user data.
3. Criar relatório de classificação por registro/tabela/config: `DESCARTAR_PM`, `MANTER_ACULTA`, `MIGRAR_CONFIG_FUNCIONAL` e `INVESTIGAR`. Não proceder com `INVESTIGAR` sem resolução.
4. Inspecionar implementações reais de `hook_uninstall`, instaladores e `PrivateMessageUninstaller`; mapear efeito efetivo de `drush pm:uninstall` e desinstalação do módulo contrib.
5. Criar snapshot versionado do banco, arquivos privados e config, **validar restauração em clone isolado**; impedir gravações novas durante o corte.

### P1 - Motor dentro de aculta_messenger

1. Portar classes de `staging/ready` com revisão arquivo a arquivo, mesclando conflitos de classes já existentes, serviços, eventos e interface ACULTA. Não fazer cópia cega.
2. Implementar descoberta correta de entidades, plugins, forms, controllers, caches, campos, templates, bibliotecas e services; renomear hooks de provider para `aculta_messenger_*`, preservando os eventos emitidos apenas quando seus consumidores dependem deles.
3. Manter um único serviço funcional de conversas/mensagens. Se IDs antigos forem mantidos como aliases, fazê-lo somente por consumidores atuais e retirar dependências de `private_message` como extensão.
4. Não portar o submódulo `private_message_notify`, a UI nativa paralela ou o envio de corpo de mensagens por email. Reusar drawer/UI Suite/SDC, `aculta_mailer`, políticas de bloqueio e privacidade, limites, CSRF e idempotência de avisos.
5. Revisar biblioteca `private_message/browser_notification` referenciada mas ausente do manifesto; resolver dependência de modo explícito.

### P2 - Procedimento de descarte com escopo

1. Criar ferramenta operacional ACULTA com modos `inventory`, `dry-run`, `purge-legacy`, `verify-empty` e `bootstrap-new`, executável **somente em clone/Runtime explicitamente autorizado**, com proteção contra ambiente produtivo e confirmação inequívoca.
2. Inventariar entidades `private_message`, `private_message_thread`, `private_message_ban`, tabelas de campos, `pm_thread_history`, index, filas específicas, config exclusiva, eventos/avisos vinculados por `field_aculta_messenger_source_pm` e respectivos dependentes.
3. Excluir por APIs Drupal na ordem de dependência enquanto o contrib ainda fornece definições instaladas, com processamento em lotes, contagem e logs. Para limpeza física de schema, usar Core Entity API/schema, evitando SQL massivo indiscriminado. Qualquer `DROP TABLE` deve ficar restrito a tabelas verificadamente exclusivas do contrib e ocorrer depois dos gates.
4. Excluir apenas avisos `message` comprovadamente gerados pelo PM antigo, sem tocar em outros avisos. Preservar usuários, contas, perfis e preferências não exclusivas. Remover somente config exclusiva do PM e reconstituir configurações do novo motor.
5. Desinstalar o contrib em clone, validando `hook_uninstall`/schema e se nenhum consumidor essencial permanece. Para impedir desinstalação destrutiva fora do escopo, realizar purga seletiva primeiro e confirmar que não restam dependências e dados. **Não usar automaticamente `drush pm:uninstall`**, especialmente se houver hooks com efeitos sobre dados compartilhados.
6. Remover o módulo do `core.extension`, `.info.yml`, Composer e lockfile, respeitando a sequência funcional do Drupal; nunca editar `core.extension` como substituto da desinstalação suportada.
7. Instalar definições limpas sob provider `aculta_messenger` e verificar que todas as tabelas do motor novo iniciam sem dados legados, preservando estruturas e configurações ACULTA não relacionadas.
8. Implementar proteção de idempotência: reexecução não apaga mensagens **novas** criadas depois do corte. Marcar fase/versão do corte e impedir segunda purga por padrão.

### P3 - Administração e integração

- Criar ou revisar administração MAIN em `/painel-administrativo/aculta/mensagens`: configurações, conversas e grupos, privacidade/bloqueios, retenção, entregas, diagnóstico e auditoria.
- Manter front das conversas em ACCOUNT e barra multidomínio. Reusar o `aculta420_v2`, SDC/UI Suite e modos claro/escuro.
- Aplicar retenção apenas em conversas do novo motor; regras de excluídos, leitura, bloqueio bilateral, permissões e cookies/caches permanecem auditadas.

### P4 - Gates e testes

- Unit, Kernel/MariaDB, Functional, JavaScript/Playwright, PHP lint, PHPCS, PHPStan nível 10 com baseline existente, Semgrep, Composer validate e Drush bootstrap/cache rebuild.
- Testes críticos: zero referências ativas ao contrib; nenhuma mensagem legada; novo envio/recebimento, histórico, threads individuais/grupo, bloqueio, contadores, avisos sem corpo no e-mail, retenção, anti-flood, idempotência e concorrência.
- Testes de não-regressão transversais: `message`, `message_notify`, `message_subscribe`, `aculta_mailer`, usuários, Fórum, Wiki, LMS, Loja, Revista, Eventos e barra multidomínio.
- Verificação de providers, schema instalado, tabelas/índices e config dependencies após reinstall limpo.
- Exercitar dois cenários: banco com PM legado populado e banco sem PM legado; ambos devem convergir para mesmo resultado.

### P5 - Entrega

- Documentar `docs/...` e skills/guardrails reais do repositório; incluir registro explícito de que histórico antigo foi intencionalmente descartado.
- Commit seletivo e push direto para `dev` **somente** com gates aprovados e conforme regras Git atuais do ACULTA. Nunca incluir arquivos alheios de outros agentes.
- Deploy no Runtime real apenas após ensaio de restauração, autorização do ambiente e gates. Havendo bloqueio, entregar código/plano e comando de conclusão, registrando pendências; não alegar migração executada.

## Critérios de aceite

1. Módulo contrib `private_message` desinstalado; Composer e config sync livres de dependência ativa.
2. `aculta_messenger` é o único provider do novo motor; sem módulo custom extra.
3. Zero mensagens, threads, leituras, participantes e bloqueios legados reaproveitados; tabelas novas geridas pelo novo módulo.
4. Dados não relacionados ao PM preservados; avisos de outros produtores intactos.
5. Envio, recebimento, notificações, UX multidomínio, anti-flood e segurança testados.
6. Cutover ensaiado em clone com backup restaurável, relatório antes/depois e sem apagamento recorrente de mensagens novas.
7. Testes completos registrados, incluindo falhas conhecidas segregadas da regressão.

## Riscos importantes

- **Perda intencional e irreversível sem backup** do histórico PM; divulgar ao time antes do deploy.
- Hooks de uninstall podem apagar tabelas próprias e afetar dados de outros módulos: inspecionar, ensaiar, não assumir segurança.
- Shared data: `message` e seus field tables podem conter avisos de várias origens. Nunca truncar essas tabelas.
- Provedor de entity type e caches persistidos podem bloquear reinstalação; usar APIs suportadas e validar no Core efetivo.
- A remoção do contrib pode deixar imports de namespace, plugins e aliases órfãos; inspeção estática e testes exigidos.