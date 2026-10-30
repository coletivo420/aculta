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

Eventos, datas e locais do purpose `eventos` (ADR-015). Usa o `aculta_calendar` para recorrência, agenda e `.ics`, e o `aculta_maps` para o mapa de locais. Ingressos pagos ficam fora da v1 e seguem plano próprio.

## Fluxo editorial: Evento é a unidade de trabalho

`aculta_event_date` continua sendo uma entidade interna do modelo Evento > Data > Local e fonte do calendário, mas a gestão editorial é centralizada no Evento:

- Criação: `EventForm` (`/painel-administrativo/eventos/adicionar`) permite configurar uma ou mais Datas e associar um Local a cada uma.
- Edição: `/painel-administrativo/eventos/{aculta_event}/editar` lista as Datas do Evento e abre as ações em popup Drupal/AJAX.
- **Não existe** rota global de “Adicionar data de evento”. As rotas `aculta_events.event_date_add`, `event_date_edit` e `event_date_delete` exigem o Evento no caminho, verificam `aculta_event.update` e confirmam no servidor que a Data pertence ao Evento (`EventDateRouteAccess`).
- A antiga rota `aculta_events.date_add` foi aposentada sem alias ou redirect temporário.

## Regra de domínio

- **Todo `/painel-administrativo/` responde somente no MAIN** — inclusive Eventos, Datas, Locais e a integração OpenStreetMap. Em subdomínios, essas rotas retornam **404** (nunca 302), o toolbar do Navigation não é renderizado e nenhuma ação editorial é exposta.
- O purpose `eventos` serve as páginas públicas (catálogo, Evento, calendário, `.ics`, embed) sem toolbar, sem links administrativos e sem ação de edição.
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

## Incremento de Locais - planejado em 2026-10-29

[Plano de implementação](../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md) e [roadmap](docs/ROADMAP.md).
Entidade existente preservada; faltam CRUD nativo, seleção/criação inline no
editor de Data, detalhe com agenda e navegação. A integração com aculta_index
inclui canônicos, relações Evento > Local, ACL, consultas paginadas e manutenção
incremental. O RC acima é evidência histórica; o novo escopo está Dev/planejado,
sem mudança de manifesto ou teste Runtime nesta revisão.


## Descontinuação de Atividades em favor de Eventos

Conforme diretriz arquitetural, atividades editoriais (`node:activity` e `/node/add/activity`) foram descontinuadas em favor exclusivo das entidades estruturadas de Eventos (`aculta_event`, `aculta_event_date` e `aculta_event_location`). A criação de novos eventos e locais ocorre exclusivamente dentro de Eventos em `/painel-administrativo/eventos/adicionar` (não confundir com as atividades educacionais do LMS).

## Experiência de organizadores — planejamento ADR-035

## ACULTA Studio Culturas — decisão de produto e domínio (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota planejada é `/studio/culturas`, atendida no purpose `eventos` (host eventos configurado pelo DomainPurposeManager); a entrada geral do Studio em `account` poderá apenas apresentar um link para essa experiência.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: decisão e planejamento documental; implementação, autorização por domínio, navegação e homologação ainda precisam ser concluídas.
