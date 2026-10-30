# ACULTA Eventos (`aculta_events`)

## Contrato obrigatório para campos de endereço

Por decisão D-54/D-55 do responsável, toda captura/edição de endereço de
eventos e locais deve reutilizar `AccountAddressForm` ou adaptador central de
`aculta_portal`, com `aculta_portal_cep/cep-address`. O país é sempre `BR`, sem
seletor editável em cadastro, edição ou no modal AJAX. Submissões que tentem
adulterar o país para outro código são rejeitadas na validação do servidor e
normalizadas para `BR` no `preSave` da entidade `EventLocation`. Preserve os campos
e entidades próprios de evento/local; não crie formulário paralelo nem replique
consulta, endpoint ou cliente de CEP. Isso também vale para etapas de revisão de
endereços importados que exibam um formulário à pessoa.

Eventos, datas e locais do purpose `eventos` (ADR-015). Usa o `aculta_calendar` para recorrência, agenda e `.ics`, e o `aculta_maps` para o mapa de locais. A versão atual mantém apenas gratuidade/preço informativos e link externo; RSVP gratuito com emissão e venda interna ainda não estão implementados. Consulte [plano de ingressos gratuitos e pagos](docs/PLANO-INGRESSOS-PAGOS.md).

## Fluxo editorial: Evento é a unidade de trabalho

`aculta_event_date` continua sendo uma entidade interna do modelo Evento > Data > Local e fonte do calendário, mas a gestão editorial é centralizada no Evento:

- Criação: `EventForm` (`/painel-administrativo/eventos/adicionar`) permite configurar uma ou mais Datas e associar um Local a cada uma.
- Edição: `/painel-administrativo/eventos/{aculta_event}/editar` lista as Datas do Evento e abre as ações em popup Drupal/AJAX.
- **Não existe** rota global de “Adicionar data de evento”. As rotas `aculta_events.event_date_add`, `event_date_edit` e `event_date_delete` exigem o Evento no caminho, verificam `aculta_event.update` e confirmam no servidor que a Data pertence ao Evento (`EventDateRouteAccess`).
- A antiga rota `aculta_events.date_add` foi aposentada sem alias ou redirect temporário.

## Regra de domínio

- **Todo `/painel-administrativo/` responde somente no MAIN** — inclusive Eventos, Datas, Locais e a integração OpenStreetMap. Em subdomínios, essas rotas retornam **404** (nunca 302), o toolbar do Navigation não é renderizado e nenhuma ação editorial é exposta.
- O purpose `eventos` serve as páginas públicas (catálogo, Evento, calendário, `.ics`, embed) e a superfície autenticada de organizadores ACULTA Studio Culturas em `/culturas`. Esta área é separada do painel administrativo e só executa escrita nas rotas canônicas de `aculta_events`, com validação de acesso no servidor.
- O bloco somente leitura `aculta_events_upcoming` ("Próximos eventos") está na sidebar do MAIN, visível apenas no purpose `main`, e não oferece ações editoriais.
- Salvar/editar/remover/reordenar Data atualiza a lista do Evento por AJAX, sem perder os campos não salvos. A ordem usa `field_peso`.
- Nenhuma entidade, catálogo ou armazenamento é duplicado por domínio; a origem continua sendo `aculta_events`.

## Validação visual (2026-10-29)

Endereços dos locais são Brasil-only e usam o formulário central de `aculta_portal`
com `aculta_portal_cep`; o assistente OpenStreetMap adapta o widget para as tags
OSM e mantém confirmação explícita antes de qualquer escrita externa. Decisão
D-55 e contrato em [`ACCOUNT-ADDRESS.md`](../../../../../../docs/portal/ACCOUNT-ADDRESS.md).

O smoke do purpose `eventos` passou 129/129 verificações no candidato atual
`a4f878d55` (events dev.3), servido no checkout de teste, em 1280/390/320 px
reais, com modo escuro. Script e instruções:
[tests/browser/eventos](../../../../../../tests/browser/eventos/README.md).

O dev.4 recusa `UNTIL` anterior ao início da série no formulário de recorrência,
com a mesma regra no expansor como guarda de dados.

O RC `0.1.0-rc.1` (candidato `89efeb5b7`) aplica a D-52: o mapa mantém apenas a
atribuição do provedor e os links para a Política de Privacidade, sem o texto
inline de envio de IP; o smoke confirmou 129/129 verificações e o mapa sem o
texto antigo. O OAuth do OSM foi validado no Runtime e o ensaio de escrita
fictícia foi concluído com limpeza (nó `14331309082`, changesets `191197897`,
`191197899` e `191197900`). Próxima etapa: validação Alpha após autorização de
release.

## Revalidação de Locais e Studio Culturas no HEAD da PR #299 (2026-10-30)

