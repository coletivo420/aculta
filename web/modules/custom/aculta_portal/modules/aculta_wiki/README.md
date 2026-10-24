# ACULTA Wiki — Wiki420 como submódulo

## Avisos da Wiki

O `aculta_wiki` publica eventos editoriais e seleciona destinatários de acordo
com seu workflow. Consumers, persistência Message, central, contagem,
preferências, deduplicação, abertura, filas e entrega de e-mail pertencem ao
`aculta_messenger`; transporte/templates permanecem no `aculta_mailer`. Avisos
no site são obrigatórios; e-mail segue modo off/direto/digest escolhido pelo
membro e política administrativa. O submódulo antigo não mantém código ou
entrega legada ativa. Consulte [`docs/ROADMAP.md`](docs/ROADMAP.md).


Submódulo de `aculta_portal` que reunirá o controlador, os formulários e as
rotas próprias da Wiki420. O plano completo, com fases, riscos e decisões
pendentes, está em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Estado atual

- Contrato Wiki→Messenger v1 documentado em
  [`docs/MESSENGER-EVENT-CONTRACT.md`](docs/MESSENGER-EVENT-CONTRACT.md): dois
  eventos versionados por revisão (verbete enviado à fila e resultado de
  proposta), destinatários por acesso atual, payload mínimo e fila persistente
  com retry. O destino é o canal comum: aviso no site obrigatório e e-mail
  opcional direto ou por resumo/digest, conforme preferência e política do
  Mailer. Os dois value objects versionados
  validam UUID/revisão, allowlist e payload (Unit 4/5). Dev.113 adiciona
  publishers nas transições. O consumer, Message, central, preferências e
  entrega foram migrados ao `aculta_messenger`; o submódulo antigo não tem
  implementação executável. Unit/Kernel centrais atuais constam no roadmap.
  O fallback editorial legado para usuários elegíveis foi removido após a
  migração; o recibo Commerce mantém a exceção documentada para convidados.

- `0.1.0-dev.113`: transições Wiki persistem envelopes v1 na Queue API
  confiável e o worker despacha somente para eventos que tenham consumer
  registrado. Falhas/no consumer mantêm o item para retry após lease; e-mail
  transacional não é alterado. Install/update 10002 prepara a tabela Core antes
  das transações para evitar DDL/commit implícito MariaDB. Unit 18/73 e Kernel
  queue 1/8 aprovados; o Kernel de decisão encontrou serviço ausente durante a
  refatoração paralela de Studio.

- `0.1.0-dev.112`: adiciona `WikiEntryReviewRequestedEvent` e
  `WikiProposalOutcomeRecordedEvent` com envelope Queue v1 e allowlist de
  estados. Unit passou 4 testes / 5 asserções.

- `0.1.0-dev.111`: `aculta_wiki_update_10001()` instala a definição da entidade
  `aculta_wiki_proposal` quando o módulo já estava habilitado antes dela existir,
  e só instala `proposed_fields` quando ainda ausente. Kernel isolado simulando
  a instalação atrasada passou (1 teste / 30 asserções). No Runtime de teste,
  `updb` instalou as quatro tabelas ausentes e `drush cr` passou; não havia outros
  updates pendentes. Firefox ESR/X11 autenticado aprovou 19/19 verificações.

- `0.1.0-dev.110`: o CTA de colaboração da home Wiki leva ao início WE SATIVA
  no Domain `account` quando a conta tem `createAccess()` e acesso real à rota;
  sem essa rota/acesso, usa o formulário nativo de criação. O Kernel focal
  verifica domínio, cache e restauração do purpose. Classe Kernel MariaDB:
  27/1.504; 304 depreciações Core/contrib, 28 PHPUnit e 3 PHP/Webform.
  A chamada é validada no Runtime pelo smoke autenticado abaixo.

- Revalidação do ciclo R2 no HEAD `9da043aeb` (2026-10-24): classe Kernel
  completa passou em MariaDB isolada, 27 testes / 1.500 asserções. Cobriu
  publicação/privacidade sincronizada com o verbete, revisão pendente, rollback,
  retry, referências históricas e limpeza segura; 304 depreciações Core/contrib
  e 28 PHPUnit. Runtime e filesystem instalado não foram alterados.

- Revalidação WIKI-R1 no HEAD `03b840a7f` (2026-10-24): providers Unit e
  Kernels de descoberta/projeção, autorização/relações e rebuild completo Wiki
  passaram em 20 testes / 425 asserções com MariaDB isolada. Projeção v4,
  relações `related_entry`, `wiki_category`, `wiki_image` e fingerprint
  confirmados; 240 depreciações Core/contrib e 3 PHPUnit. Sem rebuild ou alteração
  no Runtime.

- `0.1.0-dev.109`: a entrada WE SATIVA agora tem `/studio/we-sativa/inicio`
  no purpose `account`, com criação, retomada de rascunho autorizado e guia
  editorial. O serviço Wiki mantém dados, Entity Access e cache; o workspace
  liga de volta ao início. Kernels focais passaram em 2 testes / 208
  asserções; Runtime/visual permanecem pendentes.

- `0.1.0-dev.108`: autoria confirmada recebe aviso privado quando sua proposta
  é aceita e publicada, além de devolvida/recusada. O aviso contém apenas o
  assunto, estado, nota e URL privada; integração com avisos pessoais do
  Messenger segue em desenho. Kernel do fluxo passou em 1 teste / 220
  asserções; 202 depreciações upstream/PHPUnit.

- Revalidação R2 rotas/acesso no HEAD `8d0bf703f` (2026-10-24): Kernels de
  discovery/View, CTA via `createAccess()` e isolamento da busca por grants
  passaram em 3 testes / 167 asserções. 300 depreciações Core/contrib, 4 PHPUnit
  e uma PHP/Webform; Runtime não alterado nem homologado.

- Revalidação WIKI-R1 no HEAD `fc162b184` (2026-10-24): Kernels de acesso Wiki,
  bootstrap owner e rebuild Wiki-owned passaram em 10 testes / 368 asserções
  (MariaDB isolada). Projeção v4, relações e fingerprint inalterados; PHPUnit
  reportou 243 depreciações upstream. Runtime e geração instalada intocados.

