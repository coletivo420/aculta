# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Ambientes

- `*.aculta.toca.net.br` é o servidor de desenvolvimento (Homelab); `*.aculta.org` é produção. Os hosts por purpose estão em `docs/architecture/multidomain.md`.
- Para Drush e operações do Runtime, siga `docs/architecture/environments.md`; não presuma um caminho de checkout, necessidade de SSH ou URI padrão.
- **Antes de qualquer operação de ambiente**, confirme `hostname`, `pwd`, branch/estado Git e `php vendor/drush/drush/drush.php status`. Confirme no Drush o site URI e o banco conectados antes de executar comandos que alterem estado.
- Se `hostname` for `toca` e o Drush confirmar o Runtime `aculta_runtime`, trate a sessão como já estando no servidor de desenvolvimento: use o checkout e o Drush locais. A saída `Site URI: http://default` sem `--uri` é esperada neste ambiente e não significa produção nem erro de conexão. Para operações sensíveis ao purpose, passe `--uri` com um host de teste documentado. SSH só se justifica quando a operação necessária não estiver disponível localmente e o destino tiver sido confirmado pela documentação de ambientes.
- **Sincronização do Runtime (D-70):** após cada push de commit para `origin/dev`, atualize o Runtime para o mesmo commit. Atualize somente o código; não toque em `secrets/`, `settings.local.php`, `private/`, `var/` nem nos dados do site. Antes de trocar o head, rode `updatedb:status` e `config:status` (somente leitura). Havendo pendência, faça snapshot do `aculta_runtime` com hash e só então `updatedb` e `config:import` do `config/sync`. Verifique URI, banco e efeitos antes de cada alteração persistente. Nunca `pm:uninstall`, `DROP` nem apagamento de conteúdo. Se a sincronização falhar, pare, registre a causa e informe o responsável. Não se aplica a produção.
- Não executar `updb`, import/export de configuração, deploy ou outras operações persistentes sem primeiro inspecionar o estado, os efeitos e a possibilidade de recuperação; nunca apontar procedimentos de teste para produção.
- Se um problema pontual e verificável bloquear diretamente uma entrega autorizada, agentes podem corrigi-lo durante o desenvolvimento sem interromper a tarefa. Inspecione primeiro o impacto e a reversão, limite a correção ao bloqueador, preserve o estado recuperável quando necessário e registre a causa e o resultado no changelog/documentação pertinente. Não use essa regra para ampliar escopo, remover conteúdo ou fazer alterações destrutivas.
- Não inventar ambientes, hosts ou nomes de servidor. Use somente os definidos em `docs/architecture/environments.md` e `docs/architecture/multidomain.md`.
- Testes em `127.0.0.1` com resolução de host própria simulam o servidor de desenvolvimento, mas não são o servidor de desenvolvimento; registre-os como tal.

## Servidor web

Homelab usa Apache; a produção Hostinger Cloud Startup usa LiteSpeed. Detalhes e validações por ambiente estão em `docs/architecture/environments.md` e ADR-010.

- Não introduzir novos exemplos, regras ou dependências específicas de Nginx.
- Não manter compatibilidade com Nginx como requisito do projeto.
- Preservar o `web/.htaccess` fornecido pelo Drupal e validar as diretivas em ambos os ambientes.
- Diretivas próprias do ACULTA no `web/.htaccess` (MIME de `.mjs` do PDF.js) têm gate: `php scripts/validate-htaccess-directives.php`. Remover o bloco reprova o gate.
- No Homelab, validar `mod_rewrite`, `mod_headers` e a integração PHP-FPM por `proxy_fcgi`.
- Na produção, validar reescrita, headers, proteção de arquivos, processamento de `.htaccess` e handler PHP no LiteSpeed/Hostinger; não presumir acesso a VirtualHost ou `httpd.conf`.
- VirtualHosts, caminhos, usuários, certificados e configuração Virtualmin/Hostinger continuam específicos de cada ambiente.
- Referências a Nginx em relatórios antigos são históricas e não definem a arquitetura atual.

## Git

Branch de trabalho: `dev` (D-71; `AGENTS.md`). Repositório: `coletivo420/aculta-site` (confira com `git remote -v`).

Antes de alterações significativas, verificar `git status --short --branch`.

