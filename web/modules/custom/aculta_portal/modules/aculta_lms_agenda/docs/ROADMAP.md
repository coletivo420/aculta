# Roadmap — ACULTA Agenda LMS (`aculta_lms_agenda`)

## D-15 — agenda por matrícula

- [x] Provedor inclui prazos e eventos somente para membros da turma publicada, se o curso pai estiver publicado e
  autorizar `take course`.
- [x] Access handler aplica a mesma regra a acesso direto ao evento; subscriber protege página e `.ics` individual, com `AccessResult` cacheável.
- [x] Catálogo público consulta o access handler e varia o cache por usuário.
- [x] Kernel cobre matrícula na turma correta, matrícula exclusivamente em outra turma (vê só o evento da própria turma), ausência de matrícula, página direta e callback das rotas de `.ics`/página.
- [x] Painel do aluno usa o mesmo provedor e revalida acesso.
- [x] O hook `hook_simple_sitemap_links_alter()` remove do sitemap eventos vinculados a turmas.
- [x] O fixture Kernel instala o módulo Core `link`, necessário pela definição `field_ticketing` de `aculta_event`.

Verificação em 2026-10-28: suíte completa Agenda LMS, 12 testes / 227 assertions / 92 depreciações de Core e
contrib; Eventos 57/671/26 depreciações. PHP lint dos arquivos alterados, PHPCS e `git diff --check` passaram.
O provedor registrado em `aculta_calendar` usa contexto `user`, max-age zero e retorna vazio para visitante anônimo.
A correção do sitemap usa o hook oficial de alteração de links do Simple Sitemap, sem alteração do gerador.
Uma regressão também confirma que a rota continua respeitando a permissão existente de prévia de rascunho em evento
sem turma. Nenhum envio de e-mail, escrita externa ou dado real foi usado. Código: `1b932d29c`, `dbf2d94e8`,
`3be40be71`.

### Prontidão para RC — bloqueios registrados em 2026-10-28

- D-15 revalidado nesta sessão: suíte do módulo com 12 testes / 227 assertions
  PASS; 92 depreciações de Core/contrib. PHP lint de todos os arquivos do módulo
  PASS.
- PHPCS Drupal do módulo completo PASS após corrigir comentários, formatação de
  arrays e nomes de métodos de teste. A alteração é somente de estilo e não muda
  a regra de acesso D-15.
- `aculta_lms`, `aculta_events` e `aculta_calendar` permanecem em Dev e são
  dependências diretas. A homologação visual integrada e Runtime aguardam esses
  módulos e autorização operacional; manter `state: Dev`.

Verificação da correção de estilo em 2026-10-28: suíte completa 12 testes / 227
assertions PASS, 92 depreciações; PHP lint 15/15 PASS; PHPCS Drupal PASS.
