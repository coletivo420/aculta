# ACULTA Portal + ACULTA420 Component Design System

## Objetivo

Este documento define como o `aculta_portal` consome o design system já
estabelecido pelo tema `aculta420`.

A arquitetura visual canônica do tema está em:

`web/themes/custom/aculta420/docs/architecture.md`.

O Portal não mantém um design system concorrente.

## Camadas

```text
módulo funcional
  User / Profile / Commerce / LMS / Wiki / Forum
                    |
                    v
              aculta_portal
    access + cache + presenter + purpose
                    |
                    v
         contrato visual reutilizável
                    |
                    v
             tema ACULTA420 SDC
                    |
                    v
               Bootstrap 5
```

## Regra principal

O Portal conhece **significado e estado**.

O componente conhece **apresentação**.

Exemplo de course card:

Portal conhece:

- título autorizado;
- status LMS;
- score quando permitido;
- URL correta no COURSES purpose;
- cache tags;
- CTA disponível ou ausente.

SDC conhece:

- slot de título;
- badge de status;
- região de progresso;
- CTA;
- spacing;
- layout responsivo.

O SDC não carrega Group, CourseStatus ou User.

## Presenter/View-model

Preferir services/presenters que retornem estruturas simples e renderables
seguros.

Requisitos:

- access antes de metadata;
- cache tags/contexts/max-age preservados via Render API e `CacheableMetadata`;
- URLs construídas por purpose;
- formatted text continua render array;
- menus/actions/media permanecem renderables quando isso preserva access/cache;
- não converter markup filtrado em string crua;
- não incluir entidades inteiras como prop de SDC;
- não usar SDC como service locator.

### Fronteira com SDC

No shell multidomínio, o Portal **não instancia componentes do tema**. Ele não
deve retornar `#component: aculta420:...` nem conhecer IDs de SDC do provider
visual.

O Portal entrega:

- identidade escalar/estruturada, adequada a futuros props;
- regiões renderizáveis, adequadas a futuros slots;
- cacheability aplicada ao render tree.

O ACULTA420 decide depois se usa Twig direto, Bootstrap ou um SDC e mapeia os
dados neutros para props/slots. Isso mantém a dependência na direção correta:

```text
Core/contrib → Portal → contrato neutro → tema → SDC/Bootstrap
```

## Shell multidomínio

O shell público segue a mesma fronteira Portal → apresentação.

```text
Domain
  ↓
DomainPurposeManager
  ↓
aculta_portal
  ↓
domain_presentation/render array
  ↓
ACULTA420
```

O Portal pode preparar `purpose`, títulos, URL inicial, branding, navegação e
accent. O tema não resolve hostname, Domain access ou storage. Nenhuma entidade
`Domain` deve ser passada diretamente para SDC/Twig.

Branding específico é opcional. O contrato deve permitir fallback para ACULTA e
depois para título textual, sem impedir a criação de um novo purpose.

A arquitetura visual planejada está em
`web/themes/custom/aculta420/docs/shell.md`.

O inventário autoritativo da fronteira e o contrato alvo da linha 0.2-B estão
em [DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md).

## Bootstrap primeiro

Antes de criar markup custom, verificar se Bootstrap já fornece o primitive ou
comportamento necessário:

- card;
- badge;
- alert;
- list group;
- progress;
- pagination;
- collapse;
- offcanvas;
- modal;
- nav;
- dropdown;
- spinner/placeholders;
- grid/utilities.

A identidade ACULTA420 vem dos tokens e contratos do tema, não da reinvenção do componente estrutural.

## SDC primeiro para composição reutilizável

Criar/usar SDC quando a apresentação:

- se repete;
- tem contrato de slots/props estável;
- possui estados visuais conhecidos;
- tem ownership de assets claro;
- melhora consistência entre Portal, Views e conteúdo.

Não criar SDC apenas para embrulhar uma `<div>` usada uma vez.

## Famílias Portal

### Conta

A matriz detalhada está em
[GUARDRAILS.md](GUARDRAILS.md).

Candidatos atuais:

