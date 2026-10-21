# ADR-0001 — Agregados diários em vez de armazém de eventos brutos

Status: aceita (fase 0.1.0).

## Contexto

O Statistics precisa de séries históricas por métrica, domínio e curso. Um
armazém com cada evento bruto (visita, clique, mensagem) cresce depressa, guarda
dado pessoal sem necessidade e repete o que Commerce, LMS e e-mail já mantêm.

## Decisão

Gravar **contadores diários agregados** por `(métrica, dimensão, dia UTC)`, sem
IP, usuário, referrer ou user-agent. Retenção de 90 dias, como em `aculta_links`.
Dados de origem ficam nos módulos donos e são lidos por provedores.

## Consequências

- Volume de linhas proporcional a métricas × dimensões × dias, não a eventos.
- Não é possível reconstruir uma visita individual; isso é intencional (LGPD).
- Correções retroativas exigem recálculo a partir da fonte, não edição do agregado.
- Relatórios que cruzam áreas precisam de identificador de correlação; sem ele,
  mostrar só comparação agregada.

## Alternativas descartadas

- Content Entity por evento: custo de armazenamento e de consulta alto.
- Copiar pedidos e matrículas para o Statistics: duplicação de dado de negócio.
- Armazém externo (BigQuery, etc.): dependência e transferência de dados sem necessidade atual.
