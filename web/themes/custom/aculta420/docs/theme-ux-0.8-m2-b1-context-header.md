# THEME-UX 0.8 — M2/B1: Context Header transversal

**Data:** 2026-10-22 · **Estado:** contrato técnico de implementação; sem implantação e sem gate Runtime aprovado.
**Dependências:** [Auditoria estrutural M0-S](theme-ux-0.8-m0-s-structural-audit.md), [Roadmap visual](visual-cross-product-roadmap.md), [UI Suite](ui-suite-migration-plan.md), [Shell](shell.md).

## Decisão
O **cabeçalho multidomínio existente (Institution Bar + Domain Header)** permanece em todas as telas. Este padrão é exclusivamente um **cabeçalho contextual dentro da região de conteúdo**: título da aula, artigo, verbete ou discussão, contexto e ações autorizadas. Nunca imprimir logo institucional, menu de domínio ou utilitários globais.

## Evidência preliminar no código
- `aculta420:content-section` é SDC real, mas possui `title` com h2 e `heading` com h3 e propósito de seção editorial; **não o promover a H1 por conveniência**.
- `aculta420:card` e o catálogo UI Patterns são bases visuais reutilizáveis, mas não representam cabeçalho semântico de página.
- Breadcrumb e page title providos pelo Core podem resolver partes do problema; só criar `aculta420:context-header` se a auditoria de DOM/consumidores demonstrar lacuna.
- **Nenhuma rota/tela foi declarada consumidora verificada no Runtime** nesta fase.

## Contrato candidato SDC (não criar arquivos de componente ainda)
| Entrada | Tipo esperado | Responsável | Observação |
| --- | --- | --- | --- |
| `title` | string ou slot renderizável | provider da página | preferir elemento renderizável Core quando cache/markup exigir |
| `subtitle` | string opcional | provider | contextual, não identifica domain |
| `eyebrow` | string opcional | provider | módulo, seção ou categoria |
| `breadcrumbs` | slot renderizável | Core/Portal | acesso/URLs preservados |
| `back_link` | slot renderizável | provider | rota real; não depender apenas do histórico JS |
| `actions` | slot renderizável | provider | permissões e CSRF no backend, nunca no Twig |
| `heading_level` | enum restrito, somente se necessário | page builder | evitar H1 duplicado, não deixar editor escolher livremente |

O uso de um h1 será decidido pelo **dono do page title**, considerando o Core: ou o page title já apresenta o H1 e o SDC apresenta apenas contexto, ou um presenter autorizado transfere o título à região renderizável; **jamais dois H1 por duplicação de componentes**.

## UI Suite como requisito
1. Core SDC: `.component.yml` Core atual, schema tipado, props/slots explícitos, CSS local e tokens do tema.
2. UI Patterns: testar exposição/inserção em Views, blocos ou layouts onde houver consumidor real; **não reconfigurar displays sem necessidade**.
3. UI Styles: superfície, texto, borda e elevação semânticos disponíveis, sem CSS colorido exclusivo do LMS.
4. UI Patterns Library: story de aula, story editorial e estado sem ação, sem conteúdo protegido.
5. SDC Devel: diagnóstico, schema e warnings; qualidade Runtime pendente.
6. Views/Field/Fences: manter render arrays e wrappers reais; não duplicar breadcrumb e page title.

## Reutilização real obrigatória
- **Primeiro consumidor:** página GanjaLab420 com contexto real do curso ou aula.
- **Segundo consumidor:** uma página Wiki/Revista/Fórum efetivamente existente.
- Antes de qualquer código, identificar caminho de renderização, provider, acesso/cache, markup e se já existe solução SDC para cada.
- Não reutilizar Twig legado diretamente: migrar apresentação para SDC ou extensão tipada de componente SDC existente.

## Responsividade e acessibilidade
Mobile 320px: linha de retorno e título, ações secundárias recolhíveis **sem ocultar o header do domínio**; desktop 1280px: título/contexto à esquerda, ações à direita; mesma geometria claro/escuro. Ordem de leitura, link voltar nativo, foco visível, targets, zoom/reflow, sem JS, teclado e WCAG 2.2 AA.