- account shell;
- account identity;
- summary card;
- course card;
- action list;
- status badge;
- empty state;
- data section;
- security card;
- integration card;
- support/order summary;
- participation card;
- photo editor.

A migração é incremental. Um SDC pode conter slots com Form API ou conteúdo
atualizado por AJAX; ele não substitui essas APIs.

### Cursos

Candidatos:

- course card;
- progress/status;
- course action.

A fonte continua Drupal LMS/Group.

### Wiki

Candidatos:

- contribution card;
- revision/status badge;
- content list/empty state.

A fonte continua Node/Taxonomy/Revisions.

### Fórum

Candidatos:

- forum topic card;
- participation item;
- reply metadata;
- follow/status action quando Flag existir.

A fonte continua Forum/Node/Comment/Taxonomy.

### Loja/Apoio

Candidatos:

- product card;
- order summary/status;
- payment/support status;
- empty state.

Commerce continua fonte de verdade.

## VVJB primeiro (carrossel)

- O carrossel padrão do ACULTA é o VVJB (módulo `vvjb`, estilo de view `views_vvjb`). A home usa essa engine na view `home_editorial_highlights`.
- Cada slide do VVJB é uma linha de entidade renderizada por view. Conteúdo que vira carrossel deve ser modelado como entidade (nó, mídia ou entidade própria) e exibido por uma view.
- O componente `aculta420:carousel` é só a moldura que envolve o markup do VVJB. Não criar carrossel novo com Bootstrap nem JavaScript próprio.
- Exceção: carrossel fora do VVJB só com decisão registrada no `DEBT-REGISTER.md` ou no roadmap do tema, com justificativa.
- Motivo: uma só engine de carrossel, com navegação, teclado, reduced-motion e foco já tratados (`editorial-carousel.css` e `editorial-carousel.js`).

### Exceção semântica: sequência de fotos do álbum com VVJS

- A View `aculta_album_fotos` usa VVJS (`views_vvjs`) como slideshow de fotos paginado; não é carrossel editorial e não altera a regra Home/Projetos, que permanece VVJB.
- PhotoSwipe continua sendo o visualizador ampliado das imagens. VVJS é dono do slide atual, controles, teclado, toque e redução de movimento; o tema sincroniza miniaturas pela API/evento público VVJ Core.
- Conteúdo, ordem, pager, acesso, cache e processamento das imagens continuam nos módulos Core/contrib e no submódulo `aculta_gallery`/`aculta_image`. ACULTA420 só compõe a aparência com tokens.
- Não usar VVJS e VVJB para renderizar a mesma sequência ou criar outro engine de carrossel.

## AJAX

Componentes devem sobreviver a reattachment por Drupal behaviors.

Preferência:

- Views AJAX;
- Form API AJAX;
- Drupal Ajax API;
- Core HTMX quando apropriado.

Um SDC não deve exigir transporte AJAX próprio.

## Acessibilidade

Cada componente Portal deve cobrir, conforme o caso:

- keyboard;
- focus-visible;
- accessible name;
- heading hierarchy;
- aria-current/status;
- reduced motion;
- empty/loading/error state;
- mobile;
- títulos longos;
- contraste.

Não substituir acessibilidade funcional já fornecida por Core/contrib/Bootstrap.

## Cache e access

A camada visual não corrige falhas de cache ou access.

Presenter/Portal deve fornecer o resultado correto antes da renderização.

Para páginas privadas, revisar pelo menos:

- user;
- user.permissions;
- domain/purpose;
- entity cache tags;
- max-age.

## Anti-regressão

Não:

- copiar HTML Bootstrap repetidamente em controllers;
- criar CSS exclusivo do Portal para reproduzir componente já existente;
- mover regra LMS/Commerce/Wiki/Forum para Twig/SDC;
- adotar outro framework de componentes por conveniência;
- criar dependência em `drupal/bootstrap_components` sem decisão explícita;
- converter em massa código do tema já refatorado.

Sempre preservar a fronteira:

```text
dados/regras -> Portal -> apresentação -> SDC/Bootstrap
```
