## 0.1.0-dev.2 — validar persistência e canais no Kernel — 2026-10-24

- Adicionado Kernel MariaDB que percorre `WikiNoticeEventSubscriber` e o
  `WikiNoticeManager` com entidades Message reais. Confirma replay idempotente,
  aviso obrigatório no site com e-mail desativado e fila direta única.
- Unit Wiki: 3 testes / 20 assertions PASS; Kernel: 2 testes / 23 assertions
  PASS; PHPCS Drupal/DrupalPractice PASS. O Kernel relata duas depreciações
  `@EntityType` de Message/Message Template do Core/contrib. Acesso atual às
  entidades de origem, revogação após fila e Runtime continuam pendentes.
- Nenhum transporte de e-mail real ou configuração do Runtime foi alterado.
