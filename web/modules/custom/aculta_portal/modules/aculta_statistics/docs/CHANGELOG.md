# Changelog — ACULTA Statistics

Formato: entregas por fase, com commits e evidências. Sem versão de manifesto
até o módulo ser ativado.

## 0.1.0 (fase de fundação, em andamento)

### Documentação
- Auditoria das fontes de métrica e classificação por recurso (`CURRENT-STATE.md`),
  incluindo Fórum, Wiki e LMS, conforme pedido do responsável.
- Matriz de portabilidade com versões, licenças e decisões (`PORTABILITY-MATRIX.md`).
- Arquitetura, catálogo de métricas e integrações (`ARCHITECTURE.md`, `METRIC-CATALOG.md`, `INTEGRATIONS.md`).
- Roteiro e ADRs.

### Código portado (sem ativação)
- `Storage\DailyAggregateStorage` e `AggregateStorageInterface`: adaptado de
  `drupal/statistics` (GPL-2.0-or-later, commit `fe20f9f`).
- `Hook\StatisticsCronHooks`: retenção de 90 dias no cron.
- `Connector\SearchConsole\SearchAnalyticsWindow` e `SearchMetricsDelta`: adaptados
  de `drupal/analyze_search_console` (GPL-2.0-or-later, commit `6f054ac`), sem
  credenciais, sem texto formatado.
- Testes unitários: 9 casos, aprovados com PHPUnit 11.5.57 e PHP 8.4.26 local.

### Verificações
- `php -l` em todos os arquivos PHP novos: sem erro.
- Não validado: `DailyAggregateStorage` (depende de banco; testes Kernel na fase 0.2.0),
  compatibilidade PHP 8.5 (o ambiente local tem 8.4).

### Não feito
- Nenhum `*.info.yml`, schema ou ativação.
- Nenhuma migração de banco.
- Nenhuma instalação de módulo contrib.

### Decisões (sem código)
- Consentimento reutiliza o `agreement` do `aculta_portal` (não há módulo de consentimento no `aculta_user`).
- Contador de leitura da Wiki próprio; QR estático medido só nos novos; convergência com `aculta_links` na 0.7.0; GA4 com banner para anônimos mediante autorização de tema.
- Retenção configurável por métrica no painel administrativo (30, 90, 180, 365 dias ou indefinido), com permissão própria e confirmação antes de reduzir prazo (ADR-0003).

## 0.2.0-dev.4 (controle antiabuso do contador da Wiki)

### Código
- `VisitorKey`: identificador pseudonimo do visitante, HMAC-SHA256 do IP com o `hash_salt` do site e um escopo.
  O IP não é guardado em claro. `VisitorKeyFactory` lê o salt do bootstrap.
- Limites no beacon (`WikiReadController`), usando o serviço `flood` do Core:
  - uma leitura por visitante e verbete a cada 30 min; repetições respondem 204 sem contar;
  - no máximo 60 leituras contadas por visitante a cada hora; depois disso, 429 com `Retry-After`.
- Serviços `aculta_statistics.visitor_key` e `aculta_statistics.wiki_read_controller` com as dependências explícitas.

### Testes
- Unit: 31 casos (controlador com limite por visitante, dedupe, visitantes diferentes e chave pseudonima).
- Kernel: 14 casos, aprovados.
- Runtime de teste: duas leituras do mesmo visitante no mesmo verbete contam uma vez; identificadores do `flood` têm 64 caracteres hexadecimais e nenhum contém o IP. Dados de teste removidos depois.

## 0.2.0-dev.3 (contador de leitura da Wiki)

### Código
- Beacon `POST /estatisticas/wiki/{node}/leitura` (`aculta_statistics.wiki_read_controller`): grava
  `wiki.entries.read` com o nid como dimensão, pelo ponto único `StatisticsRecorder`. Responde 204;
  rascunho, outro tipo de conteúdo ou sem permissão de visualização respondem 404.
- `WikiReadHooks` (`node_view`, visualização completa de `wiki_entry` publicado): anexa a biblioteca
  `aculta_statistics/wiki-read` e a URL do beacon. `js/wiki-read.js` envia uma leitura por página
  com `sendBeacon` (ou `fetch` com `keepalive`), sem cookie.
