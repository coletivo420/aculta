# Endereços dependentes de ambiente (regra permanente)

Regra: **todo endereço que muda entre ambientes** (servidor de testes `*.aculta.toca.net.br`, produção `*.aculta.org`, ou
qualquer outro) deve estar declarado no mapeamento do aculta_deployer (`config/deploy.json`) e **nunca** fixo no código,
nos templates, nos assets ou na configuração exportada.

## O que conta como endereço de ambiente

- domínio e subdomínio (hostname de Domain, alias, `trusted_host_patterns`);
- URL absoluta entre hosts (link, canonical, sitemap, `og:url`);
- redirect entre hosts (`TrustedRedirectResponse` com URL de outro purpose);
- `back_url`, `notification_url` e webhook de gateway (Mercado Pago);
- link em e-mail;
- origem de CORS (se houver) e callback OAuth;
- qualquer literal `aculta.org` ou `aculta.toca.net.br` fora do mapeamento.

## Como declarar um endereço novo

1. Se o endereço vem de um **Domain** (hostname), ele já está coberto pelos registros `domain.record.*` e aliases; não
   escreva o host: use a API de domínio (abaixo).
2. Se o endereço é um arquivo de configuração ou de código que contém um host, adicione o arquivo a `environment_bound`
   em `config/deploy.json`, com o motivo:

```json
{ "file": "config/sync/minha.config.yml", "reason": "URL de webhook por ambiente" }
```

3. Se o endereço for uma base de ambiente (produção e teste), declare em `environments`:

```json
"environments": { "production": { "site": "https://aculta.org" }, "test": { "site": "https://aculta.toca.net.br" } }
```

4. Rode o gate: `php scripts/validate-environment-addresses.php` (deve passar).

## Como obter o endereço no código (sem texto fixo)

- URL de uma rota de um purpose (host correto por ambiente):

```php
$url = $this->domainPurposeManager->pathUrl('support', '/');       // https://<host de apoio do ambiente>/
$url = $this->domainPurposeManager->routeUrl('account', 'aculta_portal.my_data'); // rota no host da conta
```

- URL absoluta de uma rota no host atual: `Url::fromRoute('rota', [], ['absolute' => TRUE])`.
- Caminho relativo a um host de Domain: sempre através de `DomainPurposeManager` (nunca concatenar host).

## Pendência conhecida

Os aliases de host do servidor de testes (`config/sync/domain_alias.alias.*.yml`) estão exportados e declarados em
`environment_bound`. Migrar para provisionamento por ambiente (fora do `config/sync`) é o próximo passo do deployer.
