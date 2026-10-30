# ADR-033 — Endereço unificado, busca postal restrita e gestão de Locais em Events

- Status: aprovado como contrato para implementação futura
- Data: 2026-10-30
- Escopo: `aculta_portal`, `aculta_portal_cep`, `aculta_events`, `aculta_maps`, Activity e formulários institucionais

## Contexto

O Portal já possui consulta CEP → endereço em `aculta_portal_cep` e um formulário/adaptador central em `aculta_portal`. A auditoria do HEAD da PR #299 encontrou essa integração nos fluxos centrais de Conta/checkout/equipe/loja e nos Locais de Events/Maps. Encontrou também campos legados de endereço em `node:add/activity` e um formulário institucional com campos textuais sem evidência de uso do adaptador.

Precisamos distinguir o preenchimento por CEP, que é infraestrutura comum do Portal, da busca por logradouro e da experiência de escolha/cadastro de Local, que pertencem ao fluxo de Eventos.

## Decisão normativa

1. Todo formulário do Portal que capture ou edite endereço completo deve reutilizar o componente/adaptador central de `aculta_portal` e a consulta por CEP de `aculta_portal_cep`. Novos módulos ACULTA herdam esta regra desde o início. Adaptadores preservam entidade, armazenamento, permissões e semântica próprios.
2. O Brasil é o país padrão. Não renderizar seleção de país nos formulários brasileiros. Preservar os fluxos atuais de Conta e checkout, inclusive o comportamento atual por CEP.
3. A busca por nome de rua/logradouro é uma capacidade adicional, distinta da consulta CEP → endereço. Sua interface fica restrita a `aculta_events` e `aculta_maps`; não integrar essa busca à Conta, checkout ou a um formulário global. O módulo `aculta_portal_cep` pode fornecer contrato/serviço postal compartilhado, sem ativar a interface em outros consumidores.
4. A busca por logradouro não está implementada nem tem provedor aprovado. Antes de código/ativação, documentar fonte, cobertura, atribuição/termos, privacidade, limites, cache, timeouts, abuso e comportamento sem JavaScript. Não confundir busca postal com geocodificação de endereço salvo para coordenadas.
5. Em `/node/add/activity`, a implementação futura deve remover os campos de endereço avulsos e relacionar a Activity a uma entidade Local de `aculta_events`. O fluxo editorial terá modal Drupal com AJAX para pesquisar Locais existentes, exibir correspondências por nome e selecionar um resultado; haverá ação explícita para cadastrar Local. O formulário pai não deve salvar Local silenciosamente.
6. O cadastro de Local será um modal Drupal com AJAX, começando pelo CEP. Reutilizar o adaptador central e a biblioteca `aculta_portal_cep/cep-address`; manter número e complemento sob controle editorial e Brasil fixo. A busca postal opcional por logradouro/endereço e os resultados próximos via AJAX só aparecem em Events/Maps. A seleção confirma o Local e atualiza a referência na Activity.
7. O trabalho deve considerar a seção administrativa de Eventos em `/painel-administrativo`, seguindo os padrões de áreas administrativas próprias do LMS e Fórum. Definir rotas, permissões, navegação, listagens e operações no roadmap de Events, sem ampliar acesso por simples conveniência do modal.
8. O formulário institucional continua dono dos campos e significados institucionais existentes. Integrar o adaptador central sem fundir campos, apagar dados, expor endereço privado ou impor migração sem análise do mapeamento e plano de dados.

## Guardrails de implementação

- AJAX nunca substitui validação e autorização no servidor. Revalidar acesso de leitura ao Local selecionado e permissão de criação/edição no submit; proteger contra IDs forjados, CSRF, duplicidade e condições de corrida.
- Resultados mostram apenas Locais que a conta pode consultar. Respeitar publicação, access handlers, propósito de domínio, cache tags/contexts e privacidade da precisão/endereço.
- Estado do modal e valores do formulário pai sobrevivem a rebuilds AJAX e erros de validação. Teclado, foco, anúncios acessíveis, fechamento/retorno ao pai, erros e viewport móvel precisam de critérios de aceite.
- O serviço de CEP existente e sua UI em Conta/checkout não mudam como efeito colateral. Nenhuma busca postal em cada tecla pelo Nominatim; geocodificação continua sob o contrato de Maps.
- Não alterar Runtime, configuração ativa, catálogo de Content Index, versão ou estado de release ao apenas registrar este plano. Estados permanecem Dev/planejado até implementação e validação próprias.

## Sequência documental de implementação futura

1. Auditar entidade/campos de Activity, Local e relações; definir cardinalidade, ciclo de vida, idioma, status, ownership e migração dos campos legados.
2. Especificar UI Drupal/AJAX e acessibilidade; pesquisa de existentes por nome; seleção; caminho de cadastro; CEP primeiro; eventual busca por logradouro e candidatos próximos.
3. Aprovar provedor e contrato postal para busca por logradouro, limites/privacidade/atribuição, antes de implementar.
4. Implementar e testar Local e seletor no Events, com autorizações, cache, persistência, reparenting, deduplicação e estados AJAX.
5. Implementar painel administrativo de Eventos e integração com `aculta_index` conforme roadmap LOC, sem incluir endereços privados no índice.
6. Integrar os formulários institucionais ao adaptador central após mapear os campos estruturados e seu uso em renderização/Schema.org.
7. Atualizar changelog, status e roadmaps apenas com evidências executadas; validar Conta/checkout para provar ausência de regressão.

