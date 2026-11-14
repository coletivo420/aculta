# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · ADR-056 integralmente concluída (P0 a P4). Governança de UI Patterns 2, SDC-First e catálogo Library oficial homologados.

## Próximo passo

- **Governança de SDC e UI Patterns 2 (ADR-056 Concluída):**
  - **P0 — Documentação e Catálogo:** Catálogo unificado na UI Patterns Library oficial (`/painel-administrativo/aparencia/ui/components`). O módulo customizado legado `aculta_ui_library` foi purgado do código e desabilitado. Contratos de 150 SDCs e 244 stories documentados e alinhados.
  - **P1 — Schemas e Contratos Estritos:** 100% dos 150 componentes validados com schemas JSON estritos (2.826 checks), títulos e descrições em todas as 550 props de topo, 714 nós tipados e 279 slots. Metadados de `variants` alinhados em 7 SDCs com enum. O validador de renderização isolada V2 SDC (`validate-aculta420-v2-sdc.php`) cobre 100% dos 150 componentes sob o Drupal Runtime ativo.
  - **P2 — Matriz de Integração:** Mapeamento de 150 SDCs com 159 displays Views, 52 instâncias de bloco, 22 displays com Layout Builder e 17 templates Twig do tema.
  - **P3 — Redução dos Templates Twig:** Triagem e inventário exaustivo concluídos; tema estabilizado em 17 templates estritamente necessários como adaptadores de transição (4 Views com dados agregados/grouping sem Sources upstream, 3 adapters Comment/Form API, 3 overrides da Library oficial e 7 infraestruturas Core com suporte a `#drupal-modal` e metadados HTMX).
  - **P4 — Homologação de Runtime e Visual:** Drush conectado com 0 updates pendentes, 0 deploy hooks e 0 config drift; descoberta no Runtime confirmou 161 SDCs no total, 150 do tema e 244 stories ativas. Rota canônica da Library responde HTTP 200 com 22 grupos e 150 botões de prévia progressiva. Hosts multidomínio e rotas públicas validados via Playwright e requisições HTTP sem quebra horizontal em 320px.
- **Operação de Banco e Runtime:**
  - Limpeza focal da chave órfã residual `aculta_ui_library` na tabela `system.schema` do banco de desenvolvimento (operação sensível sujeita a plano documentado, backup privado e teste de restauração em `aculta_runtime_copia`).
  - Importação de configurações pendentes de estilo UI Patterns Views (Revista e Cursos) sob janela de sincronização autorizada.
- **Acessibilidade e Ajustes Finos (WCAG 2.2 AA):**
  - Monitorar e ajustar áreas de clique seguras (Target Size mínimo de 24x24 px conforme critério 2.5.8) em links compactos de taxonomia/metadados inline.
- **Aceite Visual Humano:**
  - Inspeção visual final das superfícies, inversões de contraste e temas claro/escuro nos navegadores de referência antes de cutover.

## Marcos e estado consolidado

- UI Patterns Library oficial ativa como fonte única de verdade: 150 manifests SDC e 244 stories auditados.
- Gates estáticos e de runtime passam 100%: `validate-aculta420-sdc-library-contract.php`, `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-sdc.php`, `validate-aculta420-layouts.php` e `validate-project-status-index.php`.
- Templates remanescentes do tema: 17 arquivos justificados e protegidos contra remoção acidental.
