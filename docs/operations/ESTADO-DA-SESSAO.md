# PHPStan — rodada 17 aculta_wiki — 2026-11-11

- **Objetivo:** zerar os erros PHPStan de produção em `aculta_wiki/src`.
- **Estado:** 42 arquivos de produção sem erros; Unit 94/455 PASS; baseline global 3451 e gate estrito verde.
- **Decisões e alternativas descartadas:** usar `DomainSiteManager`/`SiteKey::Wiki` já publicado; manter `getAccessPurpose()` do contrato `origin/dev`; filtro removido era redundante após `array_filter()`. Sem ignores e sem mudança de permissões.
- **Cuidados:** o autoload padrão do worktree pode resolver classes no checkout principal; medições foram feitas com autoload temporário priorizando as fontes do worktree. 112 erros em testes/.install permanecem baselineados.
- **Commits e arquivos:** rodada 17; Wiki, baseline, relatório, TODO/status, README e handoff. Commit `31686d586` publicado em `origin/dev`.
- **Verificações:** PHPStan Wiki src/global `[OK] No errors`; Unit 94/455, descoberta de suítes, lint PHP, YAML e índice status PASS. Gate Portal FAIL por P5.4 preexistente em `PortalController`; token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** 112 erros PHPStan em testes/.install; sem alteração nesta rodada. Validação Runtime da Wiki continua pendente.
- **Próximo passo:** retomar saneamento pelo próximo módulo após autorização para continuar.

# PHPStan — rodada 18 aculta_mailer — 2026-11-11

- **Objetivo:** zerar a dívida PHPStan de todo o módulo aculta_mailer.
- **Estado:** src, .install e testes medidos em zero erros; baseline sem entradas do módulo. SMTP usa a fábrica pública Symfony mantendo TLS configurado.
- **Decisões e alternativas descartadas:** narrowing de entidades concretas; helper valida FieldStorageDefinition; nenhum ignore PHPStan. Removido teste tautológico do TTL.
- **Cuidados:** credenciais SMTP continuam resolvidas pelo SecretProvider; porta 465 usa smtps e verify_peer é encaminhado pela Dsn. Nenhum envio externo.
- **Commits e arquivos:** base `0210e5c86`; código, testes e serviço de aculta_mailer, `phpstan-baseline.neon`, relatório de dívida, TODO/status e este handoff. Hash final consta no commit da rodada.
- **Verificações:** PHPStan módulo/global `[OK] No errors`; Unit 166/348 PASS; PHP lint 29 arquivos, YAML serviços e status index PASS; baseline global 3564. Unit global 3287/17594 com 4 falhas fora do escopo (Forum, Game, Index) e 3 deprecações. Token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** migração de consumidores `DomainPurposeManager` para `DomainSiteManager` ausente de `origin/dev`; browser/SMTP externo seguem como próximos passos do componente.
- **Próximo passo:** retomar rodada PHPStan 17 da aculta_wiki após a migração chegar a origin/dev.

# ADR-054/055 — homologação residual, galerias e correções — 2026-11-11

- **Objetivo:** corrigir inconsistências das ADRs, publicar a migração de `/galleries` e fechar as pendências de zoom, teclado e cache.
- **Estado:** `media_galleries:page_1` usa row `revista-album-card` e style `layout-grid`, marcada `UI_SUITE_NATIVE` no inventário. Homologação residual 70/70 em 320/768/1440 nos dez domínios (teclado com `:focus-visible`, zoom 200% sem overflow, cache observado). Teste autenticado pelo `auth-login.mjs` confirmou login de administrador e corrigiu H1 duplicado no dashboard do Studio (`studio-menu`). ADRs corrigidas: `ui_patterns_ckeditor5` fora do projeto, Fluent Emoji tem 66 SVGs (mínimo 48 no teste) e UI Styles Page está habilitado sem mapeamento de regiões.
- **Decisões e alternativas descartadas:** zoom emulado por `layout.css.devPixelsPerPx=2` (atalhos de teclado não chegam ao chrome via WebDriver); não duplicar o `auth-login.mjs` em Python; corrigir o gate de índice para ignorar `.claude/worktrees`.
- **Cuidados:** rotas públicas respondem `private/no-cache` e `UNCACHEABLE`, portanto cache-hit não é mensurável. O login autentica e grava a sessão, mas a requisição seguinte com o cookie emitido responde anônima; a causa não foi isolada e bloqueia perfis autenticados.
- **Commits e arquivos:** `28509465b`, `e8b7efc09`, `eea9b4129`, `0f8ae8f2d`, `2f0b198f2`, `1c6f98d0c`, `0210e5c86` publicados; runner de aceitação, manifest, ADRs, inventários, status, READMEs, gate de índice e SDC `studio-menu`.
- **Verificações:** acceptance 70/70; browser index 148 PASS; UI Patterns Views cutover 95 PASS; SDC 1801, UI Skins 134, UI Styles 40, boundary 7888 PASS; Playwright login PASS; py_compile e JSON OK.
- **Pendências e bloqueios:** aceite visual humano; sessão autenticada (configuração `SSESS`/`SESS`); cache-hit enquanto a política for private/uncacheable; RN-00–05 e demais consumidores do inventário.
- **Próximo passo:** corrigir a configuração de sessão do ambiente de desenvolvimento, concluir RN-05 com a sessão de renomeação e seguir ao próximo consumidor público.
