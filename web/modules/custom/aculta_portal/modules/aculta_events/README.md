# ACULTA Eventos (`aculta_events`)

## Propostas de Evento — F6 parcial

Revalidação local em 2026-11-01: `EventOrganizationLifecycleKernelTest` passou
29 testes / 941 assertions; os seis cenários `testEventUpdateContribution*`
passaram 6/171, cobrindo submissão privada, aplicação seletiva, autorização
atual, membership, acesso ao Local, fingerprint e rejeição de criação
duplicada por título/Data (24 depreciações Core/contrib). Runtime não alterado.

A aplicação de `event_update` recarrega Locais de Datas existentes e propostos,
limpa o access handler e verifica o acesso `view` atual do autor. A revogação de
membership em Organização reivindicada ou a mudança do Local para privado gera
conflito sem mutar o Evento. Uma execução anterior da classe passou com 23
testes / 744 assertions (24 depreciações Core/contrib).

A revisão administrativa de propostas `event_create` e `event_update` fica em
`/painel-administrativo/eventos/propostas`, protegida pela permissão nativa
`administer aculta events` no MAIN e descoberta pelo Content Admin via
`AccessManager`. A decisão aplica apenas campos selecionados pelo adaptador;
as rotas não substituem o acesso do owner. O formulário de envio de propostas
`event_create`/`event_update` continua separado da rota nativa de CRUD.
Autores com capacidade configurada podem enviar um novo Evento para revisão em
`/culturas/propostas/evento/nova`. Quem tem acesso `update` a um Evento pode
propor alterações em `/culturas/eventos/{aculta_event}/propor-alteracao`; a rota
usa revisão-base canônica e permite adicionar uma Data opcional. Ambos os
formulários gravam apenas Contributions privadas e emitem aviso Messenger mínimo;
nenhum Evento oficial muda antes da aprovação.

O workspace do autor está em `/culturas/propostas`; lista e detalhe cobrem as
propostas `events_organization`, `events_location` e `events_event`, sempre
filtradas pelo titular e pelo Entity Access da contribuição. Isso permite que
notificações de Organização e Local abram suas propostas. As rotas não têm cache
compartilhado. A ressubmissão estruturada após `needs_changes` está disponível
em `/culturas/propostas/{uuid}/revisar`: usa controles tipados para os campos
allowlisted, o widget central de endereço com BR fixo e o lifecycle que cria
nova revisão imutável, revalida estado, owner, permissão e revisão-base. A
permissão de atualização do owner só existe durante `needs_changes`. O Kernel
focado comprovou reenviar uma proposta de Evento e negou acesso a outro autor
(1 teste / 35 assertions). O formulário inicial `/culturas/propostas/evento/nova`
permite enviar título, descrição, referências publicadas e até 30 Datas como
proposta privada; a capability é revalidada pelo adapter e lifecycle, Datas
são normalizadas para UTC e nenhum Evento é salvo antes da aprovação. Um teste
Kernel comprova persistência privada, acesso negado sem capability e ausência
de entidade Event oficial (1/31). A suíte combinada atual de lifecycle e
fronteira passou com 37 testes / 1.275 assertions (24 depreciações upstream);
o teste focal de submissão `event_update` passou com 1/35.
A inspeção visual autenticada não foi repetida nesta rodada.

O adapter `events_event` aceita `event_create` e `event_update` com allowlist
editorial, valida intervalos de Datas e referências a Organizações/Locais, usa
fingerprint incluindo as Datas existentes e aplica sob lock/transação. Datas
propostas são aditivas; Datas oficiais existentes não são removidas nem
substituídas. A criação aprovada atribui o Evento ao autor e mantém `rascunho`;
estado de publicação, RSVP, titularidade e campos internos não fazem parte do
payload. Atualizações aplicam somente campos escolhidos e preservam o estado de
publicação existente. Na aplicação, a capability configurável de criação e o
Entity Access atualizado do autor são revalidados; o cache do handler do Evento
é limpo antes da checagem. Regressões Kernel cobrem a revogação de acesso entre
submissão e decisão. Envios e decisões publicam evento mínimo ao Messenger
(source, UUID/revisão, destinatário e tipo), sem payload ou parecer. O update
Messenger 10015–10016 ampliam o tipo allowlisted e foram aplicados no Runtime;
a configuração sincronizada está limpa.
As interfaces de proposta inicial e de atualização estão implementadas. A
fingerprint usa entidade recarregada para ser estável entre
valores escalares em memória e a representação persistida. Não declarar a fase
F6 concluída até cobrir conflitos restantes e homologar Runtime.

## Propostas para criação de Organização

