## 0.2.0-dev.1 — participação no Fórum (fase 3, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Regras de participação (`ParticipationRules`, derivadas de contagens, sem contador persistido): comentário de Fórum
  de quem não tem comentário publicado vai para a fila de moderação (`comment_presave`); os seguintes são publicados.
- Criação de tópico de Fórum exige comentários publicados (padrão 10) e fica bloqueada enquanto o primeiro comentário
  aguarda aprovação (`entity_create_access`). Mensagem de bloqueio no próprio acesso; a explicação na Atividade fica
  para a fase 4.
- Painel `/admin/config/aculta/portal/participacao` (permissão `administer aculta participacao`), com
  `aculta_user.settings`: limite ligado/desligado, mínimo, primeiro comentário moderado, bloqueio e papéis isentos
  (padrão: `content_editor` e `administrator`).
- Discussão vinculada: campo `field_discussao` (referência a tópico de Fórum) em `article`, `activity`, `project`,
  `document` e `wiki_entry`. A página mostra os últimos 5 comentários publicados e o link para a discussão completa no
  propósito `forum` (via `DomainPurposeManager`). Sem tópico, não há bloco.
- Pendências: configuração e campo ainda não aplicados no Runtime (`config:import` exige autorização); matriculados
  (fase LMS); cartão de pré-visualização (E6), que depende de apresentação no ACULTA420; testes (ao final da série).

# Changelog — aculta_user

## 0.1.0-dev.1 — perfil público por apelido (U1, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Rota pública `/u/{apelido}` (exceção de slug registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`). Só serve no
  propósito `main`; perfil inexistente, privado ou fora do propósito responde 404.
- Visibilidade por item (`PublicProfilePolicy`): apelido público por padrão; foto, bio, e-mail e redes sociais
  só aparecem se a própria pessoa escolher. Cidade, UF, WhatsApp, nome completo e endereço nunca são públicos.
- Formulário `/configuracoes/perfil` (apelido, perfil público e escolhas por item). Apelido com formato, palavras
  reservadas e unicidade validados no formulário.
- Campos do perfil `participante` em `config/install`: `field_perfil_publico`, `field_exibir_foto`,
  `field_exibir_bio`, `field_exibir_email`, `field_exibir_redes`, `field_bio`, `field_redes_sociais`.
- `field_nickname` passa a ser público (`profile_private: false` em `config/sync`).
- Teste unitário de `PublicProfilePolicy` (5 testes).
- `RoleSyncHooks` (`#[Hook]` user_insert/user_update): concede `wiki_contributor` a quem tem `email_confirmed` (decisão 28). Não remove o papel quando `email_confirmed` sai (não decidido). Teste unitário (3 casos).
- Pendências: vínculo do link a `/configuracoes` (bloco do Portal); apresentação no ACULTA420 (sem `data-portal-ui`
  nesta entrega); badges (U4); teste de Kernel da rota e da privacidade; execução no Runtime (`drush en` e
  `config:import` exigem autorização).