- `0.1.0-dev.107`: Kernel instala o ACULTA420 e renderiza a home Wiki sob o
  tema público; os SDCs `context-tabs` e `wiki-home` aparecem no HTML (focal
  1/96). A classe Kernel completa passou em 27/1.483, com 304 depreciações
  Core/contrib e 28 PHPUnit. HTTP 200 e Runtime seguem pendentes.

- Revalidação R2 do ciclo de imagem no HEAD `b5995d171` (2026-10-24): classe
  Kernel MariaDB completa aprovada, 27 testes / 1.475 asserções; 291
  depreciações Core/contrib e 28 PHPUnit. Upload, visibilidade, revisão
  pendente, rollback/retry e limpeza segura seguem aprovados. Runtime intocado.

- `0.1.0-dev.106`: Kernel compõe `WikiController::home()` sob purpose Wiki e
  verifica categorias, verbetes recentes e alterações recentes (focal combinado
  1/88). HTTP 200 renderizado segue pendente porque o bootstrap não ativa o tema
  ACULTA420.

- `0.1.0-dev.105`: Kernel caracteriza a home Wiki através do subscriber real:
  o Domain Wiki aceita o caminho `/wiki` servido pela raiz, e o Domain principal
  responde 404 (1 teste / 84 asserções; 199 depreciações upstream e 2 PHPUnit).
  Nenhuma alteração no Runtime.

- Integração Studio dev.5: `create wiki_entry content` não abre o inventário
  editorial do GanjaLab; autoria própria permanece na WE SATIVA. A `WikiTab`
  exige permissão editorial, revisão ou moderação de propostas, e só expõe a
  seção de verbetes compartilhados a `edit any wiki_entry content`. Unit Studio
  passou em 7 testes / 27 asserções. O smoke WE SATIVA no Firefox visível gerou
  login e confirmou 404 em `/studio/we-sativa` (0/2); Runtime e visual seguem
  pendentes até registrar a rota no container.

- `0.1.0-dev.104`: Kernel exercita o `WikiProposalDecision` real enquanto outro
  processo mantém lock MariaDB na linha do Node. A decisão aguarda a liberação,
  aplica a revisão e o teste mantém o conflito de VID obsoleto sem gravação
  parcial (focal 1/216; classe completa 27/1.468; 291 depreciações Core/contrib
  e 28 PHPUnit). PHPCS/lint passam; Runtime intocado.

- `0.1.0-dev.103`: endpoint de proposta agora declara purpose Wiki; Kernel
  comprova que acesso no Domain principal é rejeitado (focal 1/81; 199
  depreciações Core/contrib e 2 PHPUnit). Submissão privada e fila editorial
  passaram em focais 2/306; PHPCS, lint e YAML passam. Runtime intocado.

- `0.1.0-dev.102`: verbetes criados sem origem explícita recebem o Domain Wiki
  canônico. Kernel prova lookup de publicação depois da decisão do editor e
  mantém submissões posteriores em `review` sem conceder publicação à autoria
  (focal 1/111; classe Kernel revalidada em 27/1.453, 291 depreciações Core/contrib e 28
  PHPUnit). Runtime intocado.

- R2 revalidada após dev.102 no HEAD `5364d041b`: o ciclo completo de imagem
  passou em 27 testes / 1.453 asserções; 291 depreciações Core/contrib e 28
  PHPUnit. Upload privado, visibilidade alinhada à publicação do verbete,
  retenção por revisões e rollback continuam cobertos; Runtime intocado.

- `0.1.0-dev.101`: Kernel integra lookup de participação e formulário nativo;
  primeira submissão segue em `review` e não habilita publicação pela autoria
  (1/93). Sem mudança de permissões ou transições.

- `0.1.0-dev.100`: suíte Unit completa aprovada (71/400). A regressão do
  formulário sem campo de imagem agora preserva a orientação autoral sem criar
  controles de imagem (3 depreciações PHPUnit; Runtime intocado).

- `0.1.0-dev.99`: busca completa truncada para `MAX_CHARS` antes da consulta;
  `WikiControllerTest` passou (11/70), Runtime intocado.

- `0.1.0-dev.98`: Unit da prévia cobre limite de 100 caracteres, filtro
  por Entity Access e links canônicos e de busca completa (10 testes/59
  asserções). O limite da busca completa e renderização da contagem permanecem
  pendentes; Runtime intocado.

- Gate Runtime WE SATIVA (2026-10-24): Firefox visível encontrou 404 porque
  `aculta_studio.we_sativa` não consta na tabela de rotas, embora Wiki/Studio
  estejam ativos. Revalidação read-only no host `toca`, branch compartilhada e
  URI de conta confirmou Drupal 11.4.10, DB `aculta_runtime`, Index desativado,
  rota e `aculta_wiki.contributor_workspace` ausentes do container. O smoke
  Firefox encerrou em 0/2. Os três Kernels do workspace passaram no SimpleTest
  (3/240; 200 depreciações upstream). Rebuild/cache/import não foram executados;
  aguarda autorização operacional.

- `0.1.0-dev.97`: Kernel verifica o link da fila de propostas no GanjaLab, a
  rota de decisão, o ID correspondente e o acesso do moderador (1/205).
  Runtime e interface visual intocados.

- `0.1.0-dev.96`: busca e prévia tratam `q[]` como entrada inválida sem lançar
  erro ou consultar resultados; a prévia permanece `no-store, private`.
  `WikiControllerTest` passou (9/40) e `WikiSearchFormBuilderTest` (4/18).
  Runtime e interface visual intocados.

- `0.1.0-dev.95`: Kernel usa as configurações exportadas de `wiki`/CKEditor 5 e confirma disponibilidade do formato para `email_confirmed` no Node Form nativo; upload direto segue desativado. Focais 2/120; classe Kernel completa 27/1.425 (291 depreciações Core/contrib, 28 PHPUnit). PHPCS/lint e gate Drupal 11 passaram. Runtime/Firefox intocados.

