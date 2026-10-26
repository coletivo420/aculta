# ACULTA — Worklog do agente executor 2

Branch: `feat/aculta-frentes-independentes`, criada a partir de `origin/chore/aculta-m0-integridade` (HEAD `b574a182b`).
Worktree: `/tmp/aculta-frentes-independentes`. Nenhum commit foi feito no worktree principal nem na PR #298.

## Frente 1 — `aculta_commerce_melhorenvio` (ME-0.1-C, parte de código)

| Campo | Registro |
|---|---|
| Módulo | `aculta_commerce_melhorenvio` |
| Fase | ME-0.1-C: cliente OAuth2 (código e Unit). Cotação (ME-0.2-A) ainda não iniciada |
| HEAD | `docs` da frente, ver SHAs abaixo |
| Implementado | Cliente `Http\MelhorEnvioClient`; configuração `MelhorEnvioConfig`; `StateMelhorEnvioTokenStore` (tokens por ambiente); `MelhorEnvioException` (categorias); `MelhorEnvioEnvironment` e `MelhorEnvioEndpoints`. Serviços, schema e settings atualizados. Dependência `key:key` |
| Testes | Unit do módulo: **48 PASS** (29 existentes + 19 novos do cliente), 125 asserções. Kernel e Functional: não executados |
| PHPCS | Arquivos novos: 0 erros (avisos de tamanho de linha). Os arquivos antigos de `src/Address` já tinham erros antes desta frente e não foram alterados |
| Bloqueadores | ME-PEND-01: caminhos `/oauth/authorize`, `/oauth/token` e `/api/v2/me/shipment/calculate` não aparecem nas páginas públicas consultadas (docs.melhorenvio.com.br). Ficam isolados em `MelhorEnvioEndpoints`, marcados como não confirmados. Sem credenciais: nenhuma chamada real foi feita |
| Integração central | (1) Chave `key` do Drupal Key para client_id e client_secret por ambiente, provisionada conforme a política (produção no banco criptografado). (2) Rota de callback OAuth2 para validar `state` e chamar `exchangeAuthorizationCode()`. (3) Campos de `settings` (`redirect_uri`, `contact_email`, `scopes`) precisam de valores reais do cadastro do app. (4) Decisão sobre criptografia dos tokens (ME-PEND-02) |
| Próxima tarefa | Validar ME-PEND-01 no sandbox com credenciais autorizadas; depois ME-0.2-A (plugin Commerce Shipping e cotação com fixtures) |
| Estado | **Dev.** Não candidata a RC: falta validação no sandbox e Kernel |

### Commits (prontos para cherry-pick, em ordem)

| SHA | Mensagem |
|---|---|
| `f812ff495` | feat(melhorenvio): implement OAuth2 HTTP client |
| `71f85bb0a` | test(melhorenvio): cover OAuth2 client without network |
| (este documento e a docs do módulo) | docs(melhorenvio): atualizar README, roadmap e CHANGELOG |

## Frente 2 — `aculta_deployer`

| Campo | Registro |
|---|---|
| Fase | Auditoria e correção de configuração. Hardening de escrita atômica e concorrência ainda não auditado |
| HEAD | `3f4222b2a` |
| Implementado | `deploy.json`: removida entrada órfã de `environment_bound`; regra de identidade para `aculta_mailer.brand` nos perfis `production` e `test`. CHANGELOG com entrada "Não lançado" |
| Testes | `tests/run.php`: 108 PASS antes, 3 de 4 falhas resolvidas. **1 FAIL restante**: "fronteiras atuais passam" |
| Builds (ensaio, fora do repositório) | `build --target=test`: rc 0, 1436 arquivos, `system.mail` com `aculta_mailer`. `build --target=production --allow-open-blocking`: 1420 arquivos, 16 removidos, nenhum host de teste restante |
| Check | Reprova por fronteira (abaixo) e pela entrada DEP-0003 bloqueante no registro |
| Bloqueadores | (1) `aculta_portal/aculta_portal.links.menu.yml` referencia `aculta_deployer`. A regra de fronteira proíbe e o arquivo é do Portal, fora do escopo desta frente. Afrouxar a regra seria esconder a falha. (2) DEP-0003 bloqueante: decisão de deploy do responsável. Build de produção fica recusado até lá, como previsto |
| Integração central | Remover ou isolar a referência ao deployer no menu do Portal (`aculta_portal.deployer_status`), ou aceitar a exceção de fronteira em `config/boundary.json` por decisão do responsável |
| Não feito | Testes de escrita atômica, concorrência, symlink inseguro e preservação de binários: não auditados nesta execução |
| Estado | Dev. Não candidata a RC |

