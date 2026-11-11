# ACULTA - Runtime Sync v2: plano de arquitetura e implementacao

**Data:** 2026-11-11  
**Status:** planejamento; nenhuma mudanca no Runtime autorizada por este documento.  
**Escopo:** somente desenvolvimento `*.aculta.toca.net.br`, branch `origin/dev`. Nao e deploy de producao.  
**Base:** `scripts/runtime-sync.sh`, `docs/operations/RUNTIME-SYNC.md`, `AGENTS.md`, `docs/architecture/environments.md`, `docs/architecture/multidomain.md` e contratos do `aculta_deployer`.

## 0. Diagnostico fundamentado no codigo existente

1. **Bug de cache em SHA coincidente:** hoje, se o agente ja fez commit/push no checkout servido e `HEAD == origin/dev`, `sync-code` retorna sem `drush cr`. Precisamos comparar com o **ultimo SHA validado/aplicado**, nao apenas com o remoto.
2. **Checkout compartilhado nao e barreira de deploy:** edicoes de agentes ja atingem o docroot do Apache antes de qualquer comando de sync. A negacao de `sync-code` nao retira o codigo da area servida. A separacao de worktree de trabalho e runtime e objetivo arquitetural obrigatorio, nao mera otimizacao.
3. **Lock cooperativo insuficiente:** `flock` bloqueia duas instancias do script, mas nao editores, `git commit`, `composer`, scripts concorrentes ou outros agentes.
4. **Preflight interpreta mal sucesso:** `updatedb:status` e `config:status` devem ser capturados, normalizados, classificados e confrontados com baseline. `exit 0` significa apenas comando executado, nao necessariamente ausencia de pendencias. Conferir tambem `deploy:hook-status`.
5. **Classificacao por nome e insuficiente:** caminhos indicativos nao garantem risco zero, em especial dependencias transitivas, hooks, configuracao removida/renomeada, artefatos compilados, instalacao de modulos, schemas de entidades, services e permissions.
6. **Plano limitado:** `git ls-remote` observa SHA mas, sem objetos, nem sempre permite diff. Nao afirmar inventario completo com diff indisponivel.
7. **Risco documental:** `docs/architecture/environments.md` e `docs/operations/AGENT-OPERATIONS.md` ainda mencionam remoto `aculta-site`. O repositorio solicitado e `coletivo420/aculta`. Resolver em auditoria de contratos e checar `git remote -v` local antes de alterar qualquer URL. Documentos historicos de restauracao apresentam excecoes de janela especifica, que nao se convertem em permissao permanente.
8. **Multi-purpose:** validar DB/URI em pelo menos um host real e executar smoke por hosts/purposes impactados conforme `docs/architecture/multidomain.md`; `--uri` nao deve ser host arbitrario.

## 1. Contratos e invariantes

- **Fonte de codigo:** `origin/dev` autenticado e allowlistado, SHA imutavel por operacao; nao aceitar refs arbitrarias de usuario. Verificar a identidade do remoto configurado, sem alterar automaticamente remotes.
- **Integridade:** sem `reset --hard`, `clean`, `stash`, `checkout -f`, force push, sobrescrita de arquivos alheios, escrita em settings ou segredos.
- **Fail-closed:** falha/indisponibilidade de host, URI, banco, Drush, Git, Composer, detecao de risco, coordenacao ou checks bloqueia a escrita. Em `plan`, registrar `INDETERMINATE` e nao apresentar `READY`.
- **Ambiente:** testar `hostname`, branch/ref esperada para o modo atual, symlinks canonicos, document root, banco `aculta_runtime`, URL HTTPS de desenvolvimento e site path. Nao imprimir credenciais, cookies, DSN ou valores de configuracao sensiveis.
- **Mudancas persistentes:** `drush cr` escreve caches no banco e requer lock e log; `updatedb`, `cim`, desinstalacoes, delecoes e migracoes nao pertencem ao modo cotidiano.
- **Declaracao de sucesso:** somente com SHA aplicado, Drush funcional, resultado de rebuild ou justificativa documentada de skip, validacao posterior, e registro atomico persistido.
- **Estado nao equivale a Git:** separar `remote_sha`, `checkout_sha`, `last_code_sync_sha`, `last_cache_success_sha`, `last_validated_sha`, `drift_baseline`, `pending_structural_sha` e ultimo erro; ausente/ilegivel = UNKNOWN, nunca SUCCESS.
- **Concorrencia:** negar afirmacoes de atomicidade enquanto checkout servido e checkout de edicao forem o mesmo. Gates do tipo snapshot/checksum/lock reduzem risco, nao criam isolamento.