Não fazer force push. Não reescrever `dev` nem `main`. Não fazer commit de credenciais.

## Drush

No Homelab Debian, usar o Drush instalado pelo Composer:

```sh
php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php cr
```

Em uma workstation Windows opcional, o equivalente é:

```powershell
php .\vendor\drush\drush\drush.php status
php .\vendor\drush\drush\drush.php cr
```

## Banco de teste (Kernel)

Os testes de Kernel usam o MariaDB dedicado `aculta_simpletest`, com a configuração em `secrets/simpletest-db.env`
(fora do Git). Procedimento, rotação e resultados: `docs/operations/BANCO-DE-TESTE.md`. Nunca usar o Runtime
`aculta_runtime` nem SQLite para testes; nunca imprimir o conteúdo do arquivo de segredos.

## Ciclo Dev, RC e Alpha

- Seguir `docs/operations/RELEASE-LIFECYCLE.md` para classificar entregas. RC é
  por unidade e requer validação visual pré-deploy quando houver interface; a
  validação do servidor ocorre em Alpha, após deploy autorizado; o deploy no Hostinger (produção) ocorre no Beta, depois de Alpha validado.
- Um submódulo fora de RC não bloqueia automaticamente o trabalho ou a entrega
  de outro componente. Escopo de release central é decidido pelo responsável.
- Entregas vão para `origin/dev` (D-71). PR, merge em `main` e tag só com pedido
  explícito do responsável; nunca como encerramento automático de fase.

## Regra de encerramento de fase

Toda fase ou subfase concluída deve terminar em commit(s) atômicos publicados em `origin/dev` e sincronizados no Runtime (D-70). PR só é criado quando o responsável solicitar. Quando solicitado, o PR deve:

- representar uma unidade lógica clara;
- listar entregas e limites;
- classificar o estado de Runtime conforme o ciclo Alpha e registrar `DEFERRED`
  quando ainda não implantado/validado no servidor de desenvolvimento;
- não misturar trabalho de outra fase;
- apontar a próxima fase;
- permanecer rastreável mesmo quando for integrado imediatamente.

Não declarar Alpha validado sem evidência do servidor de desenvolvimento.

## Versionamento de mudanças

Antes de alterar código do Portal ou do tema, siga `web/themes/custom/aculta420/docs/versioning.md` e `docs/operations/RELEASES.md`. Portal e tema têm versões independentes; mantenha manifest e CHANGELOG correspondentes coerentes e não marque estado ainda não validado. Mudanças apenas documentais não avançam versão. Não criar tags sem pedido explícito do responsável.

## Política de commits para agentes de IA (D-70, D-71)

Motivo: trabalho que ficou só no working tree, em stash ou em branch sem integração gerou duplicidade
e código obsoleto. Nenhum trabalho deve depender de estado local.

- **Commit antes de encerrar.** Toda alteração concluída vira commit e é publicada em `origin/dev` por
  fast-forward (AGENTS.md). Não deixar trabalho só no working tree.
- **Um commit por entrega.** Só com os arquivos da entrega; verifique o conteúdo staged antes do commit.
  Se a tarefa virar entregas independentes, separe em commits atômicos.
- **Worktree local** é permitido para isolar a tarefa, sem branch, conforme AGENTS.md (D-70). Remova o
  worktree ao terminar.
- **Stash não é entrega.** Não guardar trabalho em `git stash` como destino final.
- **PR e merge.** Só com pedido explícito do responsável. Commit e push não autorizam merge. Antes de mesclar,
  confirme a base com `gh pr view <n> --json baseRefName`.
- **Não commitar** credenciais, `settings.local.php`, backups locais, `.claude/settings.local.json` nem
  conteúdo de `secrets/`.
- **Não reescrever histórico** de branch compartilhado (sem force push).
- **Mensagem de commit** em português. Trailer: apenas `Claude-Session` (CLAUDE.md).
- **Proibido: coautoria.** Agentes não incluem `Co-Authored-By` nem qualquer outro trailer de coautoria em commits, PRs ou documentos.
- **Encerramento:** antes de encerrar, informar `git status`, a branch, o commit de cada entrega e o estado
  da sincronização do Runtime. Se algo ficou fora do git, dizer o motivo.
