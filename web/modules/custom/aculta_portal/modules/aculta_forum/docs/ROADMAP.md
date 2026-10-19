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

## `0.1.0-dev.3` — Author Pane moderno (`aculta_user` + ACULTA420) (código implementado)

- Implementar variantes `author_forum`, `author_comment`, `author_forum_stacked`,
  `author_growroom_historico`; preparar `author_lms` e `author_wiki`.
- `AuthorIdentityResolver` aplica a `PublicProfilePolicy`, resolve avatar pelo
  `aculta_image` e mantém cache; perfil público exige opt-in da foto.
- `AuthorCardBuilder` retorna tema hook neutro; ações continuam sendo render
  arrays fornecidos por consumidores que já resolveram acesso.
- ACULTA420 implementa o SDC experimental `content/author-card`, inline e
  empilhado, com fallback por inicial, foco visível e tokens semânticos.
- Não portados: data de entrada, presença online, estatísticas, badges, assinatura
  e ações sociais; a política existente os exclui ou exige contexto/cache ainda
  não implementados. Integração do author card no Fórum contrib é para dev4+.
- Unit e gates estáticos direcionados em execução; browser/Runtime não validados,
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.4` — Motor executável (fundação)

- Módulo `aculta_forum`, Context API, resolver extensível e provider do fórum público.
- Configuração de instalação de Node/Comment/Taxonomy para tópicos independentes.
- Testes unitários de resolução, suporte e ambiguidade de providers.
- Views, formulários, permissões contextuais completas, instalação/runtime e prova Kernel multi-contexto seguem para fases posteriores; nenhum update/import é executado aqui.
- Runtime de teste habilitado com `--uri=https://growroom420.aculta.toca.net.br`; serviço/configuração conferidos, nenhum Node de teste criado. PHP CLI 8.4.26 limita a validação da baseline 8.5; PHP-FPM/web ainda pendente.

## `0.1.0-dev.5` — Retirada do Forum contrib

- **Em andamento: auditoria do Runtime de teste concluída; remoção não executada.**
- Provider editorial iniciado em `aculta_forum`; Unit access/cache PASS e prova
  Kernel confirma storage Comment compartilhado entre `article`, `blog` e tópico.
- `article` e `blog` agora têm Config Sync/configuração de instalação para
  `field_aculta_discussion`; o formatter Core está preparado e `author_comment`
  usa o builder compartilhado do `aculta_user`. Kernels confirmam o campo
  compartilhado e a política pública. A configuração das duas instâncias e dos
  displays full foi aplicada parcialmente no Runtime; status default aberto
  verificado em memória, sem persistir Nodes ou Comments.
- Foram observados 30 nodes `forum`, dos quais 12 publicados vinculam
  discussões a publicações. `aculta_magazine` e `aculta_user` ainda têm
  consumidores ativos; ver `docs/MIGRATION-REMOVAL.md`.
- Em Magazine 0.1.0-dev.10/.11, hooks de insert/update/delete e a API
  `ForumTopicSync` foram removidos. `LegacyPublicationTopicLookup` somente lê
  vínculos históricos para apresentação/conversão. `DiscussionHooks` omite o
  trecho antigo quando o campo existe.
  O Forum contrib ainda não foi desinstalado e referências legadas permanecem.
  Os tópicos-casca antigos serão apagados depois; não
  importar tópicos nem comentários antigos para os novos contextos.
- Inventário imediatamente pré-operação e restore point validado ainda pendentes.
- Troca de tipo da publicação re-hospeda Comments compartilhados antes de remover o Node antigo; transação e runtime ainda não foram exercitados nesta entrega.
- Exclusão escopada aprovada em ambiente de teste, uninstall oficial,
  remoção do Composer posterior e verificação de resíduos.
- Produção não é tocada por execução automática.

## `0.1.0-dev.6` — Growroom420 funcional

Categorias/subcategorias, tópicos, respostas, permissões, Views/navegação e
Author Cards do `aculta_user` para discussões públicas independentes.

Preparação parcial: formulário e displays Core de `aculta_forum_topic` foram
adicionados em dev.10 e aplicados ao Runtime de teste. A Page display serve a
raiz em dev.12; `/criar-topico` usa o Entity Form Core com Entity Create Access
em dev.13. Nenhum tópico foi salvo. A fase dev.6 permanece aberta até acesso
por participantes autorizados, submissão de tópico/resposta, moderação,
integração visual e UX serem validados. A retirada contrib dev.5 também
permanece aberta por depender de consumidores migrados e restore point válido.

