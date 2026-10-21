# Design system

## Discussões ACULTA — contrato planejado

O Forum contrib não tem templates no tema desde 0.6.0-dev.105. O novo motor usa
`node--aculta-forum-topic` e
`comment--aculta-discussion-post`. `aculta_user` prepara identidade e access
para Author Cards; ACULTA420 cuida de SDC/Bootstrap, layout, responsividade,
acessibilidade e tokens light/dark. `discussion.css` estiliza o formulário
nativo de respostas sem alterar access ou comportamento. Os seis presets estão
em `aculta_forum/docs/ADVANCED-FORUM-PORT.md`.

As Views públicas de tópicos reutilizam os mesmos cartões em “Todas”, “Novas”,
“Sem resposta” e “Mais comentadas”. O cabeçalho de navegação usa chips focáveis
com tokens semânticos; título, respostas e atividade empilham em telas menores.
Filtros, ordenação, visibilidade e contagens continuam responsabilidade do
Portal/Core, nunca do tema.

O índice `/categorias` utiliza o SDC `content/forum-category` para apresentar
hierarquia, descrição e o link filtrado que a View Core prepara a partir do TID.
O componente não carrega Taxonomy nem constrói URLs; a navegação permanece sob
responsabilidade do Portal.

Na entrada do Growroom420, o mesmo SDC apresenta até seis categorias no bloco
`block_landing` da View, posicionado na barra lateral por purpose e rota. Em
telas largas a barra lateral antecede visualmente a lista recente; em telas
estreitas aparece depois dela. Views/CTools controlam os dados, a quantidade e
o pager, enquanto o tema compõe as colunas com tokens responsivos.

O SDC `content/author-card` mantém o contrato de identidade entre contextos e
mostra uma etiqueta neutra conforme a variante: Growroom420, Comentário da
Revista, cursos e Wiki420. O módulo fornece a variante; o tema não consulta a
entidade hospedeira nem revela papel, matrícula ou atividade. Na Revista, o
Magazine fornece somente a designação Autor/Coautor; identidade, avatar, bio e
redes públicas são preparados pelo `aculta_user` segundo opt-in e política de
privacidade. Nos cartões do Fórum, apresenta opcionalmente o ano da conta
fornecido pelo `aculta_user`, que omite o dado para perfis privados e autoria
histórica. Stories cobrem separadamente `author_editorial`, `author_comment`,
`author_lms` e `author_wiki`.

O modal de upload compartilhado usa o token `user-upload-dialog`, preparado
pelo `aculta_portal`. O Fórum acrescenta `forum-attachment-dialog` ou
`forum-gallery-dialog` para os ajustes de largura do contexto. `PortalUiHooks`
traduz os tokens e `dialog.css` define dimensões responsivas e rolagem no
diálogo Core.

## Identidade

ACULTA420 usa Bootstrap como infraestrutura e mantém identidade própria por
tokens e contratos de componentes.

### Paleta

| Token | Valor | Uso |
| --- | --- | --- |
| `--aculta-green-dark` | `#0c3c29` | texto, contraste, interação |
| `--aculta-green` | `#689427` | estrutura e marca |
| `--aculta-yellow` | `#f2ca36` | ação/destaque |
| `--aculta-red` | `#d4452d` | ênfase editorial |
| `--aculta-cream` | `#fbf4e8` | superfície |
| `--aculta-white` | `#ffffff` | superfície/contraste |

O prefixo `--aculta-*` é mantido por decisão arquitetural: representa o
design language, não o machine name do tema.

## Tipografia

- display/headings/navigation/CTA: Oswald;
- corpo/forms/metadados/UI: Inter.

Fontes são declaradas na library global atual. Uma futura estratégia de
self-hosting pode ser avaliada separadamente; não misturar com componentização.

## Bootstrap mapping

Tokens ACULTA alimentam `--bs-*` para evitar duas paletas paralelas.

Sempre manter pares `color` / `color-rgb` semanticamente equivalentes.

Não redefinir Bootstrap em cada componente quando uma custom property global
resolve o problema.

## Foundations

