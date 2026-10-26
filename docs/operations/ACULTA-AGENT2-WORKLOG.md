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

## Rodada de execução complementar (correções seguras)

| Item | Resultado | Commit |
|---|---|---|
| Kernel `aculta_qrcode` em SQLite isolado (`SIMPLETEST_DB=sqlite://...` no scratchpad, não no Runtime) | **11 PASS**, 84 asserções | — |
| Kernel `aculta_links`, `aculta_activity`, `aculta_editor` | Em execução na mesma configuração; resultado a registrar | — |
| Deprecações PHPUnit de links (9) e activity (4) | Origem: `@group`, `@covers` e `@coversDefaultClass` em docblock, deprecados no PHPUnit 11. Migrado para atributos. **Zero deprecações** após a correção | `5ae71f491` |
| Deployer: gravação do registro sobre estado antigo | **Defeito real**: perda de atualização e id duplicado sob concorrência. Corrigido com controle otimista sob `.lock`. 3 testes novos PASS | `9936b17ac` |
| Patcher: auditoria de cadastro, alteração e exclusão | Não existia. Adicionada trilha no canal `aculta_patcher` (hooks em `aculta_patcher.module`) e classe pura `PatchAuditMessage` com 4 testes. Formulário passa a usar `PatchDiffValidator` | `fdc3c7b11` |
| QR Code: prévia administrativa | Rota `/codigos-qr/previa`, validação de URL, SVG privado. 6 testes Unit. Colisão com `/codigos-qr/{id}` a confirmar em Kernel | `13cad334d` |
| Patcher: exclusão controlada | Já existia: `EntityDeleteForm` com permissão `administer aculta patches`. Confirmada na auditoria | — |
| Deployer: escrita atômica | Registro e relatórios usam temporário + `rename`. Build recusa saída existente não vazia e resolve symlinks do ancestral. Confirmado por leitura e testes existentes | — |

### Pendências ainda sem execução (e por quê)

- **Fronteira do deployer:** a causa é a descrição do menu do Portal, `aculta_portal.links.menu.yml`, que contém a
  frase "Estado do aculta_deployer…". Fora dos diretórios autorizados. Mudança exata para o agente principal:
  trocar a descrição por "Estado da implantação e do ambiente de deploy." Até lá, `boundaries` reprova.
- **Melhor Envio:** caminhos de API dependem de confirmação no sandbox (ME-PEND-01). Sem credenciais.
- **Patcher, exportador:** teste de integração exige pacote instalado pelo Composer (`InstalledVersions`). Sem isso,
  a parte de integridade do exportador fica sem teste automatizado.
- **QR Code, lote e formatter:** não implementados. O lote depende de decisão de formato de saída (zip ou
  múltiplos arquivos). O formatter depende de decisão de tipo de campo.
- **Kernel de sitemap e melhorenvio:** não existem.

## Kernel de links e de outros módulos (execução em SQLite isolado)

| Módulo | Resultado | Observação |
|---|---|---|
| `aculta_links` | 7 testes, 1 erro, 1 falha, 7 deprecações | Erro de rota (`aculta_qrcode.preview_controller`) veio de edição concorrente durante a execução: reexecutado com o código estável, o erro não reaparece |
| `aculta_links`, falha real | `testDashboardPublicationFilterQueriesOnlySelectedItems`: esperado 1 item, obtido 0 | **Aberta.** Diagnóstico: a consulta do painel (`ids=[]`) roda com o usuário 1, não com o dono do rascunho, mesmo após `setAccount()`. Construir o controller explicitamente não mudou nada. Outros testes da suíte que usam `setAccount()` passam. Causa não identificada; próximo passo: afirmar `current_user` logo após `setAccount()` e verificar a permissão `manage own aculta links` do papel `links_filter_owner` |
| `aculta_activity` | 3 testes PASS, 5 deprecações | Deprecações do Kernel não classificadas (não são metadados de docblock) |
| `aculta_editor` | 7 testes PASS, 3 deprecações | Idem |

## Rodada de fechamento: pendências executáveis

