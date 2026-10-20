# Plano de retirada do Drupal Forum contrib

Status: `drupal/forum` foi desinstalado do Runtime de teste e removido do
Composer em 2026-10-20. Config Sync ainda está em revisão; a configuração não
foi importada integralmente no Runtime por causa de drift preexistente. Os
fixtures `[TESTE]` do bundle novo são tratados em
[`TEST-FIXTURES.md`](TEST-FIXTURES.md).

## Inventário conferido

| Tipo | Referências atuais observadas |
| --- | --- |
| Composer | `drupal/forum` foi removido de `composer.json`/lock; `drupal/history` permanece como dependência direta para marcadores pessoais de leitura. |
| Extensão | `forum` foi removido de `config/sync/core.extension.yml` e desinstalado do Runtime de teste. |
| Modelo de conteúdo | Node bundle `forum`, vocabulary `forums`, Node field `taxonomy_forums`, Comment bundle `comment_forum`, campo `comment_forum` no Node. Configs opcionais do módulo contrib definem ainda displays, formulário e settings. |
| Config Sync | `core.entity_*` de node.forum/comment_forum; `node.type.forum.yml`; taxonomy.vocabulary.forums.yml; `views.view.aculta_forum_topics.yml`; `flag.sinalizar_comentario` aponta a bundle `comment_forum`; `field_discussao` exportado em blog aponta à Node bundle `forum`; campos de autoria legada. |
| `aculta_user` | Em dev.42–.44, removidos consumidores contrib; regras atuais preservam a exceção de moderação para autoria histórica no bundle compartilhado. Kernel direcionado: 7 testes/84 assertions PASS. |
| `aculta_magazine` | Em dev.15 foram removidos `LegacyPublicationTopicLookup`, `ForumOpeningPost`, os campos `field_discussao` dos displays/configuração exportada e a dependência declarada `forum:forum`. `PublicationTypeConverter` continua movendo Comments compartilhados. Runtime cache rebuild e raiz Magazine HTTP 200; importação de configuração e conversão com Comments reais pendentes. |
| `aculta_messenger` | Em dev.56 foi removido `ForumTopicCounterHooks` (`preprocess_forums`), que pressupunha a listagem contrib. `MessageSubscribeEventHooks` atende o Node ACULTA `aculta_forum_topic`; avisos e contagens agregadas seguem em serviços próprios. |
| Tema | `forum-list.html.twig`, `node--forum--full.html.twig`, `comment--comment-forum.html.twig`, `forum-index.css`, `discussion.css`, traduções e estilos do padrão Forum. |
| Documentação/gates | Portal/Forum/LMS/Magazine, mapa módulo-tema, fronteira, sitemap/purpose, slugs, buscas, avisos e políticas de participação referem Forum legado. Fazer `rg` global antes da remoção final. |

Lista pesquisável no checkout: `rg -n -i 'drupal/forum|node.forum|comment_forum|taxonomy_forums|field_discussao|ForumTopicSync|ForumSectionResolver|forum\\.forum' composer.json composer.lock config/sync web/modules/custom/aculta_portal web/themes/custom/aculta420 docs`.
Este comando inclui strings históricas; revisar cada resultado e não apagar
referências de auditoria/changelog sem motivo.

### Efeitos verificados no código contrib instalado

Na versão instalada `drupal/forum` 1.1.3, foram inspecionados
`src/ForumUninstallValidator.php`, `forum.services.yml`, `forum.install` e
`tests/src/Functional/ForumUninstallTest.php`. O validator bloqueia o uninstall
enquanto houver qualquer Node do bundle `forum` ou termo no vocabulário
configurado do Forum. O teste funcional upstream confirma a ordem: remover os
Nodes; remover os termos; então executar o uninstall oficial.

