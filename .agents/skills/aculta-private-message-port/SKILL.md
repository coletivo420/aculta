---
name: aculta-private-message-port
description: Use quando alterar ou remover drupal/private_message, portar entidades ou serviços Private Message para aculta_messenger, modificar histórico de leitura ou configurações de conversas.
---

# ACULTA - porte seguro de Private Message

Fonte operacional: `web/modules/custom/aculta_portal/modules/aculta_messenger/docs/PRIVATE-MESSAGE-PORT-CUTOVER.md`. Leia também `aculta-runtime-migracoes`, `aculta-runtime-sync`, `aculta-phpstan` e `aculta-commit-entrega`.

1. Antes de escrever, inspecione `origin/dev`, módulos instalados, code tree e o tarball 4.0.0-alpha5. Valide SHA-256 `488090c43d270ea76f42440cb800575ab5f9f830d9456dd81153236f5c308925`. Preserve alterações de outros agentes.
2. Execute `python3 scripts/prepare_private_message_port.py --tar CAMINHO_TAR --output var/private-message-port --module-root web/modules/custom/aculta_portal/modules/aculta_messenger`. `ready/` é apenas staging: jamais ativar classes de entidades enquanto o contrib ainda registra os mesmos IDs.
3. Reaproveite o código upstream (não reescreva o motor). Mantenha GPL, provedor final único `aculta_messenger`, entity IDs, storage e permissões; adote namespace `Drupal\aculta_messenger` nos consumidores. Mescle serviços, rotas, hooks, schema e recursos sem sobrescrever implementações existentes.
4. Preserve os fluxos atuais de privacidade, bloqueio, flood, retenção, grupos e mensagens privadas. `private_message_new_message` deve ser emitido uma só vez. E-mail sem corpo e somente via `aculta_mailer`; nunca ativar `private_message_notify`.
5. Trate `pm_thread_history` e os owners de entidades/fields como operação **R3 estrutural**. Não executar `pm:uninstall`, `updatedb`, `config:import` nem `composer remove` sem clone, snapshot + restore testado e gates documentados. Exclusão normal pode destruir schema e dados.
6. Exija plano idempotente `preflight/dry-run/apply/verify/abort`, comparação de contagens/UUIDs, providers e config dependencies, smoke multidomínio, PHPUnit/Playwright, PHPCS/PHPStan nível 10 e segunda revisão para R2/R3.
7. Faça commit/push fast-forward em `dev` apenas do escopo próprio; reporte SHA, evidências e NO-GO de modo transparente. Nunca avance Runtime com gate falho.

**Não faça:** reinstalação limpa, perda de conversas, import silencioso de configuração, renomeação ampla de IDs persistidos, nova inbox nativa concorrente ou porta paralela independente.
