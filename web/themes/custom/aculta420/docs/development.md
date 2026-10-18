# Desenvolvimento

## Stack

- Drupal 11;
- Bootstrap5 base theme;
- Twig;
- Core SDC;
- CSS nativo;
- ES6;
- Drupal behaviors;
- `once()`;
- Drupal libraries.

Sem Node/Vite/Sass/PostCSS por padrão.

## Machine name

Tema: `aculta420`.

Hooks:

`src/Hook/ThemeHooks.php` com `#[Hook]` e DI/autowiring. A Foundation não usa
arquivo `.theme` procedural.

Libraries:

`aculta420/<library>`

SDCs:

`aculta420:<component>`

Settings:

`aculta420.settings`

Não reintroduzir provider `aculta`.

## Estrutura de CSS

O CSS global é dividido por responsabilidade explícita:

- tokens;
- base;
- layout;
- shell/component CSS;
- formulários;
- Conta/apresentação;
- Drupal/Bootstrap integration.

Não existem `css/style.css` ou `css/responsive.css` genéricos/catch-all. Regra nova deve entrar no arquivo
da responsabilidade que a possui; se nenhuma responsabilidade existente servir,
criar uma unidade nomeada e documentada em vez de recriar um arquivo residual.

Mover CSS para SDC somente quando ownership exclusivo estiver provado.

## JavaScript

Regras:

- Drupal behaviors;
- `once()`;
- attach/detach compatible;
- progressive enhancement;
- Bootstrap/contrib continuam donos de suas engines;
- nada de listener global duplicado;
- assets específicos carregam contextualmente quando possível.

## Allowlist de overrides Twig

A Foundation mantém somente overrides com delta comprovado em relação a
Core/Bootstrap5/contrib:

| Override | Motivo atual |
| --- | --- |
| `page.html.twig` | compor o shell público e regiões existentes |
| `content/off-canvas-page-wrapper.html.twig` | trocar `h-100` por `min-vh-100` e `flex-shrink-0`: sem isso o header sticky fica preso à primeira tela (medido em 0.2-F) |
| `block--system-branding-block.html.twig` | wrapper visual ACULTA420 + fallback textual |
| `navigation/breadcrumb.html.twig` | apresentar o título atual preparado pelo Portal |
| `block--block-content--type--aculta-institution.html.twig` | view modes institucionais específicos |
| `node--editorial-highlight.html.twig` | presenter do SDC `editorial-card` |
| `node--project--teaser.html.twig` | teaser de projeto existente até o Card System v1 |
| `views-view-vvjb.html.twig` | delta de integração/acessibilidade e library contextual do VVJB |

Novo override exige comparação com o template upstream da versão instalada e
uma justificativa documental. Override sem delta real deve ser removido.

## Twig

Twig apresenta. Não decide regra de negócio.

Overrides devem:

- existir por delta real;
- preservar attributes/cache/access;
- preferir herança upstream quando markup custom não agrega valor;
- evitar service calls e entity loading.

## PHP do tema

Preprocess é aceitável para adaptação de apresentação.

Integração de domínio/business rules pertence a `aculta_portal`.

Se a lógica começa a conhecer:

- Domain purpose complexo;
- Commerce;
- LMS;
- storage;
- autorização;

ela provavelmente está no lugar errado.

## Shell multidomínio

> Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.

> Color mode no ACULTA420 é uma variação de tokens, não uma variação de layout.

Ao evoluir o shell:

- usar Domain purpose como chave funcional; nunca hostname hardcoded;
- manter `DomainPurposeManager` e resolução funcional no `aculta_portal`;
- passar ao tema apenas contexto de apresentação preparado;
- `domain_presentation.identity` pode ser adaptado pelo `ThemeHooks` apenas para normalização visual; identidade incompleta falha para `NULL`, sem lookup funcional no tema;
- `purpose` não deve ser emitido no DOM sem consumidor real e nunca vira branch de layout/cor/comportamento; B.3 deriva somente `label` + `home_url` para o fallback textual;
- nunca passar entidade `Domain` diretamente para Twig/SDC;
- manter branding de purpose opcional com fallback ACULTA/texto;
- manter um único shell e variar dados, não criar headers paralelos;
- componentes novos usam semantic tokens quando já houver token para sua função;
- palette primitives permanecem em `tokens.css`, sem espalhar semântica contextual;
- color modes trocam tokens, nunca markup; não adicionar seletor/persistência antes da fase prevista;
- purpose não escolhe cores e branding por hostname no tema;
- color modes alteram tokens, não markup/geometria;
- modos compartilham tipografia, espaçamento, dimensões, grid, breakpoints,
  posicionamento, shell, navegação e comportamento;
- componentes não consultam modo de cor ou `prefers-color-scheme`; a escolha
  visual é resolvida em `css/tokens.css`;
