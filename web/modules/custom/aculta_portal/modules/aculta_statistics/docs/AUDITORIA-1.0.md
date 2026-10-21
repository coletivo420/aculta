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
| Painel de envios e painel de áreas no navegador (servidor de testes) | Verificado por captura: filtros, período, gráfico, tabela e comparação |
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
- **Testes funcionais e multidomínio:** `StatisticsDashboardFunctionalTest` escrito (acesso anônimo bloqueado, administrador com período e CSV, área inexistente 404), mas **não executado**: exige um site de teste com `SIMPLETEST_BASE_URL`, e o procedimento proíbe usar o Runtime. Pulado sem essa variável. Na captura de navegador do servidor de testes, o acesso anônimo ao painel foi negado e o administrador viu a página. Testes multidomínio não feitos.
- **Auditoria de licenças:** a matriz de portabilidade (`PORTABILITY-MATRIX.md`) cobre as dependências atuais. Nenhuma dependência nova foi adicionada.
- **Revisão visual de todos os painéis por área:** verificado só o de mídia, e o de envios.
- **Teste de carga:** não feito.

## Conclusão

O módulo está completo no código para as fases 0.1 a 0.9, exceto os itens dependentes de decisão ou de credencial
listados acima. A fase 1.0 fica aberta até as pendências de testes funcionais, multidomínio e carga, e até o cliente GA4
ser decidido.
