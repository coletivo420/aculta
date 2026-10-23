# ACULTA Content Index

Submódulo opcional do Kernel `aculta_portal`. O catálogo é derivado e poderá ser apagado e reconstruído; as entidades e permissões continuam pertencendo aos módulos de origem.

**Versão:** `0.1.0-dev.126` — valida descoberta live dos comandos Drush no Runtime de teste.
**Status:** Dev; schema, contratos, providers Magazine/Wiki/Fórum/Gallery/Media/LMS/Activity, descoberta/backfill keyset, checkpoint CAS, writer, fila incremental, rebuild por geração, consultas com cursor, relações nomeadas, auditoria e bridge opcional de Statistics implementados em código. O fingerprint combina ID, versão de projeção, entity types e bundles; mudança de versão bloqueia retomada de geração parcial. Unit do Index: **39 testes / 204 assertions**; suíte Kernel completa mais recente: **89 testes / 1.342 assertions** em SQLite (dev.122; 39 depreciações Core/contrib, 45 PHPUnit, 2 skips); MariaDB mais recente **65 / 883** (dev.76). A cobertura Wiki relation de dev.98 passou à parte com 2/32. Em dev.77, o teste focal de auditoria passou com 1/20, provando que a projeção de provider desativado permanece e não recebe job de exclusão; em dev.78, o arquivo Kernel de rebuild e consulta passou completo (15/211), incluindo os limites 0 e 101 rejeitados por ambas as APIs. A suíte Kernel completa foi repetida em dev.101, incluindo os testes Wiki relation e `course_document`. Em dev.79/80, `ContentIndexGalleryProviderKernelTest` passou com entidades reais `media_gallery`, Media e Domain (2/44): dev.79 cobre relation `gallery_image` e CRUD; dev.80 valida Entity Access publicado/rascunho, purpose `magazine`, negação quando Domain canônico falta e restauração de contexto. Em dev.81, o Kernel Media passou com 3/53; inclui `course_document` unpublished com File privado, carrega a definição/argumentos do YAML real do PDF Viewer Group e verifica descoberta no registry, backfill, update e delete. Não ativa o owner completo nem testa Group grants. O teste de referência do owner segue bloqueado por dependências ausentes na compilação ampla do Portal. O Kernel cobre schema, rebuild, consultas, relações, update hooks, adapter Activity/Media, reconciliação bidirecional, interleaving de eventos com claim antigo, lote transacional atômico, Domain+grants do Fórum pelo `ModuleHandler` real (tópico, ForumPost, Comment legado, revogação imediata de membership e ausência do Domain canônico em consulta iniciada no Domain principal), isolamento entre caches de Entity Access da Wiki e do Domain atual, e bridge Statistics com usuário não privilegiado e permissão granular registrada na fixture. O enumerador limita caches estáticos de entidades entre batches (IDs de fontes e alvos explícitos até 5.000); projeções malsucedidas limpam todos os caches de conteúdo. Benchmark de manutenção mediu setup, rebuild e auditoria em 10k/100k; a rodada 100k pós-cache terminou sem divergências (rebuild 113,511 s; auditoria 723,219 s), sem conclusão causal; a rodada 10k reportou pico por fase e latência máxima por lote. O benchmark de 1M segue em execução no MariaDB; começou antes da supressão de jobs no setup e das métricas por fase, portanto seus dados não são resultado final (ver ressalva no relatório). O harness do bridge não ativa o módulo Statistics completo por suas dependências Commerce. Harness opt-in mede Forum Group grants/Domain em três execuções MariaDB, com 30 amostras por usuário e tamanho de página; a página de 100 candidatos teve p50 mediano de 703 ms para membro e 358 ms para visitante. As medições anteriores usavam UID 1 e foram substituídas; os resultados atuais não definem SLO. PHPCS Drupal passou. Writer por lote medido em fixture de 10k teve mediana de 1.218 projeções/s, contra 152/s por projeção; a autorização medida continua limitada a fixtures, sem SLO. Runtime, concorrência entre processos, desempenho operacional em dados reais, integração Kernel/DI completa dos owners e compatibilidade operacional seguem pendentes. O bridge mantém os itens autorizados quando Statistics está ausente. Não instalado no Runtime.
**Traduções:** no dev.85, `ContentIndexTranslationKernelTest` reconstrói um Node
com duas línguas, consulta ambas pela API do Index, confirma que atualizar
`pt-br` preserva `en` e apagar a tradução remove somente sua projeção e seu
resultado; MariaDB: 1 teste / 20 assertions. A fixture concede `access content`
à role anônima. Não é uma homologação multilíngue em Runtime.

