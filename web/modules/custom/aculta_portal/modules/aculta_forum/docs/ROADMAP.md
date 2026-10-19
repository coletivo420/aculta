# Roadmap — ACULTA Forum

Linha do projeto `aculta_forum`: começou em **0.1.0-dev.1**. Roadmap de escopo
aprovado pelo responsável em 2026-10-19; o estado real de cada entrega fica no
CHANGELOG e nos commits. Dev.1 e dev.2 são documentais/fundacionais; não alteram
Runtime.

## Direção atual de produto: experiência Discourse Ubuntu

O responsável definiu como referência uma experiência parecida com
[discourse.ubuntu.com](https://discourse.ubuntu.com/). O ACULTA adotará os
padrões de leitura e navegação adequados à comunidade, mantendo Drupal nativo,
contextos privados e a identidade visual ACULTA420. A análise detalhada,
referências observadas, limites de escopo e critérios de aceite ficam em
[`DISCOURSE-EXPERIENCE.md`](DISCOURSE-EXPERIENCE.md).

Prioridades para implementação: (1) categorias compreensíveis e atividade
recente; (2) listagem densa e legível, com participantes e filtros; (3) leitura
linear, progresso e resposta contextual; (4) seguir/salvar/denunciar e
notificações; (5) preferências e acabamento mobile/acessível. IA, chat em tempo
real, aplicativo nativo e previews remotos não são requisitos deste roadmap.

| Versão | Entrega | Estado |
| --- | --- | --- |
| `0.1.0-dev.26` | Fórum como aba em Conteúdo; “Comentários e moderação” como subaba; discussões contextuais fora das filas genéricas | Runtime de teste: abas carregadas e filtro exercitado; HTTP autenticado pendente |
| `0.1.0-dev.25` | Fórum em seção independente no menu Content e correção da View que causava HTTP 500 | Runtime PARTIAL: menu, access e renderização de ambas as Views confirmados; HTTP autenticado pendente |
| `0.1.0-dev.24` | Links do Fórum no mesmo menu Content usado por Galerias | Implementado com dev.25 |
| `0.1.0-dev.23` | Links de menu explícitos para Fórum, comentários do Fórum e Revista | Implementado; Runtime PARTIAL, árvore e acesso uid 5 confirmados; HTTP autenticado pendente |
| `0.1.0-dev.22` | Separação administrativa do Fórum e contratos por contexto; Revista com conteúdo e comentários reunidos em `/painel-administrativo/conteudo/revista` | Implementado; Runtime PARTIAL, sem sessão editorial de navegador |

## Política estrutural do painel administrativo

O conteúdo de discussão não se mistura à listagem genérica em
`/painel-administrativo/conteudo`. Fórum é uma aba própria nesse painel; seus
tópicos não entram na View genérica e “Comentários e moderação” fica como aba
filha da seção Fórum. Revista reúne publicações e comentários em
`/painel-administrativo/conteudo/revista`; LMS terá área em
`/painel-administrativo/lms/forum` restrita a curso/turma/atividade e autorização
de Educador; Wiki terá área em `/painel-administrativo/wiki/forum` para
comentários de verbetes. LMS e Wiki só ativam suas rotas junto dos respectivos
adapters e depois de provar isolamento por contexto. Ver [contratos de contexto](CONTEXTS.md).

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
Author Cards do `aculta_user` para discussões públicas independentes. O modelo
visual detalhado está em `DISCOURSE-EXPERIENCE.md`.

Preparação parcial: formulário e displays Core de `aculta_forum_topic` foram
adicionados em dev.10 e aplicados ao Runtime de teste. A Page display serve a
raiz em dev.12; `/criar-topico` usa o Entity Form Core com Entity Create Access
em dev.13. O estado sem conteúdo descrito nos relatórios `.187` e anteriores é
histórico; veja abaixo a atualização `.189`. A fase dev.6 continua aberta para
UX visual, respostas reais e revisão das ferramentas de comunidade. A retirada
contrib dev.5 também permanece aberta por depender de consumidores migrados e
restore point válido.

Dev.14 acrescentou a ação “Criar tópico” à listagem pública apenas quando a rota
Core autoriza a conta atual, com cacheability de permissões e participação. A
ação ainda aguarda apresentação completa no ACULTA420 e validação visual;
nenhuma submissão persistida ocorreu. Testes Unit e gates estáticos direcionados
passaram; Runtime confirmou raiz 200, ação ausente para anônimo e rota de criação
403. Usuário autorizado e navegador permanecem sem homologação.

Histórico até `.187`: Dev.11 adicionou a View `aculta_forum_public_topics` e o campo de domínio
canônico; dev.12 conectou a Page display à raiz do purpose forum por meio do
caminho interno `/discussoes`. Runtime confirmou raiz 200, rota classificada no
purpose `forum`, lista vazia e 404 direto para `/discussoes` e `/forum`. O bloco
do acervo contrib permanece sem colocação. Criação/resposta persistida, tema e
remoção do legado continuam pendentes.

Histórico até `.187`: Dev.15 adiciona filtro exposto de categoria ao Page display, usando Taxonomy e
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

Portal `0.4.0-dev.187` / Forum `0.1.0-dev.18` fixam no teste que tópico
público usa o Comment compartilhado `aculta_discussion_post`, inicia com
respostas abertas e exibe o formulário Core inline apenas a participantes com
`post comments`; anônimos não comentam. Runtime confirmou os valores da
configuração ativa e a permissão da conta uid 5. Como não há tópico salvo, não
foi possível renderizar o formulário associado a uma entidade persistida nem
enviar resposta. Nenhum dado foi criado; o fluxo funcional segue pendente.

### Categorias e tópicos de homologação

O campo `field_aculta_categories` é obrigatório e usa o vocabulário novo
`aculta_forum_categories`. A hierarquia inicial e seus tópicos `[TESTE]` foram
criados no Runtime de Homelab por solicitação explícita do responsável, como
fixtures para validar navegação e apresentação; isso substitui a pendência
anterior de aguardar aprovação de nomes/hierarquia. Os registros não são
Config Sync e não pertencem ao acervo legado. Inventário e remoção controlada:
[`TEST-FIXTURES.md`](TEST-FIXTURES.md). Nenhum comentário foi criado.

Portal `0.4.0-dev.189` / Forum `0.1.0-dev.20` criam 12 termos (4 categorias e
8 subcategorias) e 8 tópicos `[TESTE]` de uid 5 no Runtime de teste. A correção
Pathauto dá aliases portugueses ao bundle novo. A View GET pública agora não
recebe CAPTCHA global, que a fazia abortar durante o render: a raiz mostra os
cinco tópicos da página inicial e filtro `categoria=77` mostra o tópico
esperado; os oito aliases retornam 200. O legado continua com 30 Nodes e 8
termos; 0 comentários foram criados. Unit 11/64, PHPCS e gates Portal/fronteira/
mapa/slugs passam. Inspeção visual no navegador ainda é necessária. Essa
entrega cobre a base da experiência de listagem descrita em
`DISCOURSE-EXPERIENCE.md`; não encerra a dev.6.

Portal `0.4.0-dev.190` / Forum `0.1.0-dev.21` substituem o limite sem paginação
de cinco itens por um pager Core de 20 tópicos, com navegação em português e
quantidade fixa. A listagem pode apresentar as oito fixtures simultaneamente e
continua paginável à medida que o conteúdo crescer. O filtro de categoria e os
filtros de acesso/domínio permanecem inalterados. Unit e HTTP no Runtime de teste
confirmam a View; inspeção visual e submissão de tópico/resposta seguem
pendentes. Dev.6 continua aberta.

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

Categoria com descrição, subcategoria, índice geral, breadcrumbs, tópicos
fixados, contagem cacheável de respostas/última atividade e listagem por
atividade/data. Adotar o padrão observado no Ubuntu Discourse: descrições que
explicam o destino e listas que expõem título, categoria, respostas e atividade.
Tópicos-sombra/movidos apenas se um fluxo ACULTA aprovado precisar deles.

## `0.1.0-dev.10` — Advanced Forum: leitura e estatísticas

Views Latest/Top/New/Unread/sem resposta, histórico de leitura por participante,
marcar como lido e primeiro conteúdo não lido, estatísticas e atividade
cacheáveis. Antes de juntar novos/não lidos numa navegação unificada, prototipar
e validar rótulos e filtros com usuários; a própria comunidade Discourse relata
trade-offs nesse modelo. Visualizações só após definir deduplicação, retenção,
bot policy e cache.

## `0.1.0-dev.11` — Advanced Forum: apresentação

Implementar lista de categorias, topic rows, cartões de participantes, leitor
linear, timeline/progresso, composer e navegação responsiva no ACULTA420. Usar
presets e layouts lateral/empilhado já previstos; validar light/dark, teclado,
mobile, reduced-motion e fallback sem JavaScript.

## `0.1.0-dev.12` — Participação e moderação

Flags, denúncias, salvar/seguir, citação, aprovação, edição e bloqueio por APIs
existentes e access real. Permissões de participação não serão derivadas de
trust levels/ranking do Discourse.

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
