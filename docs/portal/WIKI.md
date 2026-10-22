# Wiki420 — conteúdo, apresentação e autoria

## Discussão futura dos verbetes

Decisão de reconstrução do `aculta_forum` (2026-10-19): cada verbete poderá ter
uma única thread Comment associada ao próprio Node e exibida dentro da página.
Comentário não altera revisão editorial, não cria Node `forum` nem aparece no
Growroom420. O contrato e provider são futuros e condicionados à reimplementação
da Wiki; esta decisão não muda a autoria pública atual, que continua oculta no
verbete. Ver `aculta_forum/docs/CONTEXTS.md`.

A Wiki420 usa Drupal Node, revisão editorial, Views, taxonomia e controle de
acesso como fonte de verdade. O Portal organiza páginas e consultas; o ACULTA420
apresenta os dados com SDC e tokens de tema. A Wiki não cria uma segunda camada
de conteúdo nem uma engine de edição.

## Apresentação dos verbetes

O ACULTA420 usa o pattern aculta420:wiki-entry nos modos full e teaser. A
apresentação é inspirada na leitura enciclopédica da Wikipedia/MediaWiki,
preservando a identidade ACULTA420:

- título e síntese do verbete em destaque;
- abas e ações nativas do Drupal, visíveis conforme as permissões do visitante;
- texto principal com hierarquia clara de seções;
- imagem e categorias em painel lateral quando disponíveis;
- referências e verbetes relacionados depois do texto;
- lista de busca e Views em cards responsivos;
- listagem da Wiki continua usando a View/VVJB existente; nenhum carrossel ou
  mecanismo paralelo foi criado.

## Cabeçalho e busca

A busca fica ao lado da marca Wiki420 no Domain Header, seguindo a hierarquia de
interface da Wikipédia: marca e busca na faixa superior, conteúdo logo abaixo.
O cabeçalho Wiki é compacto e não repete o menu institucional das outras páginas.
O ACULTA420 escolhe esse layout pela presença da região neutra `wiki_search`,
montada pelo Portal com o endpoint existente. A busca é a mesma na home, nos
verbetes e nos resultados; a consulta `q` permanece preenchida ao abrir os
resultados.

a busca Wiki mantém o painel de prévia recolhido até o visitante digitar algo;
focar/clicar sem digitar não mostra a dica. Depois da primeira letra, informa o
mínimo de três caracteres. Com termo suficiente, espera 300 ms após a última
tecla, descarta respostas antigas, permite percorrer sugestões com as setas e
oferece um link para todos os resultados. A rota `/wiki/busca/previa` limita o resultado
a verbetes publicados, acessíveis e pertencentes ao Domain Wiki. A resposta é
`no-store`; sem JavaScript, o formulário GET continua levando à busca completa.
O Portal reutiliza `aculta_portal/search-preview` e seus marcadores neutros; o
ACULTA420 posiciona e estiliza o dropdown.

Validação local da implementação: home, preview com termo e preview abaixo do
mínimo responderam HTTP 200; os cabeçalhos confirmaram `Cache-Control: no-store, private`,
e o preview retornou somente verbetes Wiki. `search-preview.js` passou
na verificação de sintaxe. A navegação interativa por teclado e a inspeção visual
no servidor de testes permanecem **RUNTIME STATUS: DEFERRED**.

A Institution Bar multidomínio fica intacta e continua sendo global. A
`wiki_search` é um render array no contrato `domain_presentation`, com cache
context `url.query_args:q`; o Portal não envia classes, markup final ou tokens
do tema.

CSS do pattern e da página usa tokens semânticos Light/Dark; a folha da página
Wiki é separada por responsabilidade e integrada ao library global, enquanto o
CSS do pattern é anexado pelo SDC. Os templates recebem apenas render arrays
produzidos pelo Drupal. O pattern é experimental até validação no Runtime e
revisão visual em desktop e mobile.

## Autoria

Autoria nominal não faz parte da leitura pública de um verbete. O tipo
wiki_entry usa display_submitted: false; os presenters full/teaser não
renderizam author_name ou author_picture, e a lista de alterações recentes
mostra título e data, sem “por nome”.

Isso é uma decisão de apresentação pública. Drupal continua mantendo autor e
revisões para workflow, permissões, histórico editorial e administração. Não
remover autor da entidade nem alterar o fluxo de revisão/moderação.

## Fronteiras

- **Drupal Core/contrib:** nodes, revisões, fields, views, termos, links de
  operação e access.
- **aculta_portal:** home/busca/listagens, filtros por purpose, permissões,
  cacheability e preparação de render arrays. Não emite classes de design nem
  dados de autoria pública.
- **ACULTA420:** markup dos presenters, SDC e CSS com tokens. Não consulta
  storage nem decide access.
- **VVJB:** continua a engine de carrossel das Views quando configurada; esta
  alteração não cria comportamento JavaScript de carrossel para a Wiki.

## Verificação

Em checkout local com host da Wiki mapeado para 127.0.0.1 (simulação local, não
o servidor de testes), a home e o verbete responderam HTTP 200; a busca GET foi
renderizada no Domain Header junto à marca, e o menu primário não apareceu
nesse shell. A Institution Bar permaneceu no markup global. O verbete exibiu um
H1, seções, referências e nenhum metadado de autoria. O cache foi reconstruído.
Os gates Drupal 11+ (680), apresentação de domínio (127), design foundations
(1683), SDC (231), shell (11) e fronteira módulo/tema (789) passaram. O gate
Foundation segue bloqueado por DT-T19 preexistente: durações literais em
`css/components/portal-account.css`.

Foram criados no banco local `aculta_runtime` dois verbetes publicados apenas
para validar a experiência: “Maconha (cannabis): planta, usos e debate público”
(nid 72) e “Antiproibicionismo: conceitos, história e políticas de drogas”
(nid 73). Ambos têm síntese, seções, links internos e referências institucionais.
Não foram escritos no Runtime remoto nem em produção. A captura Chromium da
versão local excedeu o limite do navegador disponível; portanto a revisão
visual por screenshot em desktop/mobile e nos modos claro/escuro continua
pendente. **RUNTIME STATUS: DEFERRED** para validação visual no servidor de
testes; a validação HTTP e de render arrays no Runtime local passou.
## Administração das discussões

Comentários de discussão dos verbetes devem ter uma listagem administrativa
Wiki própria em `/painel-administrativo/wiki/forum`, separada da administração
geral de conteúdo e da fila do Fórum. A Revista concentra publicações e
comentários em `/painel-administrativo/conteudo/revista`.
O contrato futuro deverá limitar a consulta ao bundle/hospedeiro da Wiki e
respeitar o acesso do verbete. Revisões e edição editorial não entram nessa
fila. A rota só será ativada junto do provider Wiki e após validação de acesso;
não existe atualmente um endpoint administrativo Wiki de comentários.


## Planejamento visual Wiki420 — W420-UX 0.1.0-dev.1

A especificação transversal das telas Home, busca, categoria, verbete, histórico, discussão e contribuição está em [WIKI420-VISUAL-EXPERIENCE.md](WIKI420-VISUAL-EXPERIENCE.md). O documento é **planejamento**: reaproveita os SDCs existentes do ACULTA420, exige UI Patterns/UI Styles e preserva Institution Bar + Domain Header, com a busca Wiki já integrada. Não cria novas rotas, interfaces funcionais ou providers sem auditoria.
