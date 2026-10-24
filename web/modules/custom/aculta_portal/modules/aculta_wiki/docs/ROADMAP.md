# Roadmap — ACULTA Wiki

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md).
Fechar escopo, testes, gates e revisão visual aplicável antes de RC. Deploy em
produção só quando todos os módulos, temas e subtemas estiverem em RC.

Linha do projeto `aculta_wiki`: **0.1.0-dev.90**. Em preflight read-only de
2026-10-24, `aculta_wiki` estava ativo e `aculta_index` desativado no Runtime de
teste. A raiz `/` respondeu 200 no domínio Wiki; `/wiki` respondeu 404
intencionalmente por ser o caminho interno da raiz. Runtime segue sem
homologação integral.
O plano aprovado e o inventário de referências ficam em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../../docs/modules/WIKI-SUBMODULO.md).

### Incremento dev.90 — grants em Alterações recentes

O Kernel de busca agora caracteriza também a lista de alterações recentes:
contas de mesmo papel com grants distintos veem apenas seus verbetes, e o
fragmento tem cache context `user.node_grants:view`. Focal MariaDB passou com
1 teste / 43 asserções (283 depreciações upstream, 2 PHPUnit); Runtime intocado.

### Revalidação WIKI-R1 no HEAD `5504aced2`

Sem mudança de código ou contrato: os dois providers Unit e quatro Kernels
Wiki-owned/Index passaram em 20 testes / 425 asserções. A projeção continua v4
com fingerprint estável; PHPUnit reportou 243 depreciações upstream e 3 PHPUnit.
Os testes usaram MariaDB isolada; `aculta_index` não foi ativado nem reconstruído
no Runtime.

### Incremento dev.89 — seletor contextual WE SATIVA

O CTA dos verbetes públicos aponta para um seletor com destinos funcionais:
edição Core apenas quando Entity Access autoriza `update`, e as quatro
intenções allowlisted de proposta para participantes confirmados sem edição.
A rota revalida entidade, revisão padrão, publicação, Domain Wiki e `view`; os
links de proposta mantêm o formulário e backend existentes. Kernel focal
passou com 3 testes / 311 asserções, PHPCS/lint passaram. Runtime e navegador
não foram executados; a integração visual permanece pendente.

### Incremento dev.88 — filtros de estado no Meu Trabalho WE SATIVA

O workspace agora expõe os filtros GET `Em andamento`, `Em revisão`,
`Publicados` e `Todos` para os verbetes próprios. Como `moderation_state` não é
campo consultável diretamente pela Entity Query de Node, o serviço seleciona
candidatos pelo estado de publicação, lê workflow state na entidade carregada e
limita cada varredura a 500 Nodes. Cursor de estado é keyset por `changed`/ID;
cursor de proposta permanece separado e é preservado ao alternar filtros. Links
usam Domain `account`, resetam o cursor de verbetes e anunciam o filtro ativo.
Kernel focal passou (1/141), incluindo paginação de 20+3 verbetes sem perda ou
duplicação; os três Kernels do workspace passaram (3/235).
PHPCS/lint passaram; Runtime intocado.

### Revalidação no HEAD `d3bfd3715` — Content Index e ciclo de imagem

Sem alteração de código: providers Unit Wiki/categoria passaram (9/40), e os
quatro Kernels MariaDB de R1 passaram (11/385), mantendo projeção v4 e
fingerprint. `WikiLeadImageLifecycleKernelTest` passou com 25 testes / 1.187
asserções; foram reportadas 291 depreciações Core/contrib e 26 PHPUnit. O aviso
de diretório BrowserTest não gravável não impediu os Kernels. Nenhum rebuild,
configuração ou alteração Runtime foi executado.

### Incremento dev.87 — evidência de publicação por contexto

O adapter Wiki agora implementa o contrato do `aculta_user` e consulta apenas
`wiki_entry` publicada pelo autor no Domain Wiki ativo. A revisão padrão permanece
como evidência quando há revisão pendente não padrão; outros Domains, UID e
rascunhos não contam. Cache é por usuário quando o UID consultado é a conta
atual; consulta cross-account usa `max-age: 0`. Kernel focal 1/45, User Unit
123/439, User characterization Kernel 9/129 e Fórum Kernel 1/39 passaram.
Kernel: 200 depreciações e 2 PHPUnit. PHPCS passou; Runtime intocado. A evidência ainda não foi ligada a permissões
ou transições.

### Incremento dev.86 — revalidar R2 de rotas e acesso

No HEAD `8896527e7`, três Kernels MariaDB revalidaram rotas únicas, propósito
Wiki/Domain, processamento da raiz pública, CTA conforme `createAccess()` e
cache de busca para usuários com grants diferentes (incluindo vazio, negativo e
parâmetro malformado): 3 testes / 141 asserções. Runtime intocado.

### Incremento dev.85 — falha ao salvar Media

O uploader agora tem uma regressão Unit específica para a exceção durante
`Media::save()`, após o File tornar-se permanente. O teste comprova rollback da
transação e reset dos caches/estado do formulário. A suíte Wiki Unit passou
(67/360; 3 depreciações PHPUnit); o Kernel completo de ciclo de imagem passou
(24/1.142; 291 depreciações Core/contrib e 25 PHPUnit). PHPCS e PHP lint
passaram. Runtime intocado.

### Incremento dev.84 — revalidar WIKI-R1 no HEAD atual

No HEAD `bea9cc4c3`, os Unit dos providers Wiki/categoria passaram (9/40) e
quatro Kernels MariaDB passaram: provider, acesso/relações, bootstrap owner e
rebuild Wiki-owned (11/385; 243 depreciações Core/contrib/PHPUnit). A projeção
v4 e fingerprint não mudaram. A geração foi reconstruída apenas no banco
isolado de teste; Runtime, ativação de `aculta_index` e rebuild instalado não
foram executados.

Integração GanjaLab/Studio dev.4: a aba Wiki agora exige o módulo owner ativo
além do bundle `wiki_entry` existir; Unit focal do Studio passou 3/10 e a suíte
Unit completa 13/41. A alteração
evita UI órfã quando a configuração do bundle permanece após desativar a Wiki.

### Incremento dev.83 — Entity Access da proposta no workspace

Além de filtrar candidatos por UID, o Kernel cobre revalidação de acesso por
proposta: conta que pode abrir WE SATIVA via `submit wiki proposals`, mas não
tem `view own wiki proposals`, possui uma proposta candidata e recebe estado
vazio sem links ou card. Focal 1/45; classe Kernel completa 24/1.142, com 291
depreciações Core/contrib e 25 PHPUnit. PHPCS passou; Runtime intocado.

### Incremento dev.82 — isolamento de propostas no WE SATIVA

O Kernel da experiência agora semeia uma proposta de outro autor junto das 21
propostas próprias. As páginas permanecem 20+1 e os IDs renderizados são
exatamente os próprios, sem incluir a proposta de terceiro. Focal 1/42; classe
Kernel completa 24/1.139 (291 depreciações Core/contrib e 25 PHPUnit); PHPCS
passou. O cenário testa isolamento por autoria, não a revisão de Entity Access
negativa sobre candidatos do mesmo UID. Runtime intocado.

### Incremento dev.81 — imagem em aceite editorial

Kernel MariaDB cobre proposta de Media publicada, seleção do campo de imagem
no Form API e aplicação na revisão publicada por transições oficiais; Entity
Access da mídia é revalidado pelo serviço de decisão. A classe passou (24/1.137;
291 depreciações Core/contrib e 25 PHPUnit) e PHPCS passou. A proposta usa Media
existente e publicada; Runtime intocado.

Revisão da matriz WE SATIVA: `testWeSativaWorkspacePaginatesOwnerEntriesAndProposals`
cobre 21 propostas próprias, paginação independente 20+1 e links no Domain
`account` (Kernel MariaDB focal 1/40). Esse cenário não cobre itens de terceiros.

### Incremento dev.80 — lock de proposta entre processos

Kernel MariaDB executa worker PHP separado para adquirir a chave de idempotência
e confirma que outra chamada do serviço real não persiste proposta duplicada
enquanto o lock existe; após liberar, replay retorna a entidade já criada. A
classe Kernel passou (24/1.134; 291 depreciações Core/contrib e 25 PHPUnit) e
PHPCS passou. O harness Kernel injeta `NullLockBackend`, então o teste constrói
`DatabaseLockBackend` explicitamente; bootstrap do lock no Runtime segue sem
homologação. Nenhuma mudança Runtime.

