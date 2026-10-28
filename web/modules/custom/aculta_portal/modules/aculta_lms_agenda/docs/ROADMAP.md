# Roadmap — ACULTA Agenda LMS (`aculta_lms_agenda`)

## D-15 — agenda por matrícula

- [x] Provedor inclui prazos e eventos somente para membros da turma publicada, se o curso pai estiver publicado e
  autorizar `take course`.
- [x] Kernel cobre matrícula na turma correta, matrícula em outra turma e ausência de matrícula.
- [x] Painel do aluno usa o mesmo provedor e revalida acesso.
- [x] O fixture Kernel instala o módulo Core `link`, necessário pela definição `field_ticketing` de `aculta_event`.

Verificação em 2026-10-28: `AgendaEntriesProviderKernelTest`, 4 testes / 176 assertions; 92 depreciações de Core e
contrib. A primeira execução identificou apenas a ausência de `link` no fixture; corrigido e a suíte focal passou.
Nenhuma chamada ao Runtime, envio de e-mail ou dado real foi usado.