Commit: `3f4222b2a` fix(deployer): align deploy profiles with environment-bound files.

## Frentes 3 a 8 — não iniciadas nesta execução

| Frente | Módulo | Estado |
|---|---|---|
| 3 | `aculta_patcher` | Pendente
| 4 | `aculta_portal_sitemap` | Pendente |
| 5 | `aculta_qrcode` | Pendente |
| 6 | `aculta_links` | Pendente |
| 7 | `aculta_activity` | Pendente |
| 8 | `aculta_editor` | Pendente |

A revisão transversal (seção 12 da missão) depende das oito frentes e não foi feita.

## Frente 3 — `aculta_patcher`

| Campo | Registro |
|---|---|
| Fase | Validação de diff extraída e testada. Exportador ainda não coberto por teste de integração |
| HEAD | `04221b6e3` |
| Implementado | `PatchDiffValidator` (classe pura): limite de 256 KiB, cabeçalhos `---`/`+++`, hunk `@@`, caminhos absolutos, letra de unidade e `..`. `PatchManifestExporter` passa a usá-la, com as mesmas mensagens |
| Testes | Unit: **15 PASS**, 27 asserções (`PatchDiffValidatorTest`) |
| Bloqueadores | Nenhum técnico. Pendente: testes do exportador com `composer.lock` e pacote temporário (exige refatorar a resolução de `Composer\InstalledVersions`) |
| Observação | O exportador aceita diff que já está aplicado (validação reversa). É intencional: vendor pode estar com o patch após `composer install` |
| Não feito | Cadastro, edição, exclusão controlada, auditoria e segurança administrativa não auditados nesta execução |
| Commits | `0427ffb34` refactor(patcher): extrair validação de diff; `04221b6e3` test(patcher): cobrir validação |
| Estado | Dev |

## Levantamento das frentes 4 a 8 (sem alterações)

| Frente | Módulo | Arquivos src | Testes Unit | Resultado Unit |
|---|---|---|---|---|
| 4 | `aculta_portal_sitemap` | 1 | nenhum diretório | Sem suíte. Auditoria pendente |
| 5 | `aculta_qrcode` | 17 | 4 arquivos (incl. Kernel 1) | **27 PASS**, 44 asserções |
| 6 | `aculta_links` | 35 | 6 arquivos (incl. Kernel 1) | **8 PASS**, 65 asserções, **9 deprecações** não detalhadas |
| 7 | `aculta_activity` | 3 | 4 arquivos (incl. Kernel 1) | **6 PASS**, 19 asserções, **4 deprecações** não detalhadas |
| 8 | `aculta_editor` | 0 | nenhum Unit (1 Kernel) | Sem suíte Unit. Kernel não executado (exige banco) |

Pendências desse levantamento: identificar a origem das deprecações de `aculta_links` e `aculta_activity`; criar a
suíte Unit do sitemap; Kernel e Functional de todos (exigem instalação de teste isolada, não executadas).

Não iniciadas as auditorias profundas das frentes 4 a 8 nesta execução.

## Notas de ambiente

- Os testes Unit rodam com `vendor/` e `web/core/` do checkout principal. O worktree recebe cópia real de `web/core`
  (ignorada pelo git). O `vendor` é symlink (também ignorado). Sem a cópia real do core, o bootstrap do Drupal carrega as
  classes do módulo a partir do checkout principal e os testes não cobrem o código do worktree.
