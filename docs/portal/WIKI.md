# Wiki420 — conteúdo, apresentação e autoria

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