| Item | Resultado | Commit |
|---|---|---|
| Falha aberta `testDashboardPublicationFilterQueriesOnlySelectedItems` (links) | **Causa confirmada**: `condition('status', FALSE)` não casa com o valor `0` gravado no banco de teste. O dono, o usuário corrente, a permissão e o rascunho estavam corretos. Corrigido com inteiro explícito (`1`/`0`). Suíte Kernel de links: **7 PASS** | `7cc1c4b05` |
| Deprecações do Kernel de links (7) | Origem: módulo contrib `token` (`token.install`, `token_requirements` sem atributo `#[LegacyRequirementsHook]`). Não é código nosso | — |
| Deprecações do Kernel de activity (5) | Origem: contrib `domain` (assinatura de `Action::execute`) e uma anotação de plugin de contrib. Não é código nosso | — |
| Deprecações do Kernel de editor (3) | Origem: contrib `ckeditor5_plugin_pack` (`module_implements_alter`, `requirements` e `@EntityType`). Não é código nosso | — |
| Caminho do Melhor Envio para cotação | Comentário da comunidade no fórum oficial cita `{{url}}/api/v2/me/shipment/calculate` como rota atual e desaconselha `ecommerce/calculate`. Registrado como fonte secundária, a validar no sandbox. Autorização e token seguem sem confirmação | `9a7304b7f` |
| QR Code: lote | `GET .../codigos-qr/lote?ids=..&formato=..` gera ZIP com um arquivo por código; até 50 códigos; id inexistente derruba o lote inteiro (404). Validação em `QrCodeBatchPlanner` | `8b665bd53` |
| QR Code: formatter de campo | Plugin `aculta_qr_svg` para `entity_reference`: SVG como `data:` URI em `<img>`. Falha de geração omite o item | `8b665bd53` |
| QR Code: Kernel | **14 PASS**. O lote é testado no caminho de falha segura (sem URL pública, 503). O caminho de sucesso depende do `aculta_portal` (domínio do purpose `links`) e fica para integração | (teste no próximo commit) |
| Deployer: fronteira | Continua reprovando. A causa é o texto do menu do Portal (`aculta_portal.links.menu.yml`), arquivo fora da lista autorizada. Mudança exata para o agente principal: trocar a descrição por "Estado da implantação e do ambiente de deploy." | — |

### Pendências que continuam dependendo de decisão ou de ambiente

- **Melhor Envio:** validar `authorize`, `oauth/token` e `expires_in` no sandbox com credenciais autorizadas.
- **QR Code, sucesso do lote e do formatter:** teste de integração com `aculta_portal` habilitado (domínio `links`).
- **Patcher, exportador:** teste com pacote instalado pelo Composer.

## Rodada de integração (patcher e QR Code)

| Item | Resultado | Commit |
|---|---|---|
| Patcher: teste de integração do exportador | Resolvedor de pacote injetável (padrão: Composer). Teste com projeto temporário, `composer.lock`, pacote instalado e `git apply` real. **26 PASS** no módulo | `e8002f61a` |
| QR Code: caminho de sucesso do lote (Kernel) | Interface `QrCodePublicUrlInterface` extraída do resolvedor. Kernel injeta URL fixa pelo container. **15 PASS** na suíte | `5b6b1acdf` |
| QR Code: formatter `aculta_qr_svg` (Kernel) | Campo `entity_reference` real, renderização como `<img>` com `data:`. **1 PASS**, sem avisos | `d386e55ee` |

### Bloqueios que continuam (fora do escopo autorizado ou dependentes de ambiente)

- **Fronteira do deployer:** causada pelo menu do Portal (`aculta_portal.links.menu.yml`). Não há correção dentro do submódulo sem enfraquecer a regra de fronteira, o que não faço.
- **Melhor Envio, autorização e token:** só o sandbox com credenciais autorizadas confirma `authorize`, `oauth/token` e `expires_in`.

## Auditorias profundas: escrita, concorrência e cadastro