- Comando: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_commerce/modules/aculta_commerce_melhorenvio/tests/src/Unit`

## Frentes 4 a 8 — resultado desta execução

| Frente | Módulo | Feito | Commits | Pendente |
|---|---|---|---|---|
| 4 | `aculta_portal_sitemap` | Política de indexação testada (5 Unit). README corrigido: versão 0.1.2 e `core.extension` com módulo desabilitado | `6a9f918f6` | Teste de integração por host (Kernel). Checagem `isPurposeFrontNode` usa alias do host atual, risco não testado |
| 5 | `aculta_qrcode` | Auditoria: sem SSRF (nenhuma busca remota), destino validado no manager e no constraint, derivado nomeado por hash. Contratos de integração documentados | `5bef7c9e3` | Logo (`aculta_image`), métricas (`aculta_statistics.recorder`, métrica `qrcode_redirect` a cadastrar), prévia, lote e formatter |
| 6 | `aculta_links` | Conformidade verificada: cliques via `aculta_statistics` (métrica `links.link.clicks` existe no catálogo), tabela própria removida, QR via `aculta_qrcode` (adaptador fino, não gerador duplicado) | nenhum (sem alteração) | **9 deprecações PHPUnit** não resolvidas. Suspeita: mocks de `__get` em `LinkDestinationResolverTest` e `LinkAccessControlHandlerTest`, não confirmada. `update_10008` apaga `aculta_links_stat_daily`: confirmar que produção não tinha dados antes de qualquer deploy |
| 7 | `aculta_activity` | Projeção do Content Index mínima (título, uuid, idioma, domínio, status, datas, dono); sem corpo nem campos sensíveis. Dependência do Index é opcional (tag) | nenhum | 4 deprecações PHPUnit. 16 erros PHPCS em `src`/`tests` |
| 8 | `aculta_editor` | Matriz de formatos por papel conferida: nenhum papel comum com `full_html` ou formatos administrativos. README alinhado à versão 0.1.0-dev.5 | README commit | Kernel não executado. Sem suíte Unit |

## Revisão transversal (seção 12 da missão)

| Verificação | Resultado |
|---|---|
| 1. Dependências entre os oito | Única aresta: `aculta_links → aculta_qrcode`, declarada no `info.yml` |
| 2. APIs duplicadas | Nenhuma. Links usa `aculta_qrcode` e `aculta_statistics` como fachadas |
| 3. Serviços opcionais | `aculta_qrcode` usa `@?aculta_image.generated_file` e `@?plugin.manager.entity_print.print_engine`. Activity registra provider por tag |
| 4. Configurações de instalação | Melhor Envio: `settings` e schema coerentes, chaves só por nome. Sitemap: `core.extension` desabilitado, deploy pendente |
| 5. Ciclos de dependência | Nenhum entre os oito |
| 6. PHPCS (erros em `src` e `tests`) | melhorenvio 80 (o código novo tem 0; o resto é `src/Address` pré-existente), deployer 563 (CLI sem convenções Drupal), patcher 28, sitemap 20, activity 16, qrcode 0, links 0, editor 0. **Não corrigido**: dívida anterior às frentes, exige frente própria antes de RC |
| 7. Drupal 11+ | Todos declaram `core_version_requirement: ^11.3` |
| 8. PHPUnit (Unit) | melhorenvio 48 PASS, patcher 15 PASS, sitemap 5 PASS, qrcode 27 PASS, links 8 PASS (9 deprecações), activity 6 PASS (4 deprecações), deployer e editor sem suíte Unit |
| 9. Segurança e Entity Access | Links: testes de controle de acesso (`LinkAccessControlHandlerTest`). QR: validação de URL com testes. Melhor Envio: tokens fora de configuração. Deployer: fronteiras reprovam (ver abaixo) |
| 10. Documentação | README e CHANGELOG atualizados em melhorenvio, deployer, patcher (worklog), sitemap, qrcode (contratos), editor. Links e activity sem alteração documental nesta execução |
| 11. Integrações que dependem do agente principal | Ver lista abaixo |
| 12. Pendências antes de RC | Ver lista abaixo |

### Integrações que dependem do agente principal

1. Fronteira do deployer: `aculta_portal.links.menu.yml` referencia `aculta_deployer` (arquivo do Portal).
2. Métrica `qrcode_redirect` no catálogo do `aculta_statistics`.
3. Chaves do Drupal Key e rota de callback do Melhor Envio.
4. Decisão sobre a entrada DEP-0003 bloqueante no registro do deployer.

### Pendências antes de RC (não candidatas a RC nesta execução)

- Melhor Envio: validar caminhos da API no sandbox (ME-PEND-01) e criptografia dos tokens (ME-PEND-02).
- Testes Kernel e Functional de todos os módulos: exigem instalação de teste isolada, não executados.
- Frentes 2 e 3: auditoria de escrita atômica, concorrência e symlinks (deployer) e de cadastro, exclusão e auditoria (patcher).
- Dívida PHPCS de `aculta_deployer` e `src/Address` do Melhor Envio.
- Deprecações PHPUnit de `aculta_links` e `aculta_activity`.
