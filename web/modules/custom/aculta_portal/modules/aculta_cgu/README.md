# ACULTA CGU (Conteúdo Gerado por Usuários)

Módulo transversal para gestão e consolidação de Conteúdo Gerado por Usuários no portal ACULTA.

Unifica e consolida:
1. **Submissões e Ciclo Comunitário Privado**:
   - Entidade revisionável `aculta_contribution`.
   - Ciclo de vida com máquina de estados auditável (`CguContributionLifecycle`, `CguContributionStateMachine`).
   - Adapters para submissões por domínio (`wiki`, `events_event`, `events_location`, `events_organization`).
2. **Denúncias e Moderação Transversal**:
   - Entidade `aculta_ugc_report` para registro sigiloso de denúncias.
   - Fila centralizada de moderação (`/painel-administrativo/moderacao/denuncias`).
   - Notificação configurável por purpose (`aculta_ugc_recipients`).
