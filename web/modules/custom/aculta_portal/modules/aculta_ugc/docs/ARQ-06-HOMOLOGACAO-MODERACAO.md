# ARQ-06 — Homologação da Infraestrutura Transversal de Moderação UGC

## 1. Visão Geral
A frente **ARQ-06** concluiu a infraestrutura transversal de denúncias, moderação e UGC no módulo `aculta_ugc`, consolidando contratos de segurança, isolamento por purpose, notificações reais desacopladas, fila unificada com paginação e migração idempotente de sinalizações legadas.

---

## 2. Entregas Realizadas

### ARQ-06A: Segurança e Contratos de Denúncia
- **Acesso Obrigatório à Entidade:** Bloqueio de denúncia para qualquer conteúdo inacessível ao denunciante (`$entity->access('view') === FALSE`), prevenindo enumeração de rascunhos ou conteúdos restritos.
- **Validação de Motivos:** Allowlist restrita (`spam`, `ofensa`, `conteudo_inadequado`, `direitos_autorais`, `desinformacao`, `outro`). Rejeição de valores arbitrários.
- **Resolução de Purpose no Servidor:** Impossibilidade de o cliente forjar o purpose (`resolvePurpose`).
- **Escopo de Moderação por Purpose:** Introdução de `ModerationScope` e permissões granulares (`moderar denuncias ugc: <purpose>`). Moderadores de fórum não decidem denúncias de eventos ou portal, salvo detentores da permissão global.
- **Transições de Status:** Máquina de estados determinística (`pendente` → `em_analise` → `procedente` / `improcedente`). Decisões finais são irreversíveis.
- **Auditoria:** Registro de moderador, data e parecer técnico nos campos da entidade, com logs estruturados.

### ARQ-06B: Notificações Reais Desacopladas
- **Eliminação de Fallback Inventado:** Removido qualquer endereço arbitrário hardcoded. Se não houver destinatários homologados no `UgcRecipientConfig` do purpose ou em `default`, o envio é suprimido com aviso de log.
- **Integração com `aculta_mailer`:** Tipo de mensagem `UgcReportNoticeMailType` registrado sob a tag `aculta_mailer.type`.
- **Privacidade Rigorosa:** Nenhum dado de identidade do denunciante é trafegado no contexto da mensagem.
- **Reenvio Idempotente:** Correlação UUID v5 determinística gerada por denúncia e destinatário (`MessageIdFactory`).

### ARQ-06C: Fila de Moderação Transversal
- **Controle de Acesso:** Usuários anônimos ou sem permissão de moderação no purpose recebem `AccessDeniedHttpException`.
- **Agrupamento e Contagem:** Agrupamento por entidade alvo (`target_type:target_id`), contagem de denúncias acumuladas e lista de motivos.
- **Exclusão de Alvos Mortos:** Entidades excluídas fisicamente do banco de dados são omitidas automaticamente da listagem ativa.
- **Paginação:** Suporte a paginação limpa via parâmetros `page` e `limit`.

### ARQ-06D: Migração Idempotente das Flags Legadas
- **Rastreabilidade:** Criação do campo `legacy_flag_id` em `UgcReport`.
- **Modo Simulação:** Suporte a `$dryRun = TRUE` sem alteração de banco.
- **Idempotência:** Reexecuções não duplicam registros.
- **Rollback Seguro:** Método `rollbackMigration()` capaz de reverter exclusivamente os registros originados do legado.

---

## 3. Matriz de Testes Kernel Executados

| Teste | Casos Cobertos | Asserções | Status |
| :--- | :--- | :--- | :--- |
| `UgcModerationKernelTest` | Ciclo de vida da denúncia, decisão por moderador, agrupamento básico, integridade de alvos | 68 assertions | **100% PASS** |
| `UgcReportPolicyKernelTest` | Acesso de leitura ao alvo, anonimato, validação de motivos, anti-spoofing de purpose, limite diário, escopo por purpose, transições válidas, limites de notas | 48 assertions | **100% PASS** |
| `UgcNotifierAndQueueKernelTest` | Fallback de destinatários sem invenção, notificações com privacidade estrita, permissões de fila por purpose, alvos excluídos, migração dry-run, idempotência e rollback | 32 assertions | **100% PASS** |
| **Total** | **22 testes Kernel executados contra MariaDB** | **148 assertions** | **100% PASS** |

---

## 4. Estado de Homologação no Runtime (`aculta_runtime`)
- O módulo `aculta_ugc` não está habilitado no Runtime de desenvolvimento (`core.extension.yml`).
- A infraestrutura está pronta para instalação limpa quando for agendada a transição do Fórum e demais consumidores.
- Nenhuma alteração destrutiva foi executada no banco `aculta_runtime`.

---

## 5. Pendências e Decisões do Responsável
1. **Limite Diário por Conta (`DAILY_LIMIT`):** Mantido provisoriamente em 20 como teto defensivo contra abuso. O valor numérico definitivo depende de homologação pela governança do projeto.
2. **Handoffs Externos:** Implementação dos gatilhos visuais em Eventos (`aculta_events`) e Revista (`aculta_magazine`) atribuída às respectivas frentes ativas.