### Incremento dev.79 — imagem em revisão pendente

Kernel MariaDB agora comprova que salvar revisão não padrão com imagem
substituta não muda a projeção pública da revisão padrão: imagem e arquivo
anteriores permanecem acessíveis, enquanto a substituta permanece não publicada
e sem download anônimo. A classe completa passou (24/1.129; 291 depreciações
Core/contrib e 25 PHPUnit); lint e PHPCS passaram. Runtime intocado.

### Incremento dev.78 — fixtures Unit do hook de imagem

As fixtures de `WikiEntryImageHooksTest` agora refletem as quatro dependências
do hook e passam a fornecer um formulário associado a `wiki_entry`, necessário
para executar a alteração real. A suíte Unit completa passou: 66 testes / 344
asserções, 3 depreciações PHPUnit. Nenhum comportamento Runtime foi alterado.

### Incremento dev.77 — retorno do formulário à experiência WE SATIVA

Os formulários Core de criação/edição Wiki agora incluem retorno a `/studio/we-sativa`
quando a rota opcional existe e `WikiContributorWorkspace::access()` permite a
experiência. A URL é gerada no propósito `account`; contexts e tags do resultado
de acesso são aplicados ao formulário. Sem Studio ou sem Domain account, a Wiki
mantém o formulário e omite o link. Kernel focal de autoria: 1/70; a classe
MariaDB passou em 23/1.039 (291 depreciações Core/contrib e 24 PHPUnit). Runtime
permanece intocado.

Revalidação WIKI-R1 no mesmo HEAD: Unit dos providers Wiki/categoria 9/40;
quatro Kernels MariaDB (provider, acesso/relações, bootstrap owner real e
rebuild de geração Wiki-owned) passaram com 11 testes / 385 assertions e 243
depreciações Core/contrib/PHPUnit. O bootstrap descobriu a projeção v4 e os
destinos `taxonomy_term:wiki_category` e `media:image`; o rebuild ocorreu apenas
no banco isolado de teste. Fingerprint e contrato não mudaram. Nenhuma ativação
ou reconstrução no Runtime foi executada.

Revalidação R2 no HEAD: os Kernels de rotas, CTA e grants passaram em MariaDB
(3 testes / 141 assertions; 283 depreciações Core/contrib e 4 PHPUnit). No host
`toca`, Drush confirmou Drupal 11.4.10, URI Wiki e banco `aculta_runtime`; Wiki
ativa e Index desativado. GET read-only retornou `/` 200, `/wiki` 404 e
listagem/busca/prévia 200. Nenhuma configuração, cache ou conteúdo foi alterado.

O smoke WE SATIVA do Firefox ESR foi adicionado em
`tests/browser/aculta-wiki/we-sativa.py`, mas a execução no host de teste não
alcançou o workspace: Drush confirma `aculta_wiki` e `aculta_studio` habilitados,
enquanto o container ativo não registra a rota `aculta_studio.we_sativa`, o
provider `we_sativa` nem `aculta_wiki.contributor_workspace`. Resultado: 0/2
checks. É necessário reconstruir o container Drupal para validar o código atual;
isso continua pendente de autorização. Nenhuma alteração no Runtime foi feita.

Na fila editorial de revisões, o Kernel agora cria 26 candidatos sem grant
mais 26 revisões autorizadas. A consulta retorna 25+1 em cursores estáveis,
sem duplicação ou perda, e valida que cada item é a revisão atual. MariaDB
focal: 1 teste / 101 assertions, 198 depreciações Core/contrib e 2 PHPUnit.

### Incremento dev.76 — envio real da imagem no Form API

O Kernel agora submete um verbete de autoria confirmada pelo formulário nativo
do Node com `managed_file`, alt text e estado `draft`. A Media salva permanece
despublicada e o arquivo `private://` não permite download anônimo. O fluxo
revelou que o uploader lia apenas a lista simplificada de FIDs; agora também
normaliza a estrutura processada nativa `fids` sem mudar o contrato dos callers
diretos. A classe Kernel completa passou (23 testes / 1.036 assertions; 291
depreciações Core/contrib e 24 PHPUnit), assim como lint e PHPCS. Não houve
alteração do Runtime.

### Incremento dev.75 — revalidar WIKI-R1 no HEAD

Os Unit dos providers Wiki/categoria passaram (9 testes / 40 assertions). Os
três Kernels MariaDB do provider, acesso/relações e bootstrap owner real
passaram em conjunto (10 testes / 343 assertions; 240 depreciações do ambiente
Core/contrib/PHPUnit). A projeção v4 e os contratos não mudaram. O Runtime segue
com `aculta_index` desativado conforme o último preflight documentado; ativação,
auditoria do inventário instalado e rebuild permanecem como gate separado e não
foram executados.

### Incremento dev.74 — semântica e teclado da prévia

A prévia assíncrona é uma região `aria-live=polite` nomeada, controlada pelo
searchbox nativo; seus itens são links que continuam na sequência Tab. O JS não
atribui `role=combobox`/`aria-autocomplete` nem captura setas de edição. A Unit
do builder passou (4/18); lint PHP/JS passou. A fixture do Firefox com o JS local
passou 14/14. No Runtime anterior à mudança, a integração fez 28/35 e encontrou
role combobox incorreto, região sem nome e submit fora da viewport; o novo
markup/script não foram publicados. Referência
de padrão: [WAI-ARIA APG Combobox](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/).

### Incremento dev.73 — nome acessível do browser

O smoke de teclado usa o comando WebDriver `computedlabel` do Firefox para o
campo de busca e submit. No host público de teste, retornou “Pesquisar na
Wiki420” e “Buscar”. Isso verifica nomes computados pelo browser, não substitui
avaliação por leitor de tela. Folha candidata: 22/22; agregado Runtime: 18/20
por submit fora da viewport.

### Incremento dev.72 — foco e nome acessível no smoke

O smoke Firefox compara outline e box-shadow dos elementos focados com cópias
sem foco, para não contar uma sombra normal como indicador de foco. Também
exige nomes acessíveis para o campo e o submit e grava ambos no resumo. No
Runtime agregado, somente o submit falha o limite horizontal (18/20); com a
folha candidata por GET, 22/22 passaram. O teste não avalia leitores de tela,
contraste nem homologação do agregado publicado.

### Incremento dev.71 — teclado e foco da busca

`tests/browser/aculta-wiki/keyboard.py` percorre a busca com Tab no Firefox ESR
visível e verifica foco visível e campo/submit dentro da viewport, em 1280 e
320 px. No CSS agregado do Runtime, o submit ultrapassa a borda (x=1282 em
1280 px; x=336 em 320 px), portanto o smoke falha 18/20. Carregando a folha
candidata atual por GET no navegador, passa 22/22 e o submit fica em x=238–304
em 320 px. O tema/agregado do Runtime não foi alterado; homologação publicada,
Olivero e WCAG completa seguem pendentes.

### Incremento dev.70 — revisão concorrente no editor próprio

O Form API de edição do Node Wiki declara a revisão-base aberta. A validação
rejeita revisões obsoletas; no submit, Wiki abre transação, bloqueia a linha
principal com `FOR UPDATE` e verifica novamente antes de delegar o save ao
formulário Core. Isso serializa a edição com a decisão de proposta, que já usa
o mesmo bloqueio de linha. Kernel real cobre conflito antes do submit e uma
corrida após a validação, sem gravar o conteúdo obsoleto (1/37); a criação e a
transição `draft` → `review` passaram (1/67). Unit do uploader: 12/79.
Regressão completa da classe Kernel de mídia: 22 testes / 995 asserções, 291
depreciações Core/contrib e 23 PHPUnit. PHPCS e PHP lint passaram.
Homologação visual/autenticada e concorrência em processos HTTP separados ainda
permanecem como cobertura futura.

### Incremento dev.69 — geração Wiki v4 no Kernel owner

