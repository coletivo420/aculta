# Arquitetura — ACULTA Statistics

Estado: fase 0.1.0 (documentação e código portado sem ativação). Itens marcados
como **planejado** não existem ainda.

## Princípio

Centralizar a **inteligência** (catálogo, agregação, consulta, painel), não
duplicar os **dados de negócio**. Pedidos continuam no Commerce, matrículas e
progresso no LMS, mensagens no sistema de e-mail. O Statistics recebe eventos,
consulta provedores e mantém agregados.

## Três mecanismos de integração

1. **Coletores de evento** (`planejado`, fase 0.2.0): recebem eventos de Drupal
   (`commerce_order.order.paid`, `links.link.clicked`, etc.) e gravam incremento
   no agregado diário.
2. **Provedores de métrica** (`planejado`, fase 0.2.0): plugins que leem a fonte
   oficial e devolvem totais agregados, sem copiar registro a registro.
3. **Conectores externos** (`planejado`, fases 0.5.0 e 0.6.0): SMTP2GO, GA4 e
   Search Console, com sincronização em fila, cache, quota e histórico.

Nenhum painel consulta provedor externo de forma síncrona: o painel lê agregados.

## Componentes portados nesta fase

| Classe | Função | Origem |
|---|---|---|
| `Storage\AggregateStorageInterface` | Contrato de contador diário | Novo |
| `Storage\DailyAggregateStorage` | `merge` por métrica, dimensão e dia UTC | Adaptado de `drupal/statistics` |
| `Hook\StatisticsCronHooks` | Retenção de 90 dias no cron (`#[Hook('cron')]`) | Padrão de `aculta_links` |
| `Connector\SearchConsole\SearchAnalyticsWindow` | Janela de datas da consulta GSC | Adaptado de `drupal/analyze_search_console` |
| `Connector\SearchConsole\SearchMetricsDelta` | Variação entre períodos | Adaptado de `drupal/analyze_search_console` |

Serviço: `aculta_statistics.storage` (em `aculta_statistics.services.yml`).

Nenhum destes componentes foi instalado nem ativado. O módulo não tem
`*.info.yml`, então o Drupal não o carrega. Ativar exige a linha
correspondente no README raiz (`PROJECT-STATUS-INDEX`), decisão a tomar na
fase 0.2.0.

## Modelo de dados

Tabela proposta `aculta_statistics_daily` (implementada em `DailyAggregateStorage::TABLE`,
schema ainda não instalado):

| Coluna | Tipo | Observação |
|---|---|---|
| `metric` | varchar(64) | Identificador do catálogo, ex.: `links.link.clicks` |
| `dimension` | varchar(128) | Recorte de baixa cardinalidade; vazio se não houver |
| `day` | int | Dia UTC (dias desde 1970-01-01) |
| `total` | bigint | Contador do dia |
| `updated` | int | Timestamp da última gravação |

Chave primária: `(metric, dimension, day)`. Índice por `(metric, day)`.

Regras (validadas em código):
- métrica no formato `dominio.entidade.acao` (2 a 4 segmentos);
- dimensão com até 128 caracteres e sem `@`, para evitar e-mail e dado pessoal.

Não criar entidade de conteúdo por evento bruto: volume alto e não necessário.
Definições de métrica administráveis, se houver, usarão Configuration API (fase 0.2.0).

## Consultas e cache

- Leitura por intervalo de dias (`total()`), com cache por tag `aculta_statistics`.
- Invalidação pela tag quando um agregado é gravado (fase 0.2.0).
- Janela de dados externos declarada por fonte: o Search Console informa dados
  parciais nos últimos dias, e o Statistics deve marcar essa proveniência.

## Multidomínio

- Cada métrica pode ter a dimensão `domain` (identificador estável como
  `main.aculta`, `loja.aculta`), nunca hostname de Homelab ou produção.
- O painel usa a política de purpose do `aculta_portal`; rotas administrativas
  ficam em `main`.
- Propriedades do Search Console são mapeadas por domínio; propriedades
  sobrepostas não são somadas.

## Privacidade e acesso

- Sem IP, usuário, referrer, user-agent ou cookie nos agregados.
- E-mail do destinatário, quando necessário, só como HMAC (padrão do `aculta_mailer`).
- Permissões de consulta por fonte: relatório individual exige permissão própria;
  dashboard global mostra só agregados.

Detalhes e gates: `PRIVACY.md` (planejado na fase 0.9.0) e `docs/compliance/LGPD-INVENTARIO.md`.

## Dependências

Nenhuma dependência contrib nova nesta fase. `google/apiclient` e os clientes
GA4 ficam para as fases 0.5.0 e 0.6.0, após justificativa escrita.

## Política de versionamento

Esta fase é documental e não altera versão de manifesto (não existe `info.yml`).
A versão do componente só será declarada quando o módulo for ativado.