## 2. Interface CLI prevista (compatibilidade incremental)

| Comando | Escrita | Contrato |
| --- | --- | --- |
| `plan` | Nenhuma | remoto, local, estado aplicado, divergencia e risco; desconhecido explicitamente |
| `plan --refresh` | Apenas metadados temporarios, fora do checkout servido | busca objetos remotos em area de staging descartavel; nao altera HEAD, index ou worktree |
| `doctor` | Nenhuma | confere ambiente, dependencias, host, DB, URI, locks e contratos |
| `status [--format=json]` | Nenhuma | le o ultimo relatorio, indica stale/unknown; nao equivale a novo exame |
| `sync-code` | Git FF quando aplicavel, rebuild de cache, relatorio | executa codigo e cache com gates e sem operacoes estruturais |
| `verify` | Logs/relatorios apenas (nao mutacoes Drupal explicitas) | confere SHA, bootstrap, pendencias e smokes nao destrutivos |
| `structural-plan` | Nenhuma no Runtime | gera checklist/ordem de implantacao, backup e autorizacoes concretas |
| `structural-apply` | Futuro; somente manual e aprovado | nao construir executor automatico ate prova de rollback, autorizacao e testes de cenarios excepcionais |

**Importante:** `plan` segue estritamente somente leitura. Se `--refresh` usar outra estrategia que `staging`, documentar as escritas exatas antes de expor a opcao.

**Correcao imediata obrigatoria:** `sync-code` deve reconstruir cache quando HEAD remoto e local forem iguais **e** o ultimo SHA com rebuild validado for anterior ou desconhecido, desde que o delta entre SHA aplicado e atual seja analisavel e seguro. Em estado desconhecido ou estrutural, bloquear e orientar `doctor`/`structural-plan`. Nao simplesmente executar `cr` em codigo com updates pendentes. Se nenhum arquivo com impacto de runtime mudou, permitir skip de cache somente com razao/evidencia.

## 3. Classificador de risco baseado em manifestos

Manter contrato versionado `scripts/runtime-sync/risk-rules.yml` (ou JSON com parser ja disponivel), com teste por versao e default **UNKNOWN => BLOCK**. Separar alteracoes cumulativas desde ultimo estado validado, e nao somente `HEAD..remote`.

| Nivel | Exemplos | Acao |
| --- | --- | --- |
| R0 - documental | `docs/**`, exemplos/testes que nao sao executados no runtime | registro; sem Drush se comprovadamente nao altera runtime |
| R1 - codigo/cache | PHP/Twig/CSS/JS/SDC do app sem alteracoes adicionais detectadas | validar e `drush cr`; smoke afetado |
| R2 - artefatos/dependencias | `composer.*`, patches, bibliotecas, lock de npm, assets compilados, comandos/entrypoints, instalacao de arquivos dependentes | bloquear `sync-code` e exigir plano do build/Composer |
| R3 - Drupal estrutural | `config/sync/**`, `*.install`, `*.post_update.php`, `*.deploy.php`, `*.info.yml`, schemas, extensoes, campos, recipes, servicos com requisito de setup | plano e avaliacao de updates/cim/deploy hooks; bloqueio da rotina simples |
| R4 - destrutivo/infra | remoções de entidades/dados, uninstall, SQL, secrets/settings, webserver, DNS, armazenamento e permissoes | revisao/ordem humana especifica, snapshot e rollback comprovados |
| RX - indeterminado | diff ausente/truncado, rename nao resolvido, estado baseline inexistente, classificador desatualizado | bloquear e investigar |