O teste Wiki-owned `WikiContentIndexRebuildKernelTest` inicia o container com
Wiki, Image, Portal e Index reais, descobre os providers por tag e executa o
rebuilder de produção sobre todo o conteúdo semeado. Confirma fingerprint v4,
entrada sem `owner_uid`, projeção de metadados e relações deduplicadas de
verbete/categoria/imagem; valida e ativa a geração isolada. MariaDB: 1 teste /
42 asserções, 201 depreciações Core/contrib; PHP lint e PHPCS passaram. O teste
não carrega a configuração exportada completa nem representa o inventário do
Runtime, cuja reconstrução permanece fora do escopo sem autorização operacional.

### Incremento dev.68 — revalidar Content Index R1

No HEAD atual passaram Unit Wiki dos providers (9 testes / 40 asserções, 3
depreciações PHPUnit) e os Kernels reais do Index: provider (1/17), acesso e
relações (7/238, 201 depreciações) e owner/bootstrap (2/88, 243 depreciações),
em MariaDB dedicada. O provider v4 e o fingerprint não mudaram. O Kernel owner
semeia apenas as identidades relevantes; não comprova rebuild integral do
inventário. Index permanece desativado no Runtime de teste; nenhum rebuild ou
operação persistente foi executado nele.

### Incremento dev.67 — reflow visual da Wiki

A suíte reutilizável `tests/browser/aculta-wiki/visual.py` abriu no Firefox ESR
visível e conferiu `/`, `/wiki/verbetes`, `/wiki/busca` e 404 de `/wiki` no host
de teste. Antes da correção, mediu o submit da busca fora da página: o
`.form-control` de largura 100% também ocupava um item flex próprio. O tema
`aculta420` 0.6.1-dev.5 define `width: auto`; a folha foi carregada por GET no
browser sem mexer no agregado do servidor. Resultado: 46/46, sem overflow a
1280/390/320 CSS px, main/H1 presentes e sem erros visíveis. Runtime agregado,
Olivero e WCAG completa não foram homologados.

### Incremento dev.66 — limites do backend de propostas

A cobertura Kernel do serviço/Form API confirma nota de decisão com limite
máximo de 5.000 caracteres, tentativa de decidir proposta retirada sem reabrir
seu estado, conflito por revisão-base obsoleta e aceitação de subconjunto de
campos com modos de lista individuais e transição oficial do workflow. Focal:
1 teste / 159 asserções, com 201 depreciações Core/contrib e 2 PHPUnit; PHPCS e
PHP lint passaram. Concorrência multiprocesso permanece pendente.

### Incremento dev.65 — ownership de domínio nas rotas públicas

As rotas `/wiki`, `/wiki/busca` e `/wiki/busca/previa` agora declaram purpose
`wiki`. A omissão anterior permitia que o subscriber compartilhado não
rejeitasse esses caminhos no domínio principal. Kernel chama o
`DomainPurposeRequestSubscriber` real e confirma resposta 404 para busca Wiki
em `main`, mantendo a raiz pública e o caminho interno da Wiki (1 teste / 73
asserções). PHPCS/lint passaram; Portal e Runtime não mudaram.

### Incremento dev.64 — caminho público da Wiki em Kernel

O teste de discovery das rotas agora exercita o `PurposeFrontPathProcessor`
real: no Domain Wiki, `/` reescreve para `/wiki` e marca a requisição como
servida pela raiz; uma requisição direta `/wiki` não recebe o marcador e a
`FrontPathPolicy` a bloqueia. Focal MariaDB: 1 teste / 65 asserções, com 199
depreciações Core/contrib e 2 PHPUnit. Lint e PHPCS passaram. Nenhum código do
Portal ou Runtime mudou.

### Incremento dev.63 — regressão do ciclo de imagem

A classe Kernel `WikiLeadImageLifecycleKernelTest` passou no MariaDB dedicado:
21 testes / 941 asserções, com 291 depreciações Core/contrib e 22 PHPUnit.
Confirma Media privada para verbete não publicado, publicação/revogação conforme
o estado e as referências públicas do Node, compartilhamento, referências de
revisão histórica, rollback, retry e limpeza de órfãs. Runtime não foi alterado.

### Incremento dev.62 — regressão Kernel de rotas e acesso

No HEAD atual, passaram os cenários Kernel de rotas públicas/listagem, CTA da
home e busca por usuários com o mesmo papel e grants distintos (3 testes / 127
asserções; 283 depreciações Core/contrib/PHPUnit). O contrato já está coberto
por controller/Entity Access/cacheability e não demandou mudança no código.
Runtime permanece intocado; a validação HTTP documentada é read-only e a
homologação integral continua pendente.

### Incremento dev.61 — regressão R1 no HEAD atual

Após as mudanças da WE SATIVA/GanjaLab, a suíte Unit Wiki passou (66 testes /
341 asserções; 3 depreciações PHPUnit). Os Kernels
`ContentIndexWikiProviderKernelTest`, `ContentIndexWikiAccessKernelTest` e
`ContentIndexOwnerIntegrationKernelTest` passaram no MariaDB dedicado (10 /
343; 243 depreciações Core/contrib/PHPUnit), confirmando discovery e consulta
real das relações com acesso revalidado. Provider v4 e fingerprint não mudaram.
O Index segue desativado no Runtime de teste; não houve ativação nem rebuild.
Próximo gate R1/R2: planejar homologação segura da integração e então retomar
rotas/acesso em Kernel conforme a prioridade indicada.

### Incremento dev.60 — decisão editorial no GanjaLab

A tela de moderação compõe o contexto da proposta com SDCs existentes: cabeçalho
com links autorizados ao verbete/revisão-base e seção para resumo, campos
propostos, justificativa e fontes. Campos de decisão continuam no Form API e
Wiki mantém seu backend/acesso. Kernel focal: 1/156; classe completa: 21/941,
291 depreciações Core/contrib/PHPUnit. PHPCS/PHP lint passaram. Validação visual
e Runtime permanecem pendentes.

### Incremento dev.59 — escolhas de lista no formulário GanjaLab

O campo `list_modes` não preservava os valores do formulário sem `#tree`; ao
aceitar uma proposta, o serviço não recebia a escolha por lista de substituir
ou acrescentar. Agora o Kernel constrói e valida o Form API real, aplica a
proposta e comprova modos distintos em referências, categorias e relações
(focal: 1 teste / 154 asserções; classe completa: 21 testes / 939 asserções,
291 depreciações Core/contrib/PHPUnit). PHPCS/PHP lint passaram.

### Incremento dev.58 — persistência dos campos da proposta pelo Form API

O container `field_values` precisava preservar a árvore dos valores. Sem
`#tree`, os campos processados não coincidiam com a estrutura lida por
`getProposedFields()`. A correção foi validada construindo o formulário com o
`FormBuilder`, executando validação e submit e conferindo o título salvo na
proposta (focal Kernel: 1 teste / 59 asserções). Runtime não alterado.
Classe Kernel completa: 21 testes / 935 asserções, com 291 depreciações
Core/contrib/PHPUnit.

### Incremento dev.57 — erro de validação no formulário de proposta

Falhas detectadas pelo serviço durante a revalidação do conteúdo submetido são
apresentadas no formulário como um todo, sem associá-las falsamente ao resumo.
Kernel cobre tentativa com Media inexistente e confirma que a proposta não é
persistida (focal: 1 teste / 150 asserções). PHPCS e PHP lint passaram.

### Incremento dev.56 — status de proposta na WE SATIVA

A tela privada usa os SDCs existentes `context-header` e `content-section`.
Dados da proposta e histórico continuam protegidos pelas permissões de entidade
e de revisão; a retirada é apresentada no cabeçalho somente à autoria e nos
estados permitidos. Kernel focal: 1 teste / 148 asserções; PHPCS e PHP lint
passaram. Revisão visual Firefox/Olivero e homologação Runtime seguem pendentes.

### Incremento dev.55 — revalidar R1 no HEAD atual

Os Unit dos providers Wiki e categoria passaram (9 testes / 40 asserções). As
classes Kernel `ContentIndexWikiProviderKernelTest`,
`ContentIndexWikiAccessKernelTest` e `ContentIndexOwnerIntegrationKernelTest`
passaram em MariaDB isolada (10 testes / 343 asserções; 243 depreciações de
Core/contrib/PHPUnit). Incluem discovery de tags e bootstrap real da closure
Wiki + Portal + Index. Provider segue v4; sem alteração semântica ou rebuild.
O Index permanece desativado no Runtime de teste, que não foi alterado.

