# ARQ-06E — Handoff de Integração de Moderação UGC com Módulos Proprietários

Este documento especifica o inventário e os contratos de transição dos módulos proprietários para o serviço transversal `aculta_ugc`, sem duplicar formulários, tabelas ou filas.

---

## 1. Mapeamento de Consumidores Atuais

| Módulo | Entidade(s) Suportada(s) | Estado Atual | Destino Planejado (`aculta_ugc`) |
| :--- | :--- | :--- | :--- |
| **Fórum** (`aculta_forum`) | `node:aculta_forum_topic`, `comment:aculta_discussion_post`, `aculta_forum_post` | Flags legadas (`sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum`) consultadas via `ReportQueue` do `aculta_user`. | Consumir `aculta_ugc.report_service::createReport` para novas denúncias e delegar fila a `aculta_ugc.report_queue`. |
| **Wiki** (`aculta_wiki`) | `node:wiki_entry` | Moderação editorial core; sem denúncia ativa de usuários. | Criar adapter de ação "Denunciar verbete" consumindo `createReport($node, $user, $motivo, 'wiki')`. |
| **Revista** (`aculta_magazine`) | `node:revista` (artigos/edições) | Fluxo editorial próprio gerenciado por agentes da frente Revista. | Handoff para o agente da Revista plugar denúncia em comentários/artigos via `aculta_ugc.report_service`. |
| **Eventos** (`aculta_events`) | `aculta_event` | Entidade proprietária com validação experimental já integrada aos testes de `aculta_ugc`. | Handoff para o agente de Eventos incorporar o botão/modal de denúncia chamando `ReportService`. |

---

## 2. Contratos de Integração por Módulo

### 2.1 ACULTA Fórum (`aculta_forum`)
- **Ponto de Integração:** Substituição dos links diretos do módulo Flag por chamada à API ou action link.
- **Serviço:** `aculta_ugc.report_service`
- **Assinatura:**
  ```php
  $report = $reportService->createReport($topicOrPost, $currentUser, $motivo, 'forum');
  ```
- **Motivos Homologados:** `'spam'`, `'ofensa'`, `'conteudo_inadequado'`, `'desinformacao'`, `'outro'`.
- **Permissão de Moderação:** `moderar denuncias ugc: forum` (ou a permissão global `moderar denuncias ugc`).
- **Migração do Painel Legado:**
  - Arquivo: `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ReportQueueController.php`.
  - Transição: Delegar `items()` para `UgcReportQueue::getQueueItems($currentUser, 'forum')`.
- **Cacheability:** Cache tags `aculta_ugc_report_list` e contextos de usuário.

### 2.2 ACULTA Wiki (`aculta_wiki`)
- **Ponto de Integração:** Ação contextual no rodapé de verbetes (`node:wiki_entry`).
- **Serviço:** `aculta_ugc.report_service`
- **Chamada:**
  ```php
  $report = $reportService->createReport($wikiNode, $currentUser, 'conteudo_inadequado', 'wiki');
  ```
- **Permissão de Moderação:** `moderar denuncias ugc: wiki`.
- **Testes Necessários:** Teste funcional/Kernel validando que visitantes logados podem sinalizar verbetes e que apenas editores com a permissão específica de moderação wiki visualizam a fila pendente.

### 2.3 ACULTA Revista (`aculta_magazine`)
- **Ponto de Integração:** Artigos públicos e comentários da Revista.
- **Atenção:** Módulo sob desenvolvimento ativo por outro agente. Nenhuma alteração direta foi realizada em `aculta_magazine`.
- **Contrato Disponibilizado:**
  - Purpose: `'revista'`.
  - Serviço: `@aculta_ugc.report_service`.
  - Permissão de moderação: `moderar denuncias ugc: revista`.
  - Notificação aos editores: Configurar `UgcRecipientConfig` com id `'revista'` contendo os e-mails da redação.

### 2.4 ACULTA Eventos (`aculta_events`)
- **Ponto de Integração:** Detalhe de evento público (`aculta_event`).
- **Atenção:** Módulo sob desenvolvimento ativo por outro agente. Nenhuma alteração direta foi realizada em `aculta_events`.
- **Contrato Disponibilizado:**
  - Purpose: `'eventos'`.
  - Serviço: `@aculta_ugc.report_service`.
  - Permissão de moderação: `moderar denuncias ugc: eventos`.
  - Notificação aos organizadores: Configurar `UgcRecipientConfig` com id `'eventos'`.

---

## 3. Diretrizes de Segurança e Privacidade Invioláveis
1. **Identidade Oculta:** Nunca enviar o UID ou nome do denunciante no e-mail de notificação.
2. **Entity Access:** Nunca permitir o registro de denúncia sobre entidades que o usuário atual não possua acesso de leitura (`$entity->access('view')`).
3. **Imutabilidade de Decisões Finais:** Denúncias decididas como `'procedente'` ou `'improcedente'` não podem ser reabertas sem auditoria formal.