**Detalhes de implementacao:** usar `git diff --name-status -z` e `--find-renames`, registrar origem/destino de renomeacoes, suportar nomes com newline, arquivos excluidos, mudancas de modo e gitlinks; nao extrair caminhos com `read` separado por quebra de linha. Classificacao por arquivo e pacote; elevar risco em vez de rebaixar quando houver duvida. Para PHP `.module`, `.php`, `.services.yml`, `.routing.yml` e `.permissions.yml`, distinguir alteracao de codigo de migracao exigida; nao inferir automaticamente que toda alteracao requer DB. Criar detector conservador de hooks e de `core.extension`, nao apenas glob.

**Excecoes estruturais:** o runbook de frete documenta sequencia que faz `updatedb` com vendor antigo antes de remover o pacote. Logo `composer install -> updatedb` nao pode ser ordem universal. `drush deploy` e conveniente para casos compativeis, mas nao serve para migracoes com ordem especial/etapas humanas. Nenhum auto-`composer update` no servidor.

## 4. Operacao e coordenacao

**Etapa transitória - checkout compartilhado atual:**
- Fazer `doctor` e inventario de processos/edicoes simultaneas; padronizar lock **cooperativo** comum ao sincronizador e aos agentes/scripts que mexem com Git, cache e vendor. A ordem de locks deve ser unica e documentada para evitar deadlock.
- Debounce de pushes: agregar varias solicitacoes em um SHA alvo; idempotencia por SHA e conteudo/impacto, sem reexecutar rebuild desnecessariamente. Nao iniciar jobs por evento remoto sem fila e exclusao mutua.
- Sempre comparar `HEAD`, `index`, worktree, `origin/dev` e SHA do ultimo estado aplicado imediatamente antes e depois do passo de escrita. Alteracao concorrente => FAIL/QUARANTINED; sem rollback automatico.
- Nao permitir que o mesmo executor opere simultaneamente com Composer, `updatedb`, `cim`, cron/queue de janela ou migracoes estruturais. Fluxo de codigo isolado nao deve bloquear a recuperacao/analise.

**Estado alvo - isolamento de verdade:**
- Separar checkout de agentes (branch `dev`, commits/push) e checkout servido exclusivamente pelo sincronizador, usando outra worktree/ref destacada em SHA validado ou releases imutaveis. Git worktrees nao podem manter a mesma branch `dev` checada em dois lugares: usar HEAD detached na worktree runtime ou desenho de refs proprias, sem forcar branches.
- Arquivos persistentes, secrets, settings, public/private files, logs, vendor e symlinks exigem inventario especifico e politica de montagem/ownership antes de migrar. Sem copiar paths absolutos do servidor para codigo.
- Preparar migracao do DocumentRoot somente em janela aprovada e reversivel, testando todos os dominios. Ate la nao chamar a sincronizacao de deploy atomico.
- Nao expor na UI comandos de escrita Drupal ou CLI do Deployer. O painel pode ler status neutro/seguro via adaptador, respeitando os boundaries existentes.

## 5. Relatorios versionados e sem segredos

Estado operacional fora do Git e fora da area publica, por exemplo `var/runtime-sync/`; arquivos modo 0600, diretorio restrito, escrita em temporario + `rename` atomico, validacao de symlinks. Registro por execucao e indicador latest, com schema e migracao explicita.

Exemplo **ilustrativo**, nao evidência de execucao:
```json
{
  "schema_version": 2,
  "run_id": "2026-11-11T14:00:00Z-example",
  "mode": "sync-code",
  "outcome": "BLOCKED",
  "phase": "risk-classification",
  "remote_sha": "EXAMPLE",
  "checkout_sha": "EXAMPLE",
  "last_validated_sha": null,
  "risk": "RX",
  "gates": [{"id": "diff-available", "result": "FAIL"}],
  "writes": [],
  "secrets_redacted": true
}
```