### Incremento dev.54 — composição SDC do Meu Trabalho

`WikiContributorWorkspace` monta a área da pessoa colaboradora com os SDCs
existentes `context-header`, `content-section`, `content-grid`, `card` e
`empty-state`. Consulta, autorização, purpose/Domain e paginação continuam no
serviço Wiki. Kernel focal: 2 testes / 89 asserções; classe completa MariaDB:
21 testes / 923 asserções, com 291 depreciações upstream/PHPUnit. PHPCS e PHP
lint passaram. Falta validar visualmente com Firefox ESR em ACULTA420 e Olivero;
Runtime não foi alterado.

### Incremento dev.53 — lotes rotativos para a limpeza de imagens

O cursor persistente usa `keyvalue.expirable` com TTL de sete dias. Cada cron
inspeciona até 25 Files antigos em ordem de ID, completando o lote ao retornar
ao início; arquivos em uso ou com inspeção indisponível não impedem o progresso
pelos demais. Kernel cobre referência externa via Article, erro da inspeção,
retenção de uma hora e passagem ao 26º candidato (focal: 4 testes / 169
asserções; classe completa: 21 / 918, com 291 depreciações upstream/PHPUnit).
Próximo gate: homologar apenas em Runtime controlado/autorizado.

### Incremento dev.52 — regressões dos limites de acesso do Index

O Kernel owner real acrescenta dois limites: uma categoria fora do vocabulário
com provider Wiki fica fora da geração e da resposta outgoing; Media publicada
ligada a um verbete em `draft` não contorna a autorização da origem. Classe
Wiki/Index MariaDB: 7 testes / 238 asserções, 201 depreciações upstream.
Provider/fingerprint não mudaram; nenhum rebuild Runtime. Próximo gate continua
sendo a homologação controlada do inventário completo quando autorizada.

### Incremento dev.51 — limpeza segura de imagem órfã

O hook de cron da Wiki processa no máximo 25 Files permanentes antigos sob
`private://wiki/imagens/`, após retenção de uma hora. Chama os serviços
compartilhados do Portal para excluir Media e File somente após inspeção global
de referências; falhas e referências de revisões antigas retêm os dados. Kernel
MariaDB focal: 1 teste / 46 asserções, incluindo a remoção após exclusão da
última origem. Sem alteração do Runtime. Próximo gate: ampliar cenários de
limpeza (múltiplas referências, falha de inspeção e limite de lote) e executar a
classe Kernel completa.

### Incremento dev.50 — excluir UID autoral do provider Wiki

O provider `node:wiki_entry` deixa de preencher `owner_uid`; a coluna interna do
Index não deve armazenar identidade autoral que a Wiki não usa. A versão sobe a
4 e o fingerprint muda. Antes de ativar uma projeção v4, é necessária uma nova
geração em ambiente controlado. Unit: 9/40; Kernel Wiki/Index: 7/236;
bootstrap owner: 1/42; focal de persistência: 1/38. Gates genéricos de drift no
backfill/ativação: 2/16. Nenhum rebuild no Runtime.

### Incremento dev.49 — sincronizar relação em update/delete incremental

No bootstrap real Wiki + Image + Portal + Index, alterar `field_wiki_related`
troca o destino projetado, e excluir a origem limpa suas relações outgoing.
Classe Kernel MariaDB: 7 testes / 234 asserções; 201 depreciações upstream.
Projeção v3 preservada; nenhum rebuild ou Runtime.

### Incremento dev.48 — ciclo incremental real do Content Index

No Kernel com bootstrap real Wiki + Image + Portal + Index, backfill confirma
fingerprint do registry; edição atualiza projeção e hash; exclusão remove a
projeção ativa. Focal: 1 teste / 34 asserções e 200 depreciações upstream.
Projeção v3 mantida; não houve rebuild ou alteração no Runtime.

### Incremento dev.47 — cursor e paginação da WE SATIVA

Meu Trabalho pagina verbetes e propostas de forma independente por `changed`
e ID, mantém acesso revalidado por item, usa links GET no Domain da conta e
varia cache pelos cursores. Kernel focal combinado: 2 testes / 84 asserções,
200 depreciações Drupal/Core/contrib e 3 PHPUnit. Sem alteração no Runtime.

### Incremento dev.46 — revisão pendente e ausência de Domain

O provider real recusa uma revisão não padrão pendente enquanto a revisão padrão
publicada continua projetável. A camada de acesso do Index também falha fechada
sem o Domain canônico Wiki. Classe Kernel MariaDB: 6 testes / 185 asserções,
201 depreciações Drupal/Core/contrib. Projection v3 preservada; nenhum rebuild
ou Runtime.

### Incremento dev.45 — bootstrap real para Content Index

`ContentIndexWikiAccessKernelTest` carrega a closure real de Wiki, Image,
Portal e Index, compila o container sem fixture de provider ou ponte de acesso,
e consulta `related_entry`, `wiki_category` e `wiki_image` pela API outgoing.
Permissões distintas filtram destinos, e o Domain de entrada é restaurado.
MariaDB: 4 testes / 125 asserções; 201 depreciações Drupal/Core/contrib.
Projection v3 segue compatível; não houve rebuild, alteração de Runtime ou
prova de reconstrução integral do inventário.

### Incremento dev.44 — edição própria e prévia na WE SATIVA

Meu Trabalho mostra a rota Core de edição apenas quando `Node::access('update')`
autoriza a conta para aquele verbete no purpose `wiki`; URL é montada no Domain
Wiki e o resultado de acesso alimenta cacheability. Kernel confirma o formulário
Core e o handler de prévia do Node: conteúdo temporário no tempstore, preview
permitido à autora, negado a outra conta, sem alterar a revisão persistida.
Focal combinado: 2 testes / 111 asserções. A execução programática cobre o
handler de prévia; não equivale a uma requisição HTTP/browser completa.

### Incremento dev.43 — download da imagem conforme publicação atual

O hook de download privado nega imagens sem referência de destaque em um
verbete publicado na revisão padrão. A atualização que troca/remove a imagem
reconcilia a Media anterior, preservando referências compartilhadas atuais.
Revisões antigas continuam mantendo o arquivo no armazenamento, mas não
autorizam download público. O subconjunto Kernel de imagem passou em 8 testes /
237 asserções. Não houve limpeza física nem alteração do Runtime.

### Incremento dev.42 — persistir rascunho e enviar revisão

O workflow Core aceita `create_new_draft` a partir de `draft`, tornando o
estado inicial selecionável no Node Form API. Kernel usa o FormBuilder real
para salvar um verbete não publicado em `draft` e depois gravar nova revisão
em `review` pela transição `draft_to_review`; a autoria não pode publicar.
Focal: 1 teste / 54 asserções. Config Sync foi atualizada, sem importação ou
alteração de Runtime.

### Incremento dev.41 — construir o formulário nativo de verbete

Kernel inicializa o workflow `wiki_editorial` real e constrói o formulário
Core `node.wiki_entry` para uma conta confirmada. A ação de salvar aparece;
`draft_to_review` é autorizada e o widget não oferece publicação. Focal:
1 teste / 38 asserções. A submissão pelo Form API e a persistência de um
rascunho/revisão continuam pendentes.

### Incremento dev.40 — validar rota de criação no domínio Wiki

Kernel comprova que a rota Core `node.add` autoriza a conta confirmada com
`create wiki_entry content`. O workspace Studio cria o link com parâmetros
`node_type=wiki_entry` e Domain `wiki420_aculta_org`, mesmo quando o Domain
ativo da consulta é `main`. Focal: 1 teste / 41 asserções. O formulário Node
nativo ainda precisa de teste Kernel de construção e submit.

### Incremento dev.39 — entrada inicial WE SATIVA no Studio

Provider de experiência e rota `/studio/we-sativa` em purpose `account` delegam
ao serviço Wiki `WikiContributorWorkspace`. A primeira tela apresenta verbetes
e propostas da própria conta e link de criação somente com acesso efetivo.
Candidatos limitados a 20 itens por tipo e UID. O acesso de cada entidade é
revalidado no purpose `wiki` antes da renderização; cache varia por usuário,
permissões e domínio. Kernel focal aprovado: 1 teste / 37 asserções; classe
MariaDB: 15 testes / 612 asserções, 286 depreciações Drupal/contrib e 16
PHPUnit. Formulários de autoria, cursor/paginação e revisão visual seguem como
próximos gates.

