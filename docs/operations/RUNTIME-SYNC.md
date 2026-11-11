# Sincronizacao do Runtime de desenvolvimento - ACULTA
> **Evolucao planejada (2026-11-11):** ver [Runtime Sync v2 - plano de arquitetura e implementacao](RUNTIME-SYNC-PLANO-V2.md). Este documento descreve a primeira implementacao efetiva; o v2 ainda nao foi executado. **Falha conhecida:** quando o HEAD ja coincide com origin/dev, o script atual pode sair sem reconstruir caches. No checkout compartilhado, um bloqueio do script tambem nao impede que codigo de agentes ja esteja no DocumentRoot. Nao automatizar triggers antes de corrigir esses pontos e implementar os gates de concorrencia.


**Estado:** primeira implementacao conservadora, em `scripts/runtime-sync.sh`. Nao e deploy de producao nem substitui o `aculta_deployer`.

## Contratos verificados no projeto

- `AGENTS.md`: apos push para `origin/dev`, a rotina cotidiana so atualiza codigo e limpa cache. `updatedb`, `config:import`, `pm:uninstall`, `DROP` e exclusao de dados exigem autorizacao especifica.
- `docs/architecture/environments.md`: o servidor de desenvolvimento compartilha o checkout servido pelo Apache com os agentes, em Debian/PHP-FPM/MariaDB. Confirme host, branch e banco em cada rodada. O banco esperado e `aculta_runtime`.
- `config/sync`: fonte completa da configuracao desejada. Drift nao e resolvido por exportacao massiva ou importacao parcial automatica.
- `web/modules/custom/aculta_portal/modules/aculta_deployer/`: CLI standalone para builds, perfis, DOMAINS, robots e verificacoes; suas fronteiras proíbem dependencias diretas de Drupal, Drush e vendor. A rotina de checkout fica em `scripts/`, fora do modulo.
- `docs/operations/RUNTIME-UPDATE-WINDOW.md`: snapshots e restauracoes possuem riscos reais, inclusive privilegios de banco e existencia de dados sensiveis. Nunca presumir que o teste de restore passou.

## Operacoes e politica

| Acao | Comando | Gravacao | Autorizacao |
| --- | --- | --- | --- |
| Inventario | `bash scripts/runtime-sync.sh plan` | Nenhuma; consulta Git remoto | Livre |
| Codigo e cache | `bash scripts/runtime-sync.sh sync-code` | `git fetch`, fast-forward e `drush cr` | Segue regra de sincronizacao cotidiana apos push no desenvolvimento |
| Dependencias e esquema | Procedimento supervisionado abaixo | Composer, schema/config, possivelmente dados | Ordem explicita, snapshot verificado |
| Dominio/producao | `aculta_deployer` e runbooks de release | Build e possivel deploy | Decisao separada do responsavel |

O script **nunca** executa `composer install`, `composer update`, `updatedb`, `config:import`, `config:export`, `pm:uninstall`, `DROP`, `git reset`, `git stash` ou `git clean`. Mudanca no lockfile, config, settings, `.install`, `.post_update.php`, `.deploy.php` ou `.info.yml` bloqueia o fluxo simples e exige revisao. Esse classificador e deliberadamente conservador, mas nao substitui a revisao semantica do diff.

## Uso no checkout do servidor de desenvolvimento

A partir da raiz Git (nao de um worktree temporario), execute:

```bash
bash scripts/runtime-sync.sh plan
```

O `plan` usa `git ls-remote`, nao troca HEAD e nao escreve o checkout. Se o commit remoto nao existir no armazenamento Git local, o resumo do diff ficara indisponivel ate `git fetch` feito em uma janela autorizada.

Para a sincronizacao de **codigo e cache**, depois de conferir o `hostname -s`, o nome do banco real e uma URI de teste descrita na matriz de dominios, configure explicitamente:

```bash
export ACULTA_SYNC_ENV=test
export ACULTA_SYNC_EXPECTED_HOST="toca"
export ACULTA_SYNC_EXPECTED_DB="aculta_runtime"
export ACULTA_SYNC_URI="${DRUPAL_URI_TEST:?defina DRUPAL_URI_TEST com HTTPS de um purpose valido}"
bash scripts/runtime-sync.sh sync-code
```

