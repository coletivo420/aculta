# ADR-007: ACULTA420 Bootstrap Component Design System

Status: Accepted

Data: 2026-10-06

## Contexto

ACULTA420 0.1.0 nasce da base técnica do antigo tema `aculta`, já com Single-Directory Components (SDC) do Drupal Core e Bootstrap 5 como base estrutural/comportamental.

O projeto precisa evoluir o `aculta_portal` sem criar uma segunda linguagem
visual, duplicar markup Bootstrap ou deslocar regras de negócio para o tema.

## Decisão

O front-end público da plataforma adota o **ACULTA Bootstrap Component Design
System** como contrato visual comum.

Responsabilidades:

- **Bootstrap 5**: grid, utilities, estados, markup/behaviors estruturais e APIs
  públicas;
- **Drupal Core SDC**: contrato e empacotamento de componentes reutilizáveis;
- **tema `aculta420`**: tokens, identidade visual, componentes, patterns e shell;
- **`aculta_portal`**: dados, presenters/view-models, access, cache, Domain
  purpose e orquestração;
- **módulos funcionais**: fonte de verdade e regras de negócio.

A direção de dependência é:

```text
Core/contrib -> aculta_portal -> contratos visuais -> tema ACULTA420/SDC -> Bootstrap
```

SDCs não consultam entidades, services, storage ou regras de Domain.

## Integração Portal -> Design System

O Portal deve preferir produzir dados/render arrays semanticamente neutros e
deixar a camada de apresentação aplicar os SDCs do tema.

Quando uma rota pública exigir apresentação específica ACULTA, o contrato deve
ser explícito e documentado. Referências diretas a componentes
`aculta420:*` só são aceitáveis quando o acoplamento ao tema público é
intencional e não afeta admin/fallbacks.

O Portal não deve construir markup Bootstrap duplicado em controllers quando
um template/SDC resolve a apresentação.

## Famílias iniciais

A evolução do Portal deve reutilizar contratos comuns antes de criar uma nova
variante para cada subsistema:

- card;
- status badge;
- empty state;
- action list;
- content grid;
- pagination;
- progress/status;
- alert/feedback.

Especializações só entram quando existe diferença semântica real:

- course card;
- wiki contribution card;
- forum topic card;
- product/order card.

## Administração

Páginas administrativas continuam seguindo o tema/admin UI do Drupal.

O ACULTA420 Bootstrap Component Design System é o sistema de apresentação pública
e da experiência ACCOUNT; não deve sobrescrever gratuitamente o admin theme.

## Dependências

Não adicionar uma segunda suíte de design system sem nova ADR.

O projeto contrib `drupal/bootstrap_components` pode ser estudado como
referência, mas não é dependência aprovada. Na revisão de 2026-10-06 ele não
estava coberto pela Drupal Security Advisory Policy e duplicaria parte da
infraestrutura que o tema já possui com Bootstrap5 + Core SDC.

UI Suite Bootstrap e soluções semelhantes também não são migração implícita do
tema atual.

## Consequências

- a base madura do tema anterior é preservada, mas o provider público passa a ser `aculta420`;
- Portal e tema passam a compartilhar contratos previsíveis;
- novas telas Portal devem preferir composição a CSS/HTML ad hoc;
- business logic permanece fora do SDC;
- componentes podem ser melhor testados e documentados;
- assets específicos migram para SDC apenas quando ownership estiver claro;
- Bootstrap não deve ser duplicado nem reimplementado.

## Referências internas

- `web/themes/custom/aculta420/docs/architecture.md`
- `web/themes/custom/aculta420/docs/components.md`
- `docs/portal/COMPONENT-DESIGN-SYSTEM.md`
