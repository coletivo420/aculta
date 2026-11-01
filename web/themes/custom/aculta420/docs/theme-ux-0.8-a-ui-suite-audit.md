# THEME-UX 0.8-A/B0 — Auditoria técnica de consumidores e UI Suite

**Estado:** planejamento de execução incremental, não implementado · 2026-10-22
**Referências:** [roadmap visual transversal](visual-cross-product-roadmap.md), [plano UI Suite](ui-suite-migration-plan.md), [shell multidomínio](shell.md), [catálogo](component-catalog.md).

## Missão
Inspecionar a implementação ACULTA420 real e configurar o primeiro slice de reutilização dos padrões visuais compartilhados. Antes de novos arquivos de SDC, levantar no Runtime a presença de SDC Core, UI Patterns, UI Styles, UI Patterns Library, integrações de Views/blocos/campos/layouts, Fences, SDC Devel e a família VVJ já autorizada. O planejamento do GanjaLab420 é um dos consumidores, mas a execução de componentes e seu roadmap **pertencem ao tema**.

## Invariantes
- **Institution Bar + Domain Header globais existentes em toda rota/purpose**. O subcabeçalho de conteúdo não os substitui. Não alterar contratos Domain Purpose do Portal.
- Tema sem data access, permissões, storage ou consultas; providers oferecem render arrays e metadados de cache.
- Bootstrap 5 e tokens do ACULTA420. Light/dark idênticos em estrutura; mobile first.
- Não instalar UI Suite Bootstrap, Display Suite ou `ui_patterns_ds` sem nova decisão; não exigir Storybook externo; UI Styles CKEditor fora do escopo.
- Trabalhar na PR/branch existente, commits estritamente do tema/contratos, sem misturar agentes.

## B0.1 — fotografia fiel do Runtime (somente leitura)
- Conferir versions/habilitação por Drush, configuração sync e divergências Runtime, sem importação global.
- Inventariar todos os `.component.yml`, `.story.yml`, Twig, bibliotecas, CSS e efetivos consumidores de SDC via UI Patterns em Views, blocos, formatters, campos e layouts.
- Catalogar UI Styles: catálogo próprio e uso atual; verificar Fences sem inferir substituição da renderização de campos.
- Revisar todos os purposes com URLs reais: Início/Conta, GanjaLab, GanjaLab420, Revista, Wiki, Fórum, Gallery, Cursos e Loja conforme instalados. Identificar shell global, breadcrumbs, tabs, H1, duplicações e sticky collisions.
- Produzir tabela **rota × purpose × provider × template/SDC × UI Patterns/Styles × access/cache × shell × viewport × evidência**.

## B0.2 — classificar lacunas
Decisão por necessidade: (1) já resolvida por Core/Bootstrap/SDC; (2) integrar UI Patterns ao display existente; (3) usar UI Styles semântico; (4) variante/slot de SDC; (5) novo padrão transversal; (6) adaptador de domínio comprovadamente necessário. Para cada item documentar consumidor real inicial e segundo consumidor de outra área. Não gerar componentes sem consumidor.

## B0.3 — prova visual e qualidade
Capturas verificáveis em 320/390/768/1280/1440px, claro/escuro, teclado, zoom/reflow, leitores de tela quando ferramentas permitirem, no-JS, navegação por purpose, isolamento de usuários/turmas, AJAX e fallback; registrar **PASS/FAIL/PENDENTE com evidência**. Não considerar story prova de ACL/Group. UI Patterns Library somente com permissão administrativa.

## B1.0 — desenho para primeiro slice, sem implementação
Propor `Context Header` interno a uma página de LMS e uma de Wiki/Revista/Fórum, priorizando page title e breadcrumb Core combinados com `content-section`; avaliar protótipo por UI Patterns Library. Se comprovadamente insuficientes, especificar schema props/slots e variante **do tema**, com story; não criar componente ainda nesta fase de planejamento.

## Saídas esperadas para execução pelo agente
1. `docs/` do tema atualizado com matriz comprovada, lacunas e decisões de reuso.
2. UI Patterns: relatório de stories e integrações de display; UI Styles: inventário de tokens e estilos aplicáveis; VVJ/Fences/Section Library apenas onde usados.
3. Backlog curto de commits futuros B1/B2/B3, contendo consumidor duplo, ownership, acessibilidade e gates.
4. Separar resultados documentais do que ainda depende de alterações funcionais e testes em Runtime.

## Condição de avanço
Aprovada a matriz B0 e demonstrados pelo menos dois consumidores reais, executar **B1 em uma fatia pequena** (sem trocar shell) com SDC ou variante mínima, story, documentação e validações, antes de passar às abas B2. Nenhuma implementação é afirmada neste plano.

## Avanço do plano — B0 operacional

A [matriz de evidências B0](theme-ux-0.8-b0-execution-matrix.md) detalha as rotas/purposes, os componentes realmente consumidos e a avaliação Core SDC/UI Patterns/UI Styles, com critérios de PASS e pendências. É um instrumento de execução, **não evidência de teste Runtime já realizado**.

## Norma revisada de reutilização

**Somente componentes baseados em Drupal Core SDC e alinhados ao UI Suite podem ser reutilizados diretamente em novas áreas.** Inventariar legados Twig/Bootstrap/PHP como candidatos à migração, não como componentes prontos. O relatório B0 deve separar `SDC-CONFORME`, `SDC-PARCIAL`, `LEGADO-MIGRAR` e `DESCARTAR/SUBSTITUIR`; validar schema, slots, UI Patterns Library stories e UI Styles semânticos, além de access/cache. Não copiar apresentação antiga entre módulos para adiar migração. Preservar o cabeçalho multidomínio: qualquer modernização deve migrar padrões internos sem substituí-lo.
