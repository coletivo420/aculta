# ACULTA Content Index

Submódulo opcional do Kernel `aculta_portal`. O catálogo é derivado e poderá ser apagado e reconstruído; as entidades e permissões continuam pertencendo aos módulos de origem.

**Versão:** `0.1.0-dev.69` — escrita transacional em lote no rebuild.
**Status:** Dev; schema, contratos, providers Magazine/Wiki/Fórum/Gallery/Media/LMS/Activity, descoberta/backfill keyset, checkpoint CAS, writer, fila incremental, rebuild por geração, consultas com cursor, relações nomeadas, auditoria e bridge opcional de Statistics implementados em código. O fingerprint combina ID, versão de projeção, entity types e bundles; mudança de versão bloqueia retomada de geração parcial. Unit do Index: **39 testes / 192 assertions**; suíte Kernel do Index: **63 testes / 815 assertions** na execução atual com SQLite em memória (23 depreciações upstream e 41 avisos PHPUnit); a rodada MariaDB anterior teve 63 testes / 809 assertions. O Kernel cobre schema, rebuild, consultas, relações, update hooks, adapter Activity/Media, reconciliação bidirecional, interleaving de eventos com claim antigo, lote transacional atômico, Domain+grants reais do Fórum e isolamento entre caches de Entity Access da Wiki e do Domain atual. PHPCS Drupal passou. Writer por lote medido em fixture de 10k teve mediana de 1.218 projeções/s, contra 152/s por projeção; autorização continua limitada a fixtures, sem SLO. Runtime, concorrência entre processos, desempenho operacional em dados reais, integração Kernel/DI completa dos owners e compatibilidade operacional seguem pendentes. O bridge mantém os itens autorizados quando Statistics está ausente. Não instalado no Runtime.
**Arquitetura e roadmap:** [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).
**Plano de benchmark:** [matriz IDX 0.1-G/R](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-BENCHMARK.md).
**Auditoria e matriz de reaproveitamento:** [IDX 0.1-A](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).

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

Integrações atuais: Revista (`node:revista`), Wiki (`node:wiki_entry`, metadados e relação `related_entry`), Fórum (tópicos, ForumPosts e Comments legados), Gallery (`media_gallery` com relações explícitas para `media:image`) e LMS (`group:lms_course` com relações `course_reading` e `course_gallery`), Media (`image`, `document`, `course_document`, `forum_attachment`), com registros em `aculta_image`, Document Viewer, PDF Viewer Group e Forum. `remote_video` está adiado como dívida até haver módulo proprietário; nenhum provider é registrado. O provider Gallery não cria URL de arquivo nem projeta corpo, alt, autoria ou dados de direitos.

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

`ContentIndexGenerationManager` cria gerações `building`, permite ativar somente estados validados e mantém a geração anterior como `retained` para rollback. A troca ocorre em transação com bloqueio da linha de estado e comparação de revisão do ponteiro. O rebuild marca a conclusão do backfill por CAS; `ContentIndexRebuilder::validate()` exige essa marca, confere que o inventário de providers não mudou e executa a checagem estrutural na geração em construção antes de promovê-la. Projeções antigas na geração ativa não bloqueiam um rebuild novo já produzido pela versão corrente dos providers. `activate()` bloqueia enquanto providers do inventário têm jobs queued, processing, retry ou dead. Eventos durante a validação também são aplicados à geração `validated`; a mesma linha de estado serializa schedule e troca do ponteiro. Rollback para a geração retained permanece disponível mesmo com jobs pendentes.

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
incrementais. Gerações ativas, validadas e retidas para rollback não são aceitas.
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
Política de limpeza de providers desativados ainda depende da decisão operacional
em aberto; a auditoria não os apaga.

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
