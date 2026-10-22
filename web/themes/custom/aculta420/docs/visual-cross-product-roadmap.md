# ACULTA420 — Roadmap visual transversal (planejamento)

**Linha de planejamento:** THEME-UX 0.8 · **Data:** 2026-10-22 · **Estado:** plano, sem implementação e sem gates Runtime aprovados.

## Decisão arquitetural obrigatória
**Preservar Institution Bar + Domain Header multidomínio existentes em TODAS as áreas.** A evolução visual pode aperfeiçoar responsividade, contraste, espaçamento, navegação mobile e light/dark, mas não substitui, oculta ou duplica o shell. O futuro Context Header é **subcabeçalho de página** e não um cabeçalho global. Domain/purpose, URLs, dados, ACL e cache ficam no Portal/providers; tema somente apresenta.

## Transferência formal de escopo
O planejamento inicial nasceu na UX do estudante [GanjaLab420 0.8](../../../../../docs/portal/GANJALAB420-VISUAL-QUALITY-GATES.md), mas **componentes, tokens, CSS, histórias e padrões visuais compartilhados são backlog do TEMA**. A integração funcional por módulo permanece em suas documentações próprias. O tema não cria progressão LMS, certificados, envio de respostas, operações H5P, notas ou PDF.js paralelo.

## Inventário / escolhas atuais
- Confirmado na árvore do tema: `patterns/card` com slots e variantes; `content/course_card`; `patterns/rail`, `content-grid`, `content-section`, `figure`, `quote`; `content/author-card`; componentes Messenger, avisos, QR e Fórum.
- Existe shell multidomínio, Bootstrap 5, tokens semânticos e catálogo UI Patterns stories.
- **Lacunas candidatas, não implementadas:** context-header, context-tabs, empty/state, content-reader, resource-list, status-timeline, next-action. Primeiro verificar se Core/Bootstrap/SDCs atuais já satisfazem o uso.
- Evidência apenas de inventário estático; uso em Runtime e componentes realmente renderizados ainda requerem inspeção.

## Matriz de reuso
| Padrão | Primeiro caso | Reutilização prioritária | Ownership |
| --- | --- | --- | --- |
| Cabeçalho contextual interno | aula GanjaLab420 | Revista, Wiki, Fórum, Minha Conta, GanjaLab | ACULTA420 |
| Abas de contexto | turmas / GanjaLab | Fórum, Wiki, Conta | ACULTA420 visual; AJAX pelo módulo |
| Estados/feedback | biblioteca vazia/erro | Loja, Messenger, Revista, Wiki | ACULTA420 visual; estados pelo provider |
| Leitor editorial | aula texto/vídeo | Wiki, Revista, Document Viewer | ACULTA420 reader; mídias pelos kernels |
| Lista de recursos | biblioteca por aula | Gallery, Wiki, Revista | ACULTA420; arquivo em Media/File |
| Linha de percurso/estados | Minha Jornada | workflows de Revista/Wiki/Projetos | ACULTA420 visual; semântica pelo provider |
| Cartões | cursos/avisos/documentos | todo o portal | estender `patterns/card` antes de criar |
| Pessoa/autoria | participantes | Fórum, Wiki, Revista | `aculta_user` + `author-card` |
| PDF/H5P | aula e avaliações | consumidores reais de cada kernel | wrappers visuais, nunca players novos |

## Roadmap próprio do tema — microfases (planejadas)
| Fase | Ação | Aceite |
| --- | --- | --- |
| THEME-UX 0.8-A | auditar SDCs, slots, CSS, navegação, providers e consumidores **reais** | matriz do que existe/variante/lacuna; sem inventar evidência |
| THEME-UX 0.8-B0 | inventariar shell e renderização por purpose (GanjaLab420, Revista, Wiki, Fórum, Conta, Loja) | Institution + Domain Header presentes; conflitos mobile identificados |
| THEME-UX 0.8-B1 | criar/adaptar **context header interno** quando realmente necessário | shell global intacto; >=2 consumidores reais |
| THEME-UX 0.8-B2 | tabs responsivas, links reais e AJAX opcional do módulo | fallback sem JS, teclado, histórico correto |
| THEME-UX 0.8-B3 | estados universais: vazio, carregamento, erro, restrito e sucesso confirmado | mensagem acessível e ação verdadeira |
| THEME-UX 0.8-B4 | reader, resource-list, progress/timeline por demanda | tokens e compatibilidade multimódulo |
| THEME-UX 0.8-B5 | catalogar stories + homologar dark/light, A11y, 320–1440px | WCAG 2.2 AA e evidências Runtime |

**Ordem de implementação:** reutilizar → variante/slot → SDC transversal → adaptador especializado. Nunca gerar vários SDCs vazios na mesma fase. Só implementar quando há consumidor real e segundo consumidor validável; atualizar documentação, catálogo e story no mesmo commit. Respeitar o ciclo Dev → RC → Alpha.

## Matriz mínima de qualidade
- 320, 360, 390, 768, 1024, 1280, 1440 px, zoom 200–400%, teclado, foco, sem JS, reduced motion, modos light/dark **com geometria idêntica**.
- Instituição/Domain Header preservados em **todos os purposes**, inclusive em AJAX e mudanças de URL.
- Props/slots tipados e render arrays com attachments/cache; Twig sem queries, permissões, dados de usuário ou Group; states em origem oficial.
- Biblioteca UI Patterns: story simples, variações, vazio/erro se necessário; componente permanece `experimental` até integração validada.
- Evidência de consumidores reais e testes; sem declarar PASS com base em wireframe.

## Links
- [Shell multidomínio](shell.md)
- [Arquitetura](architecture.md) · [Componentes](components.md) · [Catálogo de SDCs](component-catalog.md)
- [Auditoria cruzada](../../../../../docs/portal/GANJALAB420-CROSS-VISUAL-AUDIT.md)
- [Cabeçalho e navegação 0.8-B](../../../../../docs/portal/GANJALAB420-SHARED-UI-PLAN.md)
- [Inventário B0/B1](../../../../../docs/portal/GANJALAB420-SHELL-CONTEXT-HEADER-B0-B1.md)