| Frente | Auditoria | Resultado | Commit |
|---|---|---|---|
| Deployer, escrita | Relatórios do build (`deploy-policy`, `deploy-report`, `transition-plan`) eram gravados direto. Agora passam por `writeAtomic` (temporário e `rename`). Teste confirma JSON válido e nenhum temporário sobrando | Corrigido | `f64c584d1` |
| Deployer, concorrência | Registro sem controle de versão: duas gravações concorrentes perdiam atualizações. Controle otimista sob `.lock`, com 3 testes | Corrigido (já no commit `9936b17ac`) | `9936b17ac` |
| Deployer, temporários | Gravação de `environment.json` e `status.json` já atômica; build recusa diretório de saída não vazio | Verificado | — |
| Patcher, cadastro | Regras de pacote, versão exata e diff extraídas para `PatchFormRules` (classe pura) e testadas: 8 casos inválidos e 1 válido. Faixas de versão são recusadas | Corrigido e testado | `2736df9fc` |
| Patcher, segurança administrativa | Permissão `administer aculta patches` com `restrict access`; exclusão por `EntityDeleteForm` com confirmação; diff nunca é executado; auditoria de criação, alteração e exclusão no canal `aculta_patcher` | Verificado | `fdc3c7b11` |

### Bloqueios externos (não resolvíveis dentro do escopo)

- **Fronteira do deployer:** a descrição do menu do Portal cita a ferramenta. Arquivo do Portal, fora da lista autorizada. Mudança exata no worklog para o agente principal.
- **Melhor Envio, autorização e token:** dependem do sandbox e de credenciais autorizadas.

## Kernel de sitemap e Melhor Envio: tentativa e bloqueio

- Tentado: Kernel de cabeamento (plugin `aculta_purpose_node`, tipo `aculta_purpose`, cliente OAuth2 com credenciais
  do Drupal Key) habilitando `aculta_portal`.
- Bloqueio: `aculta_portal` declara dependências de um site inteiro (commerce, lms, group, social_auth, domain_config,
  aculta_image, aculta_mailer, entre outros), e cada uma expõe serviços obrigatórios de outra. A rodada reexpôs uma dependência
  por vez (`file.usage`, `aculta_image.file_remover`). Um Kernel com `aculta_portal` exige uma instalação de teste completa.
- Ação: os dois arquivos de teste foram removidos, para não deixar testes quebrados no repositório.
- Ambiente: o contrib de `web/modules/contrib` do worktree foi exposto por links individuais, e `domain_config` veio de
  `vendor/drupal/domain/domain_config`. Isso é só do worktree, e não entra no git.

## Auditoria profunda das frentes 6 (links) e 7 (activity)

| Ponto da missão | Verificação | Resultado |
|---|---|---|
| Acesso público (anônimo) | Rotas `/pagina/{slug}`, `/redirecionar/{codigo}` e QR; `/pagina/{slug}/previa` e `/codigo-qr.svg` sem `_access` na rota, com checagem de entidade no controller | Preview exige `update`, QR exige `view`; demais respondem 404. Sem vazamento. Ressalva: a rota de prévia não declara `_access`, então depende do controller |
| Slugs | `UniqueField` no campo; regex `[a-z0-9]+(-[a-z0-9]+)*` na rota | Verificado |
| Publicação | Consultas públicas filtram `status = 1` e destino publicado | Verificado. Padronizado para inteiro, Kernel 7 PASS |
| Cache | Página pública com tags, contextos `user` e `url.site`; prévia com `max-age = 0` | Verificado |
| Paginação | Painel com `pager(50)` | Verificado |
| Privacidade | Nenhum IP, user-agent ou referer em `src`; estatística via `aculta_statistics` agregada | Verificado |
| URLs canônicas e purpose | `canonicalPathUrl('links', ...)` no QR e nas rotas com `_aculta_domain_purpose: links` | Verificado por leitura |
| Mobile-first e renderização visual | Requer site em execução | **Não executado** (sem runtime) |
| Frente 7, metatag | JSON-LD expõe só campos públicos do evento (local, modalidade, URL, editoriais). Nenhum campo de contato privado | Verificado |
| Frente 7, Index | Provider projeta título, uuid, idioma, domínio, status, datas e dono; sem corpo | Verificado |
| Frente 7, Kernel | 3 PASS (activity) | Já executado |

