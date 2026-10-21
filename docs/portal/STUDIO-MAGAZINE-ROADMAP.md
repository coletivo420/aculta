# Roadmap — ACULTA Studio, Revista e integração dos kernels

**Estado:** planejamento; não constitui evidência de execução. Os marcos são sequenciais e cada alteração de Runtime demanda gates e autorização próprios.

| Marco | Entrega |
| --- | --- |
| 0.1.0 | Auditar Kernel ACULTA, Fórum, Galeria, Revista, Wiki, LMS, Projetos, permissões, configuração ativa e documentação. |
| 0.2.0 | Formalizar contratos, ADR, interfaces de consumidores, providers opcionais e fronteiras de acesso/tema. |
| 0.3.0 | Criar configuração do bundle editorial `revista` e categorias, sem excluir `article`/`blog`. |
| 0.4.0 | Migrar conteúdos em procedimento auditado/dry-run: revisões, comentários, autoria, URLs, mídias, referências, Domain e SEO. |
| 0.5.0 | Consolidar `aculta_magazine`, Views, hooks, workflow e SEO; só aposentar conversor legado e bundles após homologação. |
| 0.6.0 | Formalizar consumo transversal do `aculta_gallery` e contratos de `aculta_image`; nada de Galeria proprietária da Revista. |
| 0.7.0 | Fundar `aculta_studio` (submódulo previsto): frontend, shell, abas condicionais, providers e access/cache. |
| 0.8.0 | Studio Revista: criação, revisão, moderação de comentários contextuais e galerias por referências. |
| 0.9.0 | Studio Fórum: moderação, denúncias e tópicos, respeitando o subkernel. |
| 0.10.0 | Studio Wiki: verbetes e discussões no contexto Wiki, sem tópicos públicos automáticos. |
| 0.11.0 | Studio Cursos: LMS/Group, turmas, materiais e fórum contextual. |
| 0.12.0 | Studio Projetos: gerenciamento do domínio principal. |
| 0.13.0 | Studio Galerias/Biblioteca: Gallery + Media + Image + Document Viewer conforme disponibilidade real. |
| 0.14.0 | Studio Eventos: conectar apenas quando o módulo independente `aculta_eventos` existir. |
| 0.15.0 | Serviços transversais: User, Messenger, confirmação, métricas e avisos sem storage duplicado. |
| 0.16.0 | Homologação: migração, regressão, Domain, acesso, Group, accessibility, performance e gates. |
| 1.0.0 | Estabilização, documentação, liberação controlada. |

## Gates irrenunciáveis

- Não trocar bundle por SQL direto; não excluir Nodes, comentários, aliases, Media, relações Group ou histórico sem comprovação e reversibilidade.
- Conferir `core.extension`, Runtime e código para distinguir estados implementados de planos; por exemplo, Document Viewer pode estar em migração e eventos ainda não existir.
- Preservar o funcionamento independente de Fórum e Galeria, o processamento único de imagens, identidade comum e as políticas do Messenger.
- Studio não administra permissões de conteúdo diretamente: cada operação chama Form API/Entity Access/Workflows do consumidor.
- ACULTA420 é dono de estilos; Portal e submódulos entregam dados, comportamento e templates neutros.
- Commits em branch/PR compartilhadas devem conter exclusivamente arquivos e hunks próprios, sem nova PR, rebase, merge ou force-push.
