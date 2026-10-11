# Releases e versionamento

Data da revisão: 2026-10-07.

## Versionamento

O Portal e o tema possuem versionamentos independentes.

Portal usa tag namespaced:

```text
portal-v0.x.y
portal-v1.0.0
```

Não usar tag genérica `vX.Y.Z` para o Portal.

O tema ACULTA420 usa `aculta420-theme-vX.Y.Z`; regras específicas estão em
[`web/themes/custom/aculta420/docs/versioning.md`](../../web/themes/custom/aculta420/docs/versioning.md).

- MAJOR: quebra deliberada de contrato;
- MINOR: capacidade funcional completa;
- PATCH: correção compatível validada.

Branches devem ser curtas e nascer da `main` atual.

## Subversões do tema

O tema marca a versão dentro do código (`aculta420.info.yml`, `CHANGELOG.md` e roadmap). Subversões
`-dev.N` são marcadas por mudança principal validada. O procedimento completo está em
[`web/themes/custom/aculta420/docs/versioning.md`](../../web/themes/custom/aculta420/docs/versioning.md).

Mudanças do `aculta_portal` entram no `CHANGELOG.md` do módulo, citando a versão do tema
em que foram entregues. A versão do Portal é marcada em `aculta_portal.info.yml`.

## Gate de release

Antes de tag:

- PRs obrigatórios integrados;
- Runtime PASS do escopo;
- zero Critical/High;
- config baseline aprovado;
- `updatedb:status = NONE`;
- Composer/security status conhecido;
- CHANGELOG atualizado;
- documentação atualizada;
- rollback definido;
- main/worktree limpos.

## Gate técnico

```sh
git fetch --prune origin
git switch main
git pull --ff-only origin main
composer validate
composer audit
vendor/bin/drush status
vendor/bin/drush cr
vendor/bin/drush config:status
vendor/bin/drush updatedb:status
git diff --check
```

## Gates funcionais

Aplicar [TESTING.md](TESTING.md) ao escopo do release.

Para Portal 1.0, o baseline esperado inclui pelo menos:

- Conta;
- CEP;
- Cursos;
- Apoio/Commerce;
- Wiki;
- Domain matrix;
- User A/B;
- AJAX/fallback;
- hardening.

Features planejadas (Fórum, Search, Engagement etc.) só bloqueiam 1.0 se forem
formalmente incluídas no escopo daquela release.

## Hardening

Aplicar [HARDENING.md](HARDENING.md).

## Evidence pack

Guardar no PR/release:

- SHA;
- versões Drupal/PHP;
- DB Runtime;
- Composer/audit;
- config/updatedb;
- Domain matrix;
- User A/B;
- integrações;
- hardening;
- rollback;
- limitações conhecidas.

Não copiar logs inteiros para documentação canônica.

## Tag e GitHub Release

Tag não é criada por padrão. Criar somente quando o responsável pedir explicitamente, no SHA
indicado por ele:

```sh
git tag -a portal-vX.Y.Z -m "ACULTA Portal X.Y.Z"
git push origin portal-vX.Y.Z
```

Não mover/regravar tag publicada.

Release notes devem conter resumo, upgrade notes, dependências relevantes,
limitações e rollback pointer.

## Deploy

Ordem geral:

1. backup/restore point;
2. atualizar código;
3. dependencies;
4. DB update quando necessário;
5. config import;
6. cache rebuild;
7. cron/queue;
8. HTTP/Domain smoke;
9. logs.

Produção não recebe testes destrutivos.

**Produção (decisão do responsável):** a entrega em produção não é requisito de fase, PR, release, RC nem pendência do agente. O responsável decide quando o projeto vai para produção, no fim de todo o projeto. Merge em `main` atualiza o código do repositório e não publica nada no Hostinger. As verificações de produção (`robots --env=production`, `sitemap --env=production`) são ensaios, não ações de deploy.

## Pós-deploy

Smoke dos purposes ativos e das integrações incluídas no release.

## Hotfix

Branch do baseline apropriado → correção mínima → testes → PR → patch version →
nova tag.
