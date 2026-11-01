# THEME-UX 0.8 — Continuação fora do Runtime: conformidade e primeiros slices

**Fotografia:** 2026-10-22 · **Estado:** plano executor da auditoria estática, com M1 (cobertura de story) aplicado; **sem homologação Runtime**.

## Resultado já produzido nesta sequência
Inventário da árvore da branch confirmou 38 `.component.yml` com Twig homônimo e 55 stories; criação de 3 stories para `forum-category`, `qr-preview`, `qr-download` elevou a cobertura a 58 stories, sem componentes sem arquivo story. O fato de haver arquivos não prova renderização ou integração funcional UI Patterns.

## Próxima fatia — M0-S (metadados, Twig, props e stories)
Para cada SDC comparar `.component.yml`, `.twig`, `.story.yml`, CSS local e consumidores:
- Validar schema Core 11, `props.type=object`, `required`, tipos/enum/defaults, slots documentados, `assets` e `variants`; conferir Twig acessando apenas propriedades compatíveis.
- Detectar Twig que faz query de entidade, resolve permissões, escolhe Domain ou cria estado de aprendizagem.
- Verificar stories sem dados reais, URL privada, operações de formulário ou dependência de login.
- Auditar HTML semântico (heading H1–H6, `role` e acessibilidade) e CSS tokenizado.
- Identificar SDC específico de domínio vs padrão universal, sem generalizar componente de comércio/forum somente pelo nome.

**Atenção prioritária:** metadados de QR usam a URL histórica de schema SDC; confirmar o validator e o comportamento no Core antes de trocar. `content-section` tem heading H2/H3 e não pode, só por slot/variante, virar H1 de Context Header sem revisar semântica e integração de page title. `product-tabs` não é substituto automático de tabs de navegação entre páginas.

## Fase M2 — planejamento do primeiro slice transversal
1. Inspecionar templates de page title e breadcrumb efetivamente renderizados, componentes de seção, shell e layout; evitar H1 duplicado.
2. Mapear um consumidor educacional e um consumidor editorial com rotas reais e contratos de acesso.
3. Se `content-section` não suportar cabeçalho contextual sem ruptura semântica, criar proposta de **novo SDC genérico** `context-header` — não alterar `content-section` para atender a outro uso incompatível.
4. Slots candidatos: `breadcrumb`, `back_link`, `actions`; props título e subtítulo opcionais, com política de H1 explícita. Providers controlam URLs e permissões.
5. Story padrão e estado reduzido em 320px; testes em claro/escuro, teclado, 200/400% zoom; inspeção de Institution Bar e Domain Header.
6. Uso real em pelo menos duas áreas antes de generalizar estilos, via UI Patterns quando o display realmente suportar; nunca substituir o cabeçalho global.

## Decisão no tema
Só reutilizar apresentação **SDC-CONFORME** (Core SDC com UI Patterns/Styles preparados ou integrados conforme consumidor). Legado sem SDC é material para migração, não componente compartilhável. Não reimplementar Form API, PDF.js ou H5P: sua integração visual poderá usar slots renderizáveis em um SDC.

## Entregáveis no servidor, ainda bloqueados
Validadores Core/SDC Devel, UI Patterns Library das 58 stories, auditoria de displays e UI Styles habilitados, screenshot dos purposes, DOM de cabeçalho institucional/domain, regressões A/B e dark/light. Estado de todos estes: **PENDENTE**. Registrar falhas e corrigir por commits de escopo mínimo, sem alterar a PR/branch de trabalho.
