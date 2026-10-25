# ACULTA Magazine — Roadmap de realinhamento arquitetural

**MAG 0.5-R · 2026-10-22 · Planejamento, sem alteração funcional ou homologação.**

### Minha Conta e Meu Perfil — alcance da composição Studio (dev.20)

S-R3-B3 acrescentou `/studio/conta/pedidos`, `/studio/conta/apoio` e
`/studio/conta/preferencias` como seções próprias do Studio, apoiadas nos
controllers/Form API do Portal (Commerce, Support e AppearanceForm). Nada é
migrado, duplicado nem transferido para Marley; Conta e Perfil permanecem
experiências independentes, com a Conta original permanente em
`/conta-legada` e hub em `/` de ACCOUNT. Unit atualizados no Git mas
não executados; Runtime/Firefox ESR, acesso, rotas, checkout e comparação
visual continuam pendentes conforme o roadmap do Studio. Este adendo não
altera o escopo editorial de Magazine/Marley.

### WE SATIVA no ACULTA Studio (provider inicial / Wiki dev.76)

A tela inicial V03 está implementada em `/studio/we-sativa/inicio` (Studio dev.6
/ Wiki dev.109). Ela apresenta criação e retomada de rascunho somente após
revalidação de acesso pelo owner Wiki, e liga ao Meu Trabalho quando acessível.
Kernel integrado: 2 testes / 208 asserções com ACULTA420; Unit do controller 3/3, suíte
Studio 20/61. Runtime e homologação visual seguem pendentes.

O entrypoint inicial `/studio/we-sativa` está implementado no propósito
`account`. O provider Studio delega ao `aculta_wiki.contributor_workspace`, que
consulta verbetes e propostas próprias com cursores independentes, revalida
Entity Access e encaminha criar/editar/contribuir/acompanhar para rotas e Form
API da Wiki. A experiência não expõe a fila de moderação. Kernel Wiki/Studio
completo revalidado em MariaDB no dev.77: 23 testes / 1.039 assertions na
classe de ciclo da Wiki, incluindo rota, autoria, paginação e acesso. Os forms
Core da Wiki oferecem retorno cacheável ao workspace se a rota e o acesso
forem autorizados. Browser
Firefox ESR/X11, 320 px, Olivero, fallback visual e Runtime permanecem
pendentes; a suíte visual Studio atual não cobre ainda o workspace WE SATIVA.

### Integração editorial Wiki no GanjaLab (Studio dev.2 / Wiki dev.19)

Código integrado à `WikiTab` existente: filas autorizadas de revisões e propostas são consultadas pelo serviço dono
`aculta_wiki`; decisões e edição seguem seus formulários e controles de acesso. Testes Kernel combinados foram
executados. Runtime, Firefox ESR/X11, mobile e WCAG continuam pendentes; isso não constitui homologação visual.

### Studio dev.5 — separar autoria Wiki e acesso editorial no GanjaLab

`create wiki_entry content` sozinho não abre a WikiTab editorial nem a listagem
compartilhada do GanjaLab. A autoria fica na WE SATIVA. `edit any` abre o
inventário editorial; revisão e moderação de propostas mantêm seções próprias.
Unit Studio passou 7 testes / 27 asserções. A suíte Firefox ESR visível foi
iniciada, mas ficou aguardando o Drush `uli` sem produzir URL de login ou
artefatos; foi encerrada após cerca de dois minutos. Runtime e visual seguem
pendentes.

Este documento substitui a antiga sequência linear que condicionava a criação do Studio ao fechamento do Magazine. A migração `article`/`blog` → `revista`, já executada no Runtime de teste, permanece documentada em `web/modules/custom/aculta_portal/modules/aculta_magazine/docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md` e no CHANGELOG. Não repetir migração, apagar dados ou importar Config Sync global.

## Produto e fronteiras