Contas não administrativas enviam propostas privadas `organization_create`
tanto pelo formulário `/culturas/organizacoes/nova` quanto pelo modal de
Organização do formulário de Evento. Nenhuma entidade pública é criada nem
vinculada ao Evento na submissão. A fila administrativa exige revisão e seleção
do nome antes de criar a Organização como publicada, sem titularidade ou
membership; a autoria permanece com o proponente. Depois da aprovação, a pessoa
pode iniciar o fluxo separado de reivindicação. Administradores mantêm o
cadastro direto pelo formulário.

## Propostas de Local — F5 parcial

O adapter `events_location` aceita propostas privadas de criação (`location_create`)
e atualização (`location_update`) com allowlist do perfil público. O formulário
de endereço central continua obrigatório, fixa o país em BR e conserva número e
complemento; coordenadas, OSM, status/publicação, titularidade e vínculo com
Evento não são aceitos como campos aplicáveis. Atualizações revalidam o acesso,
a impressão digital da origem e usam lock; criação só ocorre após aprovação,
como Local draft pertencente ao proponente, sem publicar nem vincular a Evento.
Uma verificação de título/endereço bloqueia duplicidade provável sem mesclar
registros automaticamente. A fila e a revisão administrativa ficam no MAIN,
em `/painel-administrativo/eventos/locais/propostas`, descobertas pelo Content
Admin sem substituir autorização Events. Testes focais locais passaram 3/100.
Decisões de Organização e Local são enviadas ao inbox do site pelo Messenger
com envelope mínimo, sem endereço, payload ou parecer; Runtime, email/digest e
workspace do autor ainda são pendentes.

Alterações propostas de endereço ou precisão incluem na revisão-base as Datas e
os Eventos ligados ao Local. Na aplicação, Datas/Eventos são recarregados do
storage e o acesso `view` do revisor é revalidado; uma mudança concorrente gera
conflito sem aplicar o endereço. `testLocationAddressContributionDetectsChangedLinkedEvent`
comprovou o cenário de Data alterada (1/26). A classe Kernel completa passou em
2026-11-01 com 30 testes / 967 assertions (24 depreciações upstream).

## Propostas de perfil de Organização — dev.9

Novas alterações de perfil em Organizações não reivindicadas são gravadas como
`aculta_contribution` pelo adapter `events_organization`. A aplicação revalida
que a Organização continua sem titular, compara a impressão digital do perfil,
obtém o mesmo lock de Organização usado pelo fluxo legado e aplica apenas os
campos selecionados pela revisão. Titularidade, reivindicações, membros, slug,
status editorial e publicação não são campos aplicáveis.

A fila em `/painel-administrativo/eventos/organizacoes/sugestoes` mostra as
novas Contributions e os registros legados `aculta_event_org_suggestion`.
Rotas e dados legados permanecem preservados para revisão durante a transição;
nenhuma migração de Runtime foi executada. Este corte ainda não fornece a área
do autor para acompanhar/revisar propostas devolvidas. A fila do Content Admin
aponta para a rota nativa Events e preserva seu acesso. Decisões persistidas
geram eventos mínimos em fila própria; o owner revalida revisão, autor e acesso
antes de emitir envelope ao Messenger. O inbox consome o tipo
`events.organization_proposal_outcome`; digest e email não estão ligados a ele.
O enqueue ocorre após a decisão editorial: se a Queue API falhar, o evento mínimo
fica no outbox KeyValue e `hook_cron()` tenta recuperá-lo em lotes de até 25.
O consumer do Messenger é idempotente por proposta, revisão, destinatário e
resultado; falha na publicação não desfaz a aplicação oficial.
Edições diretas de
Organizações reivindicadas e reivindicações continuam em seus owners atuais.

## ACULTA Studio Culturas — caminhos de organizadores (ADR-038)

A experiência de organizadores começa em `/culturas`, no purpose `eventos`.
Criação/edição de Eventos e ferramentas de Datas/Locais continuam sob este
módulo e reutilizam Entity Access, Form API, moderação e widgets existentes.
Escritas só ocorrem no purpose `eventos`; a Conta pode linkar via
`DomainPurposeManager`, sem proxy de POST. A administração privilegiada segue
no MAIN em `/painel-administrativo/eventos`. `/studio/culturas` e todos os
demais `/studio/*` foram removidos sem alias.

As rotas de organizador e o bloqueio de purpose incorreto foram verificados na
matriz HTTP (GET/POST/AJAX); os Kernels de fronteira e modal passaram (15 testes,
373 assertions), e a página autenticada `/culturas` carregou em HTTP 200. A
homologação integral de criação/edição/publicação com grants próprios de
organizador e a jornada visual completa ainda está pendente.

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

**Revalidação 2026-11-02:** `EventCalendarItemProviderKernelTest` passou com 6 testes / 116 assertions no `aculta_simpletest`, cobrindo proprietário A, proprietário B e conta anônima para agendas privadas. Nenhum código de produção ou estado do Runtime foi alterado nesta revalidação.

