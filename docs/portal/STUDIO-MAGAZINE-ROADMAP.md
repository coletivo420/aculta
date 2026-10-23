# Roadmap — ACULTA Studio, Revista e integração dos kernels

**Estado:** planejamento. Os marcos são sequenciais; o registro de execução abaixo contém evidências de Runtime e cada alteração persistente exige salvaguardas próprias.

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

## Identidade GanjaLab / GanjaLab420

O nome público de criação é **GanjaLab** (módulo técnico `aculta_studio`, URL `/studio`); a experiência do estudante é **GanjaLab420**. Consultar [contrato de marca](GANJALAB-BRANDING.md) e [UX de aprendizagem](GANJALAB420-LEARNING-UX.md). Esta definição não altera marcos funcionais nem indica conclusão das interfaces previstas.