- **Magazine:** dono do bundle `revista`, campos, classificações, validação editorial, SEO, URLs, referências, configurações e workflow de conteúdo; oferece Forms, ações e adaptadores autorizados para consumo.
- **ACULTA Studio:** plataforma transversal; **GanjaLab** é experiência editorial e de moderação; lista `Meu trabalho`, navegação, estados UI, feedback, composição e jornadas. Não cria storage, grants ou workflow paralelos. O Magazine não cria segunda área editorial.
- **ACULTA420:** aparência exclusiva por Core SDC + UI Suite, tokens, versões light/dark, responsividade e cabeçalho multidomínio preservado. Não portar templates/CSS legados que não sigam esse padrão.
- **Drupal Core:** Entity/Revision Access, Content Moderation, Workflows, Form API, Views, CKEditor 5 e render/cache são fontes de verdade.
- **aculta_user:** identidade, perfil público, cartão autor/coautor e política compartilhada de primeira participação. Não duplicar regras de confiança no Magazine.
- **aculta_forum:** comentários contextuais `field_aculta_discussion` no Node `revista`, sem tópico público automático; ForumPost revisionável é entidade do fórum público, não substituição implícita dos Comments da Revista.
- **aculta_image / aculta_gallery / Portal uploads:** Media, conversão, alt text, uploads e galerias. Usar a política compartilhada apenas onde exista consumidor real. Não inventar biblioteca ou upload paralelo.
- **aculta_document_viewer / aculta_pdf_viewer:** visualizadores reutilizados onde houver documento editorial permitido.
- **aculta_messenger:** notificações, destinatários e entrega; Magazine emite eventos/contratos de publicação, não engine própria.
- **aculta_statistics:** métricas editoriais quando contratos de coleta estiverem disponíveis. Não duplicar tracking.
- **aculta_portal:** Domain/purpose, URLs canônicas, serviços globais e políticas técnicas.

## Estado constatado na branch

**Implementado/código:** bundle `revista` único; hooks de formulário, autoria e discussão; serviço de apresentação `PostPresentation`; Views administrativas e por sessão; metatags e Pathauto; galeria via CKEditor/Entity Embed; coautoria via `node_co_authors`. Migração dos 16 Nodes/revisões e 26 aliases no Runtime de teste registrada; não equivale a deploy de produção.

**Parcial / exigir prova:** Content Moderation e transições por papel, permissões de coautores e revisões não-default, envio e moderação de Comment contextual, preview real de rascunho, URLs entre account/magazine, Config Sync com drift concorrente, comportamento mobile do formulário autenticado, cache de perfil público e SEO/canonical em páginas acessíveis.

**Observações de código:** `RevistaFormHooks` só recolhe conjunto fixo de campos, sem contrato formal de grupos e sem prova de erro/foco nos detalhes; `PostPresentation` monta links absolutos para compartilhamento e consulta perfil/caches, exigindo teste de Domain, privacidade, render-cache e XSS; `ArticleAuthorsHooks` só atua em Node publicado e view mode full no purpose magazine. São alvos de auditoria, não defeitos comprovados.

## Roadmap versionado — microfases

