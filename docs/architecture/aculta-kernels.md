# Hierarquia de kernels e consumidores ACULTA

Decisão arquitetural normativa. Esta classificação **não** corresponde a um novo tipo de módulo Drupal, não altera a instalação e não cria dependências obrigatórias entre todos os módulos.

## Idioma da plataforma

A plataforma ACULTA é monolíngue em português do Brasil (`pt_BR`; código Drupal `pt-br`). O idioma padrão do conteúdo, fixtures de Kernel, eventos e e-mails é `pt-br`. Não implementar negociação de idioma, conteúdo traduzível, fallbacks multilíngues ou abstrações de idioma em novos componentes sem uma decisão explícita de produto. Testes podem usar valores de outros idiomas apenas para comprovar rejeição de dados incompatíveis ou preservar comportamento legado; isso não constitui suporte multilíngue.

## Camadas e fontes de verdade

1. **Drupal Core e contrib** são as fontes de verdade para Node, Comment, User, Profile, Media, File, Group, Views, Workflows, Message e demais entidades/infraestruturas. Não duplicar storage.
2. **Kernel ACULTA** é o conjunto de serviços transversais e contratos comuns: `aculta_portal` (integração e política multidomínio), `aculta_user` (identidade, perfis e cartões), `aculta_image` (pipeline, otimização, deduplicação e remoção segura de imagens), `aculta_document_viewer` (leitura de arquivos conforme fases efetivamente implantadas), `aculta_messenger` (experiência de mensagens e avisos, sobre entidades contrib) e `aculta_mail_confirmer`/`aculta_mail_confirmer_user` (confirmação de e-mail/conta). Consultar o Runtime para distinguir migrações planejadas de módulos já habilitados.
3. **Subkernels funcionais reutilizáveis**: `aculta_forum` para experiência de discussões e moderação contextual; `aculta_gallery` para álbuns, referências de Media e experiência de galeria. São independentes da Revista e do Studio. Usam serviços do Kernel ACULTA e Core/contrib.
4. **Consumidores funcionais**: `aculta_magazine` (Revista), Wiki (Node/Taxonomy/Views existentes), `aculta_lms`/Group, Projetos do domínio principal e futuro `aculta_eventos`. Consomem seletivamente kernel e subkernels, mas preservam dados, acesso e regras de seu contexto.
5. **`aculta_studio` (submódulo existente; contratos multi-experiência em expansão)**: espaço de trabalho frontend por abas para autores, editores, moderadores e educadores. Agrega formulários, Views, operações e filas autorizadas, sem se tornar dono dos conteúdos, permissões, discussões ou galerias.
6. **ACULTA420**: única camada visual pública, com SDC, UI Patterns, UI Styles e tokens. Código de negócio nos módulos; apresentação no tema conforme `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Dependências e fronteiras

A hierarquia é **conceitual**; não significa `info.yml dependencies` universais ou dependências de todos em todos. Cada módulo declara apenas dependências reais. Integrações opcionais usam adapters/provedores de serviço e verificam disponibilidade e acesso, evitando ciclos, especialmente entre Studio, Fórum, Galeria, Revista e LMS.

- **Fórum público**: tópicos independentes; comentários da Revista, discussões Wiki e de cursos permanecem vinculados às entidades de origem. O subkernel reaproveita UX e contratos sem transplantar conteúdo para o fórum público. LMS preserva Group/membership.
- **Galeria**: álbuns independentes e compartilháveis, mantidos por `aculta_gallery`; Revista, Projetos, Eventos, Wiki e LMS podem referenciá-los. `aculta_image` é o único dono do processamento/limpeza de imagens. Mover ou desinstalar a Revista não deve afetar álbuns.
- **Identidade**: cartões `author_forum`, `author_comment`, `author_editorial`, `author_lms`, `author_wiki` e variantes pertencem a `aculta_user`. Os consumidores acrescentam somente o papel/contexto.
- **Documentos**: Media/File e regras de acesso são do Core e do contexto funcional; visualização de PDF/DOCX/planilhas é capacidade compartilhada do Document Viewer, sem storage paralelo. Não presumir que formatos planejados já estão ativos.
- **Mensagens e avisos**: reutilizar `aculta_messenger` e a infraestrutura Message/Private Message; não criar motores de avisos por módulo ou substituir canais sem decisão expressa.
- **Confirmação**: `aculta_mail_confirmer` valida e-mail; confirmação **não concede** automaticamente privilégio editorial. A autorização continua nos access handlers, Group e permissões reais.
- **Domain**: Projetos são institucionais do purpose principal; a Revista pode referenciar projetos, sem assumir seu cadastro. Eventos pertencem exclusivamente ao futuro `aculta_eventos`.
- **Studio**: abas Revista, Fórum, Wiki, Cursos, Galerias, Projetos, Biblioteca, Métricas e, quando disponível, Eventos. Abas, ações, contagens e Views devem respeitar entity access e contexto do domínio. Nenhuma rota frontend deve contornar Form API, Content Moderation ou permissões do sistema proprietário.

## Revista: bundle editorial unificado

O único bundle Node da Revista é `revista`; **Editorial**, **Blog/Opinião**, Notícias e demais seções são categorias via taxonomia, não bundles. Galeria não vira bundle da Revista: a integração existente incorpora entidades `media_gallery` pelo CKEditor 5/Entity Embed, sob propriedade de `aculta_gallery`. O tipo `editorial_highlight` é curadoria da Home e deve ser auditado separadamente, não eliminado automaticamente.

**Estado do código e do Runtime (2026-10-21):** `RevistaPublications::BUNDLES` lista somente `revista`; 16 Nodes foram migrados por ferramenta temporária já removida, preservando NID, UUID, VID original e 26 aliases; `article` e `blog` foram aposentados. Na revisão posterior, 12 Nodes legítimos receberam novas revisões para restaurar o estado publicado; os quatro Nodes de teste seguem rascunhos. O Runtime tem 16 Nodes `revista` e 3 `editorial_highlight`. O provider de discussão do Fórum reconhece o bundle canônico e mantém Comment no Node hospedeiro, sem criar tópicos públicos. O histórico e os backups ficam no [plano de migração](../../web/modules/custom/aculta_portal/modules/aculta_magazine/docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md). Mudanças de conteúdo futuras continuam exigindo inventário, reversibilidade e preservação de referências, mídia e workflow.

## Fluxo de manutenção

Novos serviços transversais e adaptações devem ter um dono único, contrato documentado, teste de consumo entre subsistemas, cacheability/access e gate módulo–tema. Atualizar este documento, o ADR, documentação do dono e índices no mesmo commit. Não reestruturar pastas PHP por causa dos nomes conceituais.

## Matriz de responsabilidades — referência para agentes

| Camada | Proprietário | Responsabilidade e fronteira |
| --- | --- | --- |
| Core/contrib | Drupal User, Profile, Media, File, Node, Comment, Taxonomy, Views, Group, Workflows | Entidades, persistência e access handlers nativos. |
| Kernel | `aculta_portal` | Integração, Domain purposes e políticas compartilhadas; não substitui o armazenamento especializado. |
| Kernel | `aculta_user` | Perfis, privacidade, identidade e cartões de autoria/participação; Drupal User/Profile são a fonte de verdade. |
| Kernel | `aculta_image` | Upload, transformações, compressão, variantes, deduplicação e limpeza de imagens. |
| Kernel | `aculta_document_viewer` | Leitura autorizada de PDF/DOCX/planilhas conforme implementação real; arquivos pertencem a Media/File. |
| Kernel | `aculta_messenger` | Experiência de mensagens, avisos e notificações; Private Message/Message continuam fontes de verdade. |
| Kernel | `aculta_mail_confirmer` e `aculta_mail_confirmer_user` | Confirmação de e-mail e integração com contas; não concedem privilégios de publicação. |
| Subkernel | `aculta_forum` | Discussões públicas próprias e contratos reutilizados em comentários/discussões contextuais de outros módulos. |
| Subkernel | `aculta_gallery` | Álbuns próprios, associação de Media e experiências reutilizáveis de galeria; processamento pertence a Image. |
| Consumidor | `aculta_magazine` | Publicações, categorias, SEO e fluxo editorial da Revista; Gallery/Forum são fornecedores externos. |
| Consumidor | Wiki atual | Verbetes, revisões e discussão contextual, sem abrir tópicos públicos automaticamente. |
| Consumidor | `aculta_lms` / Group | Cursos, turmas, avaliação, autorização e discussões de turma. |
| Consumidor | Projetos (`node.type.project`) | Conteúdo do domínio principal; pode referenciar galerias e publicações. |
| Consumidor futuro | `aculta_eventos` | Eventos próprios; não criar dados de evento em Magazine. |
| Ferramenta | `aculta_studio` (planejado) | Painel frontend por abas; opera serviços dos proprietários, não armazena versões paralelas. |
| Visual | `aculta420` | SDC, UI Patterns, UI Styles, Twig, CSS e tokens. |

## Matriz de consumo transversal

Esta matriz representa o **contrato-alvo**, não a confirmação de que cada integração está implementada. Cada integração deve ser verificada, ativada seletivamente e testada.

| Consumidor | User | Image | Document Viewer | Messenger | Fórum | Galeria |
| --- | --- | --- | --- | --- | --- | --- |
| Revista | Autoria/coautoria | Capas | Documentos anexos | Avisos editoriais | Comentários na publicação | Álbuns referenciados |
| Wiki | Identidade editorial | Imagens | Fontes/documentos | Avisos | Discussão no verbete | Galerias referenciadas |
| LMS | Educadores/alunos | Capas | Materiais autorizados | Avisos de turma | Discussão sob Group | Galerias didáticas |
| Projetos | Autoria | Capas | Documentos | Avisos autorizados | Contextual, se previsto | Álbuns relacionados |
| Eventos (futuro) | Organizadores | Cartazes | Anexos | Avisos | Contextual, se previsto | Fotos/álbuns |
| Studio (futuro) | Identidade e acesso | Delegação | Delegação | Notificações | Operações de moderação | Operações de gestão |

### Regras de não duplicação

- **Fórum público ≠ discussão contextual**: não copiar comentários de Revista, Wiki ou LMS para tópicos públicos; o subkernel oferece experiência e contratos, não concentração indiscriminada do conteúdo.
- **Galeria ≠ imagem**: Gallery organiza álbuns; Image gerencia política dos arquivos e derivados. A Revista é consumidora, não dona da Galeria.
- **Mensagem ≠ confirmação de conta**: Messenger entrega comunicações autorizadas; Mail Confirmer controla confirmação de e-mail; publicação continua condicionada a permissões reais.
- **Studio ≠ módulo dono do dado**: usar Views/Form API/Entity Access/Workflows do consumidor, sem tabelas próprias equivalentes ou novos fluxos de permissão.
- **Kernel ≠ dependência obrigatória universal**: modules `*.info.yml` declaram apenas os serviços necessários; providers opcionais evitam ciclos.

## Decisão normativa: ACULTA Studio (2026-10-22)

Kernel taxonomy: aculta_studio is an existing installed-capable Drupal submodule that serves as shared experience/interface platform for GanjaLab, GanjaLab420, WE SATIVA and future custom user interfaces. Existing point 5 calling it planned is obsolete; existing GanjaLab routes are real, new shared experience providers are planned. Studio does not own domain content or editorial permissions.

[Contrato e roadmap transversal](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**