O sistema de tokens tem palette, tipografia, bordas/foco, espaçamento de seção, mapping
Bootstrap e tokens semânticos de superfícies, texto, bordas, interação e shell. As primitivas
físicas da paleta permanecem em `tokens.css`.

Inventário CSS da 0.2-A:

- **KEEP PALETTE:** hex/RGB físicos permanecem em `tokens.css`;
- **MIGRATE TO SEMANTIC:** fundo da página, controles, foco, links, cartões e
  superfícies Bootstrap com função visual clara usam semantic tokens;
- **COMPONENT-SPECIFIC:** verde estrutural de cabeçalho/rodapé, navegação atual e
  seções institucionais de contraste permanecem decisões visuais locais;
- **DUPLICATE:** motion tem um único nível consumido; valores de sombra Bootstrap
  são níveis distintos e não são duplicatas removíveis;
- **DEAD:** nenhum token ou seletor foi removido como código morto sem evidência.

Novas regras devem seguir:

```text
palette tokens → semantic tokens → components
```

Um componente usa o token semântico que descreve sua função. A paleta continua
centralizada e não deve ser espalhada para representar contexto.

## Leitor PDF compartilhado

O `aculta_pdf_viewer` fornece marcação funcional neutra via atributos
`data-aculta-pdf-*`; Media/File/PDF.js continuam donos do arquivo, autorização e
comportamento. ACULTA420 apresenta esse contrato em
`css/components/pdf-viewer.css`, usando tokens semânticos de superfície,
interação, foco, borda e tipografia. A folha não declara cores por modo nem
depende do LMS ou de host. O módulo continua responsável pelo DOM e controles
dinâmicos do canvas, portanto o tema não cria um SDC que replique esse markup.

O contrato de superfície e shell inclui:

```css
--aculta-surface-page: ...;
--aculta-surface-raised: ...;
--aculta-surface-header: ...;
--aculta-text-primary: ...;
--aculta-text-secondary: ...;
--aculta-border-subtle: ...;
--aculta-shell-institution-bg: ...;
--aculta-shell-domain-bg: ...;
```

Os nomes semânticos definem função, não cor literal ou Domain purpose.

### Direção visual Design B

- fundo da página: superfície semântica verde muito suave;
- superfícies elevadas: superfície clara/branca;
- Institution Bar: superfície secundária;
- Domain Header: superfície elevada principal;
- acento estrutural: verde;
- navegação ativa: fundo amarelo e texto escuro com contraste adequado;
- valores são escolhidos nos tokens por modo, sem hex espalhado pelos componentes.

O texto ativo usa o verde escuro, não o vermelho de marca: medição de contraste
na superfície amarela deu 7.84:1 para verde escuro e 2.83:1 para o vermelho
original. O token de texto-acento também usa um tom mais escuro para cumprir AA
em texto normal sobre a superfície da página; a primitiva `--aculta-red` não foi
alterada.

As etapas 0.2-C/0.2-D implementam as faixas do shell; 0.2-A fornece somente
tokens e não altera `page.html.twig` ou markup do shell.

## Color modes

Bootstrap 5.3 usa `data-bs-theme` para color modes. ACULTA420 seguirá o mesmo
contrato.

> Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.

> Color mode no ACULTA420 é uma variação de tokens, não uma variação de layout.

Light mantém a superfície geral verde muito suave, superfícies elevadas claras e
identidade estrutural verde. Dark usa página carvão quente, superfícies grafite
quentes, texto creme/branco quente e bordas escuras neutras. Verde permanece
marca/acento/interação; amarelo indica ação/estado ativo; vermelho é ênfase
editorial. Dark não é uma versão verde-escura do ACULTA420.

O gate calcula WCAG contrast para os pares de texto/ação/shell e para o anel de
foco em cada superfície dark. O menor par de texto normal é muted text sobre
raised surface, **7.59:1**; o menor contraste do foco é **8.85:1** sobre a
superfície interativa. O gate exige 4.5:1 para texto normal e 3:1 para foco.

Componentes consomem `--aculta-surface-*`, `--aculta-text-*` e
`--aculta-border-*`; eles não conhecem light, dark ou `prefers-color-scheme`.
Essa decisão pertence à camada de tokens. Os dois modos compartilham DOM,
markup, hierarquia, componentes, tipografia, espaçamento, dimensões, grid,
breakpoints, posicionamento, shell, navegação e comportamento. Somente valores
visuais tokenizados podem variar.