| Fase | Dono | Entrega | Dependência / gate |
| --- | --- | --- | --- |
| **MAG 0.5-A** | Magazine | Auditoria comparativa de código, Config Sync, roles, fields, displays, indexes, SEO, services e dependências; matriz implementado/planejado/DEFERRED; atualizar README e contratos | Não importar sync global nem mudar Runtime |
| **MAG 0.5-B** | Magazine | Consolidar contratos editoriais para Studio: operações criar/editar, formulários Core, ações por transição, rotas canônicas, cache/access/revision; revisar `node_co_authors` | Studio E-B.2-T para listagens seguras; Drupal Content Moderation |
| **MAG 0.5-C** | Magazine | Workflow editorial revista: rascunho, revisão, publicado, rejeitado/arquivado **somente conforme estados reais**; ações autorizadas, concorrência e trilha de revisão | Grants de entidade/revisão, configuração efetiva e fixtures; não conceder publish por papel implícito |
| **MAG 0.5-D** | Magazine | Form API progressivo: título, resumo e corpo primeiro; secundários sob details; erros abrem seções e focam campos; confirmação real de save, prevenção de perda de dados | Studio E-C/E-D e ACULTA420 SDC/UI Suite; sem CSS/JS de design paralelo |
| **MAG 0.5-E** | Magazine | Prévia fiel e privada das revisões autorizadas, canonical/alias/purpose, Metatag, Open Graph, classificação, cards de autoria e compartilhamento | Studio integra interface; Portal Domain; User privacidade; teste de cache anônimo/autenticado |
| **MAG 0.5-F** | Magazine + Forum | Discussão editorial contextual, moderação e first-discussion policy; eventos após publicação real sem vazamento de rascunhos | Forum Comment adapter + User; workflow ForumPost público NÃO migra automaticamente para Comment |
| **MAG 0.5-G** | Magazine + Media owners | Integração editorial de imagem/capa/galeria, alt, embeds autorizados e opções de documentos, sem duplicar mídia | Image/Gallery/Portal upload policy/viewers; fixture de anexo privado |
| **MAG 0.5-H** | Magazine + Messenger/Statistics | Eventos editoriais idempotentes somente após transição salva para `published`; Magazine publica a origem, Messenger concentra aviso, canais, deduplicação e acesso | Decidir a audiência (autor/coautores e exclusão do ator) antes do producer; não inferir destinatários por permissão/comentário, nem criar subscrição, inbox ou conversa. Adicionar categoria `revista` às preferências comuns antes do e-mail. Statistics segue contrato próprio |
| **MAG 0.5-I** | Magazine | Higiene de configuração, consultas Views, Search API existente, SEO/categorias e documentação; tratar drift seletivamente | Fórum/workflow e mudanças concorrentes; backup e validação antes de import |
| **MAG 0.5-R** | Magazine + Studio + Tema | Unit/Kernel/Functional/browser: 2 autores, coautor, editor, moderador, anônimo, revisões, cache quente, Domain, 320–1440px, WCAG 2.2 AA, dark/light, teclado/no-JS/AJAX e benchmarks | Todos os contratos consumidores reais e fixtures aprovadas |
| **MAG 0.5-RC** | Responsável | Consolidar changelog, notas, rollback, decisão de RC | Nenhum gate DEFERRED crítico; não publicar nem mesclar automaticamente |

### Sequência de implementação independente

Começar agora por **0.5-A**, seguida de **0.5-B** (auditoria de access, revisões e providers) e itens internos de **0.5-E** (SEO, autoria e URL) que não exigem nova UI. Preparar testes de **0.5-C** sem ativar transições. Também revisar em **0.5-I** a configuração sob ownership do Magazine, sem importar configuração global.

### Sequência bloqueada ou coordenada

- **Studio E-B.2-T → E-B.3 → E-C/E-D:** UX `Meu trabalho`, cartões, editor e transições visíveis. Magazine fornece backend; Studio monta experiência.
- **ACULTA420:** novos componentes visuais exigem SDC/UI Suite e consumidor real; nenhuma substituição por CSS do Magazine.
- **Forum/User:** homologação de comentários e primeira moderação; manter Comment contextual mesmo com ForumPost revisionável no fórum público.
- **Gallery/Image/Portal:** upload, publicação contextual, Media privada, otimização e visualizadores; verificar políticas antes de criar widget.
- **Messenger/Statistics:** aguardar contrato de consumo real antes de enviar mensagens ou coletar métricas.
- **Config Sync:** coordenar dependências do workflow do Fórum, Domain por ambiente e demais alterações paralelas antes de importar; não forçar import global.

## Critérios e segurança

1. Preservar produção, conteúdo, NID, UUID, revisões, aliases, mídia e referências. Migração legada concluída não se repete.
2. Consultas com `accessCheck(TRUE)`; Entity Access e Revision Access por item, inclusive coautores; respeitar cache contexts/tags/max-age.
3. Um draft ou revisão pendente não aparece em listagens públicas, metatags públicas, contadores, notificações ou resultados sem autorização.
4. Reusar Form API, workflows e transições reais do dono; Studio não salva/publica diretamente por acesso presumido.
5. Separar contas `email_confirmed`, autores/coautores, editor, moderador, visitante e roles cruzados; simular duas contas e cache quente.
6. Preservar Institution Bar e Domain Header; tema controla layout. Mobile-first, SDC/UI Suite, 320–1440px, dark/light, reduced motion, teclado, leitor de tela e sem JS.
7. Cada microfase: commit exclusivo na branch/PR autorizada; README, CHANGELOG, roadmap, testes e evidências. Atualizar status do componente apenas após verificação real.
8. Homelab: confirmar branch/worktree, banco, URI, ambiente e backups antes de qualquer comando persistente. Distinguir PASS/FAIL/PARTIAL/DEFERRED. Não alegar Runtime com teste estático.