### Incremento dev.35 — exigir transições oficiais no aceite

Kernel verifica que moderar e editar o verbete não substitui as permissões das
transições oficiais do workflow. Sem elas, o aceite falha e proposta, revisão e
campos não mudam. Focal: 1 teste / 144 assertions; classe Kernel: 10 testes /
459 assertions, aprovada em MariaDB isolada.

### Incremento dev.34 — conflito da revisão-base em propostas

Kernel cobre a corrida em que o verbete muda após o envio da proposta e antes
da decisão: a aceitação falha com conflito, não cria revisão decisória da
proposta e preserva a revisão/título concorrentes do verbete. Um moderador sem
acesso de edição também não consegue aprovar o campo, sem alterar proposta ou
verbete. Focal: 1 teste / 140 assertions; classe Kernel: 10 testes / 455.

### Incremento dev.33 — retry seguro de imagem após rebuild

Falhas/rebuilds revertem Media e File em transação, limpam caches das entidades
e removem a chave temporária do FormState. Kernel prova nova tentativa com o
mesmo arquivo e ausência de órfãos (focal: 1 teste / 33 asserções; classe: 10
testes / 447 asserções). Unit do uploader: 12 testes / 79 asserções.

### Incremento dev.32 — listas de categorias e relações

Kernel prova `append` e `replace` para categorias e relações em propostas
sucessivas, com deduplicação e revalidação de acesso/domain dos destinos.
Focal: 1 teste / 132 asserções; classe Kernel: 9 testes / 414 asserções.
Runtime segue desabilitado.

### Incremento dev.31 — listas de propostas

Kernel confirma `append` e `replace` por decisão editorial em referências,
incluindo deduplicação de URLs. Focal: 1 teste / 122 asserções; classe Kernel:
9 testes / 404 asserções. Acrescentar cenários equivalentes para categorias e
relações segue pendente.

### Incremento dev.30 — R2: Kernel de rotas e acesso

O Kernel real confirma rota única para Início, Busca, Prévia e listagem, além do
purpose `wiki` e acesso autenticado na View. A busca distingue grants por conta
e conserva contexto de acesso em respostas negativas, vazias e `q[]` malformado.
Focal: 2 testes / 93 asserções. A ativação no Runtime segue pendente.

### Incremento dev.29 — aplicação editorial de propostas

Propostas podem conter valores estruturados dos campos editoriais autorizados.
Na aceitação, a equipe escolhe campos e, para cada lista, substituição ou
acréscimo. O backend revalida revisão-base, acesso e transições do workflow e
grava a revisão publicada e decisão em uma transação. A atualização de schema
foi criada, mas não aplicada ao Runtime. Kernel focal: 1 teste / 119 asserções;
classe completa: 9 testes / 393 asserções. Testes de listas, conflitos e acesso
negado especificamente para o novo aceite permanecem no próximo incremento.

### Incremento dev.28 — retirada de proposta

Autoria pode retirar proposta enviada ou devolvida para ajustes. Form API pede
confirmação; o serviço revalida autor, estado e VID e grava nova revisão privada
`withdrawn`. Kernel focal: 1 teste / 110 assertions; classe completa: 9 testes /
384 assertions.

### Incremento dev.27 — concorrência idempotente em MariaDB

Kernel segura o lock com uma segunda conexão física, verifica 429 sem duplicata
e depois o replay da proposta existente. Focal: 1 teste / 99 assertions; classe
completa: 9 testes / 371 assertions. O teste multiprocesso permanece pendente.

### Incremento dev.26 — idempotência da submissão

Token por formulário com expiração de 24 horas faz reenvios devolverem a
proposta original. Lock serializa requisições e gravação de entidade, Flood e
token ocorre na mesma transação. Kernel focal MariaDB: 1 teste / 97 assertions,
com replay direto e via Form API; classe completa: 9 testes / 369 assertions.

### Incremento dev.25 — limite de submissão comunitária

`WikiProposalSubmission` aplica Flood do Drupal: até 10 propostas por conta em
uma hora. A tentativa seguinte recebe HTTP 429 antes de salvar. Kernel focal
MariaDB: 1 teste / 94 assertions; classe completa: 9 testes / 366 assertions.
Idempotência entre reenvios concorrentes continua pendente.

### Incremento dev.24 — histórico privado de propostas

A tela de status lista revisões autorizadas, estado e retorno editorial, sem
identificar moderadores. A paginação por cursor aplica `view revision` a cada
revisão e limita o scan. Kernel MariaDB focal: 1 teste / 93 assertions.

### Incremento dev.23 — continuação da submissão

Ao enviar contribuição, o Form API redireciona para a rota privada da proposta
persistida. A autoria pode conferir status e retorno imediatamente, sem depender
da notificação por e-mail. Kernel focal: 1 teste / 85 assertions.

### Incremento dev.22 — status e retorno privado ao autor

A autoria consulta resultado e nota na rota protegida pela permissão de proposta
própria e pelo Entity Access da entidade. A decisão envia aviso assíncrono pelo
Mailer opcional apenas para conta ativa com e-mail confirmado. O link é
canônico no propósito Wiki; o aviso não contém o texto sugerido.
Kernel MariaDB completo: 9 testes / 355 assertions; Unit: 66 testes / 338
assertions.

### Incremento dev.21 — fila sem perda por filtragem de acesso

A fila de revisão percorre os VIDs candidatos em lotes limitados, valida cada
revisão e permite continuar por cursor após candidatos negados ou obsoletos. Um
Kernel com 26 candidatos sem grant comprova que a revisão autorizada mais
antiga não é omitida. O limite é de 500 candidatos por chamada.
Kernel MariaDB completo após a mudança: 9 testes / 344 assertions.

### Incremento dev.20 — rotas e acesso em Kernel

Reexecutada a classe Kernel com bootstrap Wiki real. As rotas públicas preservam
nomes e caminhos e respeitam `access content`. O CTA usa o acesso efetivo de
criação do Node e inclui a política compartilhada de e-mail confirmado. Não foi
necessário alterar Runtime. Resultado: 9 testes / 342 assertions.

### Incremento dev.19 — GanjaLab

A `WikiTab` preexistente consome filas da Wiki: revisões atuais via serviço
autorizado e propostas via serviço privado. A edição de verbete segue formulário
Drupal/Content Moderation; decisões de proposta seguem o Form API Wiki. O
Studio não cria ACL nem storage. Kernel combinado Wiki + Studio caracteriza
gates de seção/listagem. Runtime e browser visual permanecem pendentes.

### Incremento dev.18 — decisão editorial sem aplicação automática

A API Form grava pedido de ajustes/recusa em nova revisão e dá feedback textual.
O lock de linha no banco evita que dois revisores decidam com base na mesma
versão; o VID esperado também rejeita formulários obsoletos. Aceite e aplicação
ao verbete aguardam definição de fluxo. A integração da fila/Form com GanjaLab
no Studio também segue pendente; não foi criada interface editorial paralela.
Kernel MariaDB passou com 8 testes / 301 assertions; 284 depreciações
Drupal/contrib e 9 PHPUnit.

### Incremento dev.17 — acesso à contribuição

A submissão confirma a conta pela política `aculta_portal.email_confirmation_policy`.
O link é contextual ao verbete publicado e só aparece a contas confirmadas sem
acesso de update. O hook agrega metadados de cache de permissão/domínio e dos
resultados de Entity Access. Kernel prova os estados confirmado e não confirmado.

### Incremento dev.16 — envio comunitário

O endpoint Form API para proposta contextual está implementado. Exige a
permissão específica e Entity Access do verbete, e usa o serviço de submissão
para atualizar o VID-base no instante do envio. O conteúdo não altera o Node.
Ainda aguardam definição: semântica do aceite, aplicação no workflow `wiki_editorial`,
revisão concorrente durante a decisão e integração de moderação na WikiTab do
Studio. Runtime continua pendente.

### Incremento dev.15 — consulta editorial

