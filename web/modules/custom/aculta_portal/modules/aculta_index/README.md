# ACULTA Content Index

Submódulo opcional do Kernel `aculta_portal`. O catálogo é derivado e poderá ser apagado e reconstruído; as entidades e permissões continuam pertencendo aos módulos de origem.

**Versão:** `0.1.0-dev.28` — fingerprint de rebuild inclui versão explícita por provider.
**Status:** Dev; schema, contratos, providers Magazine/Wiki/Fórum/Gallery/Media/LMS, descoberta/backfill keyset, checkpoint CAS, writer, fila incremental, rebuild por geração, consultas com cursor, relações nomeadas, auditoria e bridge opcional de Statistics implementados em código. O fingerprint combina ID, versão de projeção, entity types e bundles; mudança de versão bloqueia retomada de geração parcial. Unit de providers/Index: **121 testes / 536 assertions**; Kernel Index/Activity no MariaDB: **33 testes / 399 assertions**. A cobertura Kernel inclui schema, rebuild, consulta, relações, adapter Activity e adapter Media de `aculta_image`; os demais providers ainda precisam de integração Kernel/DI. Runtime, desempenho sob volume e compatibilidade em produção seguem pendentes. O bridge mantém os itens autorizados quando Statistics está ausente. Não instalado no Runtime.
**Arquitetura e roadmap:** [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).
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

## Estatísticas opcionais

`aculta_index.statistics_bridge` recebe uma página produzida por `ContentIndexQuery`, cujos itens já passaram pela revalidação de Entity Access, envia no máximo 100 identidades ao contrato `ContentEntityMetricAggregatorInterface` e retorna as mesmas entidades autorizadas com totais e cursor. O consumidor pode renderizar a página sem refazer a consulta. A integração não faz junção entre tabelas. Se Statistics não estiver habilitado, o resultado marca `available=false` e não inventa totais. Statistics valida permissão, catálogo, sensibilidade e semântica de dimensão; somente métricas com dimensão de ID simples explicitamente permitida são aceitas. Métricas e eventos continuam sob propriedade de Statistics.

## Ativação

O submódulo depende do módulo base `aculta_portal`, mas não é ativado por ele. Nenhum módulo de origem depende obrigatoriamente de `aculta_index`; integrações futuras devem usar dependência opcional e manter seu comportamento principal sem o índice.

Na instalação, `hook_schema()` cria as tabelas derivadas e `hook_install()` cria o singleton com geração ativa vazia. Nenhum conteúdo é indexado ou exposto até uma futura reconstrução validada. Desativar/remover o submódulo não afeta as entidades de origem.

`ContentIndexGenerationManager` cria gerações `building`, permite ativar somente estados validados e mantém a geração anterior como `retained` para rollback. A troca ocorre em transação com bloqueio da linha de estado e comparação de revisão do ponteiro. O rebuild marca a conclusão do backfill por CAS; `ContentIndexRebuilder::validate()` exige essa marca, confere que o inventário de providers não mudou e executa a checagem estrutural antes de promover a geração. `activate()` bloqueia enquanto providers do inventário têm jobs queued, processing, retry ou dead. Eventos durante a validação também são aplicados à geração `validated`; a mesma linha de estado serializa schedule e troca do ponteiro. Rollback para a geração retained permanece disponível mesmo com jobs pendentes.

O Core Queue worker recupera estados `processing` sem progresso há cinco minutos como retry com backoff. Para inspeção operacional, `ContentIndexWorkManager::listDead()` retorna uma página de identidades com erro normalizado e `retryDead()` reenvia um item. A CLI Drush permite inspecionar e reparar dead-letters sem criar UI no Kernel.

## Operação via Drush

As operações são explícitas; completar o backfill não ativa a geração:

```text
drush aculta-index:rebuild start
drush aculta-index:rebuild batch GENERATION_ID --limit=100
drush aculta-index:rebuild validate GENERATION_ID
drush aculta-index:rebuild activate GENERATION_ID
drush aculta-index:queue status
drush aculta-index:queue dead --limit=50
drush aculta-index:queue retry WORK_ID
```

Execute `batch` repetidamente até `complete=true`, depois valide e ative. O gate
recusa ativação enquanto a fila dos providers ativos tiver trabalho pendente ou
dead-letter. A saída de `queue dead` contém identidades de entidades e deve ficar
restrita ao terminal de operadores autorizados. Os comandos foram implementados
localmente; a descoberta/execução no Runtime não foi verificada.

## Auditoria incremental

O cron processa no máximo um lote (200 linhas) por execução. O checkpoint percorre
primeiro entradas e depois relações usando as chaves primárias capturadas no início
do ciclo. Um ciclo novo só começa após 24 horas do término anterior. O estado
agrega contagens e não guarda títulos, corpos ou IDs das entidades com problemas.

```text
drush aculta-index:audit status
drush aculta-index:audit start
drush aculta-index:audit batch --limit=200
```

O comando manual pode continuar um ciclo existente; um novo `start` é recusado
enquanto outro ciclo estiver em execução. Esta auditoria é operacional e
incremental. A validação do rebuild continua fazendo suas próprias verificações
completas antes de permitir ativação.

## Relações de saída

`aculta_index.relation_query` consulta uma relação nomeada para uma identidade de
origem e retorna somente alvos presentes na geração ativa. A consulta é de um
salto e unidirecional; não percorre relações recebidas ou grafos recursivos.
Entity Access é revalidado tanto na origem quanto em até 100 alvos candidatos.
Relações com idioma `und` selecionam a tradução no idioma padrão do site. A
resposta usa cursor opaco, não calcula totais/facetas e não é cacheada.