O gate `validate-aculta420-design-foundations.php` aceita seletores de modo
somente na foundation de tokens. Ele verifica as formas comuns de seletor dark,
branches explícitos em Twig/PHP/JavaScript, os valores efetivos dos aliases e a
ausência de declarações duplicadas, ciclos e referências não resolvidas. O
subconjunto reconhecido e seus limites estão descritos em
[`development.md`](development.md); mudanças na sintaxe do contrato devem vir
com fixtures negativas correspondentes.

Estado e sequência:

1. 0.2-A/0.2-A.1 define valores semânticos light/dark por `data-bs-theme`;
2. 0.2-E/0.2-F valida visualmente o shell nos dois modos;
3. uma fase posterior adicionará `auto` via `prefers-color-scheme`, seletor e
   persistência;
4. alto contraste só será considerado após auditoria.

Esta foundation não declara Dark Mode como feature completa e não ativa um modo
por padrão. Ela prepara tokens para que uma fase posterior possa testar o shell
e componentes sem mudar markup.

Modo de cor troca tokens, não geometria nem markup do shell. Um logo poderá ter
asset light e asset dark no futuro somente se a legibilidade exigir; seu espaço,
dimensões e layout permanecem iguais e isso não autoriza DOM ou navegação
diferentes.

Referência:
https://getbootstrap.com/docs/5.3/customize/color-modes/

## Mobile First

ACULTA420 adota implementação mobile-first como decisão arquitetural em
[`ADR-012`](../../../../../docs/decisions/ADR-012-aculta420-mobile-first.md).
O componente começa pelo layout que cabe no menor espaço e ganha colunas e
navegação simultânea conforme a tarefa e o conteúdo precisem delas. Bootstrap
5.3.8 fornece breakpoints de referência; não converter media queries locais em
lote. Grid, Flexbox, `minmax(0, 1fr)` e container queries são técnicas conforme
o contexto, sem um arquivo global `responsive.css`.

Conteúdo comum deve reflow em 320 CSS px, sem rolagem horizontal da página.
Exceções bidimensionais mantêm seu próprio contêiner de rolagem. Claro e escuro
compartilham geometria e markup, conforme o contrato de modos de cor acima. O
inventário, os limites de evidência e a sequência estão na
[`auditoria Mobile First`](mobile-first-audit.md).

## VVJB primeiro (carrossel)

- O carrossel padrão do ACULTA é o VVJB (módulo `vvjb`, estilo de view `views_vvjb`). A home usa essa engine na view `home_editorial_highlights`.
- Cada slide do VVJB é uma linha de entidade renderizada por view. Conteúdo que vira carrossel deve ser modelado como entidade (nó, mídia ou entidade própria) e exibido por uma view.
- O componente `aculta420:carousel` é só a moldura que envolve o markup do VVJB. Não criar carrossel novo com Bootstrap nem JavaScript próprio.
- Exceção: carrossel fora do VVJB só com decisão registrada no `DEBT-REGISTER.md` ou no roadmap do tema, com justificativa.
- Motivo: uma só engine de carrossel, com navegação, teclado, reduced-motion e foco já tratados (`editorial-carousel.css` e `editorial-carousel.js`).

## VVJS para sequências de fotos

- O palco dos álbuns usa VVJS (`views_vvjs`) porque apresenta uma sequência fotográfica paginada com miniaturas próprias. Esse uso não muda os carrosséis editoriais Home/Projetos, que continuam VVJB.
- A ampliação PhotoSwipe continua responsável pelo lightbox da imagem; VVJS controla somente o slide atual, navegação e movimento reduzido.
- A View `galeria_produto` também usa VVJS para percorrer imagens de produto, sem autoplay. O modo de exibição `produto_galeria` e a finalidade de imagem permanecem; `product-gallery.css` mantém a imagem contida e a moldura usa tokens ACULTA420.
- A integração do palco fica no tema por `data-aculta-album-*` e pela API/evento público VVJ; o submódulo Gallery mantém conteúdo, ordem, paginação, acesso e cache.
- O tema retira do HTML o `src` das imagens de slides inativos e carrega o slide atual e o próximo ao navegar. `loading="lazy"` sozinho não impedia o Firefox de baixar todos os slides ocultos; a miniatura continua usando o carregamento lazy nativo dentro do trilho rolável.
- VVJA, VVJF, VVJH, VVJL, VVJR e VVJT ficam disponíveis para Views, mas não substituem por conveniência componentes SDC, páginas de rota, formulários ou navegação da Conta. Aplicar um formato somente com View e necessidade editorial compatíveis.
- VVJC (3D Carousel) e VVJP (Parallax) não fazem parte do catálogo instalado do ACULTA.

