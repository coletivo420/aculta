# ACULTA Content Index ↔ ACULTA Statistics

**Estado:** bridge tipado implementado no código local `0.1.0-dev.19` / Statistics `0.9.0-dev.8`; não ativado no Runtime.
**Fontes locais auditadas:** `modules/aculta_statistics/src/Storage/DailyAggregateStorage.php`, `AggregateStorageInterface.php`, `Recorder/StatisticsRecorder.php`, `Catalog/MetricCatalog.php`, `Controller/StatisticsDashboardController.php` e serviços de reconciliadores/providers.

## Donos e direção

- `aculta_index` seleciona identidade de entidade/idioma e relações autorizadas. Não grava eventos analíticos nem métricas.
- `aculta_statistics` recebe eventos, define catálogo de métricas, agrega, aplica retenção e consulta indicadores. Não passa a depender da existência do Index.
- Dados oficiais permanecem nos módulos de origem: Commerce, LMS, e-mail, Wiki/Fórum e outros.

A integração de indicadores é opcional e ocorre apenas sob demanda do consumidor: o Index não chama Statistics durante projeção, eventos, fila ou rebuild. Quando um consumidor do Index pede indicadores, o bridge seleciona e autoriza primeiro, e só então consulta a API do Statistics; agregados não são copiados para as tabelas do Index.

## Contrato de correlação (implementado)

`aculta_index.statistics_bridge` recebe filtros, busca uma página via `ContentIndexQuery` (que revalida Entity Access) e encaminha identidades autorizadas sem cruzar tabelas:

```text
seleção autorizada → (entity_type, entity_id, langcode) em lotes
                                  ↓
Statistics consulta seu próprio catálogo/armazenamento por métrica e intervalo
```

O uso inicial deve trabalhar com uma dimensão compatível com o identificador estatístico que a fonte já registra. O Statistics atual limita dimensões a 128 caracteres e rejeita e-mail; o Index não deve inventar um segundo formato. A tradução/idioma pertence à identidade do catálogo, mas não se deve contar duas vezes uma entidade em métricas que hoje usam somente ID.

Nenhuma consulta cruza diretamente `aculta_index_entry` e `aculta_statistics_daily` antes de provar cardinalidade e custo. Relações podem duplicar o conjunto; a seleção deve deduplicar identidade antes de agregar. Para conjuntos grandes, usar lotes com limite medido, não uma lista ilimitada de `IN (...)`.

## Acesso e privacidade

- Para a bridge, Statistics exige `view content-level statistics` em métricas
  públicas allowlisted; métricas internas exigem `administer aculta_statistics`
  e as restritas são negadas. Relatórios administrativos continuam exigindo a
  permissão administrativa.
- O bridge de seleção filtra o propósito/domínio quando solicitado e não retorna campos privados.
- Se um relatório apresenta dados por conteúdo, o consumer revalida Entity Access no módulo dono e agrega/suprime células antes de exibir; facetas e totais seguem a mesma regra.
- A ausência de autorização ou de contexto de domínio resulta em nenhuma identidade. Índice ausente também não significa permissão ampla.
- O Index não inclui autor/usuário nas seleções analíticas por padrão. Métricas pessoais do LMS mantêm as regras e dimensões privadas já existentes.

## DI, cache e fallback

Statistics continua executável sem `aculta_index`. O bridge do Index injeta o contrato tipado por referência de serviço opcional e trata `NULL` como indisponibilidade. Não há dependência Drupal obrigatória de Statistics para Index, nem instalação de módulos contrib para fazer a ponte.

Cache de relatório inclui tags de `aculta_statistics` e `aculta_index` quando ambas as fontes foram usadas, mais contexts de permissões e domínio apropriados. Resultado só pode ser cacheado quando a consulta e a agregação forem cache-safe; relatórios administrativos atuais que usam `max-age: 0` não ganham cache por causa da ponte.

Fallback:

1. Index desabilitado: Statistics segue seus providers e relatórios atuais, sem tentativa de conexão.
2. Index ativo, provider/filtro ausente: usar a fonte existente do Statistics ou declarar o indicador indisponível; nunca interpretar ausência como zero semântica.
3. Statistics desabilitado: Index responde somente metadados/identidades; não exibe indicadores artificiais.

## Custo e evolução

O custo principal é uma consulta paginada no índice mais uma ou mais consultas agregadas já pertencentes ao Statistics. Medir volume de IDs, consultas por lote, latência p50/p95 e memória antes de ligar a bridge a uma listagem de Studio. A API precisa permitir selecionar o conjunto sem carregar cada entidade quando a finalidade for somente agregação, mas isso não elimina os filtros seguros de domínio/permissão requeridos pelo relatório.

Conectar primeiro indicadores agregados por bundle/domínio e janela de tempo, pois evitam listas de IDs de alta cardinalidade. Consultas por entidade devem ter um caso de uso e permissão explícitos. Não criar tabela de métricas no Index nem usar relação SQL que multiplique contagens.

## Limites e pendências

- A allowlist inicial cobre métricas Wiki por `node/wiki_entry` e solicitações de arquivo por `file/file`; dimensões compostas ficam fora deste bridge.
- Métricas públicas allowlisted aceitam a permissão granular `view content-level statistics`; métricas internas exigem permissão administrativa. A autorização de cada conteúdo já foi revalidada pelo Index.
- Os testes Kernel do aggregator foram escritos, mas não iniciaram nesta sessão porque `SIMPLETEST_DB` não está configurada.
- Ainda faltam cenários Kernel ponta a ponta, fallback em container sem Statistics e medição de consultas/latência antes de conectar a listagens de Studio.