A URI nao e gravada no codigo. O script verifica o banco com `drush status --field=db-name`, executa `updatedb:status` e `config:status` **somente leitura**, atualiza apenas por fast-forward e roda `drush cr`. Usa `flock` em `var/deployer/runtime-sync.lock` (diretorio ignorado no Git). O script para se houver branch diferente de `dev`, mudancas locais, remoto indisponivel, host/DB divergente, commit divergente, ou arquivos com indicio estrutural.

**Aviso operacional:** `git fetch` e `git merge --ff-only` atuam sobre o mesmo checkout usado por agentes. Uma arvore limpa no momento do preflight nao bloqueia outros processos que editam arquivos durante a operacao. Coordenar pausa de escritas simultaneas e um gate externo obrigatorio; `flock` so impede duas instancias desse mesmo script. O `drush cr` pode atualizar tabelas de cache, nunca deve ser descrito como operacao absolutamente sem escrita no banco.

## Procedimento supervisionado para mudancas estruturais

1. **Congelar a janela.** Pausar agentes, cron e workers afetados, preservar trabalho alheio e registrar SHA local/remoto, `git status`, host, URI e banco. Comparar `composer.lock`, `config/sync`, `.install`, post-updates e hooks de deploy com a versao anterior. Nao executar o script simples para contornar bloqueios.
2. **Preflight.** Executar Drush `status`, `updatedb:status` e `config:status` no Runtime certo. Revisar `core.extension`, exclusoes de entidades/campos, dependencias, credenciais (somente nomes/estado), dominios, migracoes e alteracoes potencialmente destrutivas. Conferir perfil de ambiente do `aculta_deployer` quando aplicavel.
3. **Backup integral.** Criar dump consistente de `aculta_runtime` e snapshot dos arquivos publicos/privados, settings e chaves indispensaveis, em armazenamento privado fora do Git e fora do document root. Usar permissoes restritas, checksum SHA-256 e politica de retencao. Testar restore em banco descartavel autorizado antes de qualquer `updatedb`/`cim`; nao reutilizar `aculta_simpletest`. O usuario do Runtime pode nao ter `CREATE DATABASE`: obter banco preprovisionado pelo operador, sem elevar privilegios improvisadamente.
4. **Aprovacao.** Exibir plano, diffs, backup/restore comprovados, impacto e rollback. A autorizacao deve nomear operacoes persistentes concretas. `git push` ou uma classificacao RC nao autoriza por si so modificacoes de esquema/configuracao.
5. **Aplicacao.** Na janela aprovada, verificar novamente o checkout, instalar dependencias travadas com `composer install` (nunca `composer update` no Runtime), aplicar `updatedb` e importacao completa de `config/sync` na ordem aprovada. Em releases compativeis, `drush deploy` pode encadear updates, config, cache e hooks de deploy; revisar exatamente quais hooks ira executar antes de usar. `pm:uninstall` e exclusoes ficam fora da rotina e exigem ordem independente.
6. **Validacao.** Executar `drush cr`, `status`, `updatedb:status`, `config:status` e smokes dos purposes afetados; validar cron/filas, rotas, navegador e logs. Usar gates por escopo de `docs/operations/GATES.md`. Nao marcar PASS sem rodar as verificacoes.
7. **Falha/rollback.** Nao executar SQL de reversao improvisado. Se o esquema/estado tiver mudado, fazer rollback coordenado de codigo/lockfile/config, banco e arquivos usando o snapshot completo e procedimento previamente testado. Preservar evidencias e manter manutencao quando nao houver recuperacao comprovada.

## Lacunas e proximas iteracoes

- Criar testes automatizados de isolamento (repo Git ficticio + Drush falso) para casos sem mudanca, codigo simples, worktree sujo, nao fast-forward, falha de Drush e arquivos estruturais.
- Registrar resultado sem segredos em JSON com SHA, instante, modo, gates e resultados; apenas depois conectar o relatorio ao painel via contrato neutro do Deployer.
- Integrar politica de classificacao de risco por manifests e tags dos modulos (nao apenas padroes de caminho), preservando a necessidade de aprovacao humana de DB/config.
- Confrontar e atualizar a documentacao antiga que ainda confunde `aculta` e `aculta-site`, exige PR ou propõe importacao parcial da configuracao para sincronizacao geral.

**Nao executado nesta entrega:** contato com servidor, `drush`, backup, banco, CI remoto, Composer ou sincronizacao do Runtime. A validade de uma aplicacao real depende de teste no servidor de desenvolvimento, com operadores e bloqueios externos de concorrencia.
