# Roadmap — ACULTA Studio, Revista e integração dos kernels

**Estado:** planejamento; não constitui evidência de execução. Os marcos são sequenciais e cada alteração de Runtime demanda gates e autorização próprios.

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

## Gates irrenunciáveis

- Não trocar bundle por SQL direto; não excluir Nodes, comentários, aliases, Media, relações Group ou histórico sem comprovação e reversibilidade.
- Conferir `core.extension`, Runtime e código para distinguir estados implementados de planos; por exemplo, Document Viewer pode estar em migração e eventos ainda não existir.
- Preservar o funcionamento independente de Fórum e Galeria, o processamento único de imagens, identidade comum e as políticas do Messenger.
- Migração editorial preserva NID, UUID, aliases, histórico, Comments e referências; não usar `NodeStorage::updateType()` nem o conversor que recria Nodes.
- Studio não administra permissões de conteúdo diretamente: cada operação chama Form API/Entity Access/Workflows do consumidor.
- ACULTA420 é dono de estilos; Portal e submódulos entregam dados, comportamento e templates neutros.
- Commits em branch/PR compartilhadas devem conter exclusivamente arquivos e hunks próprios, sem nova PR, rebase, merge ou force-push.
