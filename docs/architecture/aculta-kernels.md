# Hierarquia de kernels e consumidores ACULTA

Decisão arquitetural normativa. Esta classificação **não** corresponde a um novo tipo de módulo Drupal, não altera a instalação e não cria dependências obrigatórias entre todos os módulos.

## Camadas e fontes de verdade

1. **Drupal Core e contrib** são as fontes de verdade para Node, Comment, User, Profile, Media, File, Group, Views, Workflows, Message e demais entidades/infraestruturas. Não duplicar storage.
2. **Kernel ACULTA** é o conjunto de serviços transversais e contratos comuns: `aculta_portal` (integração e política multidomínio), `aculta_user` (identidade, perfis e cartões), `aculta_image` (pipeline, otimização, deduplicação e remoção segura de imagens), `aculta_document_viewer` (leitura de arquivos conforme fases efetivamente implantadas), `aculta_messenger` (experiência de mensagens e avisos, sobre entidades contrib) e `aculta_mail_confirmer`/`aculta_mail_confirmer_user` (confirmação de e-mail/conta). Consultar o Runtime para distinguir migrações planejadas de módulos já habilitados.
3. **Subkernels funcionais reutilizáveis**: `aculta_forum` para experiência de discussões e moderação contextual; `aculta_gallery` para álbuns, referências de Media e experiência de galeria. São independentes da Revista e do Studio. Usam serviços do Kernel ACULTA e Core/contrib.
4. **Consumidores funcionais**: `aculta_magazine` (Revista), Wiki (Node/Taxonomy/Views existentes), `aculta_lms`/Group, Projetos do domínio principal e futuro `aculta_eventos`. Consomem seletivamente kernel e subkernels, mas preservam dados, acesso e regras de seu contexto.
5. **`aculta_studio` (planejado)**: espaço de trabalho frontend por abas para autores, editores, moderadores e educadores. Agrega formulários, Views, operações e filas autorizadas, sem se tornar dono dos conteúdos, permissões, discussões ou galerias.
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

## Revista: destino editorial aprovado, não migrado

O destino aprovado é o único bundle Node `revista` para publicações antes distribuídas em `article` e `blog`; **Editorial**, **Blog/Opinião**, Notícias e demais seções são categorias via taxonomia, não bundles. Galeria não vira bundle da Revista: ela consome `aculta_gallery`. O tipo `editorial_highlight` é curadoria da Home e deve ser auditado separadamente, não eliminado automaticamente.

**Estado do código:** `RevistaPublications::BUNDLES` ainda lista `article` e `blog`; Views, hooks e campos atuais ainda os consomem. Nenhuma mudança de dados é autorizada por este documento. Antes de migrar: inventariar Runtime, preparar dry-run e backup, escolher estratégia de integridade para nid/UUID/revisões/traduções, comentários, aliases/redirects, metatags, Domain, Entity Usage, referências, mídias e workflow. Não usar indiscriminadamente o conversor que recria e apaga Nodes. Não remover bundles antigos antes da homologação.

## Fluxo de manutenção

Novos serviços transversais e adaptações devem ter um dono único, contrato documentado, teste de consumo entre subsistemas, cacheability/access e gate módulo–tema. Atualizar este documento, o ADR, documentação do dono e índices no mesmo commit. Não reestruturar pastas PHP por causa dos nomes conceituais.