- seletores dark fora de `css/tokens.css` são proibidos pelo gate;
- asset de logo light/dark só pode variar se necessário à legibilidade, mantendo
  espaço, dimensão e layout iguais;
- preferir `position: sticky` a `fixed` como ponto de partida;
- reutilizar Bootstrap Collapse/Offcanvas em vez de criar engine JS própria.

O shell multidomínio já está implementado (linha 0.2.0). Mudanças nele seguem o contrato em
[shell.md](shell.md) e a classificação em [versioning.md](versioning.md).

## Configuração

Renomear machine name do tema exige sincronizar:

- `core.extension`;
- `system.theme`;
- theme settings;
- block placements;
- libraries/component provider IDs.

IDs históricos de conteúdo/config não são renomeados sem benefício funcional.
Para placements de blocos customizados, `plugin`, `settings.id` e a dependência
de conteúdo devem apontar para o mesmo UUID existente; tema e região também
precisam corresponder ao provider e às regiões declaradas pelo ACULTA420. O gate
da Foundation verifica tanto a configuração ativa quanto `config/sync`.

## Workflow

Para cada mudança:

1. atualizar docs relevantes;
2. fazer inventário de consumidores;
3. alterar uma responsabilidade por commit;
4. validar staticamente;
5. `drush cr`;
6. validar config;
7. testar páginas representativas;
8. comparar visual quando houver mudança visual.

## Gate de Foundation

Após qualquer mudança estrutural do tema:

```sh
vendor/bin/drush php:script validate-aculta420-foundation --script-path=../scripts
```

O gate falha se reaparecerem provider legado, arquivo `.theme`, catch-all CSS,
asset web sem contrato, dependência direta do tema no Portal ou library quebrada.

Para tokens e fronteiras visuais da linha 0.2:

```sh
php scripts/validate-aculta420-sdc-schemas.php
php scripts/validate-browser-validators.php
php scripts/validate-institution-content.php
php scripts/validate-lms-skin.php
php scripts/validate-aculta420-design-foundations.php
php scripts/tests/validate-aculta420-design-foundations-test.php
php scripts/tests/validate-aculta420-shell-contract-test.php
vendor/bin/drush php:script validate-aculta420-shell-contract --script-path=../scripts
```

O analyzer B.4 em `scripts/lib/Aculta420ShellContractAnalyzer.php` é compartilhado entre fixtures e Runtime; não duplicar regras de Domain/hostname/purpose branching em um segundo scanner.

O validator e seus fixtures são PHP independente do Drupal e não alteram Runtime.
Execute em Linux:

```sh
php scripts/validate-aculta420-design-foundations.php
php scripts/tests/validate-aculta420-design-foundations-test.php
```

No Windows, execute os mesmos scripts com caminhos PHP nativos, por exemplo:

```powershell
php .\scripts\validate-aculta420-design-foundations.php
php .\scripts\tests\validate-aculta420-design-foundations-test.php
```

`scripts/lib/Aculta420DesignFoundationsAnalyzer.php` separa análise de CSS,
resolução de tokens, avaliação de contraste, inspeção de branches e validação
de caminhos. O contrato de modo é:

> Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.

O scanner de CSS remove comentários antes de inspecionar seletores e reconhece
atributos de tema dark/light com os operadores CSS `=`, `~=`, `|=`, `^=`,
`$=` e `*=`, classes `.dark`/`.light` e variantes explícitas, incluindo
flags de comparação `i`/`s`, além de `prefers-color-scheme`. Em `tokens.css`, somente os dois blocos
top-level `:root, [data-bs-theme="light"]` e `[data-bs-theme="dark"]` são
permitidos; qualquer outra regra, inclusive CSS estrutural sem tokens, é
rejeitada. Cada bloco pode conter apenas custom properties. Regras ou
seletores estruturais por modo, inclusive dentro de `tokens.css`, falham.

