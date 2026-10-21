# Roadmap — ACULTA Studio, Revista e integração dos kernels

**Estado:** planejamento. Os marcos são sequenciais; o registro de execução abaixo contém evidências de Runtime e cada alteração persistente exige salvaguardas próprias.

| Marco | Entrega |
| --- | --- |
| 0.1.0 | Auditar código, Config Sync, Runtime de desenvolvimento, consumidores e referências da Revista. |
| 0.2.0 | Criar o bundle único `revista`, campos, displays, categorias, SEO, Views e workflow, mantendo os bundles antigos durante a migração. |
| 0.3.0 | Migrar Nodes, revisões, Comments, aliases e referências por APIs suportadas; depois aposentar `article` e `blog` com rollback verificado. |
| 0.4.0 | Consolidar Fórum/Galeria como serviços compartilhados, permissões e apresentação editorial; retirar conversor e duplicações obsoletas. |
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

## Gates irrenunciáveis

- Não trocar bundle por SQL direto; não excluir Nodes, comentários, aliases, Media, relações Group ou histórico sem comprovação e reversibilidade.
- Conferir `core.extension`, Runtime e código para distinguir estados implementados de planos; por exemplo, Document Viewer pode estar em migração e eventos ainda não existir.
- Preservar o funcionamento independente de Fórum e Galeria, o processamento único de imagens, identidade comum e as políticas do Messenger.
- Migração editorial preserva NID, UUID, aliases, histórico, Comments e referências; não usar `NodeStorage::updateType()` nem o conversor que recria Nodes.
- Studio não administra permissões de conteúdo diretamente: cada operação chama Form API/Entity Access/Workflows do consumidor.
- ACULTA420 é dono de estilos; Portal e submódulos entregam dados, comportamento e templates neutros.
- Commits em branch/PR compartilhadas devem conter exclusivamente arquivos e hunks próprios, sem nova PR, rebase, merge ou force-push.

## Matriz de abas do Studio

O Studio é frontend integrado à Minha Conta. Itens não autorizados não aparecem; rotas e operações devem também aplicar suas próprias verificações de acesso. **Disponibilidade planejada, não implementação concluída.**

| Aba | Dono funcional | Ações previstas | Serviços compartilhados |
| --- | --- | --- | --- |
| Início | `aculta_studio` (agregação) | Meus conteúdos, tarefas, revisão, avisos | User, Messenger, Views |
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