Concluída a primeira camada de consulta do backend editorial: serviço próprio
com filas paginadas para propostas pendentes e revisões atuais em `review`.
Revisões passam por acesso de entidade e validação de transições no purpose
`wiki`; a fila de propostas exige capacidade de moderação. A suíte Kernel MariaDB
da Wiki passou (8 testes / 274 assertions). Ainda faltam fila resistente a
candidatos inacessíveis em paginação, decisão/aplicação de propostas por Form
API, endpoints e integração da `WikiTab` do Studio. Runtime continua pendente.

## Fase 1 — Casca do submódulo

### Limpeza de verbetes e anexos — dívida coordenada com o Portal

O Portal já oferece `aculta_portal.unused_file_cleaner` para verificar e remover
Files sem uso conhecido, delegando imagens a `aculta_image`. Adicionar ao
contrato do provider Wiki a descoberta de referências necessárias à exclusão
segura de verbetes e submeter candidatos Media ao Portal ao limpar anexos. A
Wiki mantém a descoberta de embeds e relações da própria entidade; o Portal
revalida referências globais e remove os Media órfãos por Entity API.
As relações `wiki_category` e `wiki_image` do Content Index foram implementadas
com revalidação de acesso dos alvos, mas não executam nem substituem o adapter
de limpeza. A exclusão segura de anexos continua sendo um fluxo separado. Ver
[`CONTENT-CLEANUP-POLICY.md`](../../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

- Manifesto com dependências `aculta_portal`, `domain`, `views`, `node` e
  `content_moderation`.
- Documentação de entrada e regras locais.
- Saída: validadores do repositório passam; módulo continua desabilitado.

## Fase 2 — Rotas, acesso e busca

- **Código movido:** `WikiController`, `WikiSearchFormBuilder` e as três rotas
  `aculta_portal.wiki_*` estão no submódulo, preservando nomes e caminhos.
- Os pontos de domínio e purpose (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`).
  A Wiki não redefine purpose nem host.
- **Kernel verificado em dev.13:** discovery das três rotas públicas, rota da
  View `/wiki/verbetes`, requisito `access content`, buscas com dois usuários e
  grants diferentes. Runtime e `drush cr` no homelab permanecem pendentes.
- **Gates pendentes:** Config Sync sem diferenças; HTTP 200 em `/`,
  `/wiki/verbetes`, `/wiki/busca` e `/wiki/busca/previa` no domínio `wiki420`
  (verificados em 2026-10-23); `/wiki` deve responder 404 direto pela política
  multidomínio; backup privado antes de qualquer `updb`.

### Incremento de acesso e cache — dev.11

Implementado em dev.11: `WikiController::search()` varia cache por
`user.node_grants:view`, e o CTA na home consulta `createAccess()` para
`wiki_entry`, preservando o `AccessResult` cacheável. A página inicial também
declara o contexto de grants usado por `recentChanges()`. Rotas permanecem
inalteradas. A matriz lista os testes Unit; discovery das rotas e validação
O Kernel real confirma as definições das três rotas, a rota da View e o
requisito `access content`. O teste de busca usa o controller real, com grants
por conta em contas da mesma função, e confirma o contexto de cache para busca
vazia/negativa. HTTP 200 e ativação continuam gates de Runtime separados.

### Ciclo de vida da imagem de destaque — dev.43

O arquivo de destaque fica em armazenamento privado. O upload prepara Media e
File dentro de transação até o salvamento do Node; a Media acompanha as
referências atuais de Nodes publicados. Rebuild ou falha do salvamento reverte
o lote. O hook de download bloqueia referências apenas históricas, mas preserva
o arquivo no disco. Reuso em verbete publicado continua permitindo o acesso.
Limpeza global de referências segue no contrato transversal do Portal; a
ativação da Wiki no Runtime permanece separada.

### Persistência de propostas para verbetes de terceiros — dev.14

`aculta_wiki_proposal` é uma entidade própria, revisionável e privada, com
referência ao verbete e ao VID-base, intenção allowlisted, texto simples,
justificativa, até dez fontes HTTP(S), estado e nota editorial. `WikiProposalSubmission` valida
permissão de envio, purpose `wiki`, acesso de leitura, publicação e ausência de
permissão de atualização direta no verbete. Cada decisão deve gravar uma nova
revisão da proposta. Kernel MariaDB verifica gravação e isolamento de acesso.

Este incremento não disponibiliza endpoint/formulário nem a aplicação de uma
proposta ao Node. Continuam gates: antispam/limite de submissão, transições de
decisão pela Form API/Content Moderation, detecção de revisão-base obsoleta,
fila editorial paginada e feedback ao autor. O Studio ainda não consome essa
entidade. Política de retenção/expiração de propostas recusadas ou retiradas
aguarda decisão; não remover histórico automaticamente.

## Fase 3 — Documentação obrigatória da mudança de escopo

- `AGENT-CONTENT.md` e `AGENTS.md` do submódulo com o estado real de habilitação.
- Inventário LGPD com `wiki.entries.published` e `wiki.entries.updated` antes de
  criar as métricas.
- Política de privacidade: conferir “Bases legais por uso do site” sem inventar
  base legal.

## Fase 4 — `aculta_statistics` pronto para receber a Wiki

- Catálogo e retenção de 90 dias para as duas métricas.
- `WikiProvider` em `aculta_statistics`, sem importar classes da Wiki.
- Primeira publicação, revisão publicada e conteúdo importado cobertos por
  testes unitários.

## Fase 5 — Habilitação

- Só depois das fases anteriores, com decisão do responsável, backup privado e
  validação no servidor de testes.

## Integração ACULTA Content Index — IDX 0.1

- Provider `node:wiki_entry` registrado pelo próprio módulo sob a tag
  `aculta_index.provider`, sem dependência obrigatória do submódulo Index.
- Projeta metadados mínimos e declara o purpose canônico `wiki` para revalidar
  Entity Access em consultas originadas por outros domínios, com o usuário e
  grants atuais. A falha em resolver o domínio deve negar exposição.
- Relações outgoing explícitas: `field_wiki_related` → `related_entry`
  (`node:wiki_entry`); `field_wiki_categories` → `wiki_category`
  (`taxonomy_term:wiki_category`); `field_wiki_image` → `wiki_image`
  (`media:image`). Provider mínimo de categoria pertence a `aculta_wiki`; o
  provider de imagem pertence a `aculta_image`. O Index não copia corpo,
  descrição de termo, URL ou arquivo e revalida acesso de cada destino.
- O bundle/provider mínimo de Media `remote_video` pertence ao Portal e está
  disponível para os módulos consumidores. Hoje `filter.format.wiki` permite
  apenas Media `document`; uso de vídeo na Wiki não está habilitado. Decisão do
  responsável: adiar relações de vídeo até fechar os formatos editoriais e os
  contratos de acesso. Não inferir links de texto livre nem projetar URL
  oEmbed/corpo.
- Referência: [arquitetura e roadmap do ACULTA Content Index](../../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).

### Estado da integração e gates restantes

- Unit do provider Wiki cobre a allowlist, revisão padrão, três relações
  nomeadas e deduplicação; `WikiCategoryContentIndexProviderTest` cobre a
  projeção de `taxonomy_term:wiki_category`, purpose `wiki` e revisão padrão.
  A suíte Unit passou em 2026-10-23 (61 testes / 277 assertions; 3
  depreciações PHPUnit).
- Unit focal do filtro registra o provider Wiki real e confirma purpose,
  preservação do usuário, restauração de Domain e falha fechada sem Domain
  (9/80). O Kernel `ContentIndexWikiAccessKernelTest` usa `EntityHooks`, provider
  e Domains persistidos reais: nega em `main`, autoriza pelo Index em `wiki` e
  restaura o Domain anterior (1/16). O teste usa bridge de fixture; não habilita
  a Wiki nem prova discovery do YAML owner no container Drupal.
- `ContentIndexWikiAccessKernelTest` consulta `related_entry`, `wiki_category`
  e `wiki_image` pelo Index usando os YAMLs reais de `aculta_wiki` e
  `aculta_image`. Entidades Node, Taxonomy Term e Media são persistidas; Entity
  Access omite alvos não publicados, e o Domain `main` é restaurado. MariaDB:
  3 testes / 84 assertions, 5 depreciações.
- O mapa de services e o boot combinado do Index carregam as tags owner. Isso
  inclui o `ContentIndexOwnerIntegrationKernelTest`, que habilita a closure real
  de dependências, compila o container e descobre `wiki` pelo registry (1/8).
  Isso não instala toda a configuração da Wiki nem substitui grants adicionais
  ou homologação Runtime. No preflight read-only de 2026-10-23, a Wiki estava
  ativa e o Index desativado; não houve alteração de Runtime nesta fase.

Atualização WIKI-R1, 2026-10-23: `ContentIndexOwnerIntegrationKernelTest` usa a
closure real Wiki + Portal + Index e valida projeção deduplicada, filtro de
acesso, restauração do Domain e as três consultas outgoing (`related_entry`,
`wiki_category`, `wiki_image`) pelo `ContentIndexRelationQuery` real, sem bridge
de acesso. A geração de teste é semeada apenas com as identidades necessárias;
isso não comprova rebuild ou auditoria integral do inventário. A classe owner
passou com 2 testes / 88 assertions. A suíte Wiki/Index completa anterior
registrou 6 testes / 187 assertions em MariaDB; não foi reexecutada neste
incremento.

Atualização IDX 0.1-dev.161 (2026-10-23): o teste transversal
`ContentIndexOwnerIntegrationKernelTest` carregou a closure completa de
dependências da Wiki, além de Portal/LMS/Fórum. O container real descobriu o
provider `wiki` pelo YAML owner; o gate inicial passou com 1 teste / 8
assertions (83 depreciações Core/contrib). Isso comprova boot/DI e registry,
sem instalar configuração/schema nem homologar Runtime. Categorias e imagens
foram concluídas em dev.10, registrada abaixo.

Atualização IDX 0.1-dev.10 (2026-10-23): essa decisão foi encerrada após a
definição dos contratos. O provider agora projeta `wiki_category` e
`wiki_image`, e o owner da categoria foi implementado na Wiki. Kernel MariaDB
valida consulta das relações e revalida acesso a Term/Media reais (3/84; 5
depreciações). O harness é do Index/Portal. O preflight read-only de
2026-10-23 encontrou a Wiki ativa e Runtime ainda pendente de homologação. Essa
integração do Index é separada do adapter para limpeza segura de verbetes e
anexos, que permanece como dívida do projeto.

### Entregue em código (2026-10-22)

- **Listagem e imagem de destaque:** contagem e paginação da view `wiki_entries`,
  recorte 16:9 no card e aba "Imagem de destaque" consumindo o consumidor `wiki`
  da política central de upload. Rotas inalteradas.
- **Busca no site:** `wiki_entry` em `bundles.selected` de `aculta_conteudo`. A
  busca própria da Wiki e as três rotas `/wiki`, `/wiki/busca` e
  `/wiki/busca/previa` não mudaram. Verbetes já publicados entram no índice com
  `drush search-api:index` no ambiente (passo de deploy, não desta sessão).
- **Aviso de revisão:** `WikiReviewHooks` (`entity_update`, via `getOriginal()`),
  `WikiReviewMailType` (`aculta_wiki_review`) e `WikiReviewNotifier` na fila do
  `aculta_mailer`, como serviços opcionais. Tela
  `/painel-administrativo/aculta/wiki` (`administer aculta_wiki`). Desligado por
  padrão, sem destinatários, sem o nome de quem editou no contexto.
- **Pacote LGPD no mesmo commit:** inventário, cláusulas de aceite, política de
  privacidade, `AGENT-CONTENT.md` e `AGENTS.md` do submódulo.
- **Continua pendente:** importação de configuração com backup privado
  verificado, reindexação no ambiente e revisão visual em ACULTA420 e Olivero.


## WE SATIVA — fundação aprovada (WES-UX 0.2, 2026-10-22)

A experiência editorial independente da Wiki420 é definida em [WE-SATIVA-FOUNDATION.md](WE-SATIVA-FOUNDATION.md) e no [plano de contribuição contextual](../../../../../../../docs/portal/WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md). **Não reutilizar GanjaLab como interface do WE SATIVA.** Implementação iniciará por auditoria de Entity Access/Form API/workflow e resolução de purpose, depois abertura contextual por verbete e microtarefas mobile-first. Não habilitar sugestões sem backend real. **Autosave Form adiado para futuro**, sem Composer/configurações neste ciclo. Preservar Institution Bar + Domain Header e usar exclusivamente SDC/UI Suite no tema.


## WE SATIVA — arquitetura interna WES-UX 0.3 (2026-10-22)

O planejamento aprovado de todas as estruturas internas está em [WE-SATIVA-INTERNAL-ARCHITECTURE.md](../../../../../../../docs/portal/WE-SATIVA-INTERNAL-ARCHITECTURE.md). Fases 0.3-A1 e A2 deverão auditar permissões/workflows e decidir a persistência segura de propostas antes de rotas. Seguem depois wireframes, reuso SDC/UI Suite e integrações incrementais. **Nenhuma implementação funcional liberada por este documento; Autosave Form permanece futuro.**


## WE SATIVA C01 — primeira composição visual (2026-10-22)

[Porta contextual de contribuição](../../../../../../../docs/portal/WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md): um CTA autorizado a partir do verbete e seletor de intenções sem ações fictícias. Primeiro auditar `context-header.actions` versus `wiki-entry.actions`; manter um só CTA. Dependência de AccessResult, purpose/domain e URL canônica; proposta sem `update` continua bloqueada até decisão de backend. Projeto mobile-first, SDC/UI Suite, shell global preservado.


## Repriorização visual e de acesso — C01-A (2026-10-22)

A [auditoria C01-A](../../../../../../../docs/portal/WE-SATIVA-C01-A-ACCESS-AND-CTA-AUDIT.md) constatou: o SDC `context-header.actions` pode receber o convite único, enquanto `wiki-entry.actions` já exibe `content.links`; evitar CTA duplicado. O workflow `wiki_editorial` já tem rascunho, revisão e publicação, mas o papel autenticado comum não possui permissões de edição; o papel `content_editor` é privilegiado. **Antes de qualquer rota ou UI WE SATIVA:** (1) desenhar contribuição de mínimo privilégio e mecanismo de propostas sem `update`; (2) mapear todas as permissões e revisão; (3) verificar CKEditor/formatos; (4) selecionar a rota/purpose; (5) integração SDC mobile-first e teste de DOM. Próximo marco **C01-B**. Nenhuma concessão de permissão é autorizada por este roadmap.


## C01-B0 — participação de e-mail confirmado e moderação (2026-10-22)

**Decisão aprovada:** todo usuário com e-mail confirmado poderá contribuir na Wiki; somente `content_editor` modera/publica sugestões. [Contrato C01-B](../../../../../../../docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md) verifica que `user.role.email_confirmed.yml` já possui criação/edição de conteúdo próprio e envio ao estado `review`, sem publicar. Não confundir conta meramente `authenticated` com `email_confirmed`. Corrige-se a inferência incompleta da C01-A; não duplicar papel nem ampliar `edit any`. **C01-B1 parcial (dev.36):** Kernel inicializa Mail Confirmer real, aceita confirmação do realm `aculta_registration` e verifica atribuição do papel e autorização de criação. Falta caracterizar configuração importada e Runtime. **Próximos:** fechar C01-B1 no ambiente de teste; C01-B2 ADR de propostas em verbetes alheios; depois C01-C1 editor de próprios verbetes e C01-C2 microtarefas. C01-R no homelab. Autosave permanece somente futuro; nenhuma permissão, rota ou endpoint foi mudado.

## R2 — sincronização de publicação da imagem (dev.37)

Hooks reais de Node recalculam o status da Media de destaque após inserção,
atualização e exclusão. A Media permanece publicada se algum Node publicado
ainda a referenciar e fica privada após a última referência publicada ser
desativada. Kernel MariaDB cobre publicação, despublicação, reuso e exclusão do
último Node (2 focais / 56 asserções; classe completa 13 / 548). Mesmo com a
Media despublicada, Core ainda permite baixar o arquivo quando uma revisão
publicada anterior o referencia. Resta decidir se esse acesso deve seguir a
autorização Core da revisão ou o estado atual do verbete.

Dev.38 acrescenta o caso focal do validador: erro de `alt` não cria Media nem
torna permanente o File (Kernel focal 1 / 27; classe completa 14 / 575). O
fluxo completo de submissão Form API com falha de validação permanece pendente.


## Prioridade: primeira publicação e moderação Wiki (WES-MOD 0.1)

[Plano de moderação da Wiki e WE SATIVA](WE-SATIVA-MODERATION-PLAN.md): qualquer conta com e-mail confirmado pode **criar** verbete; o **primeiro verbete** exige revisão antes de publicação, tal como a primeira participação no Fórum. A transição de publicação pertence aos `content_editor`; novos verbetes e sugestões continuam moderados no workflow vigente. Fila Wiki própria, com contratos neutros compartilhados pelo `aculta_user`. Antes de novas inspeções visuais: auditar consumidores, registrar ADR do kernel, evitar duplicações e testar a regra de primeira publicação. Não reutilizar contagem de comentários do Fórum para verbetes.

## Retificação de ownership (2026-10-22)

**A política compartilhada de moderação e primeira participação reside em `aculta_user`**, estendendo `ParticipationRules`, sem migração para `aculta_portal`. Wiki e Fórum permanecem donos de filas/workflows e entidades próprias. Esta decisão substitui indicações anteriores de centralizar essa política no Portal. [Contrato revisado](../../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).


## USER-PART 0.1-A — auditoria Fórum + LMS + Wiki

O [inventário transversal](../../aculta_user/docs/USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md) estabelece `aculta_user` como proprietário da política compartilhada; Fórum e LMS mantêm entidades, queries, fila e Group; Wiki mantém workflow e revisões. Prioridade USER-PART 0.1-B (contrato de evidência de publicação) → testes de caracterização → adapter de verbetes Wiki → integração do primeiro verbete moderado. **Não reaproveitar o contador de comentários para calcular experiência Wiki**, nem conceder publicação a `email_confirmed`. Inspeção visual fica após o fechamento dos contratos funcionais.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-01/03/05/06/08: Wiki publica eventos de revisão/ajustes via adapter Messenger com ACL e deduplicação; não cria caixa de avisos. Reaproveita aculta_user para primeira participação, aculta_forum_wiki para discussões, aculta_image e viewers para mídia, aculta_statistics para métricas; mantém moderação e propostas Wiki próprias.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B / ADR-XK-01: primeira participação de Wiki passa por contrato de User com lookup próprio de verbetes publicados; toda publicação exige content_editor. Para avisos de edição/revisão, a Wiki produz eventos mínimos para o Messenger sem segunda central. O próximo gate é caracterizar o workflow e não liberar edição de terceiros diretamente.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## XK 0.1-C1 — caracterização concluída (2026-10-22)

As políticas compartilhadas de participação no `aculta_user` estão fixadas por testes Unit e Kernel, sem mudança de comportamento. `PublishedParticipationLookupInterface` foi documentada, **não implementada**: o adapter de verbetes Wiki e a regra do primeiro verbete continuam fora deste marco. Nenhuma alteração neste módulo. [Evidência](../../aculta_user/docs/USER-PART-0.1-C1-CHARACTERIZATION.md).

## WES-UX 0.4 — projeto visual por telas (2026-10-22)

[Inventário de 12 estruturas WE SATIVA](../../../../../../../docs/portal/WE-SATIVA-VISUAL-SCREENS-0.4.md), incluindo entrada pelo verbete, início, Meu trabalho, microtarefas, novo verbete, CKEditor, prévia, revisão, feedback, ajuda, fontes e mídia. Sequência V-0.4-A–R, mobile-first em toda a Wiki, componentes Core SDC/UI Suite exclusivamente, Institution Bar + Domain Header preservados. Cada tela depende de providers, estados e permissões reais; não implementar links fictícios. Autosave apenas futuro.

## WES-UX V-0.4-B — entrada, início e Meu trabalho

[Especificação detalhada das telas V01–V04](../../../../../../../docs/portal/WE-SATIVA-V-0.4-B-ENTRY-DASHBOARD-WORK.md): convite contextual único, seletor de intenções com ações realmente disponíveis, início com criação/continuação autorizadas e lista própria de trabalhos com filtros HTTP. Histórias WES-B-01 a WES-B-10, cenários vazios/erro/acesso, mobile-first 320px e claro/escuro. Somente Core SDC/UI Suite, sem rotas fictícias ou novas permissões. Próximo marco V-0.4-C (microtarefas e editor).

## WES-UX V-0.4-C — microtarefas, novo verbete, editor e prévia

[Especificação visual e funcional V05–V08](../../../../../../../docs/portal/WE-SATIVA-V-0.4-C-EDITOR-FLOWS.md): fluxos progressivos, 12 stories de aceite, Form API com CKEditor 5 **já configurado para o formato wiki**; toolbar exportada, Media integrado e upload direto de imagem desativado. Contas email_confirmed criam e enviam à moderação, content_editor publica. Sugestões de alteração de terceiros dependem de backend seguro. SDC/UI Suite, mobile-first, sem autosave nesta fase. Próximo V-0.4-D.


## WES-UX V-0.4-D — moderação visual no GanjaLab (2026-10-22)

[Contrato V09–V12 e janela GanjaLab](../../../../../../../docs/portal/WE-SATIVA-V-0.4-D-GANJALAB-MODERATION.md): `aculta_wiki` é fonte dos workflows, revisões, access e fila; `aculta_studio` oferece aba Wiki/Moderação e janela de análise, enquanto WE SATIVA mostra confirmação, ajustes recebidos, ajuda, fontes e mídia ao colaborador. Fases D-A contratos/StudioTab → D-B stories SDC → D-C fila e URLs → D-D transições → D-E Messenger e feedback → D-R homologação. Proibido duplicar painel de moderação no WE SATIVA. Mobile-first, shell multidomínio, SDC/UI Suite.

## V-0.4-R / GanjaLab D-A — auditoria do código existente

[Inventário e contrato da janela Wiki](../../../../../../../docs/portal/GANJALAB-WIKI-V-0.4-R-D-A-AUDIT.md). **A área Wiki em `WikiTab` já existe**: não criar outra aba/plugin. Acrescentar futuramente seção `moderacao` com gate próprio, pois permissão `create wiki_entry content` já abre a área para colaboradores e não equivale a permissão de moderar. StudioPresenter e URL/AJAX existentes serão preservados; `aculta_wiki` é o dono de fila, grants, revisões e POST. Ainda faltam exame dirigido do listing builder, adapter de acesso e testes Runtime. Design com SDC/UI Suite, mobile-first e shell multidomínio preservado.

## GanjaLab Wiki D-A.2 / D-B — listagens seguras e composição visual

[Auditoria de StudioListingBuilder e especificação dos componentes](../../../../../../../docs/portal/GANJALAB-WIKI-D-A2-D-B-AUDIT-VISUAL-CONTRACT.md). Achados prioritários: cache de listagens por usuário/grants, consulta auxiliar de moderation_state com accessCheck(FALSE), necessidade de autorização explícita por revisão, acesso separado para aba Moderação e paginação. **Bloquear integração da fila até testes de caracterização e provider Wiki seguro**. D-B prevê cards mobile 320px, comparador empilhado no celular e paralelo no desktop, SDC/UI Suite, shell multidomínio e fallback HTTP. Não declarar Runtime PASS.

## D-A.2-T — progresso e próximos gates

D-A.2-T: hardening inicial do GanjaLab no StudioListingBuilder (cache por usuário, checagem explícita de view revision) e teste Unit adicionados. Não equivale a autorização de fila Wiki. Antes de D-C é indispensável provider de revisão Wiki, validação por entidade e revisão, query de moderation_state correta e Kernel com dois usuários/grants.

## Decisão normativa: ACULTA Studio (2026-10-22)

WE SATIVA is an ACULTA Studio user experience hosted within the Wiki purpose, driven by aculta_wiki Form API/CKEditor and editorial access; it is not an independent interface kernel. GanjaLab moderation is another Studio experience at account purpose. Maintain different UIs and common interfaces/SDC base; V-0.4-R and D-A.2-T guards still apply.

[Contrato e roadmap transversal](../../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**

## Atualização STUDIO 0.1-B / D-A.2-T2

D-A.2-T2: aculta_studio não enumera mais todas as entidades content_moderation_state; o estado usa a revisão mais recente visível por item. Isso não fornece fila confiável/paginada: aculta_wiki ainda deve implementar provider próprio de revisões com grants e workflow. STUDIO 0.1-B separa providers de experiência WE SATIVA (purpose Wiki) da navegação GanjaLab (account).