## Referências

- `docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md`
- `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md`
- `docs/portal/STUDIO-REVISTA-EDITOR-VISUAL.md`
- `docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md`
- `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md`
- `docs/operations/DEBT-REGISTER.md`
- `web/modules/custom/aculta_portal/modules/aculta_magazine/docs/README.md`


---

## Anexo — conteúdo anterior preservado na fusão (2026-10-22)

Este anexo mantém, sem alteração, o conteúdo local anterior à reescrita acima, incluindo registros de execução. Em caso de conflito, prevalecem as seções principais.

## Integração com o ACULTA Content Index

### Exclusão segura de publicações e anexos — planejamento 2026-10-22

Na IDX 0.1-R, a Revista deve solicitar ao serviço comum do Portal a análise e
limpeza de anexos quando uma publicação for excluída por ação editorial
autorizada. A Revista fornece suas relações estruturadas e embeds de CKEditor;
Portal revalida referências compartilhadas e `aculta_image` remove imagens
somente se continuarem sem uso. A limpeza nunca será acionada pelo mero fato de
um Media deixar de aparecer no Content Index. Ver
[`CONTENT-CLEANUP-POLICY.md`](CONTENT-CLEANUP-POLICY.md).

- A Revista fornece provider de metadados e revalida Entity Access no purpose
  canônico `magazine`; o catálogo não assume propriedade do conteúdo ou da
  autorização.
- O bundle Media compartilhado `remote_video` pertence ao `aculta_portal` e
  pode ser consultado pelo Index após revalidação de Entity Access. A descoberta
  da relação publicação→vídeo embutido precisa de contrato explícito do provider
  Magazine; não projetar URL oEmbed nem copiar o corpo.
- `field_editorial_gallery` estabelece relação estruturada com Gallery sem
  substituir o embed de apresentação. O comando
  `aculta-magazine:migrate-gallery-references` oferece prévia e execução em
  lotes; migra somente embeds únicos resolvidos e deixa referências múltiplas
  para revisão editorial. Importar configuração e homologar a migração seguem
  pendentes antes do backfill no ambiente.
- O Index fornece consultas para o Studio; escrita e ações continuam sujeitas
  a acesso e revisão do módulo proprietário e às decisões do Core.
- Referência: [roadmap ACULTA Content Index](ACULTA-CONTENT-INDEX.md).

| Marco | Entrega |
| --- | --- |
| 0.1.0 | Auditar código, Config Sync, Runtime de desenvolvimento, consumidores e referências da Revista. |
| 0.2.0 | Criar o bundle único `revista`, campos, displays, categorias, SEO, Views e workflow, mantendo os bundles antigos durante a migração. |
| 0.3.0 | Migrar Nodes, revisões, Comments, aliases e referências por APIs suportadas; depois aposentar `article` e `blog` com rollback verificado. |
| 0.4.0 | Consolidar Fórum e Galeria como subkernels reutilizáveis, com permissões e apresentação editorial; retirar conversor e duplicações obsoletas. |
| 0.5.0 | Validar gates e regressões, atualizar documentação e concluir a Revista antes de iniciar o Studio. |
| 0.6.0 | Fundar `aculta_studio`: frontend, shell, abas condicionais, providers e access/cache. |
| 0.7.0 | Studio Revista: criação, revisão, moderação de comentários contextuais e galerias por referências. |
| 0.8.0 | Studio Fórum: moderação, denúncias e tópicos, respeitando o subkernel. |
| 0.9.0 | Studio Wiki: verbetes e discussões no contexto Wiki, sem tópicos públicos automáticos. |
| 0.10.0 | Studio Cursos: LMS/Group, turmas, materiais e fórum contextual. |
| 0.11.0 | Studio Galerias/Biblioteca: Gallery + Media + Image + Document Viewer conforme disponibilidade real. |
| 0.12.0 | Studio Projetos e ferramentas transversais: domínio principal, User, Messenger e Statistics. |
| 0.13.0 | Integrações opcionais e hardening; Eventos somente quando `aculta_eventos` existir. |
| 0.14.0 | Homologação: regressão, Domain, access, Group, acessibilidade, performance e gates. |
| 1.0.0 | Estabilização, documentação e liberação controlada. |

