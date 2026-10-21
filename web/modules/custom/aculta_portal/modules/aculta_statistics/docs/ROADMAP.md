# Roadmap — ACULTA Statistics

Planejamento. O estado real de cada entrega fica no CHANGELOG e nos commits.
Cada fase termina antes da próxima começar, sem autorização explícita.

## 0.1.0 — Auditoria e fundação (esta fase)

Entregue nesta fase (documentação e código portado sem ativação):

- Auditoria das fontes de métrica: [CURRENT-STATE.md](CURRENT-STATE.md).
- Matriz de portabilidade e licenças: [PORTABILITY-MATRIX.md](PORTABILITY-MATRIX.md).
- Arquitetura: [ARCHITECTURE.md](ARCHITECTURE.md).
- Catálogo inicial: [METRIC-CATALOG.md](METRIC-CATALOG.md).
- Integrações (Commerce, LMS, e-mail, GA4, GSC, fórum, wiki, multidomínio): [INTEGRATIONS.md](INTEGRATIONS.md).
- ADRs em [adr/](adr/).
- Código portado, sem ativação: storage diário, hook de retenção, janela e
  delta do Search Console, com testes unitários (9 casos).

Pendente nesta fase:

- Verificar o endpoint SMTP2GO de "entregues" na documentação oficial.
- Localizar o Commerce Reporting atual (consulta retornou 404).
- Decidir sobre contador de leitura da Wiki e sobre gráficos (`charts`).
- Decidir sobre QR estático.
- Schema da tabela `aculta_statistics_daily`: não instalado (sem migração).

Não iniciado: ativação do módulo, `*.info.yml`, `hook_schema`, painel e conectores.

## 0.2.0 — Núcleo analítico

- Ativar o módulo: `*.info.yml`, linha no `PROJECT-STATUS-INDEX` do README raiz, schema.
- Contratos de evento e de provedor.
- Catálogo de métricas em código (validação de identificador e unidade).
- Publicação de eventos e gravação no storage.
- Cache com tag `aculta_statistics`.
- Testes Kernel com `aculta_simpletest` (nunca `aculta_runtime`).

## 0.3.0 — Commerce

- `CommerceProvider` e coletor de `commerce_order.order.paid`.
- Receita por moeda e domínio; pedidos criados separados de vendas.
- Carrinho abandonado pelo cart.
- Reconciliação com pagamentos.

## 0.4.0 — LMS

- `LmsProvider` sobre `LearningDashboardManager`, `CourseCompletionPublisher` e certificados.
- Matrículas, conclusões e taxa de conclusão por curso e turma.
- Número mínimo por célula para evitar identificação em turma pequena.
- Só depois do commit de `AnswerSubmittedEvent` (outra frente).

## 0.5.0 — E-mail

- `EmailProvider` lendo `aculta_mail_log`.
- Conciliação Brevo, IMAP e SMTP2GO por `correlation_id`.
- Métricas de entregabilidade com distinção aceito/entregue.
- Conector SMTP2GO somente após confirmar o endpoint.

## 0.6.0 — Google Analytics e Search Console

- GA4 Data API (v1beta) em fila, com cache e quota.
- Consentimento (Consent Mode v2) antes de qualquer tag.
- Search Console com OAuth em `aculta_portal.secrets_manager`.
- Propriedades por domínio, sem somar sobreposição.
- Justificativa escrita para `google/apiclient` antes da instalação.

## 0.7.0 — Integrações comunitárias

- Links (convergir com `aculta_links_stat_daily`).
- QR dinâmico (decisão sobre estático).
- Fórum: série temporal de comentários.
- Wiki: leitura, se a decisão for tomada.
- Mídia: instrumentar downloads e reproduções.
- Eventos e Messenger, quando existirem como fonte.

## 0.8.0 — Dashboards

- Visão global e painéis por módulo, no design system do ACULTA420.
- Filtros por período, domínio, módulo e fonte.
- Séries temporais e comparações entre áreas, sem atribuir causalidade.
- Exportação CSV.

## 0.9.0 — Segurança e otimização

- Retenção, privacidade e revisão do LGPD-INVENTARIO.
- Índices, filas, cache e reconciliação.
- Revisão de permissões por relatório.

## 1.0.0 — Auditoria final

- Testes unitários, Kernel, funcionais e multidomínio.
- Auditoria de segurança, licenças e dados.
- Testes de desempenho.
- Revisão completa da documentação.

## Regras de ciclo

- Dev → RC → validação Alpha → produção, conforme `docs/operations/RELEASE-LIFECYCLE.md`.
- Não criar `preRC`.
- Não abrir PR nem fazer merge sem pedido explícito do responsável.

## Decisões incorporadas (2026-10-20)

- 0.6.0: GA4 com acordo `agreement` para contas; banner de cookies (Consent Mode v2) para anônimos, sujeito a autorização para o tema ACULTA420. Somente `google_tag`.
- 0.7.0: convergência do agregado de `aculta_links` no `aculta_statistics`; QR estático medido só nos QRs novos; contador de leitura da Wiki próprio (`wiki.entries.read`), após inventário LGPD.
- 0.2.0: configuração de retenção por métrica (Configuration API) e permissão própria; 0.9.0: auditoria das alterações de prazo.