**Media:** no dev.84, `ContentIndexMediaProviderKernelTest` passou com 4 testes /
76 assertions. Além dos providers image/document/course_document, carrega as
declarações reais de `aculta_pdf_viewer_group` e `aculta_forum`; o teste
`forum_attachment` verifica purpose `forum` e CRUD de Media unpublished com File
privado. Os testes não ativam os owners completos nem validam seus grants.

**Acesso de owners:** no dev.96, o Kernel Fórum passou em SQLite (4 testes /
199 assertions), cobrindo hooks reais de grants, membership, revogação,
`ContentIndexAccessFilter` e Domain canônico. O Kernel `course_document` passou
em SQLite (1/28), usando a declaração de provider do YAML owner e uma relação
Group fixture com Entity Access. O boot/DI integral do PDF Viewer Group continua
pendente; nenhum destes testes ativou módulos no Runtime.

No dev.97, a suíte Kernel completa passou em SQLite: 78 testes / 1.194
assertions, com 39 depreciações Core/contrib e 42 depreciações PHPUnit. A
rodada MariaDB aguarda a conclusão do benchmark de 1 milhão.

No dev.98, a consulta Wiki `related_entry` passou no Kernel SQLite (2 testes /
32 assertions): origem e alvos foram revalidados em `wiki`, o alvo unpublished
foi omitido e o Domain `main` foi restaurado.

No dev.99, o mesmo Kernel lê e registra a declaração real do provider em
`aculta_wiki.services.yml`; somente a ponte de Entity Access continua sendo
fixture. Boot/DI completo e Runtime seguem pendentes.

No dev.100, o Kernel LMS existente passou também em SQLite (1/33; 47
depreciações upstream), cobrindo estudante, educador, terceiro e Domain pelo
filtro do Index. Consulta outgoing de recursos do curso continua sem teste
end-to-end.

No dev.101, a suíte Kernel completa passou em SQLite: 79 testes / 1.210
assertions, com 39 depreciações Core/contrib e 42 PHPUnit. MariaDB aguarda o
benchmark de 1 milhão.

No dev.102, o Kernel Statistics registra as declarações de catálogo, storage e
agregador lidas do YAML real do owner e resolve a ponte opcional do Index pelo
DI: SQLite 1/22. Não ativa o módulo Statistics completo nem sua cadeia Commerce.

No dev.103, a suíte Kernel completa passou em SQLite: 79 testes / 1.213
assertions, com 39 depreciações Core/contrib e 42 PHPUnit.

No dev.104, foi registrado que o benchmark MariaDB 1M em execução iniciou antes
do isolamento dos jobs de fixture e das métricas por fase; seus dados parciais
não serão tratados como resultado final nem comparados a 10k/100k.

No dev.113, a classe Kernel de consulta MariaDB passou com 3 testes / 40
assertions, incluindo projeção residual de provider desativado excluída dos
candidatos. A classe Kernel LMS passou com 4 testes / 210 assertions e 53
depreciações upstream; agora cobre a aresta `course_document` após rebuild,
revalidando estudante, visitante e acesso ao documento não publicado. O plugin
Group é fixture isolada; o owner PDF e o boot/DI completo dos módulos continuam
sem verificação integral.

**Decisão de cobertura LMS:** manter esse harness isolado para provar a projeção,
rebuild e consulta outgoing `course_document`. O boot integral do owner fica como
dívida de homologação, pois depende de serviços ausentes de módulos não relacionados
ao fluxo do Index (`aculta_user` e `commerce_cart`). Não se amplia a cadeia DI nesta
microfase; a fixture não é evidência de boot completo do owner.

**Arquitetura e roadmap:** [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).
**Plano de benchmark:** [matriz IDX 0.1-G/R](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-BENCHMARK.md).
**Auditoria e matriz de reaproveitamento:** [IDX 0.1-A](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).

No dev.114, `ContentIndexConcurrencyKernelTest` verifica duas sessões MariaDB
distintas usando o prefixo isolado do Kernel (2 testes / 23 assertions). Durante
a promoção, o leitor continua na geração anterior e um agendamento concorrente
espera o commit; após retentar, seu trabalho bloqueia rollback até ser concluído.
Na limpeza final, outro gerenciador espera o commit e depois encontra a geração
já removida. O timeout de um segundo só afeta a sessão concorrente do teste.
Não equivale a carga com vários workers PHP nem à homologação no Runtime.

## Provider services

Um módulo proprietário pode registrar um provider como serviço e marcá-lo com a tag `aculta_index.provider`. O provider implementa `ContentIndexProviderInterface`, declara ID estável e lista de tipos/bundles que conhece. Serviços de módulos desativados não são carregados pelo Drupal.