[Plano LOC-00 a LOC-08](../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md), [roadmap](docs/ROADMAP.md) e [análise comparativa OpenCulturas](../../../../../../docs/architecture/ANALISE-COMPARATIVA-OPENCULTURAS-ACULTA-STUDIO-CULTURAS.md).

O código do HEAD auditado contém CRUD e páginas de Local, modal Drupal/AJAX
para selecionar ou cadastrar Local com CEP primeiro, relações e providers de
`aculta_index`, e a base do ACULTA Studio Culturas. LOC-00 a LOC-06 têm código
presente, com LOC-06 ainda parcial. Migrador e testes de migração também existem;
nenhuma migração de Runtime foi executada nesta revisão. LOC-08, validação de
domínio, UX e homologação permanecem pendentes. Não inferir execução de teste
pela presença de arquivo de teste.


## Descontinuação de Atividades em favor de Eventos

Conforme diretriz arquitetural, atividades editoriais (`node:activity` e `/node/add/activity`) foram descontinuadas em favor exclusivo das entidades estruturadas de Eventos (`aculta_event`, `aculta_event_date` e `aculta_event_location`). Administradores usam `/painel-administrativo/eventos`; organizadores autorizados usam ACULTA Studio Culturas em `/culturas`, purpose `eventos`. Datas permanecem subordinadas ao Evento. Não confundir com atividades educacionais do LMS.

## Experiência de organizadores — planejamento ADR-035

## ACULTA Studio Culturas — decisão de produto e domínio (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota `/culturas` está declarada no código do owner `aculta_events`, no purpose `eventos`; hosts são resolvidos pela configuração e `DomainPurposeManager`. A entrada do Studio em `account` pode apenas apresentar link absoluto, sem escrita cruzada.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: código parcial presente no HEAD auditado. Execução dos testes no HEAD, autorização/domínio no Runtime, navegação integrada e homologação visual permanecem pendentes.


## Ingressos gratuitos e pagos - proposta de integração (ADR-036)

O estado atual continua sem ticketing interno: `field_gratuito`, `field_pricing` e `field_pricing_info` são informativos; `field_ticketing` é link externo. Não há RSVP com emissão, venda Commerce, lote/estoque ligado a Data, emissão pós-pagamento ou check-in integrado.

- **Ownership:** `aculta_events` é dono das ofertas/lotes, inscrições/participantes, emissão e check-in. `aculta_commerce` mantém pedidos e order items pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena os tipos/contextos no carrinho já existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito (proposta):** RSVP sem cobrança, nome e e-mail (D-33/D-40), sem CPF, pedido Commerce, carrinho ou Mercado Pago. Confirmar capacidade atomicamente e emitir credencial consumível uma vez no check-in. Não coletar dados reais em produção antes da revisão LGPD/base legal/aviso/retenção.
- **Pago (proposta):** lote ligado a Evento e Data; pedido Commerce `ingresso`; Checkout Pro; somente confirmação de pagamento validada e idempotente emite o ingresso. Sem frete. Somente pedidos pagos participam do carrinho integrado e seguem a precedência D-39. Em pedidos mistos, o ingresso não exige endereço nem compõe frete.
- **Credencial compartilhada:** RSVP e compra paga convergem em participante/credencial e check-in móvel, preservando estados financeiros distintos. QR leva apenas token opaco assinado; validar no servidor, exigir autorização contextual, gravar uma entrada e bloquear reuso. Não indexar pedido, PII ou token em `aculta_index`.
- **Domínios e interface:** inscrições e compras iniciadas em `eventos`; escrita validada no servidor e nunca intermediada pelo purpose `account`. Administração técnica/global fica no MAIN. Gestão do organizador pertence ao ACULTA Studio Culturas em `/culturas`; o código é parcial e a homologação está pendente.
- **Gates financeiros:** venda paga bloqueada até parecer fiscal/jurídico (D-31/D-35), políticas de taxa, estorno e repasse (D-32 a D-34), LGPD e testes em sandbox. Repasse manual por evento; sem marketplace/split na primeira versão.
- **Compatibilidade:** preservar os campos informativos atuais sem migração destrutiva. Quando houver lotes internos, definir precedência de exibição e migração antes de ativar; não inferir uma transação do booleano de gratuidade.

### Decisões ainda abertas

RSVP obrigatório ou opcional; limite por pessoa e prevenção de duplicidade; capacidade por lote versus Data; reserva temporária/expiração/fila; cancelamento e liberação de vaga; abrangência por série; transferência; entrega/acessibilidade do ingresso; retenção/base legal; critérios para migrar links externos.

## ACULTA Studio Culturas em domínio próprio - ADR-038

A Suite mantém o nome ACULTA Studio Culturas e usa `/culturas` no purpose `eventos`, conforme ADR-038. O código da rota existe no HEAD; sua ativação só é declarada após validação do Runtime. `/studio/culturas` não é rota-alvo e responderá 404 após a migração do prefixo. O painel administrativo continua no MAIN.
