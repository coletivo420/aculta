# Auditoria cruzada de arquitetura visual — GanjaLab420 × ACULTA420

**Fase:** G420-UX 0.8-A · **Data:** 2026-10-22 · **Estado:** auditoria estática do repositório; homologação Runtime/SDC stories pendente.

## Princípio decisório

**Reutilizar → estender via variante/slot → criar padrão transversal → só excepcionalmente criar componente específico de domínio.** Todo SDC novo precisa demonstrar pelo menos **dois consumidores plausíveis e efetivos** ou provar que uma especialização incontornável (H5P/PDF, por exemplo) exige um adapter específico. Consumidor hipotético não justifica implementação antecipada: criar somente quando o primeiro consumidor estiver real e o segundo estiver documentalmente mapeado.

**O ACULTA420 é dono da apresentação** (SDC, tokens e CSS), `aculta_portal` da composição/contexto, `aculta_lms` e providers de curso/dados, Group das permissões e vínculos, e kernels (User/Image/Gallery/PDF/Messenger/Forum) de seus recursos. Não introduzir entity/storage/gradebook/progresso paralelo. `aculta_lms` **não será renomeado**.

## Evidências verificadas na árvore da branch

Os componentes abaixo possuem `.component.yml` e Twig no tema em `web/themes/custom/aculta420/components/`; existência no código **não é prova de que todos estejam conectados a consumidores ou homologados no Runtime**.

| Componente existente | Uso recomendado no GanjaLab420 | Outros consumidores úteis | Decisão |
| --- | --- | --- | --- |
| `patterns/card` (stable; default/horizontal/overlay; slots mídia/cabeçalho/conteúdo/rodapé) | atividades, certificados, materiais, turmas, aviso, estados de cartão | Revista, Wiki, Gallery, Loja, Fórum, Projetos | **reutilizar**: criar variantes só após teste de limite |
| `content/course_card` (experimental, `replaces: lms:course_card`) | Meus Cursos e continuar curso quando contrato suportar | catálogo Cursos, GanjaLab cursos | **reutilizar/adaptar** mantendo props/replaces nativos |
| `patterns/rail` (experimental, scroll nativo) | cursos, materiais relacionados | Revista, Gallery, Loja, Wiki | **reutilizar com parcimônia**, evitar trilhos onde listas acessíveis são melhores |
| `patterns/content-grid`, `patterns/content-section` | bibliotecas, índices e coleções | todas as seções editoriais | **reutilizar**; responsividade existente deve ser testada |
| `patterns/figure`, `patterns/quote`, `patterns/revista-publication` | material de leitura, mídia e citações; publicação completa não deve virar “aula” | Revista, Wiki, artigo do Fórum | reutilizar **figure/quote**, não acoplar `revista-publication` à aula |
| `content/author-card` | participante/docente quando `aculta_user` autorizar | Fórum, Wiki, Revista, Gallery | **reutilizar sem consulta ao usuário no tema** |
| `content/messenger-item`, `messenger-notification`, `institutional-notice` | avisos e notificações autorizadas | Minha Conta, Fórum, Portal | **reutilizar**; sem inbox/feeds paralelos |
| `feedback/messenger-state`, `unread-badge` | estados e contadores de avisos quando oficiais | Portal, Messenger, fórum | reutilizar **somente** estados realmente fornecidos |
| `content/qr-preview`, `qr-download` | verificação/compartilhamento de certificado se contrato permitir | Links, campanhas, Commerce, instituições | **reutilizar**, sem gerar QR com dados pessoais |
| `patterns/hero`, `patterns/carousel` | capa de curso quando útil | Revista, cursos, campanhas, Gallery | uso seletivo; **não** transformar Sala de Aprendizagem em carousel |
| `patterns/forum-category` | referências a discussões contextuais via provider | Fórum público, Wiki, comentários de curso | examinar consumidor antes de adaptar |

**Padrões existentes fora do SDC:** shell multidomínio e tokens semânticos light/dark; Bootstrap 5; UI Patterns stories e UI Styles. Revisão de `docs/mobile-first-audit.md` existente aponta limiares CSS variados e inspeção visual pendente; não presumir que componentes atuais passam a matriz 320–1440px.

## Matriz: experiências × padrões

| GanjaLab420 | Reutilização imediata a investigar | Lacuna transversal, sem implementar ainda | Reúso planejado fora de cursos |
| --- | --- | --- | --- |
| Dashboard | `card`, `course_card`, `content-grid`, `rail` | `context-header`, `next-action`, `empty-state` | Minha Conta, Revista, Wiki, Loja |
| Sala/leitura/vídeo | `figure`, `quote`, shell e CSS editorial | `content-reader`, `context-outline`, `previous-next` | Revista, Wiki, Fórum, documentação, Gallery |
| H5P | shell e `feedback-state` quando oficial | **adapter contextual H5P**, não UI genérica do player | uso futuro de H5P em outras áreas, se existir consumidor |
| Atividades | `card`, Form API, notices | `task-status`, `review-submit-shell` | formulários do Portal, workflows Revista, Wiki |
| Biblioteca/PDF | `card`, `figure`, `content-grid` | `resource-list`, `resource-context` | Wiki, Revista, Gallery, Biblioteca do portal, GanjaLab |
| Jornada/progresso | `card`, `content-section` | `status-timeline`, `progress-indicator` | campanhas, moderação/fluxos editoriais, Projetos |
| Turmas/comunidade | `author-card`, `institutional-notice`, messenger, fórum | `context-tabs`, `context-summary` | Fórum, Wiki, Minha Conta, moderação |
| Certificados | `card`, `qr-preview`, `qr-download` | `document-preview`, `record-status` | Document Viewer, comprovantes de Comércio, Biblioteca |