## Motion

Motion deve ser foundation, não decisão local de cada componente.

A Foundation mantém o contrato mínimo `--aculta-motion-fast` +
`--aculta-ease-standard`; o inventário não justificou uma segunda duração ou
uma escala maior nesta fase.

```css
--aculta-motion-fast: 180ms;
--aculta-ease-standard: ease;
```

Todos os componentes animados devem respeitar `prefers-reduced-motion`. A 0.2-A
preserva as regras existentes e não faz o estado depender de transição.

## Página de links sociais

O pattern `aculta420:social-links-page` usa perfil centralizado e links
empilhados no estilo Linktree para leitura e toque em telas móveis. Recebe os
canais do formulário de configuração do Portal e uma View de projetos com os
cards teaser já existentes, renderizada pelo VVJB do ACULTA.
O Portal fornece os canais e invoca a View; o tema controla o layout. Os ícones
Bootstrap (`instagram`, `youtube`, `facebook`) usam `currentColor` e tokens
semânticos, ficando escuros no tema claro e claros no tema escuro. A logo vem
do asset ACULTA já existente no próprio tema e recebe o token de filtro do shell.
Nesse purpose, a página preserva a barra institucional multidomínio e um rodapé
específico, omitindo o Domain Header e o rodapé padrão do site.
O botão primário leva à raiz MAIN; botões adicionais configurados no Portal
aparecem em seguida, e os canais sociais configurados permanecem no rodapé. Micropáginas podem escolher os presets `classic`, `soft` ou `outline`; o template legado omite a propriedade e mantém `classic`. Os presets alteram somente superfícies e bordas por tokens semânticos, sem CSS fornecido por usuários. O shell usa espaçamento vertical compacto para aproximar o conteúdo
da barra institucional e do rodapé, sem remover a folga mínima de leitura.

## Primitives

Primitive é uma categoria do design system, não sinônimo de SDC.

Exemplos:

- Button: primitive Bootstrap/CSS global;
- Category label: candidato a SDC;
- Icon: futuramente via Core Icon API/UI Icons;
- Media: só abstrair quando cards comprovarem contrato comum.

O SDC `aculta420:card` é um pattern composto para site building, com slots de
mídia, cabeçalho, conteúdo e rodapé, em variantes vertical e horizontal. Ele
usa classes Bootstrap estruturais e tokens semânticos ACULTA420 para superfície,
texto e borda. Botões e demais primitives simples continuam sendo Bootstrap
direto; não importamos a folha global nem os helpers do UI Suite Bootstrap.

O mesmo card inclui a variante `overlay`, usada na listagem de álbuns com a
imagem ao fundo e metadados em painel sobreposto de contraste controlado por
tokens de interação. `aculta420:figure` envolve mídia Drupal já renderizada e
aceita legenda/crédito opcionais. `aculta420:quote` apresenta citação, autoria e
fonte; citações antigas em HTML rico da Revista recebem a mesma tipografia e
superfície sem reprocessar o corpo ou alterar o conteúdo salvo.

O catálogo UI Styles também oferece elevação de superfície por
`--aculta-shadow-raised`, com o mesmo contrato de markup e valores próprios dos
tokens de cada modo. A opção é única e não substitui a sombra de foco/interação
nem permite escolher valores livres.