- `0.1.0-dev.94`: a criação oferece busca não bloqueante de até seis títulos publicados e visíveis no Domain Wiki; Entity Access é revalidado. Kernel MariaDB focal 1/34 (2/116 com submit do formulário nativo); PHPCS/lint e gate Drupal 11 passaram. O gate Portal–tema reporta 12 ocorrências existentes fora da nova classe. Runtime e visual intocados.

- R2 rotas/acesso revalidada no HEAD `c610030dd`: três Kernels MariaDB passaram (3/148), incluindo rotas, CTA por `createAccess()` e cache/grants da busca. Runtime não foi alterado nem homologado.

- Revalidação R2 no HEAD `369db1537`: Kernel completo revalidado no HEAD `c767e8fac` passou em 27 testes / 1.425 asserções; 291 depreciações Core/contrib e 28 PHPUnit. Nenhuma mudança no Runtime ou filesystem instalado.

- R1 revalidada no HEAD `b8e486673`: dois providers Unit e quatro Kernels Wiki-owned/Index passaram em 20 testes / 425 asserções; projeção v4 e fingerprint inalterados. PHPUnit reportou 243 depreciações upstream e 3 PHPUnit. Runtime e rebuild instalado intocados.

- `0.1.0-dev.93`: criação guiada pelo formulário Core; “Salvar rascunho” e
  “Enviar para revisão” são ações separadas, respeitando a transição oficial.
  Teste Kernel MariaDB focal 1/77, incluindo tentativa de publicação pelo campo
  `status`; o rascunho permaneceu privado. Runtime e visual intocados.

- `0.1.0-dev.92`: proposta contextual agora tem etapas `Escrever` e `Revisar e
  enviar`, mantém os valores server-side ao voltar e mostra os valores propostos
  antes do envio. Se o verbete mudar, não envia contra uma base silenciosamente
  atualizada; exige revisão e confirmação explícita. Kernel focal 1/202; Runtime
  e validação visual intocados.

- `0.1.0-dev.91`: moderação exige uma escolha explícita por lista aprovada
  (substituir ou acrescentar); não presume `replace`. Form API e serviço
  rejeitam ausência de escolha. Kernel focal 1/183; Runtime intocado.

- `0.1.0-dev.90`: regressão Kernel caracteriza “Alterações recentes” para duas
  contas de mesmo papel com grants diferentes; cada uma vê apenas seu verbete,
  e o fragmento declara `user.node_grants:view`. Focal 1/43; Runtime intocado.

- Revalidação WIKI-R1 no HEAD `5504aced2`: providers Wiki/categoria e Kernels
  Wiki-owned/Index passaram em 20 testes / 425 asserções; projeção v4 e
  fingerprint mantidos. Foram reportadas 243 depreciações upstream e 3 do
  PHPUnit. Runtime e rebuild instalado intocados.

- `0.1.0-dev.89`: CTA contextual abre seletor WE SATIVA para verbetes públicos.
  Pessoas autorizadas a editar recebem somente a edição nativa; demais pessoas
  confirmadas recebem quatro intenções allowlisted que levam ao formulário de
  proposta existente. Kernel focal 3/311; PHPCS e lint passaram. Runtime e
  homologação visual intocados.

- `0.1.0-dev.88`: Meu Trabalho WE SATIVA agora filtra verbetes por rascunho,
  revisão, publicado ou todos. O workflow é lido da entidade; consulta e
  paginação são limitadas, Domain `account` e cache GET preservados, e a
  paginação de propostas segue independente. Kernel focal 1/141; suíte
  workspace 3/235. Runtime intocado.

- Revalidação no HEAD `d3bfd3715` (sem alteração de código): providers Wiki/
  categoria Content Index passaram em 9/40; quatro Kernels MariaDB de R1 em
  11/385. O ciclo completo da imagem passou em 25/1.187. A projeção permanece
  v4; Runtime e reconstrução instalada não foram tocados.

- `0.1.0-dev.87`: adapter da evidência de publicação Wiki implementado por
  contrato tagged do `aculta_user`. Só revisão padrão publicada do autor no
  Domain Wiki ativo conta; consulta cross-account é sem cache. Kernel focal 1/45 e
  User Unit 123/439; regras de publicação e Runtime não mudaram.

- `0.1.0-dev.86`: Kernel R2 de rotas/acesso revalidado no HEAD `8896527e7`;
  descoberta/uniquidade, purpose, raiz pública, acesso de criação e busca por
  grants passaram em 3 testes / 141 asserções. Runtime intocado.

- `0.1.0-dev.85`: falha ao salvar a Media agora tem regressão Unit para
  rollback da transação, invalidar caches de File/Media e limpar estado do
  formulário. Wiki Unit 67/360; Kernel de ciclo de imagem 24/1.142. PHPCS/lint
  passaram; Runtime intocado.

- `0.1.0-dev.84`: revalidação WIKI-R1 no HEAD `bea9cc4c3`: providers Unit
  9/40; quatro Kernels MariaDB 11/385. Projeção v4, relações e fingerprint
  seguem estáveis. Foram emitidas 243 depreciações Core/contrib/PHPUnit; Runtime
  e reconstrução instalada não foram tocados.

- Integração Studio dev.4: a `WikiTab` exige `aculta_wiki` habilitado além do
  bundle `wiki_entry`; Unit Studio focal 3/10 e suíte completa 13/41 passam.

- `0.1.0-dev.83`: WE SATIVA revalida `view` Entity Access de proposta própria;
  pessoa com `submit wiki proposals`, mas sem `view own wiki proposals`, vê
  workspace vazio (focal 1/45; classe Kernel 24/1.142). Runtime intocado.

- `0.1.0-dev.82`: Kernel WE SATIVA inclui propostas de outra autoria e confirma
  que não aparecem no workspace de quem não é dono; paginação própria continua
  20+1 (focal 1/42; classe 24/1.139). Runtime intocado.

- A matriz WE SATIVA foi reconciliada com Kernel já existente: verbetes e
  propostas próprias paginam 20+1, com cursores e links no Domain `account`
  (focal 1/40). O teste não caracteriza candidatos de terceiros.