Dev.14 acrescentou a ação “Criar tópico” à listagem pública apenas quando a rota
Core autoriza a conta atual, com cacheability de permissões e participação. A
ação ainda aguarda apresentação completa no ACULTA420 e validação visual;
nenhuma submissão persistida ocorreu. Testes Unit e gates estáticos direcionados
passaram; Runtime confirmou raiz 200, ação ausente para anônimo e rota de criação
403. Usuário autorizado e navegador permanecem sem homologação.

Dev.11 adicionou a View `aculta_forum_public_topics` e o campo de domínio
canônico; dev.12 conectou a Page display à raiz do purpose forum por meio do
caminho interno `/discussoes`. Runtime confirmou raiz 200, rota classificada no
purpose `forum`, lista vazia e 404 direto para `/discussoes` e `/forum`. O bloco
do acervo contrib permanece sem colocação. Criação/resposta persistida, tema e
remoção do legado continuam pendentes.

Dev.15 adiciona filtro exposto de categoria ao Page display, usando Taxonomy e
Views Core no vocabulário ACULTA. Runtime confirmou o plugin, renderização do
seletor e resposta 200 com query; o vocabulário está vazio, sem termos cadastrados.

`aculta_user 0.4.0-dev.33` integra os cartões `author_forum` e
`author_growroom_historico` nos modos full/teaser de `aculta_forum_topic`. A
integração não altera cartões de comentários, autoria editorial, LMS ou Wiki;
Runtime confirmou a implementação OOP e `author_forum` em Node não salvo em
memória, sem alterar o total de tópicos. Render de tópico persistido e inspeção
visual seguem pendentes. A última checagem HTTP direta da raiz retornou 301 para
`/discussoes`; uma requisição com query de diagnóstico retornou 200. A rota direta
continua restrita. A análise do Core e do Redirect confirmou que os atributos do
Request não sobrevivem ao cache de correspondência de rotas; a correção está em
Portal `0.4.0-dev.184`; após cache rebuild, `/` retornou 200 e `/discussoes`
continuou 404. Nenhum conteúdo novo foi criado.

Portal `0.4.0-dev.185` / Forum `0.1.0-dev.16` adicionam uma mensagem Core
`text_custom` para a listagem vazia. Após importação parcial só da View, HTTP
confirmou a mensagem na raiz, filtro presente e `/discussoes` fechado; zero
tópicos/comentários criados. Inspeção visual segue pendente.

Portal `0.4.0-dev.186` / Forum `0.1.0-dev.17` acrescentam ao papel
`email_confirmed` somente criar, editar e excluir o próprio `aculta_forum_topic`.
O Config Sync preserva as permissões/dependências e peso observados no Runtime;
não concede edição/exclusão de qualquer tópico. No Runtime `toca`, a importação
parcial do papel permitiu à conta de teste acessar a rota e renderizar em memória
o formulário Core (título, corpo e categoria). O formulário não foi enviado e
continuam zero tópicos e comentários. Testes Unit cobrem o contrato da role, não
substituem o teste funcional de submissão; a fase dev.6 permanece aberta.

## `0.1.0-dev.7` — Comentários universais

Comment compartilhado quando tecnicamente suportado; renderização contextual,
encadeamento, formulários, permalink e moderação por contexto.

## `0.1.0-dev.8` — Revista/publicações

Concluído parcialmente: Comment direto em `article`/`blog` foi instalado no
Runtime de teste e os hooks que criavam, atualizavam ou despublicavam tópicos
contribuídos foram removidos. Preservar autoria editorial e nunca criar tópico
público para publicação. Serviços de leitura/conversão e o módulo legado ficam
até a migração dos consumidores e o corte seguro documentado.

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

- Cada fase encerra em commit próprio na PR já aberta, com commits pequenos, changelog, documentação,
  testes e limitações reais.
- Fase documental não atualiza versões de runtime de módulos/tema.
- Para operações de ambiente, seguir `docs/architecture/environments.md` e
  validar hostname, pwd, Git, Drush URI/banco, efeitos e recuperação.
- O estado local não comprova Runtime; usar PASS/FAIL/PARTIAL/DEFERRED.
- Variantes de autoria são contratos distintos por contexto. A autoria histórica do Growroom usa `author_growroom_historico`; ela nunca passa pelo resolver de contas User.
- O plano antigo F1–F9 e seus resultados permanecem histórico; decisões
  incompatíveis estão marcadas como superadas em `DECISOES.md`.