Status possiveis: `PLANNED`, `READY`, `NOOP`, `BLOCKED`, `PARTIAL`, `FAILED`, `VALIDATED`, `INDETERMINATE`. Cada gate usa `PASS`, `FAIL`, `SKIP`, `DEFERRED` ou `N/A` com motivo. Nao persistir hostname completo, DSN, IP, credenciais, stdout integral de Drush nem dados pessoais. Registrar comandos/exit code e hashes, com saida resumida sanitizada.

Nao sobrescrever `var/deployer/status.json` (schema 1 e consumidor proprio do Deployer). Criar adaptador/leitor que traduzo o estado do runtime ao contrato neutro existente, somente se fronteiras aprovarem. Retencao rotativa e limite de tamanho configuraveis.

## 6. Mudancas estruturais supervisionadas

Precondicoes: diff semanticamente inspecionado; ordem de hooks, Composer e config estabelecida; `doctor` com banco/host correto; matriz de impacto por purpose; janela sem agentes concorrentes, cron e filas relevantes; backup consistente + checksum + ensaio de restauracao, incluindo arquivos persistentes e dependencias quando aplicavel; autorizacao nominal de comandos persistentes.

Ordens possiveis:
- Caso comum: codigo preparado em ambiente isolado, dependencias instaladas a partir do lock, updates DB, importacao completa de config, caches, deploy hooks e smoke, respeitando a versao do Drush.
- Caso excepcional: como frete/Commerce, migrar com vendor antigo e aplicar uninstall autorizado antes de `composer install` com remocao do pacote. A ordem vem de runbook especifico, nao de inferencia por glob.
- `drush deploy` apenas quando a sequencia padrao inteira e os hooks forem aprovados, sem etapas intercaladas de migracao/uninstall.

No erro, parar e registrar estado `PARTIAL` ou `FAILED`. Nunca considerar um rollback de Git suficiente depois de alteracao de banco/config/arquivos; restaurar um conjunto consistente somente com runbook e snapshot comprovado.

## 7. Testes e gates antes da adocao

Teste shell automatizado com repo bare remoto, checkout(s) temporarios e Drush fake instrumentado; incluir:
1. HEAD igual ao remoto + cache ainda pendente (correcao da falha atual);
2. HEAD igual ao remoto + cache validado (NOOP);
3. fast-forward seguro com arquivos de codigo e rebuild unico;
4. docs only sem rebuild quando permitido pela politica;
5. arquivo `config/sync`, `*.install`, post update, `*.deploy.php`, `composer.lock`, patch e rename -> BLOCK;
6. worktree/index sujos, untracked, branch/ref errada, remote trocado, heads divergentes -> BLOCK;
7. host, URI ou DB errados; Drush indisponivel ou saida malformada -> BLOCK;
8. drift existente e drift surgindo; updatedb pendente e deploy hooks pendentes -> reportar corretamente;
9. falha `cr` apos Git atualizado -> PARTIAL sem carimbar sucesso;
10. duas instancias simultaneas e agente escritor cooperativo -> serializar;
11. arquivo renomeado/newline, diff incompleto, symlink e interrupcao SIGINT/SIGTERM -> nao corromper estado;
12. estado persistente ausente ou invalido, schema antigo, erro de escrita atomica e recuperacao;
13. runbook especial de Composer/Commerce -> nao propor ordem padrao universal;
14. multi-purpose: smoke por host afetado, nenhum acesso a dominio de producao.

Gates minimos: `bash -n`, ShellCheck (se disponivel; `DEFERRED` se nao), suite isolada, validadores de escopo, `aculta_deployer` boundaries/tests se consumidor alterado. Runtime real e smoke HTTPS so apos janela com preflight e evidencias; testes fake nao equivalem a validacao real.

