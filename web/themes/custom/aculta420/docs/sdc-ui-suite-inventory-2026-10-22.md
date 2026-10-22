# ACULTA420 — Inventário estático SDC/UI Suite e backlog de migração

**Fase:** THEME-UX 0.8-A / B0-R1 · **Fotografia:** 2026-10-22 · **Referência:** branch `docs/aculta-forum-0.1.0-dev.1`, PR #297.
**Método:** árvore GitHub da branch, metadados SDC amostrados e catálogo UI Styles. **Não equivale a auditoria no Runtime**.

## Contagem verificável
- **38** arquivos `.component.yml` no diretório `web/themes/custom/aculta420/components/`.
- **38/38** têm Twig homônimo presente na árvore.
- **55** arquivos `.story.yml` distribuídos pelos componentes; **35/38** têm pelo menos um story.
- **3 sem story:** `content/forum-category`, `content/qr-preview`, `content/qr-download`.
- Arquivo `aculta420.ui_styles.yml` possui categorias **surface, text, border, elevation**; integração real com displays ainda não verificada.
- Um arquivo CSS local não é obrigatório por SDC: existem componentes sem CSS próprio, o que por si só não é defeito (podem consumir tokens/Bootstrap).

**Atenção:** números referem-se a **arquivos na árvore**, não a SDCs ativos, renderização bem-sucedida, schema validado, acessibilidade, uso em UI Patterns ou instalação Runtime.

## Componentes inventariados por grupo
| Grupo | Componentes | Stories ausentes | Estado preliminar |
| --- | --- | --- | --- |
| Content | author-card, course_card, editorial-card, forum-category, institutional-notice, messenger-compose, messenger-item, messenger-notification, product-buy, product-details, qr-download, qr-preview | forum-category, qr-download, qr-preview | base SDC presente; 3 com lacuna de catálogo |
| Donation (raiz) | donation-card, donation-dedication, donation-notification, donation-pane, donation-quick, donation-summary-container | nenhum | base SDC + story; consumidores pendentes |
| Feedback | consent-banner, messenger-state, unread-badge | nenhum | base SDC + story; verificar semântica antes de generalizar |
| Patterns | alerts-trigger, card, carousel, cart-offcanvas, content-grid, content-section, figure, hero, messenger-list, messenger-read-status, messenger-trigger, product-tabs, quote, rail, revista-publication, social-links-page, wiki-entry | nenhum | base SDC + story; integrações e consumo pendentes |

## Critérios e classificação preliminar
- **SDC-BASE-AUDITAR:** todos os 38, porque arquivos `.component.yml` e Twig existem; **não** classificar automaticamente como SDC-CONFORME sem validação de schema, stories, UI Patterns/Styles, consumers, cache e browser.
- **SDC-PARCIAL (catálogo):** `forum-category`, `qr-preview`, `qr-download` não têm stories; completar e validar antes de propor nova reutilização transversal.
- **ATENÇÃO DE SCHEMA:** `qr-preview.component.yml` usa URL de schema `project/sdc` em vez da URL Core SDC encontrada em `patterns/card`; validar compatibilidade com Core Drupal 11 e harmonizar apenas se o validator confirmar exigência. Auditar `qr-download` analogamente.
- **LEGADO-MIGRAR:** padrões Twig/PHP/Bootstrap fora dos 38 SDCs devem ser inventariados no Runtime e código de consumidores; não presumir que todos os arquivos Twig sejam componentes ou que todos exijam migração.
- **DESCARTAR/SUBSTITUIR:** nenhuma conclusão ainda; exige prova de duplicação ou incompatibilidade.

## Foco prioritário: componente × consumidor × ferramenta UI Suite
| Ordem | Base SDC existente | Consumidores alvo | UI Suite / lacuna a investigar | Decisão preliminar |
| --- | --- | --- | --- | --- |
| P0 | `patterns/content-section` | GanjaLab420 + Wiki/Revista | Tem title h2 e heading h3; **não promover a context header H1** sem revisar semântica | **adaptar/novo SDC contextual** após auditoria de page title |
| P0 | `patterns/card` (stable) | materiais, turmas, atividades + Revista/Wiki/Loja | confirmar stories e uso real nos displays UI Patterns | reutilização candidata, condicionada a Runtime |
| P0 | `feedback/messenger-state`, `content/institutional-notice` | estado de LMS + Messenger/Conta | estado de Messenger tem semântica própria; não renomear para feedback genérico sem prova | avaliar SDC de estado transversal sem quebrar contratos |
| P0 | `content/forum-category` | Fórum + listas contextuais | **sem story**; conteúdo específico de Taxonomy | preencher catálogo; não generalizar como card universal |
| P1 | `content/course_card` | dashboard LMS + catálogo de Cursos | mantém `replaces: lms:course_card` e slot `start_link` | não quebrar contrato ou exigir UI Patterns se replace já serve |
| P1 | `content/author-card` | Fórum + participantes da turma + Wiki/Revista | preservar `aculta_user` como owner e props neutras | reuso candidato após validação de acesso |
| P1 | `patterns/figure`, `quote`, `content-grid` | reader, materiais, Revista/Wiki/Gallery | testar UI Styles e stories; não recriar markup Twig paralelo | adaptar composição via SDC |
| P1 | `patterns/rail` | cards relacionados + Gallery/Revista | uso de scroll e teclado; evitar carrossel de aulas obrigatório | condicionado à acessibilidade |
| P2 | `content/qr-preview` / `qr-download` | certificado + Links/campanhas | **sem stories**; validar schema Core, props de URL, segurança | adequar catálogo antes de reuso |
| P2 | `patterns/product-tabs` | Loja e possível navegação contextual | semântica específica Commerce/JS; tabs de páginas usam links reais | não reciclar até provar semântica compatível |