## Registro de execução — Magazine 0.3.0 — 2026-10-21

- No Runtime `toca`/`aculta_runtime`, 16 Nodes e 16 revisões foram migrados a
  `revista`, preservando NID, UUID, VID e 26 aliases. Nenhum conteúdo ou arquivo
  compartilhado foi excluído.
- `article` e `blog` foram aposentados após inventário e contagem zero; Views,
  Pathauto, Search API, Metatag e referências de LMS foram ajustados ao bundle
  canônico. `/node/add/revista` está ativo; as rotas antigas retornam 404.
- HTTP no purpose Revista: raiz 200, `/revista` redireciona canonicamente e as
  listagens editoriais respondem 200. A próxima entrega permanece 0.4.0:
  integração compartilhada de Fórum/Galeria, permissões e revisão completa do
  workflow e da apresentação. Studio continua bloqueado até o gate 0.5.0.

## Registro de execução — Magazine 0.5-B — 2026-10-22

Limpeza de dados no Runtime de teste autorizada em 2026-10-22: 12 Nodes
`revista`, 17 Media `image` e 17 arquivos sem uso externo foram removidos; um
Media `remote_video` ficou preservado. Evidências e backups:
[`MAGAZINE-TEST-CLEANUP-2026-10-22.md`](MAGAZINE-TEST-CLEANUP-2026-10-22.md).

- Contratos editoriais consumíveis pelo Studio publicados em
  `aculta_magazine/docs/STUDIO-INTEGRATION-CONTRACT.md`,
  `EDITORIAL-ACCESS-MATRIX.md`, `EDITORIAL-ACTOR-STATE-MATRIX.md` e
  `ROUTES-DOMAIN-CONTRACT.md`. O inventário parte das rotas, acessos, form
  modes e revisões reais do Core; onde não há API, o documento registra lacuna.
- O contrato de formulário (`RevistaFormHooks` + `js/revista-form.js`) agrupa os
  campos secundários, abre o grupo com erro e leva o foco ao primeiro campo
  inválido, mantém o envio sem JavaScript e restaura o aviso de alterações não
  salvas no retorno por bfcache.
- Testes: Kernel do Magazine para autoria, coautoria, revisão não padrão,
  preview, transições permitidas e recusadas, purpose/domínio, cache entre
  contas e validação do formulário; Unit do Portal para a precedência de
  purpose de rota. Os testes usam os atores reais do Config Sync; nenhum papel,
  estado ou transição foi inventado.
- Integração mínima no Studio verificada em 0.5-B.9. O consumidor não pediu
  wrapper nem adaptador: usa `node.add`, `entity.node.edit_form`,
  `entity.node.canonical`, revisões, `content_moderation_control` e a listagem
  do próprio Studio com `accessCheck(TRUE)`. A única divergência de contrato era
  o purpose das operações, corrigida no consumidor em `7500090c`, commit
  separado do Magazine.
- Operações ainda condicionadas a fases posteriores, registradas no contrato:
  E-B.3 (ações editoriais explícitas, caso apareça consumidor), E-C (editor
  dedicado) e E-D (revisão/aprovação). Coautoria na interface (B-02) e listagem
  de coassinados (B-03) dependem de decisão com `aculta_user`/`aculta_studio`.
- Nenhuma importação de configuração, migração, exclusão ou alteração de
  permissões foi executada. MAG 0.5-C não foi iniciada.

## Registro de execução — Magazine 0.5-A — 2026-10-22

- Auditoria arquitetural completa publicada em
  `aculta_magazine/docs/AUDIT-MAG-0.5-A.md`, com inventário do bundle `revista`,
  campos, displays, workflow, serviços, hooks, testes e segurança, e achados
  A-01 a A-12 classificados por dono.
- Documentação normativa criada: `ARCHITECTURE.md` (tabela exata das oito
  transições do workflow `revista`) e `DEPENDENCY-MATRIX.md` (Studio, User,
  Forum, Image, Gallery, Document/PDF Viewer, Messenger, Statistics, LMS
  Resources, Portal/Domain, ACULTA420 e contrib).
