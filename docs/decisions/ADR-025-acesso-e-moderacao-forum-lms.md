# ADR-025 — Acesso e moderação do Fórum no contexto do LMS

**Status:** vigente desde 2026-10-20.  
**Escopo:** tópicos e comentários associados a turmas e cursos do LMS.  
**Origem:** `0.1.0-dev.1`, `0.1.0-dev.2` e `0.1.0-dev.15`, [histórico arquivado de aculta_forum_lms](../history/changelog/aculta_forum_lms.md).

## Contexto

Discussões de turma são conteúdo do Fórum ligado a Groups do LMS. A matrícula,
o acesso ao curso e a permissão de avaliação pertencem ao Group; grants globais
do Fórum duplicariam essas fontes de autorização.

## Decisão

1. A resolução do contexto exige uma relação Group válida e, para turma, um
   único curso pai. Ausência ou ambiguidade falha fechada.
2. Leitura do tópico delega acesso ao Group e revalida membership no contexto.
   Educadores recebem acesso contextual pela permissão `grade students`.
3. Educadores podem ver Comments pendentes/rejeitados do contexto e executar a
   operação Core `approve`; isso não concede `update`, `delete` nem
   `administer comments`.
4. Contexto, cacheabilidade e autorização são reavaliados nos handlers e
   operações de moderação; o adapter não cria grants globais.

## Evidência vigente

`LmsDiscussionContextProvider` resolve e autoriza Group/curso;
`LmsCommentModerationAccessHooks` limita as operações a `view` e `approve`.
Contrato local: [aculta_forum_lms.md](../architecture/aculta_forum_lms.md).