Cada página de Local oferece exportação da agenda contextual em
`/locais/{id}/agenda.ics`. O endpoint usa `CalendarQuery` e
`CalendarIcsExporter`, consulta somente o provider de Events, cobre a mesma
janela de 30 dias da agenda e revalida `view` do Local. Locais privados ficam
disponíveis apenas às contas autorizadas; as respostas são `private, no-store`.
O feed não inclui outros Locais nem providers LMS.

O dashboard `/culturas` também revalida `view` do Local antes de exibir seu
título ou município/UF. Se a conta pode editar o Evento, mas não visualizar o
Local relacionado, a coluna informa “Não definido”. A resposta agrega os
metadados de cache das decisões de acesso para impedir compartilhamento entre
contas.

Validação em 2026-11-01: Kernel Events em `aculta_simpletest`, 123 testes /
2.597 assertions PASS; Unit, 10 testes / 23 assertions PASS. Foram registradas
51 depreciações Core/contrib. A cobertura inclui Local público, filtro por
Local, acesso privado do owner versus outra conta e ocultação de Local privado
no dashboard do organizador. Matriz HTTP de purpose/host e Runtime seguem
pendentes.

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


## Ingressos gratuitos e pagos — decisões D-60/D-61, implementação pendente (ADR-036)

`field_gratuito`, `field_pricing` e `field_pricing_info` continuam informativos; `field_ticketing` é link externo. O modelo `aculta_event_offer` registra ofertas/lotes inativos; o update `10015` adiciona campos internos de política RSVP e capacidade nominal em Evento/Data. `RsvpPolicyResolver` valida configurações completas e contexto de capacidade, mas não cria inscrição nem reserva vaga. RSVP permanece desligado por padrão e os campos não aparecem no formulário do organizador. Não há endpoint de inscrição, emissão, venda Commerce, capacidade consumida ou check-in.

- **Ownership:** `aculta_events` é dono das ofertas/lotes, inscrições/participantes, emissão e check-in. `aculta_commerce` mantém pedidos e order items pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena os tipos/contextos no carrinho já existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito:** RSVP opcional por Evento, sem cobrança, nome e e-mail (D-33/D-40), sem CPF, pedido Commerce, carrinho ou Mercado Pago. A capacidade será atômica e configurável por Evento/Data/Lote; regras de limite, duplicidade, reserva/expiração, espera e cancelamento também são configuráveis por Evento. Emissão/check-in ainda não estão implementados. Não coletar dados reais publicamente antes da revisão LGPD/base legal/aviso/retenção.
- **Pago (proposta):** lote ligado a Evento e Data; pedido Commerce `ingresso`; Checkout Pro; somente confirmação de pagamento validada e idempotente emite o ingresso. Sem frete. Somente pedidos pagos participam do carrinho integrado e seguem a precedência D-39. Em pedidos mistos, o ingresso não exige endereço nem compõe frete.
- **Credencial compartilhada:** RSVP e compra paga convergem em participante/credencial e check-in móvel, preservando estados financeiros distintos. QR leva apenas token opaco assinado; validar no servidor, exigir autorização contextual, gravar uma entrada e bloquear reuso. Não indexar pedido, PII ou token em `aculta_index`.
- **Domínios e interface:** inscrições e compras iniciadas em `eventos`; escrita validada no servidor e nunca intermediada pelo purpose `account`. Administração técnica/global fica no MAIN. Gestão do organizador pertence ao ACULTA Studio Culturas em `/culturas`; o código é parcial e a homologação está pendente.
- **Gates financeiros:** venda paga bloqueada até parecer fiscal/jurídico (D-31/D-35), políticas de taxa, estorno e repasse (D-32 a D-34), LGPD e testes em sandbox. Repasse manual por evento; sem marketplace/split na primeira versão.
- **Compatibilidade:** preservar os campos informativos atuais sem migração destrutiva. Quando houver lotes internos, definir precedência de exibição e migração antes de ativar; não inferir uma transação do booleano de gratuidade.

Transferência de credencial é sempre permitida, auditável e invalida a credencial anterior (D-60). `aculta_events` é dono das ofertas/lotes e RSVP gratuito não usa Commerce (D-61). Estes comportamentos estão decididos, mas não implementados.

Permanecem pendentes: parecer fiscal/jurídico para venda paga, revisão LGPD/base legal antes da coleta pública de dados, escolha técnica do item Commerce para ingressos pagos e detalhes de acessibilidade/entrega da credencial e categorias de operador do check-in. A lista não inclui os itens já resolvidos por D-60/D-61.


## ACULTA Studio Culturas em domínio próprio - ADR-038

A Suite mantém o nome ACULTA Studio Culturas e usa `/culturas` no purpose `eventos`, conforme ADR-038. A rota está ativa e foi verificada no Runtime de desenvolvimento; `/studio/culturas` e demais rotas públicas `/studio/*` foram removidas sem alias/redirect. O painel administrativo continua no MAIN.
