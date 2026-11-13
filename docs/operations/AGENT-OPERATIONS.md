# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Ambientes

- `*.aculta.toca.net.br` é o servidor de desenvolvimento (Homelab), único ambiente em uso. Não há host de produção ainda. Os hosts por purpose estão em `docs/architecture/multidomain.md`.
- Para Drush e operações do Runtime, siga `docs/architecture/environments.md`; não presuma um caminho de checkout, necessidade de SSH ou URI padrão.
- **Antes de qualquer operação de ambiente**, confirme `hostname`, `pwd`, branch/estado Git e `php vendor/drush/drush/drush.php status`. Confirme no Drush o site URI e o banco conectados antes de executar comandos que alterem estado.
- Se `hostname` for `toca` e o Drush confirmar o Runtime `aculta_runtime`, trate a sessão como já estando no servidor de desenvolvimento: use o checkout e o Drush locais. A saída `Site URI: http://default` sem `--uri` é esperada neste ambiente e não significa produção nem erro de conexão. Para operações sensíveis ao purpose, passe `--uri` com um host de teste documentado. SSH só se justifica quando a operação necessária não estiver disponível localmente e o destino tiver sido confirmado pela documentação de ambientes.
- Se um problema pontual e verificável bloquear diretamente uma entrega autorizada, agentes podem corrigi-lo durante o desenvolvimento sem interromper a tarefa. Inspecione primeiro o impacto e a reversão, limite a correção ao bloqueador, preserve o estado recuperável quando necessário e registre a causa e o resultado no changelog/documentação pertinente. Não use essa regra para ampliar escopo, remover conteúdo ou fazer alterações destrutivas.
- Não inventar ambientes, hosts ou nomes de servidor. Use somente os definidos em `docs/architecture/environments.md` e `docs/architecture/multidomain.md`.
- Testes em `127.0.0.1` com resolução de host própria simulam o servidor de desenvolvimento, mas não são o servidor de desenvolvimento; registre-os como tal.
- Para testes autenticados no runtime `test`, confirme `captcha.settings:enable_globally=0` antes da rodada. Esse estado é o bypass operacional de `skip CAPTCHA` exclusivo do ambiente de testes; não desative CAPTCHA em produção.
- Para Runtime Sync v2, a fonte versionada é `coletivo420/aculta`, branch `dev`. Confirme sempre o remote real com `git remote -v` antes de agir; não o reconfigure automaticamente. Regras: `AGENTS.md` e skills `aculta-runtime-sync`/`aculta-runtime-migracoes`.
- Sincronização do Runtime: `AGENTS.md`, `docs/operations/RUNTIME-SYNC.md`, plano v2 e skills `aculta-runtime-sync`/`aculta-runtime-migracoes`. Não executar updatedb/config:import por haver pendências; requerem autorização explícita.

## Servidor web

Homelab usa Apache; a produção Hostinger Cloud Startup usa LiteSpeed. Detalhes e validações por ambiente estão em `docs/architecture/environments.md` e ADR-010.

- Preservar o `web/.htaccess` fornecido pelo Drupal e validar as diretivas em ambos os ambientes.
- Diretivas próprias do ACULTA no `web/.htaccess` (MIME de `.mjs` do PDF.js) têm gate: `php scripts/validate-htaccess-directives.php`. Remover o bloco reprova o gate.
- No Homelab, validar `mod_rewrite`, `mod_headers` e a integração PHP-FPM por `proxy_fcgi`.
- Na produção, validar reescrita, headers, proteção de arquivos, processamento de `.htaccess` e handler PHP no LiteSpeed/Hostinger; não presumir acesso a VirtualHost ou `httpd.conf`.
- VirtualHosts, caminhos, usuários, certificados e configuração Virtualmin/Hostinger continuam específicos de cada ambiente.

## Drush

No Homelab Debian, usar o Drush instalado pelo Composer:

```sh
php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php cr
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
- Estado atual: desenvolvimento. RC, Alpha e Beta são etapas futuras, sem data; não há deploy nem migração em andamento. Merge em `main` não é deploy.
- Ambientes: o servidor de desenvolvimento é `*.aculta.toca.net.br` (com `.br`). Não existe produção ainda. `127.0.0.1` simula o servidor de desenvolvimento, mas não é ele. Não invente hosts.

## Regra de encerramento de fase

Entregas de codigo sao publicadas em `origin/dev` apos revisao e gates do escopo. Runtime Sync e condicional ao impacto no codigo servido, acesso seguro e preflight; mudanca documental nao exige rebuild. Revisao R0-R3: `docs/operations/AGENT-REVIEW-POLICY.md`. PR so por pedido explicito. Quando solicitado, o PR deve:

- representar uma unidade lógica clara;
- listar entregas e limites;
- classificar o estado de Runtime conforme o ciclo Alpha e registrar `DEFERRED`
  quando ainda não implantado/validado no servidor de desenvolvimento;
- não misturar trabalho de outra fase;
- apontar a próxima fase;
- permanecer rastreável mesmo quando for integrado imediatamente.

Não declarar Alpha validado sem evidência do servidor de desenvolvimento.

## Versionamento de mudanças

Antes de alterar código do Portal ou do tema, siga `web/themes/custom/aculta420_v2/docs/versioning.md` e `docs/operations/RELEASES.md`. Portal e tema têm versões independentes; mantenha manifest e CHANGELOG correspondentes coerentes e não marque estado ainda não validado. Mudanças apenas documentais não avançam versão. Não criar tags sem pedido explícito do responsável.