```yaml
services:
  aculta_example.content_index_provider:
    class: Drupal\aculta_example\Index\ExampleContentIndexProvider
    tags:
      - { name: aculta_index.provider }
```

O registry detecta IDs duplicados, IDs inválidos e declarações malformadas de entidade/bundle durante a construção do container. Cada provider declara `getProjectionVersion(): int`; essa versão integra o fingerprint da geração e deve ser incrementada quando a projeção mudar. Gerações parciais com outra versão não podem ser retomadas. Os DTOs neutros `ContentIndexProjection` e `ContentIndexRelation` e o contrato `ContentIndexProviderInterface` vivem no namespace base do Portal, portanto podem ser implementados sem dependência do submódulo. O registry não concede nem verifica acesso a conteúdo.

Providers podem implementar `ContentIndexAccessContextProviderInterface` para indicar um purpose canônico, e `ContentIndexAccessPolicyProviderInterface` para acrescentar uma regra contextual à Entity Access. O filtro troca temporariamente o Domain, combina ambas as decisões para o mesmo usuário e restaura o contexto anterior. Sem provider ativo, Domain pedido ou policy determinável, a entrada não é exposta. O Index não infere purpose por hostname nem reduz grants/Group.

Integrações atuais: Revista (`node:revista`), Wiki (`node:wiki_entry`, metadados e relação `related_entry`), Fórum (tópicos, ForumPosts e Comments legados), Gallery (`media_gallery` com relações explícitas para `media:image`) e LMS (`group:lms_course` com relações `course_reading`, `course_gallery` e `course_document`; Unit 5/31 e Kernel outgoing 4/210 em aculta_lms_resources 0.1.0-dev.14), Media (`image`, `document`, `course_document`, `forum_attachment`), com registros em `aculta_image`, Document Viewer, PDF Viewer Group e Forum. `remote_video` está adiado como dívida até haver módulo proprietário; nenhum provider é registrado. O provider Gallery não cria URL de arquivo nem projeta corpo, alt, autoria ou dados de direitos.

## Escrita e reconstrução

`ContentIndexWriter::write()` continua sendo a API incremental por identidade.
O rebuild chama `writeBatch()` com páginas de até 500 projeções: o lote inteiro
é gravado numa transação, conflitos revertem o lote para repetição pelo
checkpoint e a revisão do cache de candidatos avança uma vez após alterações.
A invalidação de cache acontece depois do commit. O limite de lote acompanha o
máximo do enumerador e do rebuilder; operações incrementais de worker continuam
usando escrita unitária.

Em fixture de 10.000 Nodes, o backfill de três rodadas passou de mediana
65,97 s para 8,21 s após o batching. Isso mede o provider fixture e o banco
local dedicado; não é SLO nem previsão de produção. Consulte a
[comparação completa](../../../../../../docs/portal/benchmarks/ACULTA-CONTENT-INDEX-MAINTENANCE-2026-10-23.md).

## Estatísticas opcionais

`aculta_index.statistics_bridge` recebe uma página produzida por `ContentIndexQuery`, cujos itens já passaram pela revalidação de Entity Access, envia no máximo 100 identidades ao contrato `ContentEntityMetricAggregatorInterface` e retorna as mesmas entidades autorizadas com totais e cursor. O consumidor pode renderizar a página sem refazer a consulta. A integração não faz junção entre tabelas. Se Statistics não estiver habilitado, o resultado marca `available=false` e não inventa totais. Statistics valida permissão, catálogo, sensibilidade e semântica de dimensão; somente métricas com dimensão de ID simples explicitamente permitida são aceitas. Métricas e eventos continuam sob propriedade de Statistics.

Os filtros da página precisam corresponder ao tipo e bundle aceitos pela métrica. No adapter atual, `wiki.entries.*` exige `node:wiki_entry` e `media.files.requests` exige `file:file` (além de permissão administrativa). Uma página incompatível é rejeitada pelo Statistics; o bridge não descarta conteúdo nem calcula totais parciais.

## Ativação

O submódulo depende do módulo base `aculta_portal`, mas não é ativado por ele. Nenhum módulo de origem depende obrigatoriamente de `aculta_index`; integrações futuras devem usar dependência opcional e manter seu comportamento principal sem o índice.

Na instalação, `hook_schema()` cria as tabelas derivadas e `hook_install()` cria o singleton com geração ativa vazia. Nenhum conteúdo é indexado ou exposto até uma futura reconstrução validada. Desativar/remover o submódulo não afeta as entidades de origem.

