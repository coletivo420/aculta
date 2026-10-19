# ACULTA Forum — Growroom420 e discussões contextuais

Submódulo de `aculta_portal`. Versão atual: **0.1.0-dev.13**. O
direcionamento vigente, aprovado pelo responsável em 2026-10-19, é reconstruir
as discussões ACULTA com APIs nativas e retirar Drupal Forum contrib depois de
uma migração segura, por fase própria. A dev.4 introduziu o módulo executável e
a Context API; a View pública já serve a raiz do Runtime de teste e `/criar-topico`
usa Entity Form/Entity Create Access do Core. Nenhum tópico foi criado. `aculta_user`
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
- Remoção do Forum contrib e exclusão de dados aguardam os pré-requisitos da dev.5.

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

A dev.5 mantém a retirada em andamento: os Comments diretos de `article`/`blog`
estão instalados; os hooks e a API capazes de sincronizar tópicos legados foram
removidos. O índice público do novo bundle está ativo na raiz do Runtime de teste
e `/criar-topico` verifica Entity Create Access. Ainda faltam submissão
persistida/homologação visual, migrar consumidores contrib restantes e validar
restore point antes de excluir conteúdo/desinstalar o módulo. Releases Portal,
User e tema conservam linhas próprias; o roadmap detalha as próximas entregas.