## Referências

- [Contrato central de endereços](../portal/ACCOUNT-ADDRESS.md)
- [Plano da busca por logradouro](../portal/CEP-ADDRESS-SEARCH-PLAN.md)
- [Plano Locais e Content Index](PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md)
- [Guardrails do Portal](../portal/GUARDRAILS.md)


## Aditivo: modal em popup e integração Events/Maps (2026-10-30)

- “Modal” significa popup sobreposto à tela atual, aberto pelo sistema de diálogo/modal do Drupal. Não é painel inline, formulário expandido na página, nova rota de página inteira nem off-canvas.
- O popup deve manter contexto e dados da Activity pai, abrir a busca de Local e permitir seguir para outro popup de cadastro. Após seleção/salvamento confirmado, fechar o popup filho e atualizar a referência no formulário pai via AJAX.
- `aculta_events` é dono da entidade Local, seu cadastro, ciclo editorial, permissões e vínculo com Events/Activity. `aculta_maps` consome a mesma entidade e seus dados autorizados para mapa, geocodificação e links; não cria entidade ou cadastro paralelo de Local.
- Os contratos entre módulos devem ser explícitos por serviços/contratos neutros. Maps não deve depender de detalhes internos de formulário de Events, e Events não deve duplicar geocodificação/renderização de Maps.
- Critérios do popup: foco inicial e retorno ao acionador, fechamento por controles e Escape conforme padrão Drupal, navegação por teclado, erros acessíveis, loading/empty state, proteção contra submissão duplicada e persistência do formulário pai em rebuild/error AJAX.


## Aditivo: Eventos como centro editorial de Datas e Locais (2026-10-30)

- A gestão editorial de eventos é centralizada em Eventos. aculta_event_date continua sendo uma entidade interna do modelo Evento > Data > Local, mas não deve ser apresentada como conteúdo independente nem como uma tarefa separada “Adicionar data de evento”.
- Na criação do Evento, o formulário deve permitir organizar uma ou mais datas e associar um Local a cada uma. Na edição, a pessoa administra datas, horários e Locais dentro do contexto do Evento.
- A interface administrativa lista Eventos como unidade de trabalho. Ações de criar, editar ou remover datas ficam dentro do Evento correspondente; não deve haver item de menu, botão global ou formulário de conteúdo que inicie uma Data sem o contexto do Evento.
- Rotas técnicas de Data, se necessárias internamente, devem receber o Evento de origem e validar no servidor o acesso de criação/edição ao Evento e à Data. Não podem funcionar como operação editorial autônoma. Acesso direto sem contexto autorizado deve ser negado; rotas aposentadas seguem a política sem aliases/redirects temporários.
- O popup Drupal/AJAX de Local é iniciado a partir da edição da Data dentro do Evento e retorna a referência ao mesmo contexto, preservando os dados ainda não salvos do Evento e da Data.
- A administração poderá configurar no painel de Eventos os papéis autorizados a criar Eventos e Locais. O padrão é content_editor; a configuração não substitui autorização de servidor nem deve conceder publicação automaticamente.

Este aditivo complementa os itens anteriores que citavam Activity ou formulários de Data avulsos. Para a interface atual, o fluxo centralizado em Eventos prevalece; o modelo de entidades e o contrato de privacidade permanecem.


## Implementação da centralização editorial (2026-10-30)

A centralização descrita acima foi implementada em `aculta_events`:

- A rota global `aculta_events.date_add` foi removida (404, sem alias). A criação, edição e remoção de Datas usam `aculta_events.event_date_add`, `event_date_edit` e `event_date_delete`, sempre com `{aculta_event}` no caminho.
- `_entity_access: aculta_event.update` e `EventDateRouteAccess` verificam no servidor o acesso ao Evento e o vínculo Data > Evento; acesso direto sem contexto autorizado é negado.
- `EventForm` cria uma ou mais Datas com Local por Data e, em edição, lista e administra as Datas do Evento por popup Drupal/AJAX, retornando ao Evento de origem.
- O popup de Local (`EventLocationModalWidget`/`EventLocationModalForm`) recebe `wrapper_id` para funcionar dentro de cada Data e revalida `access('view')` na seleção e no cadastro.
- A política configurável de papéis (`aculta_events.permission_policy`, padrão `content_editor`) é aplicada nos access handlers e nas submissões.
- A entidade `aculta_event_date` e o modelo Evento > Data > Local permanecem; calendário, `.ics`, recorrência, embed e página pública continuam consumindo a Data como fonte de dados.

Evidência: `EventDateManagementKernelTest`, `EventFormDatesKernelTest`, suíte Kernel de Events e suíte `aculta_calendar` PASS (ver ROADMAP do módulo). Nenhuma migração de dados ou escrita no Runtime foi executada.
