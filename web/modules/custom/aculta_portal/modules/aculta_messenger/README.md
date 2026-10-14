# ACULTA Messenger

Módulo de integração do ACULTA Portal para conversas privadas, eventos de
mensagem, assinaturas e avisos institucionais. A fonte de verdade de cada
capacidade permanece no módulo Drupal que a fornece; este submódulo compõe a
experiência e aplica os contratos de acesso e Domain do Portal.

## Escopo arquitetural

- Private Message mantém conversas, mensagens, participantes e suas regras de
  acesso.
- Message mantém eventos persistentes configurados como tipos de mensagem.
- Message Notify entrega mensagens por notificadores existentes e pela
  infraestrutura de e-mail do Drupal.
- Message Subscribe e Flag mantêm assinaturas; assinar nunca concede acesso.
- Site Alert mantém alertas temporários globais.
- Comunicados editoriais persistentes, se aprovados na fase correspondente,
  serão conteúdo Drupal (Node/Views), sem substituir Site Alert.
- `aculta_messenger` não cria engine de mensagens, tabelas, entidades espelho,
  transporte SMTP ou regras visuais.

## Compatibilidade e evidência

O `composer.lock` registra Drupal Core 11.4.8 e trava os cinco projetos nas
versões estáveis instaladas. Seus arquivos foram auditados após a resolução
Composer. O inventário e os limites da inspeção estão em
[INTEGRATIONS.md](docs/INTEGRATIONS.md).

O PHP CLI disponível nesta execução é 8.4.26. O projeto pede PHP 8.5; esta
máquina não comprova validação em PHP 8.5. Compatibilidade de cada dependência
com PHP 8.5 também não é declarada até examinar código e metadados instalados.
O transporte de e-mail já instalado é o caminho de entrega previsto: Drupal Mail
com Symfony Mailer/Mailer Plus. O Messenger não adiciona SMTP ou outro
transportador.

## Domain Purpose

`account` é o único purpose da caixa postal e da experiência do membro.
`main` é o único purpose administrativo, inclusive para manutenção de Site
Alert e de comunicados. Outros purposes podem originar eventos e apontar para a
central de mensagens; não recebem caixas postais próprias. Rotas e URLs
cross-purpose usam `DomainPurposeManager`; mutações não atravessam hosts.

## Documentação

- [Arquitetura, fontes de verdade e decisões](docs/ARCHITECTURE.md)
- [Segurança, privacidade e permissões](docs/SECURITY.md)
- [Inventário de integrações e compatibilidade](docs/INTEGRATIONS.md)
- [Configuração e operação](docs/CONFIGURATION.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](CHANGELOG.md)

## Estado da implementação

O módulo declara dependências, mas ainda não foi ativado. As rotas, entidades,
formulários, bloqueio e contadores de Private Message foram confirmados no
código instalado para reutilização; a integração ACULTA ainda não adiciona
runtime. As permissões `use private messaging system` e `access user profiles`
não aparecem na configuração exportada de papéis, então nenhuma concessão será
feita pelo submódulo. A política de quais papéis podem iniciar conversas precisa
ser aplicada por configuração administrativa revisada antes da ativação da
experiência para membros.
