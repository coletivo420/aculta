# ADR-037 — Descontinuação do tipo de conteúdo Atividade em favor de Eventos

- **Status:** Aceita; vigência imediata.
- **Data:** 2026-10-30.
- **Decisores:** responsável pelo produto e equipe de desenvolvimento.
- **Contexto:** O pacote OpenCulturas original trazia o tipo de conteúdo de nó `activity` ("Atividade / evento"), concebido para registrar atividades culturais e eventos com campos de endereço e data desestruturados. No ACULTA, o subsistema dedicado de Eventos foi implementado no módulo `aculta_events` com entidades próprias (`aculta_event`, `aculta_event_date`, `aculta_event_location`), validação centralizada de CEP/endereço brasileiro (`aculta_portal_cep`), controle de recorrência (`aculta_calendar`), isolamento de domínio no purpose `eventos`, e a futura experiência Studio Culturas (ADR-035). A convivência de `node:activity` causava duplicidade editorial, links órfãos e confusão entre eventos e atividades educacionais do LMS (`lms_activity`).
- **Decisão:** O tipo de conteúdo `activity` (`node:activity`) é oficialmente descontinuado e declarado como **legado**:
  1. Nenhum novo conteúdo do tipo `activity` deve ser criado. A criação na barra de ferramentas e no menu administrativo foi desativada via `ActivityRetirementHooks`, expondo exclusivamente "Cadastrar evento" (`aculta_events.event_add`) e "Adicionar local" (`aculta_events.location_add`).
  2. Todas as rotas de criação e edição direta de `activity` são bloqueadas ou redirecionadas administrativamente para o ecossistema `aculta_events`.
  3. Dados históricos remanescentes tiveram locais e dados migrados idempotentemente para `aculta_events` (atualização 10012 de `aculta_events`). O bundle `activity` em `config/sync/node.type.activity.yml` permanece apenas como registro histórico de esquema para evitar falhas de integridade referencial até a purga definitiva autorizada pelo responsável.
  4. Atividades educacionais do LMS (`lms_activity`) e atividades do ACULTA Activity (`aculta_activity`) são subsistemas distintos e permanecem inalterados.
- **Consequências:**
  - O catálogo público, busca cultural (`aculta_index`) e painel administrativo utilizam unicamente `aculta_events`.
  - Formulários e rotas de `node:activity` não aparecem em navegação nem são aceitos para novas publicações culturais.