- Config Sync: `node.revista.default` (form) passou a declarar a dependência
  `workflows.workflow.revista` e os displays `default`, `full` e `teaser`
  passaram a exibir `content_moderation_control`, espelhando o Runtime. Sem
  importação, sem `drush cim` e sem alteração persistente no homelab. Os quatro
  arquivos deixaram de aparecer em `drush config:status`; o drift restante é de
  outras frentes e não foi tocado.
- Achados de outros donos apenas documentados, sem correção local: coautoria na
  interface (A-03, decisão com `aculta_user`), Pathauto legado (A-05), hook de
  instalação histórico (A-06, mantido para atualização), purpose `main` no link
  de edição do Studio (A-07) e feed do User consultando bundle aposentado (A-12, resolvido em `aculta_user` 0.4.0-dev.69; agora consulta `revista`).
- Próximo: 0.5-B, contratos editoriais consumíveis pelo Studio (acesso,
  revisão, preview, transições, Form API e domínio), sem segunda interface
  GanjaLab dentro do Magazine.

## Registro de execução — Magazine 0.4.0 — 2026-10-21

- O provider contextual do `aculta_forum` passa a reconhecer somente `revista`
  com `field_aculta_discussion`; o teste confirma que os bundles aposentados
  `article` e `blog` não são mais resolvidos. Acesso continua agregado do Node,
  do campo Comment, do estado da discussão e das permissões do usuário.
- A integração de galeria usa as entidades `media_gallery` existentes no corpo
  CKEditor 5 via Entity Embed, com criação/renderização de propriedade de
  `aculta_gallery`; a decisão do responsável foi manter esse vínculo existente,
  sem novo campo de galeria no Node editorial.
- Unit do provider: 5 testes / 28 assertions PASS. Gate de Revista permanece em
  andamento: workflow e permissões do papel manual `content_editor` foram
  configurados; falta validar apresentação e operações autenticadas de revisão,
  comentários/galerias, regressões e consistência de Config Sync antes de 0.5.0.
  Studio ainda não iniciado.

## Gates irrenunciáveis

- Não trocar bundle por SQL direto; não excluir Nodes, comentários, aliases, Media, relações Group ou histórico sem comprovação e reversibilidade.
- Conferir `core.extension`, Runtime e código para distinguir estados implementados de planos; por exemplo, Document Viewer pode estar em migração e eventos ainda não existir.
- Preservar o funcionamento independente de Fórum e Galeria, o processamento único de imagens, identidade comum e as políticas do Messenger.
- Migração editorial preserva NID, UUID, aliases, histórico, Comments e referências; não usar `NodeStorage::updateType()` nem o conversor que recria Nodes.
- Studio não administra permissões de conteúdo diretamente: cada operação chama Form API/Entity Access/Workflows do consumidor.
- ACULTA420 é dono de estilos; Portal e submódulos entregam dados, comportamento e templates neutros.
- Commits em branch/PR compartilhadas devem conter exclusivamente arquivos e hunks próprios, sem nova PR, rebase, merge ou force-push.

## Matriz de abas do Studio

Estrutura implementada na fase 0.6.0: áreas em abas superiores (uma por dono), submenu à esquerda com as seções de
cada área e abas próprias de cada seção, com as ações no painel. Cada área implementa `sections()`. As áreas abaixo
são a matriz-alvo; a presença de uma aba depende do dono estar habilitado e da permissão real de cada ação.

O Studio é frontend integrado à Minha Conta. Itens não autorizados não aparecem; rotas e operações devem também aplicar suas próprias verificações de acesso. **Disponibilidade planejada, não implementação concluída.** As abas seguem a diretiva normativa (`docs/architecture/aculta-kernels.md`); a página agregadora inicial do Studio não é aba e não consta dessa matriz.