- `0.1.0-dev.81`: aceite editorial no Form API inclui imagem publicada
  selecionada pelo editor; revisão publicada recebe a referência validada
  (Kernel focal 1/167; classe 24/1.137). Runtime intocado.

- `0.1.0-dev.80`: Kernel MariaDB valida contenção do lock de idempotência entre
  processos PHP independentes e ausência de proposta duplicada (24/1.134).
  Runtime intocado.

- `0.1.0-dev.79`: Kernel prova que uma revisão pendente mantém a imagem da
  revisão pública visível e deixa a imagem substituta privada (classe completa
  24/1.129). Runtime intocado.

- `0.1.0-dev.78`: fixtures Unit do hook de imagem acompanham a assinatura atual
  e constroem um formulário `wiki_entry`; Wiki Unit passou (66/344, 3
  depreciações PHPUnit). Runtime intocado.

- `0.1.0-dev.77`: formulários Core de `wiki_entry` oferecem retorno cacheável ao
  Meu Trabalho WE SATIVA quando o provider Studio e o acesso Wiki estão ativos.
  O link usa o purpose `account`; ausência da rota omite a ação. Kernel completo
  passou na dev.77 (23/1.039); PHP lint e PHPCS passaram. Runtime intocado.

- Revalidação Content Index no HEAD após dev.77: Unit dos providers Wiki/categoria
  9/40; quatro Kernels combinados (provider, acesso/relações, discovery owner e
  rebuild de geração Wiki-owned) 11/385 em MariaDB isolada, com 243 depreciações
  Core/contrib/PHPUnit. Provider v4 e fingerprint permanecem iguais; nenhum
  rebuild ou ativação no Runtime foi executado.

- Preflight read-only 2026-10-24: no host `toca`, Drush confirmou Drupal 11.4.10,
  URI `wiki420.aculta.toca.net.br` e banco `aculta_runtime`; `aculta_wiki` está
  ativo e `aculta_index` desativado. HTTP: `/` 200, `/wiki` 404, listagem/busca/
  prévia 200. Nenhuma configuração, cache ou dado foi alterado.

- Smoke WE SATIVA no Firefox ESR visível: a suíte reutilizável está em
  `tests/browser/aculta-wiki/we-sativa.py`. A execução chegou ao host, mas a
  rota/provider não está registrado no container ativo (0/2 verificações); a
  consulta Drush também não encontrou `aculta_wiki.contributor_workspace`.
  Requer rebuild do container para validar a integração visual; não foi
  executado sem autorização. Evidências temporárias em `/tmp/aculta-wiki-we-sativa`.

- Fila editorial de revisões: regressão Kernel MariaDB cobre 26 candidatos sem
  grant seguidos por 26 revisões autorizadas, duas páginas 25+1, sem perda ou
  repetição e apenas VIDs atuais. Focal: 1/101; 198 depreciações Core/contrib e
  2 PHPUnit.

- `0.1.0-dev.76`: envio de imagem pelo formulário Node/Form API real passou em
  Kernel MariaDB. Upload `managed_file` resulta em Media privada para Node
  `draft`; classe de ciclo da imagem: 23/1.036, PHP lint e PHPCS aprovados.
  Runtime permanece intocado.

- `0.1.0-dev.75`: R1 revalidada no HEAD. Unit dos providers Wiki/categoria:
  9/40; três Kernels MariaDB combinados (provider, acesso/relações e owner
  real): 10/343. O bootstrap real descobre Wiki + Image e revalida Entity
  Access. Projeção v4 permanece inalterada; ativação do Index e rebuild integral
  no Runtime seguem pendentes.

- `0.1.0-dev.74`: prévia da busca usa região viva nomeada; o campo fica como
  searchbox nativo e links são percorridos por Tab. Unit 4/18 e fixture Firefox
  do JS local 14/14 passam. No Runtime antigo, o smoke integrado fez 28/35: ele
  ainda serve a semântica combobox incorreta e submit fora da viewport; atualização
  e homologação pendentes.

- `0.1.0-dev.73`: nome acessível do campo e do submit é obtido via WebDriver
  `computedlabel` do Firefox; o host de teste reportou “Pesquisar na Wiki420” e
  “Buscar”. O candidato segue 22/22, agregado Runtime 18/20.

- `0.1.0-dev.72`: o smoke compara indicador de foco computado com o estado sem
  foco, exige nome acessível no campo e submit e registra ambos no JSON. Com o
  CSS candidato passou 22/22; o agregado Runtime permanece em 18/20 devido ao
  submit fora da viewport, sem ter sido alterado.

- `0.1.0-dev.71`: smoke reutilizável de teclado verifica foco visível e limites
  de viewport no campo/submit da busca, em Firefox ESR. O CSS agregado atual do
  Runtime de teste falha no submit (x=336 a 320 px; x=1282 a 1280 px); a folha
  candidata carregada por GET no browser passa 22/22. O agregado não foi
  atualizado; Olivero e WCAG integral continuam pendentes.

- `0.1.0-dev.70`: edição própria WE SATIVA agora preserva conflitos. O Form API
  carrega a revisão-base, valida antes do submit e revalida com lock transacional
  do Node no save handler; Kernel cobre sessão obsoleta e corrida após validação
  (1/37), além do fluxo normal draft → review (1/67). Unit do uploader: 12/79.
  Classe Kernel de mídia completa: 22/995; 291 depreciações Core/contrib e 23
  PHPUnit.

- `0.1.0-dev.69`: Kernel proprietário inicializa Wiki + Image + Portal + Index,
  descobre os três providers, executa geração v4 completa para o inventário de
  teste e valida fingerprint, campos projetados e relações (1/42; 201
  depreciações Core/contrib). A geração é ativada somente no banco de teste;
  instalação/inventário do Runtime não foram auditados.

- `0.1.0-dev.68`: R1 revalidada no HEAD após os incrementos recentes. Unit Wiki:
  9/40 (3 depreciações PHPUnit); Kernels reais Index provider 1/17, acesso
  7/238 (201 depreciações) e owner/bootstrap 2/88 (243 depreciações), aprovados
  em MariaDB dedicada. Provider v4 inalterado; rebuild integral e Runtime
  continuam pendentes.

