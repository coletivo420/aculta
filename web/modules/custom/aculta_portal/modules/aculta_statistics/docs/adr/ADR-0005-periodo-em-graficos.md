# ADR-0005 — Seleção de período em todos os gráficos

Status: aceita pelo responsável (2026-10-21).

## Contexto

O painel de envios tinha gráficos fixos por período (1, 7 e 30 dias) em blocos separados, e o responsável pediu
seleção de período e de tipo nos gráficos, como em outros painéis de envio. Nenhum gráfico do projeto podia ficar
sem essa escolha.

## Decisão

- **Todo gráfico com eixo de tempo** tem seletor de período. O conjunto padrão é **7, 14 e 30 dias**
  (`MailDashboard::CHART_PERIODS`). Valor fora do conjunto volta ao padrão de 7 dias.
- O período vale para o gráfico, para as contagens e para as tabelas da mesma página, para não haver números
  de períodos diferentes lado a lado.
- A seleção fica na query (`dias`, `tipo`) e a página usa GET, sem JavaScript. Um botão "Aplicar" torna a
  escolha acessível sem script.
- Filtros de tipo são listas fixas no código (`FILTERS`), nunca texto livre.
- O gráfico é SVG inline, com legenda, valores em tabela e rótulos escapados. Sem biblioteca externa.
- **Exceção:** gráfico de instantâneo sem eixo de tempo (por exemplo, a barra de progresso do aluno, que mostra
  a situação atual) fica fora da regra. Quando ganhar série, ganha o seletor.
- **Série diária por aluno** não é criada: é tratamento de dado pessoal ao longo do tempo e exige revisão LGPD
  antes (`docs/compliance/LGPD-INVENTARIO.md`). Séries são agregadas por turma e situação.

## Consequências

- Novo gráfico com tempo precisa do seletor no mesmo commit, com teste de página.
- Painéis do aculta_statistics (0.8.0) já nascem com esta regra.