O scanner Twig verifica condições `if`/`elseif` e ternários em tags `{% ... %}`
e `{{ ... }}`. Em ternários, somente predicados são avaliados; quando há um
ternário dentro de uma condição `if`, o prefixo externo da condição também é
preservado para não perder uma decisão de modo que o envolva. Valores de
resultado como `{% set label = compact ? theme : 'dark' %}` não são decisões
por modo. Em condições Twig, arms do ternário são removidos para análise,
preservando predicados antes e depois dele. O scanner PHP
usa `token_get_all()` para condições `if`/`elseif`, `switch` com `case` nas
formas com chaves e `endswitch` aninhado, incluindo labels encerrados por `:`
ou `;`, e `match` usando apenas condições de
arms antes de `=>`; textos nos resultados não são ramificações.
comentários são removidos das expressões avaliadas. O scanner JavaScript
reconhece `if` e discriminantes de `switch` com parênteses balanceados,
`switch`/`case`, ternários multiline/nested,
alternância de classes/atributos e escritas em `dataset.theme`,
`dataset.bsTheme`, `dataset.colorMode` ou `dataset.colorScheme`, mesmo quando o
valor vem de variável ou função. Seletores de classe também são inspecionados
dentro de pseudo-classes funcionais como `:where()` e `:is()`.
Decisões Twig/PHP/JavaScript reconhecem também `colorScheme`/`color_scheme` e
getters `getColorScheme()`. O scanner JavaScript preserva ternários com arms
objeto, ignora `?.` e `??` ao contar níveis de ternário, e reconhece literais
regex simples ao balancear condições `if`. Também protege todas as classes de
modo conhecidas em `classList` e atribuições simples/compostas de `className`.
Seletores de atributo quoted ou unquoted com operadores de substring são
considerados perigosos quando o valor parcial pode selecionar `dark` ou `light`.
JavaScript inline em `<script>` dentro de Twig também passa pelo scanner JS.
O balanceador de `if` e `switch` ignora regex literals simples com escapes e
classes de caracteres; não implementa o lexer completo de ECMAScript.
Blocos `<style>` inline em Twig passam pelo detector de seletores de modo.
Scripts inline também são verificados contra inicialização ou persistência
prematura; o uso de `localStorage` para uma chave sem relação com tema/cor é
permitido. Mutação de classes por valores literais ou getters/variáveis de modo
é proibida. Identificadores e palavras entre aspas são avaliados separadamente:
`label === 'dark theme'` não é decisão de color mode.
Em `switch`, a análise considera o discriminante e as expressões dos labels
`case`, não texto arbitrário nos consequentes; PHP aceita a forma `endswitch`.
Writes simples e compostos (`=`, `??=`, `||=`, `&&=`) em dataset de modo e
chamadas opcionais (`setAttribute?.()`) ou regulares para atributos de modo e
atribuições `className` com classes protegidas são proibidos mesmo com valores
dinâmicos quando aplicável. O scanner também mantém isolados os labels de
switches aninhados nos dois estilos PHP. O scanner JavaScript é delimitado e
não substitui um parser completo.

O gate remove comentários CSS antes da checagem de literais. Fora de
`tokens.css`, rejeita cores hexadecimais e funções CSS de cor (`rgb`, `hsl`,
`hwb`, `lab`, `lch`, `oklab`, `oklch`, `color()`, `color-mix()` e
`device-cmyk()`), além de nomes de cores CSS em declarações de propriedades
que aceitam cor. A lista de propriedades coberta inclui `color`, backgrounds,
borders, outlines, sombras e `fill`/`stroke`; não é um parser completo de
valores CSS e não classifica identificadores arbitrários como cores. Fixtures
cobrem comentários, `oklch()`, `lab()` e nome literal `red`.

| P2 da revisão da PR #80 | Fixture que prova a regressão |
| --- | --- |
| P2-01 seletor/regra estrutural em `tokens.css` | `Dark selector in tokens stylesheet`; `Structural declaration inside dark token block` |
| P2-02 arms `case` PHP | `PHP switch case arm` |
| P2-03 arms `case` JavaScript | `JavaScript switch case arm` |
| P2-04 ternário em statement Twig | `Twig statement ternary` |
| P2-05 aliases de tokens novos | `new ACULTA token alias must resolve`; ciclos e referências ausentes |
| P2-06 atribuição `dataset` | `JavaScript dataset.theme assignment`; `JavaScript dataset.bsTheme assignment` |
| P2-07 raízes Windows | matriz de drive letter, UNC, caminho relativo e traversal |
| P2-08 Bootstrap por modo | `dark-only Bootstrap body mapping regression` |
| Revisão PR #83: object literal e predicate Twig externo | `JavaScript ternary with object arms`; `Twig enclosing mode predicate around unrelated ternary` |
| Revisão PR #83: case `;` e `?.`/`??` | `PHP semicolon case terminator excludes consequent`; `JavaScript optional chaining case does not consume consequent` |
| Revisão PR #83: classes compostas e classes protegidas | fixtures `className compound`, `className logical` e `classList is-dark` |
| Revisão PR #83: getter de color scheme | fixtures do getter em Twig, PHP e JavaScript |
| Revisão PR #83: regex literal e seletores parciais | `regex parentheses`; prefixo/sufixo/substr de atributo |
| Revisão PR #83: união de tokens por modo | `dark-only token was not required to resolve in light mode` |
| Revisão PR #83: sufixo Twig após ternário | `Twig suffix mode predicate after unrelated ternary` |
| Revisão PR #83: regex no corpo de switch | `JavaScript switch scans mode case after regex brace` |
| Revisão PR #83: alias `VAR()` e RGB da superfície | fixtures `case-insensitive VAR()` e `ACULTA surface RGB companion mismatch` |
| Revisão PR #83: JS inline e CSS arbitrário em tokens | fixtures `Twig inline script` e `ordinary structural CSS rule in tokens stylesheet` |
| Revisão PR #83: CSS import e inline style | `tokens stylesheet cannot import another stylesheet`; `Twig style block cannot add a mode selector` |
| Revisão PR #83: persistence, classes dinâmicas e falsos positivos textuais | fixtures `inline storage`, `dynamic className/classList`, `noticeDismissed` e `string-only mode` |
| Revisão PR #83: regex URL | `regex URL slashes do not truncate JavaScript if condition` |
| Revisão PR #83: CSS comentário e sintaxe de cor moderna | fixture positiva `CSS comments do not count as raw colors`; negativas `oklch`, `lab` e nome CSS `red` fora de `tokens.css` |