- `0.1.0-dev.67`: Firefox ESR/X11 visualizou home, listagem e busca em 1280,
  390 e 320 px; 46/46 verificações passaram com o CSS atual da Wiki carregado
  por GET no browser. Identificado e corrigido overflow do submit em
  `aculta420` 0.6.1-dev.5. O agregado Runtime não foi reconstruído; Olivero e
  WCAG 2.2 AA completos pendentes.

- `0.1.0-dev.66`: Kernel cobre nota acima de 5.000 caracteres, decisão de
  proposta retirada, revisão-base obsoleta e aplicação editorial aprovada via
  workflow/Form API (1/159; 201 depreciações Core/contrib e 2 PHPUnit).
  Concorrência entre processos segue pendente.

- `0.1.0-dev.65`: rotas home, busca e prévia agora declaram purpose `wiki`;
  Kernel confirma 404 em busca no domínio `main` via subscriber real (1/73).
  Lint/PHPCS passaram; Portal e Runtime não mudaram.

- `0.1.0-dev.64`: Kernel integra a reescrita da raiz pública Wiki com o caminho
  interno `/wiki` e sua política 404 (1/65; 199 depreciações Core/contrib e 2
  PHPUnit). PHP lint e PHPCS passaram; código do Portal não mudou.

- `0.1.0-dev.63`: ciclo de vida da imagem revalidado no Kernel completo em
  MariaDB isolada (21/941; 291 depreciações Core/contrib e 22 PHPUnit).
  Privacidade acompanha a publicação do verbete e compartilhamento; regressões
  de rollback, histórico e limpeza passaram. Runtime intocado.

- `0.1.0-dev.62`: rotas, listing, CTA e busca com grants foram revalidados em
  Kernel no MariaDB isolado (3/127; 283 depreciações Core/contrib/PHPUnit).
  Código de rota/acesso e Runtime não mudaram.

- `0.1.0-dev.61`: R1 revalidada após as mudanças de interface: Unit Wiki 66/341;
  três Kernels Index/Wiki em MariaDB isolada 10/343 (243 depreciações de
  Core/contrib/PHPUnit). Provider v4 e fingerprint permanecem inalterados;
  Runtime, ativação e rebuild continuam pendentes.

- `0.1.0-dev.60`: formulário de moderação no GanjaLab compõe cabeçalho,
  links de contexto e contribuição usando SDCs; decisões e campos permanecem
  em Form API. Kernel focal: 1/156; classe completa: 21/941, 291 depreciações.

- `0.1.0-dev.59`: o formulário GanjaLab preserva escolhas individuais de
  `list_modes`; Kernel usa FormBuilder e aplica proposta pelo caminho de
  decisão, inclusive modos append/replace (focal: 1/154; classe completa:
  21/939, 291 depreciações Core/contrib/PHPUnit).

- `0.1.0-dev.58`: `field_values` preserva a árvore no Form API. Kernel usa
  `FormBuilder` para processar o formulário, valida os valores e persiste o
  título selecionado na proposta (focal: 1 teste / 59 asserções; classe: 21 /
  935, com 291 depreciações Core/contrib/PHPUnit).

- `0.1.0-dev.57`: erros de revalidação backend no formulário de proposta são
  apresentados como erro do formulário, sem marcar incorretamente o resumo.
  Kernel focal: 1 teste / 150 asserções; PHPCS/PHP lint passaram.

- `0.1.0-dev.56`: status privado de proposta usa SDCs `context-header` e
  `content-section`; a ação de retirada fica no cabeçalho apenas para autoria
  e estados elegíveis. Focal Kernel: 1 teste / 148 asserções; PHPCS/PHP lint
  passaram. Validação visual ainda pendente.

- `0.1.0-dev.55`: revalidação R1 no HEAD atual: Unit dos providers Wiki e
  categoria (9/40) e três classes Kernel relacionadas ao Index (10/343)
  passaram em MariaDB isolada. O provider permanece v4; fingerprint/rebuild em
  Runtime e ativação do Index continuam pendentes.

- `0.1.0-dev.54`: Meu Trabalho da WE SATIVA compõe cabeçalho, cards, seções,
  grade e estados vazios com SDCs existentes do tema ACULTA420. A Wiki mantém
  autorização, Domain, cache e paginação. Kernel focal: 2 testes / 89
  asserções; homologação visual Firefox permanece pendente.

- `0.1.0-dev.53`: limpeza de imagens usa cursor expirável e lotes rotativos de
  25 itens, evitando que candidatos antigos ainda referenciados bloqueiem os
  seguintes. Kernel cobre retenção, reuso por Article, inspeção fail-closed e
  avanço além do primeiro lote (focal: 4/169; classe completa: 21/918, 291
  depreciações Core/contrib/PHPUnit); Runtime inalterado.

- `0.1.0-dev.52`: Kernel owner Wiki/Index cobre destino sem provider de
  Taxonomy e Media publicada anexada a origem em rascunho; a consulta outgoing
  não revela nem inventa destinos (7 testes / 238 asserções; 201 depreciações
  upstream). Sem mudança de projeção ou rebuild.

- `0.1.0-dev.51`: cron remove imagens de destaque órfãs após uma hora, em lotes
  de até 25 arquivos, usando os verificadores compartilhados do Portal para
  referências globais e históricas. Kernel MariaDB focal: 1 teste / 46
  asserções; sem limpeza ou alteração no Runtime.

- `0.1.0-dev.50`: o provider Wiki v4 não persiste `owner_uid`; a ausência foi
  verificada no provider unitário e na entrada real do Index. O fingerprint muda
  e exige uma geração nova antes da ativação. O gate genérico de backfill e
  ativação rejeita fingerprints antigos (2/16). Sem rebuild em Runtime.

- `0.1.0-dev.49`: o Kernel owner real atualiza o destino `related_entry`, remove
  a relação anterior e limpa saída ao excluir o verbete. Classe Index:
  7 testes / 234 asserções; projeção v3 mantida.

- `0.1.0-dev.48`: Kernel owner real cobre backfill, fingerprint da geração,
  atualização incremental do rótulo/hash e remoção após delete. Focal:
  1 teste / 34 asserções; projeção v3 mantida.

