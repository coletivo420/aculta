# Teste visual — avatar padrão

Cobre a listagem pública de perfis (`/membros`), o perfil público
(`/u/<apelido>`) e as páginas iniciais dos domínios de Revista, Cursos, Wiki,
Conta e Fórum, além de Meus dados e Mensagens autenticados. Verifica a regra de
avatar da entrega `feat/avatar-padrao`:

- foto liberada → avatar do acervo (`AvatarPool`) → nada; **a inicial do nome
  nunca é usada**;
- o acervo é componente obrigatório e o último fallback;
- perfil privado não aparece na listagem pública;
- as classes `aculta-uistyle--surface-raised` e `aculta-uistyle--border-subtle`
  estão nas linhas da view.

## Ambiente

- **Local**, não é o servidor de desenvolvimento remoto. O script fala com o `runserver`
  do Drush em `127.0.0.1:8080` e resolve os hosts no próprio Firefox
  (`network.dns.localDomains`).
- Usa os aliases locais do Domain Alias (`*.aculta.test:8080`), porque o padrão
  de alias local inclui a porta. O host canônico `*.aculta.org` e o host
  `*.aculta.toca.net.br` não são usados nesta execução.
- O Fórum não tem alias local (`forum_aculta_toca_net_br` é só `homelab`); o
  script tenta `growroom420.aculta.toca.net.br:8080` e registra a limitação.
- Firefox ESR visível em `DISPLAY=:0`, via geckodriver. Nunca Chromium nem
  headless.
- O worktree precisa estar com o site bootável. O `settings.local.php` local
  (não versionado) precisa liberar os hosts `*.aculta.test(:8080)?`, incluir
  `services.avatar.yml` com `cookie_secure: false` e `cookie_domain: ''` (login
  em HTTP no runserver) e ter o update 10013 (`aculta_user_nickname`) aplicado.

## Execução

```bash
cd /home/piradopirata/worktrees/feat-avatar-padrao
vendor/bin/drush rs 127.0.0.1:8080 --no-browser &
LOGIN_URL="$(vendor/bin/drush uli --uri=http://conta.aculta.test:8080 --uid=15 2>/dev/null | tail -1)"
DISPLAY=:0 BASE_URL=http://aculta.test:8080 LOGIN_URL="$LOGIN_URL" \
  SUITE_OUT=/home/piradopirata/worktrees/avatar-evidence \
  python3 tests/browser/aculta-avatar/visual.py
```

### Tema Olivero

A troca de tema é estado global do site. O comando de execução restaura com
`trap`, mesmo se o teste falhar:

```bash
cd /home/piradopirata/worktrees/feat-avatar-padrao
vendor/bin/drush config:set system.theme default olivero -y && vendor/bin/drush cr
trap 'vendor/bin/drush config:set system.theme default aculta420 -y && vendor/bin/drush cr' EXIT
LOGIN_URL="$(vendor/bin/drush uli --uri=http://conta.aculta.test:8080 --uid=15 2>/dev/null | tail -1)"
DISPLAY=:0 BASE_URL=http://aculta.test:8080 LOGIN_URL="$LOGIN_URL" \
  SUITE_OUT=/home/piradopirata/worktrees/avatar-evidence-olivero \
  python3 tests/browser/aculta-avatar/visual.py
```

## Limitações

- O login único é gerado para o uid 15 (`coletivo420`), que é administrador;
  Mensagens e Meus dados são verificados com essa conta. O roster/sala/feedback
  do LMS e os comentários da Wiki exigem uma conta estudante/educadora e um
  curso/turma concreto; a cobertura de visibilidade do roster está no Kernel
  `ClassRosterVisibilityTest`.
- O Fórum depende de um alias local inexistente; se a navegação falhar, o
  teste registra a página como não verificada.
- Não altera conteúdo. O login único cria sessão e pode atualizar o último
  acesso.
- Artefatos (PNG e logs) ficam em `$SUITE_OUT`, fora do repositório.