## 8. Microfases e criterios de aceite

| Fase | Entrega | Gate para fechar |
| --- | --- | --- |
| F0 | auditoria de realidade: refs remotas, document root, owners, processos, baseline de DB/config/deploy hooks e remotos em documentos | inventario com divergencias, sem alterar Runtime |
| F1 | corrigir caso SHA igual, estados persistentes, parser confiavel de status Drush, fail-closed e testes de regressao | testes deterministas PASS; nenhuma operacao estrutural |
| F2 | classificador versionado R0-R4/RX, diff robusto, `plan --refresh`, formato JSON e `doctor` | matriz de fixtures e traces; nenhuma classificacao desconhecida como R1 |
| F3 | lock cooperativo com agentes, fila/debounce de pushes, idempotencia por SHA e auditoria POSIX | testes de concorrencia; no checkout compartilhado, declarar risco residual |
| F4 | separar checkout servido do checkout de agentes; preparar assets/vendor e politica de dados persistentes | janela aprovada, plano de retorno e validacao multidominio; sem migracao automatica |
| F5 | `structural-plan`, runbooks por tipo e provas de backup/restore (sem executor autonomo) | aprovacao por operacao e validacao de ordem; nada destrutivo automatico |
| F6 | painel read-only por adaptador Deployer + indicadores de drift, SHA e historico | testes de boundary, ACL, schema e logs sem segredos |

**Ordem recomendada:** iniciar F0-F2; iniciar F3 antes de qualquer trigger automatico; F4 e prerequisito para afirmar isolamento, deploy controlado e sincronizacao segura sem observacao da edicao de agentes. F5 e F6 so apos contratos estaveis.

## 9. Decisoes operacionais pendentes

- A politica dos agentes permitira migrar o desenvolvimento para worktrees separados do DocumentRoot? Recomenda-se **sim**, em janela planejada.
- Quem e dono de disparar `sync-code`: agente apos push, coordenador da fila ou operador? Recomenda-se agente solicitar, coordenador executar; sem webhook direto inicialmente.
- Qual baseline autoritativo do Runtime sera semeado? Exigir inspecao presencial de SHA, banco/config/hooks e cache antes de declarar primeira sincronizacao validada.
- Como o painel vai ler o status sem quebrar o schema 1 e os boundaries do `aculta_deployer`? Preferir adaptador read-only, sem invocar CLI.
- O remoto registrado em documentos antigos e diferente do repositorio indicado nesta tarefa; confirmar `git remote -v` do servidor e normalizar docs de operacao sem supor que todos os casos historicos sejam erro.

## 10. Nao executado / nao autorizado aqui

Nenhum `composer`, Drush, update DB, `config:import`, alteracao em Apache, symlink, permissao, host, Runtime, backup, restauracao ou teste no servidor de desenvolvimento. Este documento estabelece plano e gates, nao comprovacao de execucao.

## 11. Integração às skills de agentes (2026-11-11)

- `.agents/skills/aculta-runtime-sync/SKILL.md` e `references/CLASSIFICACAO-E-TESTES.md`: fluxo pós-push, risco R0-R4/RX, bug de cache com HEAD coincidente, concorrência e provas de validação.
- `.agents/skills/aculta-runtime-migracoes/SKILL.md` e `references/ORDEM-E-RECUPERACAO.md`: migrações supervisionadas, restore, Composer, Drush, rollback e fronteira com Deployer.
- Aliases sob `.claude/skills/` e `.codex/skills/`. As skills de commit e gates direcionam para as novas. `docs/operations/TESTES-SKILLS-RUNTIME-SYNC.md` cobre RS-01 a RS-12.
- **Escopo:** publicação dessas skills não implementa F1-F6 do sincronizador, não corrige seu bug de cache nem atesta execução do Runtime. As fases de código, integração e homologação continuam pendentes.