- `0.1.0-dev.47`: Meu Trabalho pagina verbetes e propostas separadamente com
  cursores keyset, acesso revalidado e links GET no domínio da conta. Cache
  varia pelas duas chaves. Kernel: 2 testes / 84 asserções.

- `0.1.0-dev.46`: Kernel real também prova que uma revisão não padrão pendente
  não substitui a revisão publicada projetada e que a falta do Domain Wiki nega
  a consulta. Classe: 6 testes / 185 asserções; 201 depreciações upstream.

- `0.1.0-dev.45`: Kernel do Index agora inicializa `aculta_wiki`, `aculta_image`,
  Portal e Index reais, sem ponte YAML de providers ou acesso. Discovery confirma
  owner único de `node:wiki_entry`, categoria e Media; consultas outgoing filtram
  origem/destinos e restauram Domain (4 testes / 125 asserções).

- `0.1.0-dev.44`: WE SATIVA mostra ação de edição somente após revalidar
  `update` do próprio verbete no purpose Wiki e envia o link ao Domain Wiki.
  Kernel confirma o formulário Core, preview privado via tempstore e que a
  prévia não cria revisão nem altera o rascunho (2 testes / 111 asserções).

- `0.1.0-dev.43`: acesso ao download das imagens privadas acompanha referências
  atuais de verbetes publicados. Revisões antigas preservam os arquivos sem
  deixá-los disponíveis após a remoção/despublicação; outro verbete publicado
  que compartilhe a Media mantém o download permitido. Kernel nos cenários de
  imagem: 8 testes / 237 asserções; Runtime não alterado.

- `0.1.0-dev.42`: o workflow mantém a gravação inicial no estado `draft` como
  escolha explícita do formulário Core. Kernel submete o Node como rascunho e
  depois cria revisão `review` por `draft_to_review`; publicação segue
  indisponível à autoria confirmada (focal: 1 teste / 54 asserções).

- `0.1.0-dev.41`: Kernel constrói o formulário Core de `wiki_entry` sob a conta
  confirmada, com o workflow real, campos, ação de salvar e escolha de revisão;
  a opção de publicação não aparece. Focal: 1 teste / 38 asserções. Submissão e
  persistência pelo Form API ainda pendentes.

- `0.1.0-dev.40`: o Kernel verifica acesso à rota nativa `node.add` para a
  conta confirmada, e o link “Criar verbete” do workspace conserva os
  parâmetros e o Domain Wiki quando a página está no contexto `main` (focal:
  1 teste / 41 asserções). O build e submit do formulário nativo ainda estão
  pendentes.

- `0.1.0-dev.39`: primeira entrada WE SATIVA em `/studio/we-sativa` sob
  purpose `account`. O Studio encaminha para `WikiContributorWorkspace`, que
  consulta até 20 verbetes e propostas próprios por UID, revalida acesso por entidade e
  expõe criação somente após `createAccess('wiki_entry')`. Kernel cobre rota,
  propósito, isolamento por autoria e retorno ao propósito original (focal:
  1 teste / 37 asserções; classe: 15 / 612; 286 depreciações Drupal/contrib e
  16 PHPUnit). Formulários
  de criação/edição e integração visual mobile ainda não estão concluídos.

- `0.1.0-dev.38`: Kernel executa o validador com `alt` vazio e confirma erro,
  nenhuma Media e File temporário (focal 1 / 27; classe 14 / 575 em MariaDB
  isolada). O submit completo pelo Form API segue pendente; Runtime não mudou.
- `0.1.0-dev.37`: inserção, atualização e exclusão sincronizam o status da
  Media de destaque com os Nodes publicados que a referenciam. Kernel cobre
  publicação, despublicação, exclusão do último Node e reuso (2 focais / 56;
  classe Kernel: 13 testes / 548 asserções em MariaDB isolada). Mesmo com Media
  privada, Core permitia acesso ao arquivo por revisão histórica publicada;
  esse acesso foi fechado pelo hook `file_download` em dev.43.
- `0.1.0-dev.36`: Kernel usa o Mail Confirmer real (`aculta_registration`),
  confirma a conta e verifica o papel `email_confirmed` e a permissão efetiva
  de criar `wiki_entry` via Entity Access. Focal: 1 teste / 33 asserções;
  classe Kernel: 11 testes / 492 asserções em MariaDB isolada. Runtime e
  configuração importada continuam sem homologação por este cenário.
- `0.1.0-dev.35`: Kernel comprova que aceite exige transições oficiais e não
  grava parcialmente em caso de conflito ou falta de acesso editorial. Focal:
  1 teste / 144 assertions; classe Kernel: 10 testes / 459 assertions,
  aprovada em MariaDB isolada; 286
  depreciações Core/contrib e 11 do PHPUnit.
- `0.1.0-dev.34`: Kernel cobre conflito de revisão-base e moderador sem
  permissão de editar o verbete (focal: 1 / 140; classe: 10 / 455).
- `0.1.0-dev.33`: retry de imagem após rebuild limpa caches e estado temporário
  revertido; Kernel confirma novo envio sem órfãs. Focal: 1 teste / 33
  asserções; classe Kernel: 10 testes / 447 asserções; Unit do uploader: 12
  testes / 79 asserções.
- Preflight read-only de 2026-10-23: Wiki ativa e Index desativado no Runtime de
  teste. Na URI `wiki420`, `/` entrega a home (200); `/wiki` responde 404
  intencionalmente por ser o caminho interno da raiz. Listagem, busca e prévia
  retornam 200. Runtime ainda não está homologado integralmente.
- `0.1.0-dev.32`: Kernel cobre `append`/`replace` em categorias e relações, com
  deduplicação e destinos visíveis no domínio Wiki. Focal: 1 teste / 132
  asserções; classe Kernel: 9 testes / 414 asserções. Runtime desabilitado.
- `0.1.0-dev.31`: Kernel prova `append` e `replace` para referências em propostas
  sucessivas; append deduplica URLs e replace remove valores anteriores.
  Focal: 1 teste / 122 asserções; classe Kernel: 9 testes / 404 asserções.
  Categorias/relações ainda precisam de casos Kernel específicos.