## Ordem de migração/adequação recomendada
1. **M0 — verificar status dos 38 SDCs** com validação Core/SDC Devel, compatibilidade dos `*.component.yml`, stories no UI Patterns Library, UI Styles e consumidores efetivos. Sem isso não autorizar reuso novo.
2. **M1 — cobrir stories ausentes** de `forum-category`, `qr-preview`, `qr-download`, com fixtures fictícias, estado padrão e autorização administrativa no catálogo; corrigir metadados se validadores exigirem.
3. **M2 — cabeçalhos contextuais:** inventariar page title, breadcrumbs, preprocess e templates legados; verificar primeiro `content-section` e Core/Bootstrap. Se contrato H1 e slots não couberem semanticamente, criar **novo SDC transversal** apenas com consumidores reais.
4. **M3 — tabs contextuais:** procurar tabs de GanjaLab/Conta/Wiki/Fórum implementadas sem SDC; migrar apresentação para SDC compatível com UI Patterns; AJAX e navegação continuam nos módulos.
5. **M4 — feedback universal:** avaliar notices e mensagens Core/Messenger; não copiar Twig; compor SDC genérico com estados fornecidos pelo provider.
6. **M5 — leitor e recursos:** migrar molduras editoriais úteis para SDC, aproveitando `figure`, `quote`, `content-grid` e `card`; manter H5P/PDF.js como engines dos kernels, não recriá-los.
7. **M6 — histórico/progresso:** SDCs de apresentação neutra somente após comprovar segundo consumidor (ex.: workflow Wiki/Revista); nenhum cálculo pedagógico no tema.

## Matriz da próxima inspeção Runtime — PENDENTE
| Evidência | Status |
| --- | --- |
| Versões e habilitação Core SDC / UI Patterns / Library / UI Styles / SDC Devel / Fences / VVJ | PENDENTE |
| Consumidores reais em Views, fields, layouts e blocos | PENDENTE |
| HTML/semântica de shell Institution Bar + Domain Header por purpose | PENDENTE |
| Uso do catálogo UI Styles em displays | PENDENTE |
| Renderização de cada story, schema warnings e JS | PENDENTE |
| Screenshots 320/390/768/1280 light/dark e no-JS | PENDENTE |
| Acesso/cache por usuário e Group em componentes consumidores | PENDENTE |

## Gate de adoção de componente
**Reuso permitido somente depois de:** SDC Core válido, props/slots e UI Patterns/Styles compatíveis com o caso de uso, story, semântica, evidências de consumidores, acessibilidade e provider responsável por cache/acesso. Core Form API, PDF.js e H5P **não** precisam ser recodificados em SDC; o wrapper visual compartilhável sim.

## Próximo lote recomendado ao executor
**THEME-UX 0.8-B0-R2:** inspeção em servidor e catálogo UI Patterns, classificação individual com evidência real; em seguida **M1** com stories e schema, e **B1** contextual abaixo do shell multidomínio. Não criar novos SDCs até a validação B0.

**Limitação metodológica:** esta é uma auditoria estática, sem testes ou alterações funcionais. Dados de uso em produção não foram inferidos da simples presença dos arquivos.

## Atualização de execução estática — M1 (2026-10-22)

**Corrigida a lacuna de arquivos stories no repositório**: adicionados
`content/forum-category/forum-category.default.story.yml`,
`content/qr-preview/qr-preview.default.story.yml` e
`content/qr-download/qr-download.default.story.yml`.

- Fotografia anterior: 38 SDCs / 55 stories / 3 sem story.
- **Após commits:** 38 SDCs / **58 stories** / **0 sem story** na árvore.
- Os exemplos são determinísticos, sem consultas, sem dados reais, sem uploads e sem geração efetiva de QR. Links fragmentários são demonstrativos.
- **M1 parcial:** cobertura de arquivo resolvida, mas parsers/schema/story renderer e visualização no UI Patterns Library ainda **PENDENTES** no Runtime.
- `qr-preview` e `qr-download` usam referência de schema da antiga árvore `project/sdc` enquanto `card` usa a URL de Drupal Core; catalogado para comparação de metadados/validador na etapa M0, sem afirmar invalidade.

A definição de conformidade permanece rígida: *presença de SDC e story não prova SDC-CONFORME*. Para isso, validar metadados, acesso/cache do consumidor, uso UI Patterns/Styles quando apropriado, HTML, acessibilidade e light/dark em Runtime.

## Pendências identificadas para a auditoria fora do servidor

1. Realizar revisão estática cruzada das definições dos 38 arquivos `.component.yml` contra os contratos Core SDC e propriedades usadas nas Twig.
2. Procurar candidatos legados de apresentação em Twig/CSS/JS que ainda não tenham base SDC — com **evidência de consumidor**, não por extensão de arquivo apenas.
3. Revisar contrato de heading do `content-section` (h2/h3) e markup de Context Header que usará h1 sem duplicar títulos do Core.
4. Auditar semântica de navegação `product-tabs` e navegação Ajax Studio antes de promover variante compartilhada.
5. Evitar alterações automáticas de schemas/markup do QR até validação específica, pois o componente é usado fora do LMS.