`ContentIndexGenerationManager` cria gerações `building`, permite ativar somente estados validados e mantém somente a geração anterior como `retained` para rollback; gerações retidas mais antigas passam a `discarding` na transação de ativação e cron/Drush as expurgam em lotes limitados. A troca ocorre em transação com bloqueio da linha de estado e comparação de revisão do ponteiro. O rebuild marca a conclusão do backfill por CAS; `ContentIndexRebuilder::validate()` exige essa marca, confere que o inventário de providers não mudou e executa a checagem estrutural na geração em construção antes de promovê-la. Projeções antigas na geração ativa não bloqueiam um rebuild novo já produzido pela versão corrente dos providers. O worker sincroniza as gerações `active`, `building`, `validated` e a única `retained`, usando sequência por identidade e invalidação de cache por geração. A ativação de geração `validated` ou `retained` exige inventário de providers compatível e que não haja trabalho queued, processing, retry ou dead. Enquanto há geração retida, schedule e retry usam o fence da mesma linha singleton da ativação, fechando a corrida entre drenagem da fila e troca do ponteiro.

**Política decidida:** somente a geração imediatamente anterior permanece `retained` e recebe mudanças incrementais para permitir rollback. Na ativação, gerações retidas mais antigas passam atomicamente a `discarding`, deixam de receber eventos e são expurgadas em lotes limitados pelo cron/Drush. Rollback é recusado quando a fila não está drenada ou quando o fingerprint dos providers diverge.

O Core Queue worker recupera estados `processing` sem progresso há cinco minutos como retry com backoff. Para inspeção operacional, `ContentIndexWorkManager::listDead()` retorna uma página de identidades com erro normalizado e `retryDead()` reenvia um item. A CLI Drush permite inspecionar e reparar dead-letters sem criar UI no Kernel. Retry manual exige que o provider proprietário esteja registrado no container ativo. Se o módulo owner estiver desabilitado/removido, o item permanece em dead-letter e não é reenfileirado sem autoridade de projeção.

## Operação via Drush

As operações são explícitas; completar o backfill não ativa a geração:

```text
drush aculta-index:rebuild start
drush aculta-index:rebuild batch GENERATION_ID --limit=100
drush aculta-index:rebuild validate GENERATION_ID
drush aculta-index:rebuild activate GENERATION_ID
drush aculta-index:rebuild discard GENERATION_ID
drush aculta-index:rebuild discard-batch GENERATION_ID --limit=500
drush aculta-index:rebuild cleanup --limit=500
drush aculta-index:queue status
drush aculta-index:queue dead --limit=50
drush aculta-index:queue retry WORK_ID
```

Execute `batch` repetidamente até `complete=true`, depois valide e ative. O gate
recusa ativação enquanto a fila dos providers ativos tiver trabalho pendente ou
dead-letter. A saída de `queue dead` contém identidades de entidades e deve ficar
restrita ao terminal de operadores autorizados. Os comandos foram implementados
localmente; a descoberta/execução no Runtime não foi verificada.

Uma geração parcial incompatível ou abandonada pode ser encerrada com `discard`
somente enquanto estiver em `building`. Repita `discard-batch` até `complete=true`;
cada chamada remove no máximo o limite solicitado de entradas e relações, apaga
os cursors persistidos dessa geração e, por último, remove seu registro. A troca
para `discarding` impede novas escritas e remove a geração dos destinos de eventos
incrementais. Gerações ativas, validadas e a geração anterior retida para rollback não são aceitas. Gerações antigas supersedidas são expurgadas por `cleanup` ou cron.
O procedimento afeta apenas dados derivados do índice, nunca entidades de origem.

## Auditoria incremental

O cron processa no máximo um lote (200 linhas) por execução. O checkpoint percorre
primeiro entradas e depois relações usando as chaves primárias capturadas no início
do ciclo. Um ciclo novo só começa após 24 horas do término anterior. O estado
agrega contagens e não guarda títulos, corpos ou IDs das entidades com problemas.
Quando uma entrada da geração ativa usa uma versão antiga de provider ainda
registrado, o lote agenda novamente sua identidade no work manager após persistir
o checkpoint. A fila deduplica o trabalho e o worker relê a entidade pelo provider
atual. O mesmo lote carrega fontes em grupos limitados e compara o hash canônico da
projeção. Fonte ausente, tradução removida ou bundle que saiu do ownership
confirmado agenda exclusão; hash divergente agenda nova projeção. Provider,
entity type ou bundle sem ownership verificável é contado e preservado. Todo job
é agendado depois do checkpoint e somente se a geração auditada continuar ativa.
Providers desativados ou sem ownership verificável permanecem preservados e não
recebem jobs destrutivos. Após reativação, o provider owner precisa ser registrado
e a auditoria deve confirmar que a fonte não existe antes de enfileirar a exclusão
da projeção derivada; o worker apaga somente essa projeção.

