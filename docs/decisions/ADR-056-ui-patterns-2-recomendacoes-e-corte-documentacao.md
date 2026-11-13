# ADR-056 — UI Patterns 2 como contrato de apresentação e corte orientado por evidência

- **Status:** aceita pelo responsável em 2026-11-13.
- **Escopo:** tema `aculta420_v2`, componentes SDC e suas integrações de apresentação Drupal.
- **Motivo:** a documentação do tema divergiu do código e o acúmulo de templates e instruções legadas dificulta compor o site com UI Suite.
- **Referências:** [recomendações oficiais UI Patterns 2](https://project.pages.drupalcode.org/ui_patterns/2-authors/2-best-practices/), [autoria de componentes](https://project.pages.drupalcode.org/ui_patterns/2-authors/0-authoring-a-component/), [presenter templates](https://project.pages.drupalcode.org/ui_patterns/1-users/5-presenter-templates/), [stories e Library](https://project.pages.drupalcode.org/ui_patterns/2-authors/1-stories-and-library/), [release UI Patterns](https://www.drupal.org/project/ui_patterns/releases).

## Decisão

1. SDC é o contrato visual do tema. Cada componente novo deve incluir schema, Twig, assets necessários e ao menos uma story descoberta pela UI Patterns Library. A UI Patterns Library é a fonte única para o catálogo de SDCs/stories. `aculta_ui_library` não mantém catálogo nem prévias próprias; sua rota administrativa antiga é somente um redirecionamento de compatibilidade para a UI Patterns Library.
2. Props carregam dados simples, tipados e necessários à apresentação; slots recebem conteúdo renderizável do Drupal. Não passar render arrays por props, nem criar prop drilling para filhos/renderables que podem ser slots. Slots são opcionais e devem tolerar qualquer conteúdo renderizável; não testar tipo de prop em Twig nem aplicar filtros dependentes de estrutura a slots. Props obrigatórias devem ter fallback seguro no template. Manter slots independentes; não indexar uma lista de slots a partir de outra. Usar a prop type `links` para menus, breadcrumbs, links e paginação. Componentes permanecem independentes, usam variants para diferenças visuais do mesmo modelo (não props que imitam variants), declaram default funcional e preferem nomes de variants alinhados aos modificadores de markup. Evitar combinações de props impossíveis, IDs fixos e contexto Twig implícito.
3. Usar a integração UI Patterns correspondente somente quando necessária: Blocks para blocos configuráveis; Layouts para seções do Layout Builder; Field Formatters para campos; Views para row/style/display. A escolha de integração não transfere consulta, autorização, acesso, cache ou regras de conteúdo ao tema.
4. Preferir construtores de display do Drupal para entidades e Views. Um presenter template pode permanecer como adaptador leve quando não há construtor apropriado, por exemplo no shell da página ou em menus. Não converter templates mecanicamente em SDCs.
5. Os metadados devem usar nomes agnósticos ao negócio quando o componente for reutilizável; peças internas devem ser identificáveis como internas, agrupadas e com slots `expected` quando a API instalada suportar. Stories devem exemplificar estados úteis sem listas artificiais de renderables. Twig deve usar `attributes` no wrapper, loopar slots com proteção para renderable unitário/lista e ser lintado. `include()` explícito é a opção para dependências inevitáveis entre componentes.
6. O corte legado será orientado por consumidores comprovados no código e Config Sync. Documentação e roadmaps devem refletir o checkout antes de planejar remoções. Migrações ocorrerão em lotes revisáveis com critérios de saída, validação de acesso/cache e evidência de renderização.
7. O tema mantém um shell único e as cinco regiões declaradas atualmente. A opção e o template paralelo `classic`/`v2` estão removidos do código-fonte. Compatibilidade temporária exigida por cache de Runtime compartilhado não reintroduz a variante como configuração ou contrato do tema.

### Decisões de escopo confirmadas pelo responsável

- SDC sem consumidor será candidato a remoção somente se não estiver publicado/selecionável em UI Libraries, não tiver consumidor no código/configuração e não houver plano aprovado. SDCs que integrem UI Libraries são preservados. Todo item oferecido pelas UI Libraries deve ser implementado como SDC; itens não SDC devem ser convertidos ou retirados do catálogo. Todo SDC mantido e todo SDC novo recebe story.
- Renomear componentes somente quando representarem um conceito reutilizável cujo nome atual está preso a domínio. Respeitar referências persistidas; preparar migração/compatibilidade antes da mudança.
- O alvo é tentar remover os 19 templates atuais. Primeiro procurar substituição por entity display, Layout Builder, Views, Field Formatter, Block ou SDC chamado pelo render array. Só permanecerá um presenter mínimo quando o Drupal/UI Patterns exigir o theme hook e não houver display builder equivalente. Não remover contratos Core/HTMX/Forms sem substituto funcional.
- **Revisão posterior (2026-11-13):** revogada a manutenção do catálogo de blocos reais em `aculta_ui_library`. Removidos catálogo e prévias próprias; a rota legada `/painel-administrativo/aculta/ui-library/blocos` redireciona à UI Patterns Library, preservando acesso restrito no MAIN durante compatibilidade. O checkout mantém 136 SDCs e 212 stories; três SDCs continuam sem story como dívida explícita até auditoria de consumidores. Não remover este alias ou desinstalar o módulo sem decisão operacional própria.

## Relação com decisões anteriores

Esta ADR complementa a decisão vigente de corte radical da ADR-054: especifica como aplicar as recomendações oficiais UI Patterns 2 e exige reconciliação documental antes de priorizar novos lotes. Trechos antigos dos planos que determinam shell paralelo ou homologação visual como pré-condição estão superados. Preserva Layout Builder nos displays compatíveis, as cinco regiões do shell, os contratos da ADR-053 e as fronteiras entre tema e Portal. Não autoriza importação de configuração, migração estrutural ou operação de Runtime.

## Estado verificado no código e Config Sync

Primeira leitura realizada em 2026-11-13:

- `aculta420_v2.info.yml` declara as regiões `institution`, `header`, `content`, `sidebar_first` e `footer`; `templates/layout/page.html.twig` é o shell de página encontrado no checkout.
- O Config Sync habilita UI Patterns, Blocks, Field, Field Formatters, Layouts, Library e Views; também habilita UI Styles, integrações de Block, Layout Builder, Page, UI Patterns, Views e Entity Status. O Composer lock declara UI Patterns 2.0.x-dev e UI Patterns Layouts 2.0.23. Isso prova estado versionado, não estado carregado no Runtime.
- O tema contém 136 manifests SDC e 212 stories. `hint`, `sales-panel` e `secret-reveal` não têm story irmã no checkout atual.
- A release estável mais recente verificada é UI Patterns 2.0.23 (13/10/2026), compatível com Drupal 10.6/11.3/12; o projeto usa `2.0.x-dev` no lock. Confirmar no P0 a razão do branch dev antes de sugerir troca de dependência. A UI Patterns Library 2.0 ainda usa templates `hook_theme`; a própria documentação prevê uma possível transição para SDC/display building em 2.1/2.2, então overrides de Library precisam de teste de compatibilidade nessa evolução.
- O tema contém 19 templates Twig. Entre eles há adaptadores de shell, infraestrutura Core/UI Patterns e templates de módulos; cada um precisa de classificação por consumidor antes de remoção.
- A documentação ainda contém métricas antigas (127/188), a afirmação de que UI Styles não está presente e o contrato antigo do seletor classic/v2. Roadmaps e arquitetura serão corrigidos a partir desses fatos e a matriz de consumidores será completada por leitura dos displays, Views, chamadas PHP/Twig e stories.

## Plano de execução

| Fase | Trabalho | Saída e critério de conclusão |
| --- | --- | --- |
| P0 — Verdade documental | Conferir README, instruções locais, arquitetura, catálogo de componentes, gates e roadmaps contra manifests, código, Composer e Config Sync. Corrigir status e preservar entradas históricas como histórico; entender por que o Composer usa branch dev e a evolução da Library. | Fontes canônicas refletem checkout e estado da dependência; discrepâncias restantes explicitamente marcadas. |
| P1 — Contratos SDC | Auditar metadados agnósticos, subcomponentes internos, tags/links/expectations, props/slots e fallbacks vazios, prop type `links`, atributos, variants/defaults/ortogonalidade, dependências Twig, filtros e renderable unitário vs lista, IDs, lint e stories. Cruzar cada SDC com consumidor e inventariar todos os itens ofertados nas UI Libraries. | Preservar SDCs publicados/selecionáveis em UI Libraries; garantir que todo item dessas bibliotecas seja SDC. Cortar apenas órfãos fora delas, salvo plano aprovado. Componentes mantidos/nascidos têm stories significativas; schemas e render isolado válidos; Twig lint PASS. |
| P2 — Mapa de integração | Cruzar SDCs com blocos, displays de entidade, Layout Builder, Views e formatters exportados. Confirmar descoberta e fontes suportadas na versão instalada, sem ativar módulos por inferência. | Matriz SDC → consumidor → integração → dados/acesso/cache; lacunas e duplicações priorizadas. |
| P3 — Corte de apresentação | Tentar eliminar todos os templates de tema com substituição UI Patterns/display builder ou render arrays SDC. Testar o fallback Core no shell e a Library upstream. | Lotes pequenos; cada template removido tem substituição real. Exceções limitadas aos hooks sem display builder; não perder acesso, cache, tradução, attachments, semântica ou interação. |
| P4 — Homologação | Rodar gates focais, comparar páginas públicas e autenticadas, revisar Library/stories e confirmar cache-hit, teclado, claro/escuro e larguras relevantes. | Evidência por consumidor e Runtime status explícito. Sem sincronização ou rebuild bloqueado por preflight. |

## Próxima ação

Concluir P0. O roadmap do tema deve ser a fila executável de P1–P4 e só marcar uma migração como concluída com referência a código/configuração e evidência. A primeira revisão corrigiu métricas e contradições identificadas; a triagem inicial dos 19 templates está no roadmap; a confirmação de consumidores por domínio e o cruzamento completo dos SDCs continuam pendentes.