- Dependência declarada em `drupal:node` (o controlador tipa `NodeInterface`).
- Retenção do contador: a métrica `wiki.entries.read` segue o prazo configurado no painel (padrão 90 dias).

### Decisão
- A leitura da Wiki foi antecipada do roteiro da 0.7.0 por decisão do responsável (contador próprio,
  sem consentimento, com retenção por métrica). Base legal confirmada em 2026-10-20 e registrada em `LGPD-INVENTARIO.md`.

### Testes
- Unit: 25 casos (incluindo 4 do controlador da Wiki).
- Kernel: 14 casos (incluindo 3 do beacon). Cada requisição HTTP fica em teste próprio: o Kernel de teste
  mantém o estado da requisição anterior e uma segunda chamada na mesma execução responde 405 por engano.

### Validação no Runtime de teste
- `wiki420.aculta.toca.net.br`: beacon em verbete publicado responde 204 e a leitura é contada (teste de
  fumaça; a linha de teste foi removida depois). Verbete inexistente responde 404.
- `conta.aculta.toca.net.br` responde 404 para verbete da Wiki, pela política de purpose: verbete não se serve fora do domínio `wiki420`.
- Página do verbete responde 200 e anexa o `drupalSettings.acultaStatistics.wikiRead`.

## 0.2.0-dev.2 (banner de consentimento e testes de formulário e cron)

### Código
- Opção `consent_banner.enabled` (padrão desligada) em configuração, com schema.
- `ConsentBannerHooks` (`page_attachments`): anexa a biblioteca `aculta_statistics/consent`
  e a configuração de cookie e Consent Mode só quando a opção está ligada. A tag
  `config:aculta_statistics.settings` invalida o cache ao alternar a opção.
- `js/consent.js`: Consent Mode v2 com padrão negado, cookie `aculta_consent_v1`
  (180 dias, SameSite=Lax) e atualização ao aceitar ou recusar. Sem banner de terceiros.
- Aliases `services.yml` por classe, para autowiring do hook.

### Testes
- Kernel: 11 casos (storage, cron por métrica, formulário de retenção, hook do banner), aprovados contra `aculta_simpletest`.
- Unit: 21 casos, aprovados.

### Validação
- Runtime de teste: `drush cr` aplicado; página de `conta.aculta.toca.net.br` responde 200; banner presente com `hidden`; biblioteca não anexada (opção desligada).

## 0.2.0-dev.1 (núcleo analítico, em andamento)

### Código
- `aculta_statistics.info.yml` com versão `0.2.0-dev.1`, core `^11.3`, sem dependência de outros módulos.
- Schema `aculta_statistics_daily` em `aculta_statistics.install` (chave `(metric, dimension, day)`, índice `(metric, day)`).
- Catálogo de métricas (`MetricCatalog`, `MetricDefinition`) com sete métricas iniciais.
- Gravador único `StatisticsRecorder`: recusa identificador fora do catálogo.
- `DailyAggregateStorage` ganhou `countBefore` e `purgeMetricBefore`.
- Retenção por métrica (`RetentionPolicy`) em configuração: lista `retention` com `metric` e `days`
  (dias; 0 = indefinido). Chaves de configuração não aceitam ponto, por isso a lista.
- Cron aplica o prazo de cada métrica (`StatisticsCronHooks`).
- Formulário de retenção com confirmação quando a redução apaga linhas (`RetentionSettingsForm`).
- Rota `/painel-administrativo/aculta/estatisticas/retencao`, permissão `administer aculta_statistics`.
- Aliases por classe e interface no `services.yml` para autowiring.

### Verificação
- Unit: 21 testes, aprovados (PHPUnit 11.5.57, PHP 8.4.26 local).
- Kernel: 6 testes, aprovados contra `aculta_simpletest` (procedimento de `docs/operations/BANCO-DE-TESTE.md`).
- Aviso conhecido: `sites/simpletest/browser_output` não gravável (não afeta Kernel).

### Não feito nesta versão
- Ativação no Runtime de teste (`updb`) e schema instalado lá.
- Banner de consentimento para visitante anônimo (tema ACULTA420), autorizado mas não iniciado.
- Painel de consulta de métricas (dashboards são 0.8.0).