- `0.1.0-dev.30`: R2 reforça unicidade das rotas e acesso/purpose da listagem
  com Kernel real; busca confirma isolamento por grants e cache em entradas
  negativas, vazias e malformadas. Focal: 2 testes / 93 asserções.
- `0.1.0-dev.29`: propostas guardam valores estruturados nos campos editoriais
  permitidos. Na aceitação, a equipe escolhe os campos aprovados e, para cada
  lista de referências, categorias ou relações, escolhe substituir ou
  acrescentar. A aplicação exige revisão-base atual, acesso editorial e as
  transições oficiais do workflow até `published`; a atualização é transacional.
  Focal: 1 teste / 119 asserções. Classe Kernel: 9 testes / 393 asserções.
  Runtime desabilitado.
- `0.1.0-dev.28`: autoria pode retirar propostas enviadas ou devolvidas, com
  confirmação Form API, nova revisão privada e preservação do histórico.
  Kernel focal: 1 teste / 110 assertions; classe completa: 9 testes / 384
  assertions antes do dev.29.
- `0.1.0-dev.27`: Kernel usa uma segunda conexão MariaDB para testar lock
  concorrente do token, recusa sem duplicar e replay posterior. Focal: 1 teste /
  99 assertions; classe completa: 9 testes / 371 assertions. Processos PHP
  separados ainda não foram testados.
- `0.1.0-dev.26`: reenvios do mesmo formulário retornam a proposta criada por
  até 24 horas; lock e transação protegem a gravação. Kernel focal: 1 teste / 97
  assertions; concorrência multiprocesso segue pendente.
- `0.1.0-dev.25`: submissões limitadas a 10 por hora por conta via Flood Core;
  excedentes recebem HTTP 429 sem persistência. Kernel focal: 1 teste / 94
  assertions; classe completa antes da idempotência: 9 testes / 366 assertions.
- `0.1.0-dev.24`: o status privado apresenta histórico paginado de revisões
  autorizadas, com estado e retorno editorial; nomes de moderadores não são
  expostos. Kernel focal: 1 teste / 93 assertions.
- `0.1.0-dev.23`: após enviar, a autoria segue diretamente para a página privada
  da proposta recém-criada. Kernel focal do fluxo de submissão: 1 teste / 85
  assertions.
- `0.1.0-dev.22`: pessoas autoras podem abrir status e retorno da própria
  proposta em `/wiki/propostas/{proposal}`. Decisões de ajustes/recusa enfileiram
  um e-mail mínimo para contas ativas e confirmadas, com link canônico privado.
  Entity Access agora usa o parâmetro `{proposal}` nas duas rotas editoriais.
  Kernel completo: 9 testes / 355 assertions.
- `0.1.0-dev.21`: fila de revisões percorre candidatos em lotes limitados e
  mantém cursor ao filtrar itens sem acesso. Kernel prova que 26 revisões
  recentes sem grant não escondem a revisão autorizada anterior. Kernel MariaDB
  completo: 9 testes / 344 assertions.
- `0.1.0-dev.20`: rotas e acesso foram repetidos em Kernel com bootstrap real.
  O CTA de colaboração usa `createAccess('wiki_entry')` efetivo: conta
  confirmada com permissão pode abrir; conta sem confirmação segue negada.
  Suíte MariaDB: 9 testes / 342 assertions; Runtime segue desabilitado.
- `0.1.0-dev.19`: a `WikiTab` existente do GanjaLab consome as filas proprietárias
  da Wiki. Revisões mostram ação para o Form API nativo do Node; propostas abrem
  a decisão Wiki em contexto account. Acesso a cada seção é distinto, e
  moderador de proposta não recebe listagem genérica de verbetes. Runtime e
  revisão visual Firefox/X11 pendentes.
- `0.1.0-dev.18`: o backend e Form API registram “pedir ajustes” e “recusar”
  como novas revisões privadas, com nota editorial, checagem de conflito e lock
  contra decisões simultâneas. Kernel MariaDB completo: 8 testes / 301
  assertions, sem warnings (284 depreciações Drupal/contrib e 9 PHPUnit).
  Aceite/aplicação e integração Studio seguem pendentes até decidir como o
  conteúdo entra no Node.
- `0.1.0-dev.17`: propostas exigem e-mail confirmado por `aculta_portal`.
  Verbetes publicados exibem link contextual apenas a colaboradores confirmados
  sem permissão de edição direta; o render varia cache por conta, permissões e
  domínio. Kernel completo: 8 testes / 290 assertions, sem warnings (284
  depreciações Drupal/contrib e 9 PHPUnit); verifica a regra e a recusa de conta
  não confirmada.
- `0.1.0-dev.16`: pessoas com `submit wiki proposals` podem abrir o Form API em
  `/wiki/verbetes/{node}/contribuir`. A rota exige acesso de visualização ao
  verbete e o serviço revalida estado e VID-base ao salvar. A proposta continua
  privada e não é aplicada automaticamente. Kernel verifica rota e callback.
- `0.1.0-dev.15`: serviço `aculta_wiki.editorial_queue` consulta propostas
  pendentes e revisões atuais em `review`. A fila de Nodes revalida acesso de
  leitura, revisão e atualização e exige transição válida sob o purpose `wiki`;
  proposta usa permissão editorial e paginação por cursor. Kernel completo da
  classe: 8 testes / 274 assertions; módulo permanece desabilitado no Runtime.
- `0.1.0-dev.14`: existe persistência privada revisionável para propostas de
  terceiros, com revisão-base e serviço que valida acesso, publicação e
  purpose `wiki`. Nenhuma expiração
  automática foi criada; retenção para propostas encerradas aguarda decisão.
- `0.1.0-dev.13`: rotas públicas e View da listagem são descobertas pelo
  bootstrap Kernel real; o acesso à busca é caracterizado com grants diferentes
  entre contas da mesma função. Busca vazia/negativa varia por
  `user.node_grants:view`. O ciclo de imagem e os contratos do Index continuam
  conforme dev.12.