`forum_uninstall()` não exclui Nodes, Comments nem termos por conta própria.
Ele apaga os storages dos campos `node.taxonomy_forums`,
`node.comment_forum` e `taxonomy_term.forum_container`, chama `FieldPurger` com
lote de até 10 itens e libera o nome de bundle `forum`. Portanto, a exclusão de
conteúdo autorizada precisa ser uma etapa explícita anterior; o uninstall não é
um substituto para a limpeza dos Nodes/Comments. Remover `taxonomy_forums`
também afeta todos os Nodes que tenham esse field storage, mesmo fora do bundle
`forum`; o inventário não encontrou motivo para assumir que apagar apenas os
Nodes satisfaz esse impacto.

O vocabulário `forums` tem o campo legado `field_categoria_revista`. A auditoria
somente leitura de 2026-10-19 encontrou 5 dos 8 termos com referência a
`editorial_category` e 3 sem valor. Os 12 Nodes de publicações que apontam para
tópicos antigos (8 `article`, 4 `blog`) já têm `field_editorial_category`; nos
12 casos a categoria direta é igual ao destino indicado pelo mapeamento antigo
do termo do Fórum. Assim, a categoria editorial não precisa ser migrada do
vocabulário antigo; o campo auxiliar e seus valores podem ser retirados com o
legado, enquanto os campos diretos e os termos `editorial_category` permanecem.
O field config em Config Sync pertence à estrutura Magazine e depende
explicitamente de `taxonomy.vocabulary.forums`; remover essa definição de campo
e revisar sua field storage antes de retirar a configuração do vocabulário.

Na auditoria de 2026-10-19, os tópicos antigos eram destino de
`field_discussao` e abertura histórica de 12 publicações. Essa situação foi
encerrada em 2026-10-20: a Entity API excluiu Nodes/termos antigos e removeu os
vínculos vazios, preservando as publicações. `field_aculta_discussion` segue
como Comment direto da Revista. A submissão de Comment real continua pendente;
isso não reverte a decisão de manter vazio o Fórum antigo.