Os SDCs `course_card` compõem esse pattern em sua variante
vertical, mantendo seus contratos especializados. O antigo `project-card` foi removido com o tipo projeto. Projetos preservam mídia já
renderizada, categoria, resumo e CTA; o cartão do LMS mantém props/slots,
marcação de integração e dados/access/cache fornecidos pelo Drupal LMS. A
variante sobreposta segue reservada a mídias editoriais apropriadas, sem forçar
recorte de logos de projeto ou depender de URL de imagem do curso.

### Consumidores UI Patterns

Adotar UI Patterns em um consumidor novo somente quando houver uma necessidade
real e repetível de montagem editorial. Confirmar que o contrato do SDC é neutro
e estável, que a integração preserva render arrays/access/cache e que Bootstrap,
Core ou um consumidor existente não resolvem o caso. Registrar consumidor e
story no mesmo commit. Não converter displays em lote nem substituir VVJB/VVJS,
Form API, view modes ou lógica do Portal por conveniência.

## Variants

Drupal Core suporta variants SDC em 11.2+.

Usar variant quando:

- semântica é a mesma;
- estrutura principal é a mesma;
- diferença é uma apresentação nomeada e finita.

Não usar variants para codificar regra de negócio ou criar combinatória
arbitrária de opções.

## Assets

- foundations realmente globais ficam em `aculta420/global`;
- CSS global não significa CSS sem ownership: cada arquivo representa uma responsabilidade;
- não manter `css/style.css`, `css/responsive.css` ou outro catch-all residual;
- CSS/JS exclusivo de SDC fica no diretório do componente;
- integrations/patterns usam libraries contextuais quando o consumidor é identificável; o carrossel editorial é o caso-base;
- evitar asset global “por conveniência”.

## Branding assets

Assets técnicos do tema ficam em:

`assets/branding/aculta420/`

Originais e exports devem permanecer separados. Alterar nome de arquivo não
significa redesenhar identidade visual.

Branding de purpose é opcional e deve respeitar o fallback documentado em
[shell.md](shell.md).

## Anti-regressão

Não:

- introduzir cor literal duplicada quando existe token;
- espalhar palette primitives por componentes quando já existe token semântico;
- codificar cores de Domain purpose ou hostname no tema;
- criar JavaScript de color-mode antes da fase do seletor/persistência;
- criar nova fonte/tipografia sem decisão;
- embarcar Bootstrap novamente;
- usar utilitário Bootstrap para substituir contrato semântico necessário;
- criar motion incompatível com reduced-motion.

## Código no Aculta Portal, Design no ACULTA420

> **Código no Aculta Portal, Design no ACULTA420.**

Toda a aparência do Portal vive aqui. O módulo marca elementos com `data-portal-ui="token"`; `src/Hook/PortalUiHooks.php` (mapa `TOKENS`) troca cada token pelas classes do ACULTA420. Novo token = nova entrada no mapa, com o nome semântico.

