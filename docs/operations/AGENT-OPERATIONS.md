# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Ambientes

- `*.aculta.toca.net.br` é o servidor de testes (Homelab); `*.aculta.org` é produção. Os hosts por purpose estão em `docs/architecture/multidomain.md`.
- Para Drush e operações do Runtime, siga `docs/architecture/environments.md`; não presuma um caminho de checkout, necessidade de SSH ou URI padrão.
- **Antes de qualquer operação de ambiente**, confirme `hostname`, `pwd`, branch/estado Git e `php vendor/drush/drush/drush.php status`. Confirme no Drush o site URI e o banco conectados antes de executar comandos que alterem estado.
- Se `hostname` for `toca` e o Drush confirmar o Runtime `aculta_runtime`, trate a sessão como já estando no servidor de testes: use o checkout e o Drush locais. A saída `Site URI: http://default` sem `--uri` é esperada neste ambiente e não significa produção nem erro de conexão. Para operações sensíveis ao purpose, passe `--uri` com um host de teste documentado. SSH só se justifica quando a operação necessária não estiver disponível localmente e o destino tiver sido confirmado pela documentação de ambientes.
- **Padrão de desenvolvimento funcional:** quando a sessão for confirmada como o servidor de testes, aplique mudanças funcionais no Runtime assim que o escopo estiver coerente para smoke test contínuo. Verifique URI, banco, configuração e efeitos antes de cada alteração persistente. Isso não autoriza operações destrutivas nem alterações em produção.
- Não executar `updb`, import/export de configuração, deploy ou outras operações persistentes sem primeiro inspecionar o estado, os efeitos e a possibilidade de recuperação; nunca apontar procedimentos de teste para produção.
- Se um problema pontual e verificável bloquear diretamente uma entrega autorizada, agentes podem corrigi-lo durante o desenvolvimento sem interromper a tarefa. Inspecione primeiro o impacto e a reversão, limite a correção ao bloqueador, preserve o estado recuperável quando necessário e registre a causa e o resultado no changelog/documentação pertinente. Não use essa regra para ampliar escopo, remover conteúdo ou fazer alterações destrutivas.
- Não inventar ambientes, hosts ou nomes de servidor. Use somente os definidos em `docs/architecture/environments.md` e `docs/architecture/multidomain.md`.
- Testes em `127.0.0.1` com resolução de host própria simulam o servidor de testes, mas não são o servidor de testes; registre-os como tal.

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

Branch principal:

main

Repositório: `coletivo420/aculta` (origin pode manter URL antiga com redirect; confira `git remote -v`).

Antes de alterações significativas, verificar:

git status

Não fazer force push.

Não reescrever o histórico da branch main.

Não fazer commit de credenciais.

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
  validação do servidor ocorre em Alpha, após deploy autorizado.
- Um submódulo fora de RC não bloqueia automaticamente o trabalho ou a entrega
  de outro componente. Escopo de release central é decidido pelo responsável.
- Não abrir PR nesta frente nem como encerramento automático de fase. Permanecer
  no branch aberto pelo responsável, registrar commits atômicos e aguardar pedido
  explícito antes de PR, merge ou tag.

## Regra de encerramento de fase

Toda fase ou subfase concluída deve terminar em commit(s) atômicos na branch
autorizada. PR só é criado quando o responsável solicitar. Quando solicitado, o PR deve:

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

## Política de commits para agentes de IA (regra permanente)

Motivo: trabalho que ficou só no working tree, em stash ou em branch sem PR gerou
duplicidade, código obsoleto e uma base de PR errada (PRs mesclados em branches antigos,
e não na `main`). Esta regra existe para que nenhum trabalho dependa de estado local.

- **Commit antes de encerrar.** Toda alteração concluída vira commit no próprio branch
  antes do fim da sessão. Não deixar trabalho só no working tree.
- **Um commit por tarefa.** O agente trabalha no branch de trabalho já aberto pelo responsável
  e registra a tarefa em um commit atômico, sem criar ou trocar de branch por causa da tarefa.
  Se a tarefa for ampliada e passar a conter entregas independentes, separar em commits atômicos
  por tarefa antes de encerrar.
- **Publicar cada commit** no branch atual com `git push` após concluir a tarefa. Não há commit
  "pendente" depois que a sessão termina.
- **Usar a PR aberta do branch atual.** Antes de iniciar ou publicar trabalho, verificar se já há
  PR aberta para o branch. Contribuir nela somente com commits da tarefa autorizada, conferindo
  o diff e os arquivos staged para não incluir o trabalho de outros agentes. Não abrir outra PR
  sem pedido explícito do responsável.
- **Merge só no final.** O PR é mesclado uma única vez, quando a tarefa estiver concluída e o
  responsável autorizar. Não mesclar entregas parciais nem PRs intermediários.
- **Stash não é entrega.** Não guardar trabalho em `git stash`. Stash temporário só dentro
  de um comando, e nunca como destino final.
- **Diretório de trabalho:** usar o checkout local aberto pelo responsável, conforme a seção “Diretório de trabalho obrigatório”. Worktree ou clone temporário só pode ser usado a pedido explícito do responsável.
- **Verificar a base do PR antes de mesclar.** `gh pr view <n> --json baseRefName` deve
  apontar para `main` (ou para o branch base declarado). Trocar a base antes da fusão, e
  confirmar depois com `git merge-base --is-ancestor <commit> origin/main`.
- **Mesclar só com autorização explícita** do responsável. Commit e push não autorizam fusão.
- **Não commitar** credenciais, `settings.local.php`, backups locais, `.claude/settings.local.json`
  nem conteúdo de `secrets/`. Verificar o conteúdo staged antes do commit.
- **Não reescrever histórico** de branch compartilhado (sem force push).
- **Mensagem de commit** em português, com o trailer `Co-Authored-By` do agente.
- **Encerramento:** antes de encerrar a sessão, informar o estado de `git status`, o
  branch e o commit de cada entrega. Se algo ficou fora do git, dizer o motivo.
