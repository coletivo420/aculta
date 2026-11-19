# ACULTA CGU (Conteúdo Gerado por Usuários)

Módulo transversal para gestão e consolidação de Conteúdo Gerado por Usuários no portal ACULTA.
Resulta da fusão e consolidação dos antigos módulos `aculta_contributions` e `aculta_ugc`, eliminando duplicações estruturais e centralizando tanto as propostas comunitárias quanto o sistema de moderação e denúncias.

## Funcionalidades e Responsabilidades

### 1. Submissões e Ciclo Comunitário Privado (ex-Contributions)
- **Entidade `aculta_contribution`**: Entidade revisionável com isolamento estrito de dados, fingerprint de revisão, integridade de autor e idempotência.
- **Ciclo de vida com máquina de estados**:
  - `Drupal\aculta_cgu\Service\ContributionLifecycle` (`aculta_cgu.contribution_lifecycle`)
  - `Drupal\aculta_cgu\Service\ContributionStateMachine` (`aculta_cgu.contribution_state_machine`)
  - Transições auditáveis de proposta: submissão, retirada, aprovação parcial/total, rejeição.
- **Adapters por Domínio**:
  - Registro extensível via service tag `aculta_cgu.contribution_adapter` coletado por `aculta_cgu.contribution_adapter_registry`.
  - Adapters ativos para `wiki` (verbetes da Wiki420), `events_event`, `events_location` e `events_organization` (Eventos ACULTA).

### 2. Denúncias e Moderação Transversal (ex-UGC)
- **Entidade `aculta_cgu_report`**: Registro sigiloso e padronizado de denúncias sem vazamento de dados do denunciante para o denunciado.
- **Fila Centralizada de Moderação**:
  - Painel administrativo unificado em `/painel-administrativo/moderacao/denuncias`.
  - Operações de decisão individual e em lote (`CguReportDecisionForm`).
  - Serviço `aculta_cgu.report_queue` para consulta paginada e triagem.
- **Configuração de Notificação por Domínio/Purpose**:
  - Entidade de configuração `aculta_cgu_recipients` com destinatários dedicados por purpose (fórum, revista, wiki, eventos).
  - Serviço `aculta_cgu.moderation_notifier` integrado ao `aculta_mailer`.
- **Ponte com Flagging**:
  - Sincronização e integração com flags de denúncia da comunidade (Growroom420/Fórum).

## Arquitetura e Segurança
- O módulo é transversal e neutro: não depende de lógicas específicas de apresentação dos módulos clientes.
- Segue a regra de isolamento: não vaza identidades de denúncias e garante controle de acesso rigoroso em propostas de conteúdo privado.


A configuração de limites é propriedade de `aculta_cgu.settings`. Um post-update move os valores ativos do ID anterior, remove o objeto antigo e mantém rollback por snapshot pré-update.