Este estudo é somente código/documentação: nenhum validator foi contornado,
nenhum campo/termo/Node foi alterado e o módulo continua habilitado. A referência
API confirma que o validator existe para impedir perda de dados durante o
uninstall; ver [registro de mudança do Core](https://www.drupal.org/node/2404451)
e [API do validator](https://api.drupal.org/api/drupal/core%21modules%21forum%21src%21ForumUninstallValidator.php/function/ForumUninstallValidator%3A%3Avalidate/11.x).

### Registro histórico de consumidores removidos

O antigo `DiscussionHooks` consultava `comment_forum` sem `topic->access('view')`
para renderizar trechos editoriais. Foi removido após confirmar que todos os 12
Nodes atualmente referenciados têm `field_aculta_discussion`; a Revista usa agora
o formatter Core direto e não carrega resumo de Comments do tópico antigo.
`ForumTopicHooks` de links legados foi removido (o Runtime não possui Comments
`comment_forum`). `LegacyPublicationTopicLookup` e `ForumOpeningPost` foram
removidos em dev.15 após a limpeza documentada abaixo.

## Bundles Drupal Forum e decisão de limpeza Growroom

F-D32 registrou que os Nodes `forum` e Comments `comment_forum` representavam o
Growroom restaurado. Em 2026-10-20, F-D33 substituiu a decisão de preservar esse
conteúdo: o responsável determinou que o banco de teste ficasse sem tópicos,
respostas, termos do vocabulário `forums` ou referências ativas àqueles tópicos.
Nenhum conteúdo antigo será importado para os bundles ACULTA.

O Drupal Forum contrib continua instalado apenas até que os consumidores,
campos, Views, flags e configurações dependentes sejam retirados pelo processo
oficial. O novo vocabulário `aculta_forum_categories` e seus 12 termos são
estrutura atual e permanecem. A limpeza do Runtime não alterou Nodes da Revista,
Wiki, LMS, usuários ou comentários diretos de publicação.

## Auditoria do Runtime de teste — 2026-10-19

Ambiente confirmado antes da consulta: `hostname=toca`, checkout
`/home/piradopirata/projetos/aculta-site`, branch
`docs/aculta-forum-0.1.0-dev.1`, Drush conectado ao banco `aculta_runtime`.
Foi usado `--uri=https://growroom420.aculta.toca.net.br` para resolver o
purpose. Esse é o Runtime Homelab de teste, não produção. Consultas Entity API
com `accessCheck(FALSE)` foram somente de leitura e retornaram:

| Dado legado | Quantidade | Observação |
| --- | ---: | --- |
| Nodes bundle `forum` | 30 | 12 publicados vinculados a publicações; 1 publicado independente; 17 não publicados sem referência editorial |
| Nodes bundle `forum` referenciados por `field_discussao` | 12 | Um vínculo editorial por node; manter o vínculo temporariamente até o comentário direto estar operacional, depois excluir o tópico-casca sem importar seu conteúdo |
| Comments bundle `comment_forum` | 0 | Campo configurado, sem entidades Comment nesse bundle |
| Terms no vocabulário `forums` | 8 | Campo legado `field_categoria_revista`: 5 termos mapeiam para `editorial_category`, 3 vazios; categorias diretas das 12 publicações vinculadas conferem. Ver auditoria detalhada abaixo. |
| Arquivos de Config Sync com dependências dos bundles/campos antigos | 24 | Inclui permissões, flags, Magazine e displays |
| Flagging entities | 2 no total; 0 do Forum | Ambos apontam a `site_alert` (flags `aculta_site_alert_dismissed` e `aculta_site_alert_read`) |
| Drupal Core `validateUninstall(['forum'])` | Bloqueado | Exige excluir todos os nodes `forum` e todos os termos `forums` antes do uninstall |

Restore point pré-limpeza criado em 2026-10-19 no servidor `toca`, após
confirmar Drush URI Magazine e banco `aculta_runtime`:
`/home/piradopirata/.local/share/aculta-private-backups/forum-removal-before-delete-20261019.sql.gz`.
Arquivo fora do Git, modo `0600`, 5,841,604 bytes; `gzip -t` passou. SHA-256:
`17ce96d31de417f18892a6366a929162044df30b09dfbff058da77e6451183da`.
Este ponto recupera o banco, não os arquivos públicos/privados. O dump ainda não
foi restaurado em uma base isolada; nenhuma exclusão foi executada.

Consultas adicionais de 2026-10-19, somente leitura, confirmaram: 8 publicações
`article` e 4 `blog` têm `field_discussao` não vazio; todas as 12 têm
`field_aculta_discussion` e `field_editorial_category` preenchidos. Comparação
por entidade confirmou que as 12 categorias diretas correspondem exatamente
ao destino do `field_categoria_revista` no termo do tópico vinculado. No lado
dos Nodes `forum`, 4 usam o termo `Editorial` e 25 `Notícias`; entre os 12
tópicos vinculados à Revista, são 4 `Editorial` e 8 `Notícias`. Esses resultados
eliminam a necessidade de migração de categoria, mas não validam o formulário
de Comment direto: sua submissão/exibição no navegador continua pendente.

### Política de dados para o novo Fórum

O responsável determinou que o novo Fórum deve começar limpo. Portanto, o
conteúdo do Drupal Forum antigo **não será migrado** para `aculta_forum_topic`
nem convertido em `aculta_discussion_post`. Na janela de retirada autorizada,
serão eliminados os tópicos legados e todos os comentários ligados a eles; a
consulta imediatamente anterior deverá confirmar a contagem e os bundles reais
de Comment, incluindo respostas aninhadas. No inventário de 2026-10-19 havia 30
tópicos `forum` e zero comentários `comment_forum`.

Os Nodes editoriais e seu conteúdo permaneceram intocados; comentários diretos
da Revista não foram apagados. O `field_discussao`, o lookup legado e a
apresentação editorial como abertura de tópico foram retirados do código. Não
remover contas, Nodes editoriais, cursos, turmas, atividades, verbetes, imagens
ou arquivos durante a limpeza do Fórum.

O uninstall também exige retirar o vocabulário antigo `forums`. A verificação
confirmou que as categorias editoriais permanecem nos campos diretos das
publicações e que o mapeamento auxiliar não diverge. A remoção de termos e do
campo legado só pode ocorrer na janela autorizada de limpeza e não autoriza
excluir o vocabulário `editorial_category` ou dados de outras funcionalidades.

No estado da auditoria de 2026-10-19, os consumidores ainda impediam o uninstall.
Magazine removeu sua dependência e seus leitores em dev.15. `aculta_user`, as
configurações contrib, os templates do tema e o pacote Composer ainda precisam
de revisão antes da desinstalação oficial.

Os backups observados em `/home/aculta/private/aculta` datam de 2026-10-17 e
2026-10-18 e foram criados para outras alterações. Nenhum foi validado como
restore point específico desta retirada; não são base suficiente para exclusão.
Não foi criado backup nesta subfase.

### Atualização dev.9 — discussão direta na Revista

Após a auditoria read-only acima, foram aplicadas quatro configurações por
importação parcial no Runtime `aculta_runtime`: field instances Comment de
`article` e `blog`, mais os dois displays full. Config Sync ficou sem divergência
nesses objetos. O default aberto foi observado sem persistência em Nodes antigos.
Com o usuário de teste uid 5, a renderização em memória incluiu o formulário de
comentário; nenhum comentário foi submetido. Havia 14 publicações publicadas, 30
Nodes Forum legados, nenhum `comment_forum` e nenhum Comment compartilhado.

Os 12 Nodes `forum` ligados às publicações e seus vínculos `field_discussao`
continuam intactos. Os hooks de insert/update/delete de `aculta_magazine` foram
retirados: salvar, atualizar ou excluir uma publicação não cria, altera ou
despublica tópico legado. Permanecem temporariamente serviços de leitura e
conversão dos vínculos antigos e o ajuste visual de links de comentários. O
Forum contrib, o pacote Composer, os tópicos e o vocabulário não foram removidos.

### Limpeza posterior dos fixtures do fórum novo — 2026-10-19

Em nova conferência do Runtime `toca` (Drupal 11.4.10, URI do purpose `forum`,
banco `aculta_runtime`), foram encontrados os oito Nodes `[TESTE]` 383–390 do
bundle `aculta_forum_topic`, todos publicados por uid 5. O responsável já havia
determinado que os tópicos e comentários de teste fossem removidos para deixar
o novo Fórum sem conteúdo. Antes da operação, cada ID foi revalidado por bundle,
título e owner; os Nodes não tinham Comments, Flags, nem referências editoriais
ou externas. A Entity API removeu somente esses oito Nodes. Não havia Comments
de teste.

Consulta posterior confirmou 0 Nodes `aculta_forum_topic` e 0 Comments
`aculta_discussion_post`. Os 12 termos da hierarquia inicial foram preservados
como estrutura de categoria solicitada. O Fórum legado continua ativo: 30 Nodes
`forum` (13 publicados/17 não publicados), 8 termos `forums`, 0 Comments
`comment_forum` e 12 vínculos `field_discussao` das publicações. Nenhuma
configuração foi alterada, e nenhum conteúdo editorial ou legado foi removido.
Os oito aliases antigos não têm mais registro de Pathauto e respondem HTTP 404.
Detalhes e IDs: [`TEST-FIXTURES.md`](TEST-FIXTURES.md).

### Decisão operacional da auditoria de 2026-10-19 (superada por F-D33)

Naquele momento, foi registrado que não se deveria excluir o legado antes de
validar o restore e os consumidores editoriais. A determinação de 2026-10-20
substituiu essa decisão para o conteúdo do Runtime de teste. O registro histórico
permanece para explicar a sequência; ele não é a instrução operacional atual.

## Portas e pendências após a retirada

1. Resolver a configuração de Flags e confirmar a política para sinalização de tópicos/comentários históricos sem conceder edição ou moderação desses itens.
2. Preparar importação dirigida de Config Sync somente após revisar o diff ativo completo e os efeitos por configuração.
3. Executar smoke autenticado de criação, primeira resposta, estados de moderação e permissões; confirmar que conteúdo Growroom histórico não pode ser alterado ou reaberto por `content_editor`.
4. Auditar código, Config Sync, Views, tema, Search API, sitemap, Messenger e dependências, distinguindo referências históricas daquelas executáveis.
5. Testar instalação/atualização/desinstalação em banco dedicado e validar restauração do dump em base isolada, sem usar o Runtime para testes Kernel.
6. Completar homologação visual planejada em Firefox/X11 conforme `docs/portal/TESTES-NAVEGADOR.md`.

## Limpeza e retirada do Forum contrib no Runtime de teste — 2026-10-20

Ambiente verificado imediatamente antes da operação: `hostname=toca`, checkout
`/home/piradopirata/projetos/aculta-site`, branch
`docs/aculta-forum-0.1.0-dev.1`, Drupal 11.4.10, URI
`https://growroom420.aculta.toca.net.br`, banco `aculta_runtime`. Produção não
foi consultada nem alterada.

Restore point integral, privado e externo ao Git:
`/home/piradopirata/.local/share/aculta-private-backups/aculta_runtime-forum-content-before-delete-20261020.sql.gz`;
modo `0600`, 6,397,232 bytes, `gzip -t` PASS, SHA-256
`2ad6723d6dc56599681cdeafd7758291698ee5b0d92d4e40734fd5835de65f43`. O dump
contém dados do Runtime; não copiar para repositório, produção ou artefatos
públicos. Restore isolado não foi executado.

Inventário repetido imediatamente antes da operação confirmou exatamente 30
Nodes `forum`, zero Comments `comment_forum`, oito termos no vocabulário
`forums` e 12 Nodes editoriais com `field_discussao` apontando a esses tópicos.
Os 12 Nodes tinham `field_aculta_discussion` preenchido e eram `article`/`blog`;
nenhum outro bundle usava os termos `forums`. O vocabulário novo
`aculta_forum_categories`, com 12 categorias, ficou fora do escopo.

Em uma transação via Entity API foram removidos os 30 tópicos e oito termos,
apagados comentários `comment_forum` (zero encontrados) e limpos os 12 valores
legados de `field_discussao` nas publicações. Nodes editoriais, seus conteúdos,
revisões de conteúdo e comentários diretos permaneceram. As verificações após a
operação encontraram zero em: Nodes `forum`, Comments `comment_forum`, termos
`forums`, referências ativas e históricas `field_discussao` aos tópicos,
revisões dos Nodes excluídos, aliases, flaggings, `forum_index` e tabela
relacional `forum`.

`module_installer->validateUninstall(['forum'])` retornou lista vazia. O
uninstall oficial foi executado; após o aviso do Drupal sobre seis instâncias
vazias de `field_discussao` sem bundles válidos, as instâncias e o storage foram
removidos via Field API, com zero valores/revisões confirmados. Verificação
posterior: módulo, bundle, configs, tabelas e índices contrib ausentes. Só então
`composer remove drupal/forum` retirou o pacote; `drupal/history` continua como
dependência direta.

Restore point imediatamente anterior ao uninstall, já sem conteúdo antigo:
`/home/piradopirata/.local/share/aculta-private-backups/aculta_runtime-forum-clean-before-uninstall-20261020.sql.gz`;
modo `0600`, 4.279.109 bytes, `gzip -t` PASS, SHA-256
`bae45c1dc7d0fc751bcb1777dc0779dd8ca873debefa4857d6da83afe9dde402`.
Restauração em base isolada não foi validada. Nenhuma importação integral de
Config Sync ou `updb` foi realizada; drift preexistente exige revisão antes de
importação dirigida.

Cache rebuild passou; raízes do Growroom420 e Revista responderam HTTP 200, e
Conta anônima respondeu HTTP 302 esperado. Smoke autenticado e revisão de
permissões/Flags continuam pendentes.

## Rollback e limites

Código/config pode ser revertido; a remoção dos dados exige restauração do
backup compatível ou reimportação idempotente, nunca apenas `git revert`. O
restore point pré-uninstall descrito acima não contém os tópicos antigos; para
recuperar esses dados seria necessário o dump pré-exclusão indicado no registro
histórico. Nenhuma ação atingiu produção; merge em `main` não é deploy.
