# Auditoria final do ACULTA Statistics (fase 1.0)

Data: 2026-10-21. Escopo: o módulo `aculta_statistics` nas fases 0.1 a 0.9, e o que foi tocado nos módulos produtores
para centralizar estatísticas (ADR-0004). Não é auditoria externa.

## O que foi verificado

| Item | Resultado |
|---|---|
| Unit do módulo | 88 casos, 370 asserções: OK |
| Kernel do módulo (banco `aculta_simpletest`) | 27 casos, 91 asserções: OK |
| Gate de aceite único (`validate-single-acceptance.php`) | PASS |
| Gate de status do índice (`validate-project-status-index.php`) | PASS |
| Gate Drupal 11+ (`validate-aculta-portal-drupal11.php`) | PASS (1007 verificações) |
| Gate de fundação do tema (`validate-aculta420-foundation.php`) | PASS (368 verificações) |
| Painel de envios e painel de áreas no navegador (servidor de testes) | Verificado por captura: filtros, período, gráfico, tabela e comparação. Rótulo do último dia corrigido no gráfico (mailer 0.1.0-dev.60) |
| Funcional do painel (`StatisticsDashboardFunctionalTest`) | 2 casos, 18 asserções: OK, executados num servidor PHP em `127.0.0.1` (simula o servidor de testes, não é o Runtime). Acesso anônimo negado, administrador com período e CSV, área inexistente 404 |
| Guarda de rotas (`RoutingAccessGuardTest`) | OK: toda rota administrativa exige `administer aculta_statistics`; só o beacon da wiki é pública |
| Domínio das rotas administrativas no Runtime (leitura) | `aculta_statistics.dashboard`, `.area`, `.dashboard_export` e `.retention` têm purpose `main`, pela regra de caminho `/painel-administrativo/**` |
| Conciliação de e-mails no Runtime | 69 envios na janela, igual ao total do log |
| Produtor do LMS (instantâneo) no Runtime | Roda sem erro; sem turmas no ambiente, sem linhas |

## Segurança e privacidade

- **Dado pessoal por aluno** (`lms.student.status`, sensibilidade R): fora do painel agregado e do CSV. Apagado na exclusão da conta e na revogação, em todos os dias. Coberto por teste Kernel e Unit.
- **Aberturas de mídia** (`media.files.requests`): por arquivo, sem identificar quem abriu. Conta só abertura permitida e não conta pedaço do visualizador.
- **CSV**: campos com aspas, vírgula ou quebra de linha são escapados. Os valores vêm de identificadores internos e de purposes, não de texto livre do usuário.
- **Acesso**: todas as rotas exigem `administer aculta_statistics`. Área inexistente responde 404. Parâmetros fora da lista são ignorados.
- **Cache**: página do painel com `max-age` 0 e contexto de query.
- **Auditoria de prazo**: toda alteração de retenção gera registro no canal `aculta_statistics`, com métrica, valor antigo, novo e usuário.
- **Consentimento**: aceite único (`aculta_user_agreement_terms`). Decisão do responsável (2026-10-21): o consentimento embutido atende o propósito jurídico do projeto.

## Desempenho

- A página faz uma consulta por dia e métrica (até 60 para o período de 30 dias, mais a comparação do período anterior).
- Índices existentes: chave primária `(metric, dimension, day)` e índice `metric_day`.
- Não foi feito teste de carga. Pendente.

## O que não foi verificado (pendências assumidas)

- **Cliente GA4 real (0.6.0):** o padrão é o cliente nulo, desligado. A dependência `google/apiclient` tem justificativa proposta (ADR-0006), mas não foi instalada: `composer.json` e `composer.lock` têm alterações não commitadas de outra frente. Espera a outra frente commitar.
- **Search Console:** só os cálculos portados (janela e delta). Sem consulta à API.
- **Painéis em tema Claro:** as telas de estatísticas e de envios usam o tema administrativo Claro. O CSS do ACULTA420 para elas (`statistics.css`) não se aplica a essas telas. Decisão do responsável (2026-10-21): ajustar só o conteúdo.
- **Multidomínio:** não há teste de requisição em cada host (main, conta, loja...). A regra de domínio das rotas administrativas foi conferida no Runtime e pela guarda de rotas. Teste por host fica pendente.
- **Auditoria de licenças:** a matriz de portabilidade (`PORTABILITY-MATRIX.md`) cobre as dependências atuais. Nenhuma dependência nova foi adicionada.
- **Revisão visual de todos os painéis por área:** verificado só o de mídia, e o de envios.
- **Teste de carga:** medição única, com dados sintéticos, no banco de teste (27 mil linhas: 90 dias, 300 dimensões por dia, e 180 linhas de domínio). Página de 30 dias por wiki: 0,38 s. CSV de 30 dias: 0,01 s. Página de 30 dias com filtro de domínio: 0,04 s. Gravação diária de 27 mil linhas: 32 s, por apagar e regravar linha a linha. Não é teste de carga contínua nem de concorrência.

## Conclusão

O módulo está completo no código para as fases 0.1 a 0.9, exceto os itens dependentes de decisão ou de credencial
listados acima. A fase 1.0 fica aberta até as pendências de testes funcionais, multidomínio e carga, e até o cliente GA4
ser decidido.

## Verificação de 2026-10-21 (Runtime de teste, somente leitura)

- **Por host (GET em `/painel-administrativo/aculta/estatisticas`, anônimo):** `aculta.toca.net.br` responde 403 (permissão negada). `conta.` e `loja.` respondem 302 para o principal. Ou seja, a rota administrativa só é servida no host principal, como manda a política de domínio. Não há teste automatizado por host.
- **Visual das áreas (administrador, período de 30 dias):** vendas, cursos e fórum renderizadas com seletor, comparação, gráfico, tabela e rótulos legíveis. As demais áreas (e-mail, links, mídia, wiki) usam o mesmo modelo, e mídia e wiki foram vistas antes da correção do rótulo. Tudo no tema administrativo Claro.
- **Clientes GA4 e Search Console:** desligados (sem credenciais). A verificação real (`aculta-statistics:google-check`) está pronta e depende das credenciais da conta de serviço.