Toda a aparência do Portal vive aqui: templates Twig de apresentação (shell da Minha conta, sanfona, modal de termos,
editor de foto, caixa de status do carrinho), CSS e o JS de layout (`js/address-layout.js`). O tema sobrescreve os
templates do módulo com o mesmo nome; o módulo mantém fallback neutro. O tema não tem rota, `form_alter`, serviço nem
chamada ao módulo. Os atributos `data-portal-account-*`, `data-aculta-*` e `data-portal-cart-status*` são contrato de
comportamento: não remova ao mudar o visual. Inventário: `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Ícones: Bootstrap Icons (regra permanente)

- Ícones de interface são **Bootstrap Icons** (`<i class="bi bi-…" aria-hidden="true">`). Não usar outro pacote nem SVG
  solto para ícone. Exceção: logo de marca de terceiros em `assets/brands/<marca>/`, com `alt` do nome da marca
  (hoje: `assets/brands/mercado-pago/mercado-pago.svg`, na caixa de pagamentos de Meus pedidos).
- Gate: `php scripts/validate-module-theme-boundary.php`, seção 12.

## Diálogo da busca

O popup acionado pela barra multidomínio usa Drupal Dialog e é centralizado nos
dois eixos da viewport pelo seletor `[data-portal-ui="search-popup"]` em
`css/components/dialog.css`. Largura e altura máximas preservam margem de tela
em dispositivos menores; o tema controla apenas a apresentação, e o Portal
continua responsável pela rota e pelo formulário.

## Catálogo Commerce

A View `aculta_shop_catalog` solicita o view mode Commerce `catalog`. O template
`commerce-product--produto-loja.html.twig` apresenta esse modo usando o SDC
`aculta420:card`, com a galeria renderizada pelo Portal, título, resumo, preço,
formulário Commerce e link para o detalhe. O modo completo do produto mantém a
composição existente de galeria e abas.

## Cores só em tokens (regra permanente)

- Fora de `css/tokens.css`, nenhum CSS do tema usa cor literal (hex, `rgb()`, `hsl()`, `color-mix()` etc.). Use um token
  semântico. O gate `scripts/validate-module-theme-boundary.php` (seção 12c) reprova cor literal.
- Pares de texto e fundo andam juntos (ex.: `--aculta-interactive-text` sobre `--aculta-interactive-bg`;
  `--aculta-interactive-hover-text` sobre `--aculta-interactive-hover-bg`). Nunca combine um texto de um par com o fundo de outro.
- **Status** (novos): `--aculta-status-{error|ok|warning}-{text|surface|border}`. Texto de status sobre a superfície
  de status: claro 5,56:1 (erro), 6,86:1 (ok), 7,55:1 (atenção); escuro 7,17:1, 7,16:1, 9,37:1.
- **Sobreposição** (fundo de diálogo): `--aculta-scrim` (claro e escuro).



## Wiki420

A apresentação enciclopédica da Wiki usa o pattern experimental wiki-entry,
a identidade tipográfica e as superfícies semânticas ACULTA420. Os links usam o
token de link do tema; divisórias, painéis e foco usam tokens semânticos. Não
copiar a paleta literal da Wikipedia nem criar seletores de modo fora de
tokens.css. Ver components.md e docs/portal/WIKI.md.

## Tokens não documentados até 2026-10-14

Levantados na varredura de 2026-10-14: declarados em `css/tokens.css` e sem entrada neste documento. Os valores são os declarados no bloco `:root`; alias (`var(--...)`) aponta para o token de origem. "Uso" é o número de arquivos de tema (CSS, componentes e templates) que o referenciam, fora de `tokens.css`. Uso 0 significa token declarado para estado futuro ou alias sem consumidor hoje, e não deve ser removido sem verificar o contrato de componente.

| Token | Valor declarado | Uso (arquivos) | Família |
|---|---|---|---|
| `--aculta-border` | `var(--aculta-border-default)` | 0 | Bordas: cor padrão e variantes (acento, forte). |
| `--aculta-border-accent` | `var(--aculta-green)` | 11 | Bordas: cor padrão e variantes (acento, forte). |
| `--aculta-border-default` | `rgba(12, 60, 41, 0.16)` | 14 | Bordas: cor padrão e variantes (acento, forte). |
| `--aculta-border-strong` | `rgba(12, 60, 41, 0.45)` | 7 | Bordas: cor padrão e variantes (acento, forte). |
| `--aculta-button-hover-bg` | `var(--aculta-interactive-hover-bg)` | 0 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-hover-text` | `var(--aculta-interactive-hover-text)` | 0 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-primary-bg` | `var(--aculta-interactive-bg)` | 1 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-primary-text` | `var(--aculta-interactive-text)` | 1 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-secondary-bg` | `transparent` | 0 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-secondary-border` | `var(--aculta-text-primary)` | 0 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-button-secondary-text` | `var(--aculta-text-primary)` | 0 | Botões: alias dos estados interativos (primário, secundário, hover). |
| `--aculta-control-border` | `var(--aculta-border-strong)` | 2 | Borda de controles de formulário. |
| `--aculta-dialog-accent` | `#f7d64a` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-control-bg` | `#e9f1fc` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-control-text` | `#111111` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-primary-bg` | `#f5c93a` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-primary-text` | `#1a1a1a` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-secondary-bg` | `#fdf8ea` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-secondary-text` | `#163d2a` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-surface` | `#6b9a2c` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-dialog-text` | `#ffffff` | 1 | Diálogos (popups): cores literais da paleta do diálogo, por papel. |
| `--aculta-focus` | `var(--aculta-focus-ring)` | 0 | Foco visível (anel de foco). |
| `--aculta-focus-ring` | `var(--aculta-green-dark)` | 16 | Foco visível (anel de foco). |
| `--aculta-font-body` | `"Inter", sans-serif` | 11 | Famílias tipográficas (corpo e títulos). |
| `--aculta-font-display` | `"Oswald", sans-serif` | 18 | Famílias tipográficas (corpo e títulos). |
| `--aculta-interactive-active-bg` | `var(--aculta-yellow)` | 12 | Estados interativos (ativo, borda). |
| `--aculta-interactive-active-text` | `var(--aculta-green-dark)` | 5 | Estados interativos (ativo, borda). |
| `--aculta-interactive-border` | `var(--aculta-yellow)` | 8 | Estados interativos (ativo, borda). |
| `--aculta-link` | `var(--aculta-text-primary)` | 5 | Links: cor do texto e variante editorial (vermelho) e hover. |
| `--aculta-link-editorial` | `var(--aculta-red)` | 0 | Links: cor do texto e variante editorial (vermelho) e hover. |
| `--aculta-link-editorial-hover` | `var(--aculta-text-primary)` | 1 | Links: cor do texto e variante editorial (vermelho) e hover. |
| `--aculta-motion-quick` | `150ms` | 1 | Duração curta de transição (150ms), ao lado de --aculta-motion-fast. |
| `--aculta-nav-current-bg` | `var(--aculta-yellow)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-nav-current-text` | `var(--aculta-interactive-active-text)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-nav-current-underline` | `var(--aculta-text-accent)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-nav-hover-bg` | `var(--aculta-interactive-hover-bg)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-nav-hover-text` | `var(--aculta-interactive-hover-text)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-nav-hover-underline` | `var(--aculta-text-inverse)` | 0 | Navegação: atual e hover (fundo, texto, sublinhado). |
| `--aculta-shadow-hover` | `0 0.125rem 0.5rem rgba(12, 60, 41, 0.08)` | 3 | Sombra no hover. |
| `--aculta-shell-active-bg` | `var(--aculta-interactive-active-bg)` | 0 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-shell-active-text` | `var(--aculta-interactive-active-text)` | 0 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-shell-border` | `var(--aculta-border-default)` | 1 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-shell-domain-text` | `var(--aculta-text-primary)` | 3 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-shell-institution-text` | `var(--aculta-text-primary)` | 1 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-shell-logo-filter` | `brightness(0)` | 3 | Cabeçalho e barra institucional: bordas, textos, filtro do logo, estado ativo. |
| `--aculta-space-section` | `clamp(2.25rem, 4.5vw, 4.25rem)` | 6 | Espaço vertical entre seções (fluido). |
| `--aculta-status-error-border` | `#b3261e` | 1 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-error-surface` | `#fde8e7` | 2 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-error-text` | `#b3261e` | 2 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-ok-border` | `#2e7d32` | 1 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-ok-surface` | `#e6f4ea` | 3 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-ok-text` | `#1e5e2f` | 2 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-warning-border` | `#c28a00` | 1 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-warning-surface` | `#fff4cc` | 1 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-status-warning-text` | `#624b00` | 1 | Estados (ok, erro, atenção): texto, superfície e borda. |
| `--aculta-surface-interactive` | `var(--aculta-surface-raised)` | 3 | Superfícies: interativa, muted e RGB da página (para transparências). |
| `--aculta-surface-muted` | `var(--aculta-cream)` | 11 | Superfícies: interativa, muted e RGB da página (para transparências). |
| `--aculta-surface-page-rgb` | `242, 247, 240` | 0 | Superfícies: interativa, muted e RGB da página (para transparências). |
| `--aculta-text-accent` | `#b23a26` | 6 | Textos: destaque, inverso e muted. |
| `--aculta-text-inverse` | `var(--aculta-white)` | 7 | Textos: destaque, inverso e muted. |
| `--aculta-text-muted` | `var(--aculta-green-dark)` | 2 | Textos: destaque, inverso e muted. |

Novo token no 0.6.0-dev.22: `--aculta-motion-quick: 150ms`, usado em `css/components/portal-account.css` para fechar a DT-T19 (durações fora de tokens).