- `0.1.0-dev.12`: upload da imagem de destaque usa armazenamento privado e
  transação compartilhada com o salvamento do verbete; a mídia só é publicada
  quando a revisão submetida do verbete está publicada. O incremento dev.11
  mantém cache da busca por grants e o CTA usa acesso real de criação. O
  provider opcional do Content Index permanece na versão de projeção 3.
- Kernel MariaDB também cobre a remoção da imagem na revisão atual: a Media e o
  arquivo são preservados enquanto uma revisão antiga do verbete os referencia.
- `WikiLeadImageLifecycleKernelTest` inicializa `aculta_wiki` e verifica
  `/wiki`, `/wiki/busca`, `/wiki/busca/previa`, a View `/wiki/verbetes` e o
  requisito `access content`. A busca real filtra grants de contas diferentes
  da mesma função e mantém contexto de cache nos resultados vazios/negativos.
- No preflight read-only de 2026-10-23, `aculta_wiki` estava ativo e
  `aculta_index` desativado no Runtime de teste. A configuração exportada e o
  estado efetivo do Runtime continuam sendo verificados separadamente.
- O estado do Runtime varia por ambiente; não inferir habilitação pela
  existência de código ou configuração exportada.
- As rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa` estão definidas neste
  submódulo com os nomes `aculta_portal.wiki_*`; `/wiki/verbetes` permanece
  contrato da listagem. A disponibilidade no site depende da ativação efetiva
  do módulo no Runtime.

## Limites

- Domínio e purpose (`wiki420` → `/wiki`) continuam no Portal. Este módulo não
  cria purpose, host nem alias.
- O submódulo não depende de `aculta_statistics`. As métricas agregadas de
  publicação são produzidas pelo `WikiProvider` da estatística, que observa
  nodes do bundle `wiki_entry` sem importar classes da Wiki.
- Conteúdo sincronizado ou importado não gera evento de publicação.
- Configuração continua em `config/sync`, com UUIDs e IDs preservados. A
  propriedade de cada item é decisão pendente do responsável.
- `WikiContentIndexProvider` projeta título, UUID, bundle, idioma, domínio,
  publicação, datas e relações allowlisted de `field_wiki_related` (`related_entry`),
  `field_wiki_categories` (`wiki_category`) e `field_wiki_image` (`wiki_image`).
  `WikiCategoryContentIndexProvider` projeta apenas metadados mínimos para
  `taxonomy_term:wiki_category`; imagens usam o provider `media:image` já
  registrado por `aculta_image`. Nenhum provider copia corpo, descrição de
  termo, URL, arquivo ou binário. Os serviços só são descobertos pelas tags
  quando Index e owners estão ativos; não há dependência obrigatória entre
  `aculta_wiki` e `aculta_index`.
- Os testes Unit cobrem metadados allowlisted, revisão padrão, deduplicação das
  relações nomeadas, purpose `wiki` e a projeção mínima da categoria.
- `ContentIndexWikiProviderKernelTest` cobre backfill e CRUD com a classe real
  do provider (1 teste / 17 assertions); usa tag de fixture e não habilita o
  módulo Wiki nem valida Domain/grants reais. A declaração/tag owner e a
  consulta autorizada são cobertas separadamente pelo teste seguinte.
- `ContentIndexWikiAccessKernelTest` consulta `related_entry`, `wiki_category`
  e `wiki_image` com providers dos YAMLs owners Wiki e Image, dados reais Node,
  Term e Media e a policy Portal via fixture. Entity Access filtra categoria e
  imagem não publicadas; a consulta outgoing revalida origem/alvos em purpose
  `wiki` e restaura `main` (MariaDB: 3 testes / 84 assertions, 5 depreciações).
- Em dev.166, `ContentIndexOwnerIntegrationKernelTest` com a closure real Wiki +
  Portal + Index testa projeção/deduplicação e `ContentIndexAccessFilter` com
  hooks e serviços reais, sem bridge de acesso. Acesso é negado em `main`,
  revalidado em `wiki`, alvos não publicados são filtrados e o Domain original
  é restaurado (1 teste / 34 assertions; 231 depreciações).
- Reexecução MariaDB isolada em 2026-10-23 dos três Kernel Wiki/Index: 6 testes /
  181 assertions, aprovados; 243 depreciações agregadas de Core/contrib/PHPUnit.
  A consulta outgoing end-to-end continua coberta pelo harness de acesso com
  fixture; não houve rebuild integral do inventário nem alteração no Runtime.

### Handoff para o ACULTA Content Index

O contrato Wiki está em `WikiContentIndexProvider` v4 (categoria v1): `node:wiki_entry`,
revisão padrão, purpose `wiki`, metadados allowlisted e relações
`related_entry` → `node:wiki_entry`, `wiki_category` →
`taxonomy_term:wiki_category` e `wiki_image` → `media:image`. O provider dos
termos pertence à Wiki; o de Media pertence a `aculta_image`. Destinos seguem
sujeitos a Entity Access na consulta do Index. Alteração semântica futura exige
incremento da versão e rebuild de geração; esta entrega não dispara rebuild.
Termos/categorias e imagens não devem bloquear infraestrutura genérica ou
outros providers do Index. O Kernel owner agora consulta as três relações
outgoing por `ContentIndexRelationQuery` real no bootstrap Wiki + Portal + Index;
a geração é semeada apenas com as identidades relevantes e não valida rebuild
integral do inventário. A projeção não inclui corpo, revisão pendente, UID autoral,
descrição, URL ou arquivo. Homologação Runtime continua separada.

## Documentação

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — fases e critérios de saída.
- [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md) — cobertura planejada.
- [`CHANGELOG.md`](CHANGELOG.md) — mudanças por versão.
- [`AGENTS.md`](AGENTS.md) — regras locais para agentes.


## Frente planejada: WE SATIVA

[Fundação do WE SATIVA](docs/WE-SATIVA-FOUNDATION.md): experiência editorial própria da Wiki420 com contribuição contextual a partir do verbete, fluxos progressivos mobile-first, Drupal Form API/revisões e autorização por capacidade, independente do GanjaLab/GanjaLab420. A fundação é documental, não altera rotas ou permissões. Autosave Form fica somente para consideração futura.
