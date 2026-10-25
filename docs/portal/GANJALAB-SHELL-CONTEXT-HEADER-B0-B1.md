# ACULTA420 / ACULTA Studio Ganjalab — 0.8-B0/B1: Auditoria do shell e cabeçalho contextual

**Data:** 2026-10-22 · **Estado:** planejamento, sem implementação.
**Base:** [shell multidomínio](../../web/themes/custom/aculta420/docs/shell.md) · [fase 0.8-B](GANJALAB-SHARED-UI-PLAN.md).

## Decisão obrigatória
A **Institution Bar + Domain Header** existente é obrigatória no portal inteiro. A fase B0/B1 **não a redesenha, não a substitui, não cria outra global**. O padrão `context-header` é uma região interna da página, abaixo do shell persistente, e será compartilhado com ACULTA Studio Ganjalab, Revista, Wiki, Fórum, ACULTA Studio Marley e Minha Conta. Modernizações do shell global podem ser propostas em mudanças separadas e compatíveis com o contrato atual.

## B0 — Inventário de consumidores reais (primeiro)
1. Enumerar regiões Twig, preprocess, builders e SDC do cabeçalho global, navegação de purpose e breadcrumb de cada rota; verificar precedência Portal → DomainPurposeManager → ACULTA420.
2. Amostrar ao menos: dashboard ACULTA Studio Ganjalab, Sala, ACULTA Studio Marley, Revista, Wiki, Fórum, Minha Conta e Loja; verificar purpose e acesso reais. Não pressupor rota/instalação pronta.
3. Registrar em matriz: rota/purpose; header global efetivamente renderizado; título/subtítulo/breadcrumb internos; slots; owner; CSS e JS; dados/autorização/cache; padrão responsivo; dark/light.
4. Inspecionar se Core breadcrumb, page title, Bootstrap/nav, `patterns/content-section` ou outro componente existente já cobre `context-header`; só abrir proposta de novo SDC após prova de lacuna.
5. Conferir duplicação de H1, cabeçalhos sticky sobrepostos, overflow 320px, foco, estado de menus móveis e conteúdo por AJAX; localizar pontos de melhoria visual do shell **sem mudar sua identidade**.

## B1 — Contrato contextual reutilizável
```text
INSTITUTION BAR — existente e persistente
DOMAIN HEADER   — existente e persistente
─────────────────────────────────────────
Context Header  — interno à página
  [← Voltar ao curso] [breadcrumb opcional]
  Título / subtítulo
  [ações contextuais opcionais]
─────────────────────────────────────────
Conteúdo da página (leitura, H5P, PDF, fórum, wiki etc.)
```
O header interno recebe `breadcrumb`, `title`, `subtitle`, `back_link`, `actions` apenas como dados e render arrays autorizados. Não resolve hostname, domain, rota, user ou Group. A linha de breadcrumb ou o link Voltar não devem duplicar informações sem propósito. Título H1 único por documento, sem impor tags inválidas ao Core.

**Responsividade:** 320–390px empilhar as ações sem esconder shell; 768px reorganizar conforme o espaço; ≥1024px título e ações lado a lado quando cabível. Modos claro/escuro preservam geometria. Sem CSS `position: fixed` criando terceira barra global; componentes sticky devem respeitar offsets e safe areas existentes.

## Critérios para eventual SDC novo
- Primeiro verificar se `content-section`/breadcrumb e renderização de título nativas bastam.
- Se faltar contrato, criar SDC genérico `aculta420:context-header` com props tipadas, slots renderizáveis e CSS tokenizado.
- Adotar pelo menos dois consumidores **reais**: um ACULTA Studio Ganjalab e outro Wiki/Revista/Fórum/Conta; não bastam usos hipotéticos.
- Criar story UI Patterns, documentação de props e estados, testes 320/390/768/1280px, light/dark, teclado, zoom, no-JS, regressão do cabeçalho multidomínio.
- Cada entrega funcional em commit separado, preservando alterações concorrentes da PR.

## Plano em microfases
| Marco | Produto | Critério |
| --- | --- | --- |
| B0.1 | mapa de shell, rotas e estruturas HTML reais | dois ou mais purposes e todos os destinos principais amostrados |
| B0.2 | inventário Core/Bootstrap/SDC e CSS | decisão reutilizar/estender/criar com evidência |
| B0.3 | matriz de regressão do cabeçalho global | nenhuma duplicação/ocultação; acesso/cache preservados |
| B1.1 | wireframes do context header mobile/desktop | hierarquia consistente abaixo do shell |
| B1.2 | implementação mínima, só após B0 | primeiro consumidor real + segundo consumidor real |
| B1.3 | stories/testes/documentação de todos os consumers | gate WCAG 2.2 AA + light/dark + 320–1440px |

**Próximo após B1:** B2 `context-tabs` com links reais, AJAX opcional controlado pelo módulo e fallback no-JS, sempre sob o mesmo shell global.

**Status:** apenas contrato e planejamento; nenhuma auditoria Runtime B0 declarada concluída.
