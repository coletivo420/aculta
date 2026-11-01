# ADR-0003 — Retenção configurável por métrica no painel administrativo

Status: aceita pelo responsável (2026-10-20).

## Contexto

Os agregados de visita (Wiki, Links) e os de operação (vendas, conclusões) têm
riscos e utilidades diferentes. Um prazo fixo no código não permite ao
administrador reduzir o armazenamento nem ajustar o prazo à política de privacidade.

## Decisão

- **Escopo:** prazo de retenção por métrica do catálogo (ex.: `wiki.entries.read`,
  `links.link.clicks`, `commerce.revenue.gross`).
- **Opções:** lista fixa de 30, 90, 180, 365 dias e **indefinido**. Padrão para
  os contadores da Wiki: 90 dias.
- **Indefinido:** liberado para todas as métricas, por decisão do responsável.
  Risco registrado: contadores de visita podem acumular dados por tempo ilimitado
  se o administrador escolher essa opção. Mitigação prevista: aviso na tela e
  registro de auditoria da alteração (fase 0.9.0).
- **Permissão:** nova permissão própria de administração do `aculta_statistics`,
  concedida a papéis administrativos. Sem checagem por nome de papel.
- **Redução de prazo:** o painel mostra quantas linhas serão apagadas e pede
  confirmação. A exclusão ocorre no próximo cron.
- **Padrão de cron:** a retenção padrão de 90 dias de `StatisticsCronHooks` passa a
  ser o valor inicial de cada métrica, não uma constante.

## Consequências

- O prazo de cada métrica vira configuração do Drupal (Configuration API), com
  schema, para ser exportável e auditável (fase 0.2.0).
- A exclusão em lote precisa de índice `(metric, day)` (já previsto em `ARCHITECTURE.md`).
- O `aculta_links` deve adotar o mesmo modelo ao convergir (fase 0.7.0).
- A retenção aplica-se também a dado de operação: a tela deve deixar claro
  quando "indefinido" é usado.