| Aba | Dono funcional | Ações previstas | Serviços compartilhados |
| --- | --- | --- | --- |
| Revista | `aculta_magazine` | Criar `revista`, rascunhos, revisão, categorias, comentários | User, Image, Forum contextual, Gallery |
| Fórum | `aculta_forum` | Tópicos, denúncias, respostas, moderação | User, Image, Messenger |
| Wiki | Wiki atual | Verbetes, propostas, revisões, discussão do verbete | User, Forum contextual, Document Viewer |
| Cursos | `aculta_lms`/Group | Cursos e turmas autorizados, aulas, materiais, avaliações e conversas | User, Document Viewer, Messenger, Forum contextual |
| Galerias | `aculta_gallery` | Criar/organizar álbuns, selecionar e referenciar Media | Gallery, Image, User |
| Projetos | Node `project`, purpose principal | Gestão institucional e relações com publicações/galerias | User, Gallery, Document Viewer |
| Biblioteca | Drupal Media/File | Seleção e organização autorizada, documentos | Image, Document Viewer |
| Métricas | `aculta_statistics` | Painéis e contadores permitidos | Serviços estatísticos existentes |
| Eventos (futuro) | `aculta_eventos` | Eventos e programação quando o módulo existir | Providers opcionais |

## Entregas mandatórias por fase

**Primeiro Magazine (0.1.0–0.5.0)**: auditar consumidores `article`/`blog`, definir `revista`, preservar ou descartar legado somente após inventário/backup e autorização de ambiente, consolidar Views, campos, taxonomia, workflow, SEO e comentários, comprovar independência de Fórum/Galeria e concluir os gates. A exclusão autorizada de conteúdo legado não autoriza apagar Media, galerias, projetos, usuários nem dados compartilhados.

**Depois Studio (0.6.0–1.0.0)**: implementar submódulo `aculta_studio`, shell no frontend e providers independentes por aba, reaproveitando permissões, Forms e Views do proprietário. Não desenvolver duas frentes em paralelo. Testar editorial, moderadores, Wiki, educadores, Domain, Group e usuários de acesso restrito antes de considerar concluído.

**Fronteira funcional:** projetos permanecem no domínio principal; eventos pertencem a `aculta_eventos`; Galeria e Fórum são subkernels independentes; Studio é ferramenta transversal e Magazine é consumidor.

## Contratos de UX do Studio (2026-10-22; especificação, não implementação)

- [Conceito visual e navegação pessoal-first](STUDIO-VISUAL-CONCEPT.md): `Minhas publicações` e equivalentes são o foco padrão em cada área; conteúdo de terceiros só em vistas de equipe/moderação inequivocamente separadas, após autorização dos donos.
- [Fluxos de criação, edição, preview e revisão](STUDIO-EDITOR-FLOWS.md): editor desktop/mobile, integração com Form API, CKEditor 5 quando cabível, estados de workflow, feedback e critérios de aceitação.
- Linha visual `0.2.0-dev.1` é **independente** dos marcos funcionais `0.1.0–1.0.0` deste roadmap; não implica que qualquer fase funcional foi concluída, nem autoriza implementação sem gates.

## Alinhamento visual LMS Course Builder — 2026-10-22

O planejamento específico [Course Builder mobile-first](STUDIO-LMS-COURSE-BUILDER-VISUAL.md) introduz a linha **CB-UX 0.3.0–0.3.8** como trilha de design e implementação progressiva do marco funcional **Studio Cursos 0.10.0**; não renumera nem marca como concluídas as etapas anteriores. O foco padrão é **Meus cursos** (criados/geridos), com vistas separadas para Equipe e turmas. O construtor apresenta **Curso → Módulo → Item pedagógico** com árvore à esquerda e editor à direita no desktop e **uma tela por tarefa no mobile**; edição por Form API/CKEditor 5 e navegação AJAX progressiva. Reordenação precisa de API canônica, operação equivalente por teclado/mobile e avaliação do impacto em turmas ativas. Acesso a cursos meramente visíveis não equivale a permissão de gestão. O curso pertence a Group/LMS; Studio não persiste a árvore nem notas.

## Identidade ACULTA Studio Marley / Ganjalab

A experiência editorial é **ACULTA Studio Marley** (`marley`); a experiência estudantil é **ACULTA Studio Ganjalab** (`ganjalab`). A infraestrutura segue `aculta_studio`. Consultar [contrato de identidade e rotas](ACULTA-STUDIO-IDENTITY.md) e [UX de aprendizagem](GANJALAB-LEARNING-UX.md). Nomes funcionais das áreas e regras dos módulos proprietários permanecem inalterados.