Também há casos válidos para custom property, comentários, branches não
relacionados ao modo, e casos inválidos para seletor `.dark`,
`[data-theme="dark"]`, duplicatas, contraste insuficiente e superfície verde.
Cada fixture inválida exige exit code diferente de zero; fixtures válidas
exigem sucesso.

O analisador CSS cobre regras planas, blocos de modo top-level, blocos aninhados
usados para detectar seletores e `var(--token)` sem fallback. Não é um parser
CSS completo; `var()` com fallback, CSS nesting fora do contrato e seletores
dinâmicos não convencionais ficam fora do subconjunto. PHP analisa branches por
tokens; Twig e JavaScript usam scanners deliberadamente delimitados, não ASTs
completas. Construções dinâmicas que ocultem o identificador de modo não são
inferidas. Ampliações exigem fixture positiva e negativa para cada forma nova.

Todos os tokens `--aculta-*` e `--bs-*` declarados em qualquer bloco são
unidos e resolvidos em ambos os modos; `var()` é reconhecido sem diferença de
caixa no nome da função, preservando a caixa do nome da custom property. O RGB
da superfície de página também deve corresponder ao token de cor. Um token declarado somente em dark deve
também ser resolvível em light. O contrato enumera tokens de cor ACULTA/Bootstrap e tokens
RGB, exigindo que cada RGB Bootstrap corresponda à cor companheira; valores
inválidos (incluindo alpha não numérico), duplicatas, ciclos e referências não
resolvidas falham.
Os tokens base de cor Bootstrap e a borda translúcida também têm fontes/valores
aprovados explicitamente por
modo (por exemplo, primary → `--aculta-green`); mudar simultaneamente a cor e
seu RGB para outro valor não contorna o gate. Os resultados de `--bs-dark`,
`--bs-gray`, `--bs-black` e da borda inválida de formulário têm mapeamentos
específicos de light/dark, registrados no próprio contrato do analisador.
Comparações de cor normalizam os canais numericamente, portanto `rgb()` e hex
equivalentes são tratados como a mesma cor. O parser aceita formas RGB legacy
com vírgulas ou a forma moderna separada por espaços, com slash-alpha opcional
somente após os três canais; não aceita mistura de vírgulas legacy com
slash-alpha moderno.
Cores de primeiro plano translúcidas são compostas sobre a superfície opaca
antes da medição WCAG; superfícies de contraste precisam ser opacas. Mappings
Bootstrap e pares RGB são conferidos separadamente em light e dark. Contrastes
seguem WCAG AA; a paleta charcoal/graphite é protegida por valores aprovados
explícitos.
Raízes aceitas são absolutas POSIX ou Windows (drive letter/UNC), sem segmento
`..`; o caminho é então canonicalizado com `realpath()` antes da leitura.
O subconjunto JavaScript não é um parser AST: a leitura de `switch` suporta
parênteses balanceados e strings, mas construções geradas dinamicamente e
sintaxe de template complexa ficam fora do contrato. Ao adicionar token de cor
ou forma de decisão, atualize a lista contratual e inclua fixtures válidas e
inválidas correspondentes.

## Testes mínimos

Para mudanças runtime:

- PHP lint;
- Twig/YAML sanity;
- cache rebuild;
- config status/import;
- MAIN;
- ACCOUNT;
- SUPPORT;
- COLETIVO420;
- WIKI420;
- SHOP;
- COURSES;
- desktop/mobile;
- teclado/foco;
- reduced-motion quando houver animação.

Para SDC:

- schema;
- empty/long content;
- slots ausentes opcionais;
- asset attachment;
- accessibility states.

## Release

Toda release do tema atualiza:

- `aculta420.info.yml`;
- `CHANGELOG.md`;
- `docs/roadmap.md`;
- documentação afetada.

Tags:

`aculta420-theme-vX.Y.Z`