## Kernel de sitemap e Melhor Envio: resolvido por lista de módulos completa

| Módulo | Resultado | Commit |
|---|---|---|
| `aculta_portal_sitemap` | Kernel de cabeamento: plugin `aculta_purpose_node` descoberto e entidade `simple_sitemap_type` registrada. **2 PASS** | `bee2ed803` |
| `aculta_commerce_melhorenvio` | Kernel do cliente OAuth2 no container real. **3 PASS**, sem rede | (commit seguinte) |

**Defeito real encontrado no caminho:** `aculta_commerce_melhorenvio.shipment_address_binder` usava autowire por classe
para o mapper, sem alias pela classe. O container não compilava com o módulo habilitado. Corrigido com argumentos
explícitos (`bee2ed803`).

**Como foi resolvido:** a lista de módulos foi gerada pelo fechamento transitivo dos `info.yml` (67 módulos, incluindo o core
necessário), mais os serviços que o `aculta_portal` usa sem declarar em `dependencies` (`aculta_user`, `config`, `system`,
`file`). As deprecações (50 e 66) vêm de contrib e core e não foram classificadas uma a uma.

**Ressalva:** os testes de sitemap que geram URLs de conteúdo continuam sem cobertura, porque exigem nós e domínios
cadastrados.

## Auditorias restantes (frentes 5 e 8) e mobile-first de links

| Item | Verificação | Resultado |
|---|---|---|
| Frente 8, segurança de HTML | Formatos exportados (`config/sync/filter.format.*.yml`): nenhum `allowed_html` libera `script`, `iframe`, `object`, `embed`, `style` ou `on*`. Nenhum papel comum recebe `full_html` | Verificado. Teste de política: 16 PASS (`ce0a6c310`) |
| Frente 8, achado | `usuario_aculta` libera `img src` para usuários com e-mail confirmado. O Drupal recusa `data:` em `src` por padrão | Aceito. Registrado |
| Frente 5, contraste | `QrCodePreset` recusa combinação abaixo de 4,5:1 (razão WCAG para texto normal) | Verificado, com teste em `QrCodePresetTest` |
| Frente 6, mobile-first | Templates de links e CSS responsivo | **Não verificado visualmente**: exige site em execução |
| Frente 5, revisão visual de prévia e lote | Idem | **Não verificado visualmente**: exige site em execução |

## Fronteira do deployer fechada (autorização explícita do responsável)

- Edição autorizada: `aculta_portal.links.menu.yml`, descrição do item `aculta_portal.deployer_status`. O texto deixou de citar a ferramenta: "Estado da implantação e do ambiente de deploy."
- Resultado: `aculta-deployer boundaries` PASS; `tests/run.php` do deployer PASS (todos os testes).
- Nenhuma mudança de comportamento: é só o texto exibido no menu.

## Pendências após as decisões do responsável

- Melhor Envio: sem credenciais de sandbox nesta rodada. Caminhos `authorize`, `oauth/token` e `expires_in` seguem em ME-PEND-01.
- Revisão visual (prévia e lote do QR Code; mobile-first de links): decisão do responsável de executar no ambiente de testes do projeto. Checklist a seguir.

### Checklist para a revisão visual no ambiente de testes

1. Prévia: `/painel-administrativo/aculta/codigos-qr/previa?url=https://exemplo.org` retorna SVG com cabeçalhos `no-store` e CSP `sandbox`; URL `javascript:` retorna 422.
2. Lote: `/painel-administrativo/aculta/codigos-qr/lote?ids=<id1>,<id2>&formato=svg` baixa um ZIP com `qr-<codigo>.svg` por código; id inexistente retorna 404 e nada é entregue.
3. Formatter `aculta_qr_svg` em um campo de referência: a imagem aparece como `<img>` com `data:` e sem `<svg>` inline.
4. Links em viewport de celular (360 px): páginas `/pagina/{slug}` sem rolagem horizontal, botões com área de toque adequada, prévia `/pagina/{slug}/previa` acessível só pelo dono.
