# ACULTA — instruções para agentes

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Regras gerais do repositório, válidas para todos os agentes. Em caso de conflito, prevalecem a instrução explícita mais recente do responsável e as regras de segurança abaixo. Regras específicas de ambiente, Drush, servidor web e testes ficam em `docs/operations/AGENT-OPERATIONS.md`; regras por escopo, no índice `docs/operations/AGENT-POLICY-INDEX.md`. Não replique aqui históricos nem planos.

## Sessão

- Antes de trocar de sessão, encerrar uma fase ou transferir o trabalho, atualize o handoff em [`docs/operations/ESTADO-DA-SESSAO.md`](docs/operations/ESTADO-DA-SESSAO.md): objetivo, o que foi feito, arquivos alterados, verificações e resultados, pendências e próximos passos, com caminhos concretos de leitura. Não registre segredos.
- Atualize o registro existente em vez de criar históricos paralelos.

## Git (D-70, D-71)

- **Branch:** `dev`. Cada entrega é commitada e enviada a `origin/dev` por fast-forward, logo após o commit. Push automático, sem autorização por hash.
- **Worktree local sem branch é permitido** para isolar a tarefa: `git worktree add --detach <caminho fora do repositório> origin/dev`. Ao integrar, `git push origin HEAD:dev` e depois `git worktree remove <caminho>`.
- **Não crie branches, PRs nem publique em outra branch**, salvo ordem explícita do responsável. PR e merge em `main` só com pedido explícito; commit e push não autorizam merge. Tag só com pedido.
- **Um commit por entrega**, só com os arquivos da entrega. Verifique o conteúdo staged antes do commit. Se a tarefa virar entregas independentes, separe em commits atômicos.
- **Antes de editar**, confira `git status --short --branch`. Preserve alterações de outras pessoas e não descarte trabalho alheio.
- **Se o remoto avançar ou o push for recusado**, integre por rebase sem sobrescrever trabalho, revise e tente de novo.
- **Nunca** force push, reset destrutivo nem reescrita de histórico compartilhado.
- **Stash não é entrega.** Não guarde trabalho em `git stash` como destino final.
- **Mensagem de commit** em português, com o trailer `Claude-Session` quando a sessão for do Claude (CLAUDE.md).
- **Coautoria é proibida.** Agentes não incluem `Co-Authored-By` nem outro trailer de coautoria em commits, PRs ou documentos.
- **Não commitar** credenciais, `settings.local.php`, `.claude/settings.local.json`, backups locais nem conteúdo de `secrets/`.
- **Ao concluir**, informe os commits enviados com hash, as validações executadas, o estado do Runtime e qualquer bloqueio. Não diga que publicou se o push não foi confirmado.

## Runtime (D-70)

- **Sincronização:** após cada push em `origin/dev`, atualize o Runtime de desenvolvimento para o mesmo commit. Atualize somente o código. Não toque em `secrets/`, `settings.local.php`, `private/`, `var/` nem nos dados do site.
- **Antes da troca de head**, rode `updatedb:status` e `config:status` (somente leitura).
- **Havendo pendência**, faça snapshot do `aculta_runtime` com hash e, depois, `updatedb` e `config:import` do `config/sync`. Pare e informe o responsável se qualquer passo falhar.
- **Nunca** `pm:uninstall`, `DROP`, nem apagamento de conteúdo ou de dados, sem ordem explícita.
- **Testes de Kernel** usam só `aculta_simpletest`. Nunca crie bancos.
- **Não se aplica a produção.** Produção não recebe sincronização automática nem estados `estados/*.sqlite`.

## Escopo e segurança

- Altere somente o necessário para a tarefa. Preserve contratos existentes, trabalho paralelo e dados.
- Não faça deploy nem alteração de produção sem autorização explícita.
- Drupal fica em `web/`; módulos customizados em `web/modules/custom/`; tema em `web/themes/custom/aculta420/`. Siga os padrões Drupal/PHP e os contratos dos módulos envolvidos.
- Não invente contratos nem altere ownership de rotas, entidades, permissões, workflow ou integrações sem verificar a documentação e o código atuais.
- Não invente hosts, nomes de servidor, CNPJ, endereço ou dados institucionais.

## Arquitetura e validação

- Suítes atuais: Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/wesativa`; Green Stash `shop:/greenstash`; Culturas `eventos:/culturas`. `aculta_studio` é infraestrutura compartilhada, não uma raiz pública. `/studio` e `/studio/*` respondem 404, sem alias nem redirect.
- Rotas administrativas e de integração existem só no domínio principal e respondem 404 nos demais. A experiência de organizadores em `/culturas` permanece separada.
- Preserve os módulos responsáveis e valide acesso no servidor, inclusive em escritas.
- Padrão Drupal 11+: `docs/portal/DRUPAL-11-STANDARDS.md`.
- Antes de concluir, execute as verificações relevantes e registre os resultados. Não alegue testes que não executou.

## Referências

- Ambientes, Drush, servidor web, banco de teste e ciclo de release: `docs/operations/AGENT-OPERATIONS.md`, `docs/architecture/environments.md`.
- Regras por escopo: `docs/operations/AGENT-POLICY-INDEX.md`.
- Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-70 e D-71 vigentes).
- Contratos de domínio e rotas: ADR-038, `docs/portal/ADMIN-DOMAIN-POLICY.md` e documentação de arquitetura pertinente.
