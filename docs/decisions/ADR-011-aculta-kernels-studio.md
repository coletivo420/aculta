# ADR — Kernel ACULTA, subkernels compartilhados e Studio

**Estado:** Aceita como direção arquitetural; implementação em fases. **Data:** 2026-10-21.

## Contexto

ACULTA contém serviços transversais (usuários, imagens, documentos, mensagens e confirmação), mecanismos compartilháveis com estruturas próprias (Fórum e Galeria), e consumidores (Revista, Wiki, LMS, Projetos, Eventos). A interface editorial é fragmentada entre rotas administrativas dos subsistemas.

## Decisão

Adotar os níveis **Core/contrib → Kernel ACULTA → subkernels (Fórum/Galeria) → consumidores funcionais**, com **ACULTA Studio** como interface frontend transversal e **ACULTA420** como responsável exclusivo pela apresentação. Esta é uma classificação lógica e não uma cadeia de dependências rígidas. Contratos seletivos e adapters opcionais evitam ciclos.

- Fórum público guarda apenas discussões gerais; discussões em publicação, verbete ou turma pertencem ao contexto original e reutilizam contratos do Fórum.
- Galeria é subkernel reutilizável (`aculta_gallery`): álbuns independentes da Revista, que a consome; processamento de imagem continua em `aculta_image`.
- Kernel ACULTA: `aculta_portal`, `aculta_user`, `aculta_image`, `aculta_document_viewer`, `aculta_messenger` e `aculta_mail_confirmer`/`aculta_mail_confirmer_user`, sem duplicar dados Core/contrib. Confirmação de e-mail não concede privilégio editorial, e mensagens não substituem o controle de confirmação.
- Revista tem **um** tipo de publicação `revista` e categorias editoriais. Migração de `article`/`blog` concluída por Entity API, preservando NID, UUID, revisões e aliases; ver evidência e rollback no [plano de migração](../../web/modules/custom/aculta_portal/modules/aculta_magazine/docs/MIGRATION-ARTICLE-BLOG-TO-REVISTA.md).
- Projetos pertencem ao domínio principal. Eventos terão o módulo próprio `aculta_eventos`.
- Studio oferece abas autorizadas por área (Revista, Fórum, Wiki, Cursos, Galerias, Projetos, Biblioteca, Métricas, Eventos futuro) e reutiliza Views, Form API e workflows dos donos.

## Consequências

Nenhum renomeio de módulo, alteração de esquema ou migração de conteúdo decorre automaticamente deste ADR. Adoção em fases exige inventário, provas de acesso cross-domain/Group, documentação dos providers/consumers e testes. A apresentação continua sob `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

Detalhamento normativo: [hierarquia de kernels](../architecture/aculta-kernels.md). Roadmap: [Studio e Revista](../portal/STUDIO-MAGAZINE-ROADMAP.md).