```text
drush aculta-index:audit status
drush aculta-index:audit start
drush aculta-index:audit batch --limit=200
```

O comando manual pode continuar um ciclo existente; um novo `start` é recusado
enquanto outro ciclo estiver em execução. A auditoria verifica estrutura e
reconcilia a geração ativa em lotes limitados, nas duas direções. A fase de
entradas carrega entidades por tipo, revalida o provider proprietário e compara o
hash canônico; projeções divergentes são refileiradas e fontes confirmadamente
ausentes são removidas via work manager. A fase `sources` percorre providers e
tipos pelo enumerador keyset e encontra entidades elegíveis sem entrada no
catálogo, também refileirando-as. Ownership/provider indisponível é contado e
preservado; providers desabilitados e gerações retidas não são apagados. Quando
o provider volta a ser habilitado, ele retoma ownership e a auditoria valida as
fontes antes de reenfileirar projeções ou remoções. O Portal não mantém manifesto
para excluir dados de um owner indisponível. O cursor persistido identifica
provider, entity type e último ID, sem expor IDs no estado
Drush. A validação do rebuild mantém suas verificações estruturais da geração em
construção.

## Cache interno de candidatos

A consulta guarda somente a página limitada de identidades candidatas por até
60 segundos. A chave inclui geração, revisão monotônica, filtros, posição
keyset, limite e fingerprint dos providers. O writer incrementa a revisão na
mesma transação em que grava ou remove projeções e invalida as páginas da
geração. Uma consulta que concorre com escrita só armazena a página se a revisão
não mudou durante a leitura. Entidades originais e Entity Access são resolvidos
novamente em toda chamada; o DTO continua sem cacheabilidade compartilhável
(max-age 0). Escritas diretas ao catálogo fora do writer podem permanecer
visíveis até o TTL expirar e não fazem parte do contrato de invalidação.

## Relações de saída

`aculta_index.relation_query` consulta uma relação nomeada para uma identidade de
origem e retorna somente alvos presentes na geração ativa. A consulta é de um
salto e unidirecional; não percorre relações recebidas ou grafos recursivos.
Entity Access é revalidado tanto na origem quanto em até 100 alvos candidatos.
Relações com idioma `und` selecionam a tradução no idioma padrão do site. A
resposta usa cursor opaco, não calcula totais/facetas e não é cacheada. Edges
que resolvem ao mesmo alvo e idioma aparecem uma única vez; a paginação usa o
`entry_id` imutável do alvo.

No dev.115, `ContentIndexMagazineAccessKernelTest` consulta a relação
`related_activity` depois do rebuild com os providers Magazine e Activity
declarados nos YAMLs owner. Entity Access é revalidado sob `magazine` para a
publicação e `main` para a atividade; o Domain de entrada é restaurado.
MariaDB: 2 testes / 31 assertions PASS, 5 depreciações upstream. Grants
específicos além do Entity Access e boot completo dos owners continuam
pendentes.


No dev.116, `ContentIndexGalleryProviderKernelTest` também consulta `gallery_image`
pela API outgoing após reconstruir a geração. Os providers Gallery e `image`
são carregados pelos YAMLs owner; a conta autenticada pode ver a Gallery no
purpose `magazine` e a imagem pela Entity Access própria. MariaDB: 2 testes /
48 assertions PASS, com 9 depreciações Core/contrib. O harness não ativa os
owners completos nem verifica grants específicos de Gallery/Media.


No dev.117, a Gallery de teste aponta para uma imagem publicada e uma não
publicada. Ambas permanecem como relações derivadas; a consulta outgoing
retorna somente a imagem permitida pela Entity Access da Media. MariaDB: 2
testes / 51 assertions PASS, com 9 depreciações Core/contrib.


No dev.118, o service-map Unit compila a referência do manager Group com um
serviço sintético tipado e verifica que o provider LMS declara versão 1 quando
o plugin PDF Group está ausente. Unit do Index: 39 testes / 204 assertions
PASS, com 10 depreciações PHPUnit.


Em 2026-10-23, regressões Unit focadas nos adapters passaram sem banco:
Activity 4/13, Fórum 3/14, Gallery 2/13, LMS Resources 13/70, Magazine
24/96, Wiki 57/258 e contratos compartilhados Portal 2/8; o Unit do Index
passou com 39/204. O conjunto reportou 37 depreciações PHPUnit/upstream.
Esses resultados não substituem as suítes Kernel nem o boot dos owners.
