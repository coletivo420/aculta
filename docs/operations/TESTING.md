# Estratégia de testes

Data da revisão: 2026-10-07.

## Princípio

Revisão estática e validação Runtime são gates diferentes. Revisão de diff não
autoriza declarar comportamento funcional como PASS.

## Gate estático

Conforme o escopo:

- diff e conflitos;
- lint/sintaxe disponível;
- referências internas;
- source-of-truth;
- APIs upstream;
- ausência de segredos;
- separação Portal/tema;
- atualização documental;
- `git diff --check`.

## Gate Runtime

Mudança funcional deve testar, conforme aplicável:

```sh
composer validate
composer audit
vendor/bin/drush status
vendor/bin/drush cr
vendor/bin/drush config:status
vendor/bin/drush updatedb:status
```

Adicionar lint e testes específicos dos arquivos alterados.

### Gate ACULTA420 Foundation

O tema possui gate próprio, separado de Portal/Commerce:

```sh
vendor/bin/drush php:script validate-aculta420-foundation --script-path=../scripts
```

Esse gate é somente leitura e valida provider/config, OOP hooks, fronteira
Portal/tema, libraries/assets, Twig/YAML, descoberta SDC e invariantes
anti-regressão de CSS. O gate `validate-portal-commerce-security.php` não deve
voltar a testar internals do tema.

## Configuration Sync

- aplicar apenas configuração pretendida;
- classificar drift antes de exportar;
- revisar paths alterados;
- não usar `cex` em massa para mascarar divergência;
- release exige baseline de configuração conhecido e aprovado.

## Matriz Domain

| Purpose | Homelab | Produção |
| --- | --- | --- |
| MAIN | aculta.toca.net.br | aculta.org |
| ACCOUNT | conta.aculta.toca.net.br | conta.aculta.org |
| SUPPORT | apoio.aculta.toca.net.br | apoio.aculta.org |
| MAGAZINE | revista420.aculta.toca.net.br | coletivo420.aculta.org |
| WIKI | wiki420.aculta.toca.net.br | wiki420.aculta.org |
| SHOP | loja.aculta.toca.net.br | loja.aculta.org |
| COURSES | cursos.aculta.toca.net.br | cursos.aculta.org |
| FORUM *(quando ativo)* | forum.aculta.toca.net.br | forum.aculta.org |

Para rota especializada:

```text
host correto -> comportamento esperado
host incorreto -> 404/policy documentada
```

## Identidades mínimas

- anonymous;
- User A;
- User B;
- administrador parcial;
- administrador completo quando necessário.

User A/B é obrigatório para dados privados: Profile, Address, apoio, cursos,
participação, favoritos e preferências.

## Fluxos críticos

### ACCOUNT

Dashboard, dados, endereço, foto, segurança, conexões, cursos e apoio.

Validar:

- access;
- submit/reload;
- AJAX + fallback;
- cache;
- isolamento;
- OAuth/Change Mail sem interceptação indevida.

### WIKI

Published/unpublished, revisions, Diff, workflow, Domain Source, canonical,
access e busca.

### COURSES

Membership, progresso, avaliação, shared session, ACCOUNT ↔ COURSES e wrong-host.

### SUPPORT / Commerce

Donation Flow, checkout, payment, webhook, order access e ausência de metadata
privada pública.

### CEP

Válido/inválido, timeout, stale response, preenchimento, submit/reload,
reattachment e User A/B.

## AJAX e acessibilidade

Testar quando afetado:

- JS enabled/disabled;
- slow/failed response;
- double click;
- back/forward/refresh/deep link;
- focus/keyboard;
- aria-live/status/error;
- behavior reattach;
- cache/access;
- mobile/reduced motion.

## Failure modes

Simular quando relevante:

- SMTP indisponível;
- OAuth indisponível;
- CEP timeout;
- backend de busca vazio/indisponível;
- webhook inválido;
- config contrib ausente;
- membership stale.

Falha externa deve degradar de forma controlada.

## Evidência

Registrar no PR/release, não em documento novo:

- commit;
- ambiente;
- identidade/host;
- expected/actual;
- PASS/FAIL;
- logs minimizados;
- finding e retest.
