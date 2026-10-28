# Roadmap — ACULTA Agenda LMS (`aculta_lms_agenda`)

## D-15 — agenda por matrícula

- [x] Provedor inclui prazos e eventos somente para membros da turma publicada, se o curso pai estiver publicado e
  autorizar `take course`.
- [x] Access handler aplica a mesma regra a acesso direto ao evento; subscriber protege página e `.ics` individual, com `AccessResult` cacheável.
- [x] Catálogo público consulta o access handler e varia o cache por usuário.
- [x] Kernel cobre matrícula na turma correta, matrícula exclusivamente em outra turma (vê só o evento da própria turma), ausência de matrícula, página direta e callback das rotas de `.ics`/página.
- [x] Painel do aluno usa o mesmo provedor e revalida acesso.
- [x] O fixture Kernel instala o módulo Core `link`, necessário pela definição `field_ticketing` de `aculta_event`.

Verificação em 2026-10-28: `AgendaEntriesProviderKernelTest`, 4 testes / 222 assertions; 92 depreciações de Core e
contrib. Suítes completas: Eventos 57/671/26 depreciações; Agenda LMS 12/222/92. PHP lint (64 arquivos), PHPCS,
`git diff --check` e `drush cr` no Runtime de teste passaram. O provedor registrado em `aculta_calendar` usa
contexto `user`, max-age zero e retorna vazio para visitante anônimo. URLs de eventos de turma ainda aparecem no
sitemap: a correção depende de autorização para editar o gerador protegido por outra frente. Nenhum envio de e-mail,
escrita externa ou dado real foi usado. Código: `1b932d29c`.
