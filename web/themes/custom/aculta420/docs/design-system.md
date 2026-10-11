# Design system

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

## VVJB primeiro (carrossel)

- O carrossel padrão do ACULTA é o VVJB (módulo `vvjb`, estilo de view `views_vvjb`). A home usa essa engine na view `home_editorial_highlights`.
- Cada slide do VVJB é uma linha de entidade renderizada por view. Conteúdo que vira carrossel deve ser modelado como entidade (nó, mídia ou entidade própria) e exibido por uma view.
- O componente `aculta420:carousel` é só a moldura que envolve o markup do VVJB. Não criar carrossel novo com Bootstrap nem JavaScript próprio.
- Exceção: carrossel fora do VVJB só com decisão registrada no `DEBT-REGISTER.md` ou no roadmap do tema, com justificativa.
- Motivo: uma só engine de carrossel, com navegação, teclado, reduced-motion e foco já tratados (`editorial-carousel.css` e `editorial-carousel.js`).

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

## Primitives

Primitive é uma categoria do design system, não sinônimo de SDC.

Exemplos:

- Button: primitive Bootstrap/CSS global;
- Category label: candidato a SDC;
- Icon: futuramente via Core Icon API/UI Icons;
- Media: só abstrair quando cards comprovarem contrato comum.

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
