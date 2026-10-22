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