## Próxima execução versionada
| Marco | Entrega | Gate |
| --- | --- | --- |
| M2-01 | inventário DOM de H1, breadcrumb, header e templates por purpose | Runtime/URLs reais |
| M2-02 | confirmar 2 consumidores e solução existente | provider + cache/access |
| B1-01 | decidir estender SDC conforme ou criar SDC `context-header` | justificativa e contrato |
| B1-02 | implementar SDC mínimo + stories + catálogo do tema | Core SDC/UI Patterns/UI Styles |
| B1-03 | aplicar a duas áreas e testar shell/no-JS/mobile/dark | evidência do Runtime |

**Escopo desta entrega:** planejamento e critérios. Nenhum novo componente ou alteração de rotas foi implementado.

## Auditoria de código B1 — continuação

[Inspeção de breadcrumb, headings e limites de reuso](theme-ux-0.8-b1-source-audit.md) confirma por código que o breadcrumb já tem template e estilo do tema, `content-section` usa H2/H3 e `product-tabs` possui semântica de painéis internos. Não criar outro breadcrumb ou forçar o SDC editorial a emitir H1. Consumidores reais e DOM ainda não confirmados no Runtime.

## Decisão revisada — ownership do H1 (2026-10-22)

**Não preservar obrigatoriamente a renderização nativa do H1.** O `Context Header` poderá assumir o **H1 canônico e visível** da página. O Drupal continua fonte de verdade do título e da entidade; o **provider/page builder decide onde renderizar** esse título, suprimindo ou removendo a saída visual nativa redundante no mesmo contexto. Não ocultar H1 duplicado com CSS nem desativar o page title globalmente: a substituição é **cirúrgica por rota/consumer**, com fallback definido para páginas que ainda não adotaram o SDC.

Contrato revisado: o SDC apresenta um slot `page_title` renderizável (preferencial quando vindo do Core) ou uma prop `title` texto processado; um dos dois é escolhido pelo provider. O componente é dono da tag `h1` visível quando configurado em `heading_mode: primary`; modo secundário só com semântica explicitamente justificada. Nunca renderizar slot com H1 já encapsulado **dentro** de outro H1. Checar `html_head`/metadados, breadcrumbs, âncoras, views e leitores de tela; não alterar TitleResolver ou entidades.

**Gate:** exatamente um H1 primário em cada página elegível, persistência de Institution Bar + Domain Header, acesso/cache e fallback sem JS. Esta mudança substitui as sugestões anteriores deste documento de preservar o H1 Core fora do SDC; não é implementação concluída.

## Atualização do estado real na branch — 2026-10-22 (verificação posterior)

**Importante:** a orientação acima foi redigida antes da inclusão do SDC. A árvore atual da branch **já contém** `components/patterns/context-header/context-header.component.yml`, `.twig`, `.css` e stories `default` e `editorial`. Portanto a instrução histórica "não criar arquivos" já não descreve o estado do código; **não criar componente duplicado**.

A inspeção de arquivos confirma:
- `status: experimental`; props `title` obrigatória, `eyebrow` e `subtitle` opcionais;
- slots `breadcrumbs`, `back_link` e `actions`, a serem fornecidos por render arrays autorizados;
- `<h1>` incondicional quando componente renderiza título, exigindo gate rigoroso contra H1 duplicado;
- CSS escopado e flex responsivo em 48rem, com tokens semânticos.
- **Não há evidência, nesta inspeção, de uso por tela real ou renderização aprovada no Runtime.**

### Próxima microfase B1-C — integração sem duplicação
1. Identificar e documentar **um consumidor GanjaLab420 e um consumidor editorial existente** e seu page title/breadcrumb efetivo; não supor rotas.
2. Se o Core já renderiza H1, o integrador deverá **suprimir apenas o bloco de título redundante naquela página**, sem ocultar o H1 por CSS nem afetar outras páginas. Não ativar SDC antes de confirmar essa escolha de ownership.
3. Integrar render arrays de breadcrumb/back/actions; conferir metadados de access/cache e que ações ficam indisponíveis para perfis sem permissão.
4. Validar no UI Patterns Library as duas stories, tokens UI Styles e CSS em mobile/dark/light; não exigir que consumidores não compatíveis sejam forçados a UI Patterns no display.
5. Preservar Institution Bar e Domain Header globais; conferir AJAX e no-JS.
6. Após verificar dois consumidores e gates, registrar fase como implementada/homologada, removendo instruções históricas conflitantes no próximo ciclo editorial.

**Status desta atualização: SDC existente na branch; integração e gate Runtime pendentes.**
