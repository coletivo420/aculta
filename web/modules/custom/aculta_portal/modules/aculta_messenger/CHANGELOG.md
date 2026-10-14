# Changelog

Mudanças relevantes do ACULTA Messenger.

## 0.1.0-dev.5 — assinaturas de tópicos do fórum

- Reutiliza a flag existente `seguir_topico` em Nodes `forum`; não cria outra
  flag e não ativa `message_subscribe_ui`.
- Enfileira a primeira publicação; o worker revalida publicação atual e gera
  link pelo purpose `forum` via `DomainPurposeManager`. Sem alias português, não
  envia link com slug técnico.
- Seleção de destinatários usa a API pública de Flag; Message Subscribe aplica
  status de conta e acesso atual do Node.
- Evento Message registra Node, revisão, estado e tentativas. Lock e chave de
  deduplicação protegem reprocessamento; erros deixam evento pendente para retry.
- A entrega passa pelo notifier configurado e ferramenta de e-mail já instalada
  via Drupal Mail; nenhum transporte SMTP novo é criado.
- Group permanece fora: `gnode` não está habilitado no projeto.
- RUNTIME STATUS: DEFERRED — teste de assinatura, access e transporte controlado
  aguardam Homelab; sem mudança de banco ou Config Sync.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.518 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist permanecem intactos.

## 0.1.0-dev.6 — integração administrativa de Site Alert

- Adiciona a instância do bloco nativo Site Alert à região Destaques do ACULTA420,
  disponível nos purposes que usam esse tema. Agenda e severidade vêm da
  entidade Site Alert; o intervalo de atualização é 300 segundos.
- Criação, edição e remoção continuam nos formulários/permissões nativos do
  Site Alert; as rotas administrativas permanecem sob o purpose `main`.
- Nenhuma role recebe permissão de Site Alert automaticamente; não há grants
  atuais às permissões nativas na configuração exportada.
- Comunicados persistentes Node/Views não foram criados: os tipos atuais não
  fornecem segmentação de acesso verificável nem fluxo de revisão atribuído para
  esse propósito. Evita-se publicar um aviso como conteúdo público sem contrato.
- RUNTIME STATUS: DEFERRED — bloco, agenda e apresentação ainda exigem teste no
  Homelab. Nenhum módulo foi habilitado; sem banco ou Config Sync alterado.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.519 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist não foram alterados.

## 0.1.0-dev.4 — avisos privados por Message Notify

- Enfileira eventos do hook nativo de nova mensagem e resolve a thread após o
  salvamento, usando Queue API e lock do Core.
- Grava notificações como entidades Message com referência ao Private Message de
  origem, destinatário e estado; retries consultam a entidade para deduplicação.
- Usa `message_notify.sender` e Drupal Mail já instalado, com assunto genérico,
  corpo sem conteúdo da conversa e link para a raiz do purpose `account`.
- Revalida usuário ativo, permissões, membership/access e bloqueios nas duas
  direções. Limita tentativas de entrega a três.
- Lifecycle guard bloqueia Private Message Notify em conjunto (template
  upstream inclui o corpo privado) e protege uninstall com notificações/fila
  pendentes.
- RUNTIME STATUS: DEFERRED — sem módulo habilitado, envio real ou validação no
  Homelab. Sem mudanças de banco ou Config Sync.
- Verificações estáticas: PHP lint, class loading/interface, YAML de instalação,
  gate Drupal 11+ (696 checks), endereços de ambiente (1.506 arquivos) e
  `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL por duas rotas de `message_subscribe_ui`
  dentro do pacote contrib recém-instalado. O submódulo de UI não é dependência
  nem será habilitado por esta fase; gate e allowlist permanecem intactos. A
  incompatibilidade precisa de solução antes da homologação/merge.

## 0.1.0-dev.3 — integração inicial de mensagens privadas

- Acrescenta callback OOP `#[Hook('form_alter')]` ao formulário nativo de
  criação/resposta do Private Message. O Form API contrib continua controlando
  destinatários, validação, salvamento e renderização.
- Acrescenta rate limit pelo serviço Flood do Core: dez envios válidos por UID
  em uma hora; validações que já falharam não registram envio.
- Mantém bloqueio, threads, entidades, caixa de entrada, contagem de não lidas e
  handlers de acesso no Private Message. Nenhuma permissão é concedida a role.
- Aplica bloqueio nos dois sentidos no formulário por meio do serviço de ban
  nativo, sem revelar a identidade do usuário bloqueador.
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
