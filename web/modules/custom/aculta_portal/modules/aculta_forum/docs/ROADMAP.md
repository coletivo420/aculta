# Roadmap — ACULTA Forum

Linha do projeto `aculta_forum`: começou em **0.1.0-dev.1**. Roadmap de escopo
aprovado pelo responsável em 2026-10-19; o estado real de cada entrega fica no
CHANGELOG e nos commits. Dev.1 e dev.2 são documentais/fundacionais; não alteram
Runtime.

## `0.1.0-dev.1` — Auditoria e fundação documental (entregue)

- Estado GitHub, branches e PRs; base `main` verificada.
- Inventário real de Forum contrib, Composer/config, consumidores, cartões,
  componentes, integrações e riscos de remoção.
- Pesquisa upstream de Drupal 11+ e matriz de capacidades do Advanced Forum.
- Contratos de quatro contextos, ownership, privacidade/cache e migration gates.
- Documentação de `aculta_forum`, `aculta_user`, Portal e ACULTA420 sincronizada.
- Nenhuma alteração de banco, Runtime, conteúdo, dependência, entidade ou rota.

## `0.1.0-dev.2` — Fundação de cartões (`aculta_user`) (entregue)

- Implementados `AuthorIdentityResolver` e `AuthorIdentity`, compondo perfil e link público já existentes.
- Variantes User compartilham payload mínimo; autoria histórica fica separada e não é convertida em conta.
- Testes Unit direcionados para payload neutro, cache e autoria legada.
- Sem renderização, CSS, alterações de tema ou mudanças no Runtime.

## `0.1.0-dev.3` — Author Pane moderno (`aculta_user` + ACULTA420)

- Implementar variantes `author_forum`, `author_comment`, `author_forum_stacked`,
  `author_legacy`; preparar `author_lms` e `author_wiki`.
- Portar capacidades pertinentes do Author Pane após validação funcional.
- Testar mobile, acessibilidade, modos de cor, cache e privacidade.

## `0.1.0-dev.4` — Motor executável

- Módulo `aculta_forum`, Context API/providers, Node/Comment/Taxonomy e Views.
- Configuração exportável, serviços DI, permissões, rotas e testes iniciais.
- Validar nomes <=32, instalação e uso real de Comment em mais de um contexto.

## `0.1.0-dev.5` — Retirada do Forum contrib

- Migrar/remove consumidores e tópicos automáticos de publicação.
- Inventário de dados e ponto de recuperação antes de qualquer remoção.
- Exclusão escopada aprovada em ambiente de teste, uninstall oficial,
  remoção do Composer posterior e verificação de resíduos.
- Produção não é tocada por execução automática.

## `0.1.0-dev.6` — Growroom420 funcional

Categorias/subcategorias, tópicos, respostas, permissões, Views/navegação e
Author Cards do `aculta_user` para discussões públicas independentes.

## `0.1.0-dev.7` — Comentários universais

Comment compartilhado quando tecnicamente suportado; renderização contextual,
encadeamento, formulários, permalink e moderação por contexto.

## `0.1.0-dev.8` — Revista/publicações

Remover sincronização automática antiga e usar comentários na entidade editorial;
preservar autoria editorial e nunca criar tópico público para publicação.

## `0.1.0-dev.9` — Advanced Forum: categorias e navegação

Índices, contadores, fixados/movidos/sombra quando pertinente, paginação,
navegação contextual e última atividade, todos access-aware.

## `0.1.0-dev.10` — Advanced Forum: leitura e estatísticas

Marcar lido/ler tudo, primeira resposta não lida, estatísticas e popularidade/
visualizações com modelo de cache/performance e privacidade aprovado.

## `0.1.0-dev.11` — Advanced Forum: apresentação

Completar seis presets, layouts lateral/empilhado, interfaces compactas e
light/dark no ACULTA420.

## `0.1.0-dev.12` — Participação e moderação

Flags, denúncias, salvar/seguir, citação, limites, aprovação, edição e bloqueio
de tópico via APIs existentes e access real.

## `0.1.0-dev.13` — Fóruns internos LMS

Adapter opcional `aculta_forum_lms`; curso/turma/atividade; Group/membership,
cartões educacionais, busca e notificações privadas. Provar isolamento em testes.

## `0.1.0-dev.14` — Contrato Wiki420

Provider futuro, discussão única por verbete, `author_wiki` e navegação interna.
Não reimplementar Wiki nesta fase.

## `0.1.0-dev.15` — Integrações gerais

Messenger, Image, Gallery, PDF, Game, eventos, cache e suas políticas de acesso.

## `0.1.0-dev.16` — Busca e descoberta

Search API, índice por contexto, filtros/Views e descoberta sem vazamento privado.

## `0.1.0-dev.17` — Consolidação

Revisar APIs, ownership, documentação, código morto, matriz Advanced Forum e
variantes; preparar homologação.

## `0.1.0-rc.1` — Homologação integral

Unit, Kernel, Functional, gates Drupal/tema/fronteira, Domain, acesso, cache,
segurança, browser/mobile, light/dark, acessibilidade, LMS, Wiki contratual,
Magazine, notificações, desempenho e install/update/uninstall/rollback.

## `0.1.0-rc.2` — Correções

Corrigir regressões verificadas e repetir cobertura pertinente.

## `0.1.0` — Estável

Release notes, upgrade, operação, rollback e revisão final. Não implantar produção
automaticamente.

## Regras de avanço

- Cada fase encerra em PR próprio, com commits pequenos, changelog, documentação,
  testes e limitações reais.
- Fase documental não atualiza versões de runtime de módulos/tema.
- Para operações de ambiente, seguir `docs/architecture/environments.md` e
  validar hostname, pwd, Git, Drush URI/banco, efeitos e recuperação.
- O estado local não comprova Runtime; usar PASS/FAIL/PARTIAL/DEFERRED.
- O plano antigo F1–F9 e seus resultados permanecem histórico; decisões
  incompatíveis estão marcadas como superadas em `DECISOES.md`.
