# Changelog

Mudanças relevantes do ACULTA Messenger.

## 0.1.0-dev.3 — integração inicial de mensagens privadas

- Acrescenta callback OOP `#[Hook('form_alter')]` ao formulário nativo de
  criação/resposta do Private Message. O Form API contrib continua controlando
  destinatários, validação, salvamento e renderização.
- Acrescenta rate limit pelo serviço Flood do Core: dez envios válidos por UID
  em uma hora; validações que já falharam não registram envio.
- Mantém bloqueio, threads, entidades, caixa de entrada, contagem de não lidas e
  handlers de acesso no Private Message. Nenhuma permissão é concedida a role.
- RUNTIME STATUS: DEFERRED — módulo não habilitado; fluxo, fallback sem JS,
  isolamento entre usuários e callback Flood aguardam validação no Homelab.

## 0.1.0-dev.2 — módulo integrador e dependências

- Adiciona `aculta_messenger.info.yml` como submódulo opcional do Portal, com
  dependências explícitas nos cinco módulos integrados.
- Adiciona ao Composer as versões estáveis resolvidas: Private Message 3.0.7,
  Message 1.9.0, Message Notify 1.5.0, Message Subscribe 2.1.0 e Site Alert
  1.4.0. O dry-run indicou cinco instalações, nenhuma atualização/remoção.
- Código-fonte foi instalado em `web/modules/contrib` somente pelo Composer e
  não será versionado. Não ativou módulos, executou import/export de config ou
  alterou banco/runtime.
- Confirma o inventário upstream nos arquivos efetivamente instalados; ver
  `docs/INTEGRATIONS.md` e `docs/ARCHITECTURE.md`.
- E-mail reutiliza Drupal Mail com Symfony Mailer/Mailer Plus existente, pela
  ferramenta de entrega já instalada. Não instala SMTP nem cria transporte.
- Verificações: `composer validate` válido (com warnings preexistentes de
  constraints exatas), `composer audit` sem advisories, Drupal extension
  discovery PASS,
  gate do Portal PASS (692 checks), metadata YAML PASS e links Markdown PASS.
  Instalação Drupal em banco/runtime e PHP 8.5 permanecem pendentes.
- `git diff --check`: PASS.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — fundação arquitetural

- Documenta fontes de verdade, contratos de Domain, públicos, privacidade,
  dependências candidatas e plano incremental.
- Registra que as cinco dependências ainda não estão no lock nem instaladas.
- Nenhuma mudança funcional ou instalação Composer.
- STATIC: PASS — `git diff --check` e links Markdown internos (nove arquivos).
- RUNTIME STATUS: DEFERRED (sem código funcional).
