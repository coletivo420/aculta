# ACULTA Forum — Growroom420 e discussões contextuais

Submódulo em preparação de `aculta_portal`. Linha do projeto: **0.1.0-dev.3**. O
direcionamento vigente, aprovado pelo responsável em 2026-10-19, é reconstruir
as discussões ACULTA com APIs nativas e retirar Drupal Forum contrib depois de
uma migração segura, por fase própria. Este diretório ainda é documental; não
há `.info.yml`, rotas ou motor de discussão executável nesta base. `aculta_user`
já dispõe de um builder neutro de cartões; o ACULTA420 apresenta o SDC
experimental compartilhado. A integração dos cartões nas discussões atuais e no
motor novo segue por contexto e fases.

## Arquitetura e regras

- Growroom420 hospeda somente tópicos públicos independentes.
- Publicações, LMS e Wiki usam comentários no contexto da entidade original; não
  há tópico público automático para publicação ou verbete.
- LMS tem adapter opcional e acesso revalidado por Group/membership e Entity Access.
- `aculta_user` é o dono único de identidade e cartões de autor/participante/perfil. Variantes distinguem `author_forum`, `author_comment`, `author_editorial`, `author_lms`, `author_wiki` e `author_growroom_historico`; autoria histórica não passa pelo resolver de User.
- ACULTA420 é o dono visual; `aculta_forum` entrega contrato neutro, acesso e cache.
- Core/contrib permanecem fonte de verdade para Node, Comment, Taxonomy, Views,
  Search API, History e Flag. Sem storage paralelo de resposta/categoria.
- Imagens passam por `aculta_image`; avisos pelo `aculta_messenger`.
- Remoção do Forum contrib e qualquer exclusão de dados não fazem parte de dev.1.

## Documentação

- [Arquitetura](docs/ARQUITETURA.md)
- [Contextos e API](docs/CONTEXTS.md)
- [Ownership](docs/OWNERSHIP.md)
- [Matriz Advanced Forum](docs/ADVANCED-FORUM-PORT.md)
- [Pesquisa](docs/PESQUISA.md)
- [Plano de retirada/migração](docs/MIGRATION-REMOVAL.md)
- [Classes e hooks planejados](docs/CLASSES-AND-HOOKS.md)
- [Matriz de testes](docs/TEST-MATRIX.md)
- [Decisões](docs/DECISOES.md)
- [Roadmap](docs/ROADMAP.md)
- [Reconstrução histórica](docs/RECONSTRUCAO.md) — histórico de pesquisa/importação, não autorização de exclusão.
- [Changelog](CHANGELOG.md)

O contrato de cartões, privacidade e API fica em
[`aculta_user/docs/AUTHOR-CARDS.md`](../aculta_user/docs/AUTHOR-CARDS.md). Normas
comuns: [Drupal 11+](../../../../../../docs/portal/DRUPAL-11-STANDARDS.md),
[guardrails](../../../../../../docs/portal/GUARDRAILS.md),
[fronteira módulo-tema](../../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md),
[mapa módulo-tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md),
[fonte da verdade](../../../../../../docs/portal/SOURCE-OF-TRUTH.md),
[participação](../../../../../../docs/portal/PAPEIS-PARTICIPACAO.md),
[LMS](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md),
[Wiki](../../../../../../docs/portal/WIKI.md) e
[notificações](../../../../../../docs/portal/AVISOS-E-NOTIFICACOES.md).

## Estado confirmado na auditoria

Base local/GitHub: `origin/main` `d4a0d66e` (2026-10-19). Composer contém e
`core.extension.yml` habilita `drupal/forum` 1.1.3. Há Node `forum`, Taxonomy
`forums`, Comment `comment_forum`, Views, flags, serviços e apresentações
existentes em `aculta_user`, `aculta_magazine`, `aculta_messenger` e ACULTA420.
Consulte [inventário de remoção](docs/MIGRATION-REMOVAL.md) e
[auditoria de cartões](../aculta_user/docs/AUTHOR-CARDS.md).

## Versão e execução

A fase atual `0.1.0-dev.3` entrega a base dos cartões; dev.1 foi documental e
dev.2 fundou identidade cacheável. Releases Portal, User e tema conservam linhas
próprias. Fases funcionais precisam de commits rastreáveis, validações reais e
status Runtime explícito. O roadmap detalha os gates. A retirada do Forum contrib
continua futura, sem desinstalação, exclusão, import/export de configuração,
update de banco ou deploy nesta fase.
