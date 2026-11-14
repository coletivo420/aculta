# PHPStan — rodada 18 aculta_mailer — 2026-11-11

- **Objetivo:** zerar a dívida PHPStan de todo o módulo aculta_mailer.
- **Estado:** src, .install e testes medidos em zero erros; baseline sem entradas do módulo. SMTP usa a fábrica pública Symfony mantendo TLS configurado.
- **Decisões e alternativas descartadas:** narrowing de entidades concretas; helper valida FieldStorageDefinition; nenhum ignore PHPStan. Removido teste tautológico do TTL.
- **Cuidados:** credenciais SMTP continuam resolvidas pelo SecretProvider; porta 465 usa smtps e verify_peer é encaminhado pela Dsn. Nenhum envio externo.
- **Commits e arquivos:** base `0210e5c86`; código, testes e serviço de aculta_mailer, `phpstan-baseline.neon`, relatório de dívida, TODO/status e este handoff. Hash final consta no commit da rodada.
- **Verificações:** PHPStan módulo/global `[OK] No errors`; Unit 166/348 PASS; PHP lint 29 arquivos, YAML serviços e status index PASS; baseline global 3564. Unit global 3287/17594 com 4 falhas fora do escopo (Forum, Game, Index) e 3 deprecações. Token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** migração de consumidores `DomainPurposeManager` para `DomainSiteManager` ausente de `origin/dev`; browser/SMTP externo seguem como próximos passos do componente.
- **Próximo passo:** retomar rodada PHPStan 17 da aculta_wiki após a migração chegar a origin/dev.