**Importante:** os nomes na coluna de lacunas são *candidatos de padrão visual*, não SDCs existentes; variantes de componentes existentes têm prioridade. Não reaproveitar `progress-indicator` para inferir estados de LMS, doação ou workflow: cada provider fornece sua própria semântica e cache.

## Backlog priorizado por valor transversal

| Prioridade | Padrão pretendido | Contrato de apresentação | Segundo consumidor a validar |
| --- | --- | --- | --- |
| P0 | **`context-header`** | breadcrumb, título, volta, subtítulo e ações renderizadas | Revista/Wiki/Minha Conta |
| P0 | **`context-tabs`** | tabs responsivas com links reais, aria-current e fallback sem JS | GanjaLab, Fórum, Wiki, Conta |
| P0 | **`empty-state` / `state-message`** | vazio, erro, restrição, carregamento; sem lógica de negócio | Commerce, Messenger, Revista, Wiki |
| P1 | **`content-reader`** | corpo editorial, recursos e ações via slots; reader limitado em largura | Revista, Wiki e Document Viewer |
| P1 | **`resource-list`** | lista de mídia/link com tipo e contexto fornecidos pelo dono | Gallery, Wiki, Document Viewer |
| P1 | **`status-timeline`** | passos/estados fornecidos, ícone + texto; não calcular conclusão | Workflows Revista/Wiki, Projetos |
| P1 | **`next-action`** | CTA prioritário e resumo contextual sem determinar a ação | Minha Conta, Studio/GanjaLab |
| P2 | **`record-status`** | estado textual de documento/certificado/comprovante | Commerce, Document Viewer |
| Especial | **adapter H5P / PDF** | somente envelope/layout/contexto, player e toolbar continuam nos kernels | depende de consumidores existentes; não abstrair interface privada dos players |

**Não criar todos os padrões de uma vez.** Implantar em slices conforme primeiro consumidor, com segundo consumidor mapeado, story de demonstração e documentação de API/slots.

## Contrato obrigatório para componente novo/variante

1. **Reuso:** listar consumidor inicial real + outro consumidor viável; se inexistente, justificar componente local e proibir nome global genérico.
2. **Onde:** componente reutilizável sob o tema ACULTA420 em `components/patterns/`, `content/` ou categoria real existente; não criar diretórios vazios.
3. **Dados:** props tipadas/enums simples e slots de render arrays do Drupal; `enforce_prop_schemas: true`; tema sem EntityManager, queries, ACL ou decisões de Group.
4. **Access/cache:** provider/controller/elemento renderável fornece tags/contexts e isola usuário/turma; nenhum componente recebe dados não autorizados.
5. **Style:** tokens semânticos, Bootstrap 5, layout mobile first e light/dark com **mesma geometria**; não duplicar CSS entre LMS/Revista/Wiki.
6. **UX:** links reais e no-JS, teclado, foco, aria-live quando necessário, WCAG 2.2 AA, reflow/zoom, motion, campos com labels.
7. **Qualidade:** status `experimental` até consumidor real e teste; atualizar `components/README.md`, `docs/components.md`, `docs/component-catalog.md`; criar pelo menos um `.story.yml` sob o próprio SDC para UI Patterns, estados vazios/erro quando cabíveis. Visual 320/390/768/1280 e light/dark; evidência antes de `stable`.
8. **Evitar anti-padrões:** componente `ganjalab420-*` que só muda cor/cabeçalho, listener JS duplicado, nova implementação de PDF.js, player H5P próprio, cartão novo onde `card` com slots atende.

## Plano de execução futura 0.8-A → B

**A1 (feito estaticamente):** mapear os SDCs existentes e os contratos de telas/documentos; produzir matriz de reutilização. **A2 (pendente Runtime):** verificar consumidores efetivos, variantes, stories e CSS no browser; fotografar lacunas com evidência. **A3:** classificar por reuse / extend / new no inventário atualizado.

**B1:** primeiro slice transversal `context-header` e tabs apenas após verificar alternativas do shell e SDCs. **B2:** estados vazios/erros reutilizáveis. **B3:** reader e resource-list para Sala/Biblioteca, reaproveitando figura/citações e viewer existente. **B4:** progresso/atividade somente depois de validar contrato de estado. **B5:** aplicar um mesmo padrão em GanjaLab420 e em pelo menos uma outra área com testes, sem causar regressão.

**Gate de homologação 0.8-A:** apresentar para cada tela componente existente, adaptação ou lacuna, props/slots necessários, provider responsável, regra de acesso/cache, segundo consumidor, testes e status no Runtime. Não declarar gate concluído até essa matriz ser validada no servidor.

## Arquivos técnicos auditados

- `web/themes/custom/aculta420/components/**/*.component.yml` (inventário de árvore e amostras de contratos).
- `web/themes/custom/aculta420/components/README.md`
- `web/themes/custom/aculta420/docs/components.md`
- `web/themes/custom/aculta420/docs/component-catalog.md`
- `web/themes/custom/aculta420/docs/mobile-first-audit.md`
- `web/themes/custom/aculta420/docs/shell.md`
- `docs/portal/GANJALAB420-*.md`

**Limitação:** não houve validação de Runtime, inspeção de HTML resultante nem execução de testes nesta etapa. O status de implementação e compatibilidade de cada componente deve ser revalidado na branch antes de modificar o código.

## Transição 0.8-A → 0.8-B

A matriz de reaproveitamento desta auditoria orienta o [plano 0.8-B de cabeçalhos, abas e estados transversais](GANJALAB420-SHARED-UI-PLAN.md). Verificação estática concluída; validação Runtime e integração efetiva seguem pendentes. Priorizar variantes dos SDC existentes antes de criar abstrações novas.
