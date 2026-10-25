# Cookies de sessão por ambiente (aculta_deployer)

Documento de dono do deployer. Regra: o domínio do cookie de sessão compartilhado é **endereço de ambiente**
(ver `ENDERECOS-AMBIENTE.md`). Não é fixo no código nem em configuração exportada.

## Por que existe

Os purposes do Portal (MAIN, Revista, Conta, Apoio, Loja…) são hosts diferentes do mesmo site. Para o usuário continuar
logado ao navegar entre eles, o cookie de sessão precisa valer para todos os subdomínios do ambiente. O Drupal faz isso
com `session.storage.options.cookie_domain` (ver `CROSS-DOMAIN-REQUEST-POLICY.md`). Sem o valor certo, o login de um host
não é aceito no outro (sintoma observado no Runtime: o administrador logado em `conta` não chegava à Revista).

## Política por ambiente

| Ambiente | `cookie_domain` | Arquivo que define | Carregado por | Situação |
| --- | --- | --- | --- | --- |
| **test** (Homelab, `*.aculta.toca.net.br`) | `.aculta.toca.net.br` | `web/sites/default/services.homelab.yml` (versionado) | `settings.local.php` do Homelab | Vigente |
| **production** (`*.aculta.org`) | `.aculta.org` | `web/sites/default/services.hostinger.yml` (provisionado no servidor; **não versionado**) | `settings.hostinger.php.example` (modelo) | Declarado; provisionar no RC |

Campos fixos em ambos os ambientes (já em `services.homelab.yml`): `cookie_samesite: Lax`, `cookie_httponly: true`,
`cookie_secure: true`. Não mudar sem explicar (regra de segurança do AGENTS).

A fonte declarada é `config/deploy.json`, seção `session_cookie`. A verificação é o comando:

```sh
php web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer cookies
```

O comando é somente leitura. Sai com código 1 se um ambiente diverge do declarado. Quando o arquivo real de produção não
existe no repositório (é o caso), confere o modelo `services.hostinger.yml.example`.

## Transição: teste → produção

Quando o site vai para produção (no deploy do RC), o cookie muda de `.aculta.toca.net.br` para `.aculta.org`. Passos:

1. Provisionar `web/sites/default/services.hostinger.yml` no servidor de produção com `cookie_domain: '.aculta.org'`.
   O arquivo não entra no Git.
2. Rodar `aculta-deployer cookies` contra o modelo: deve sair com `ok production`.
3. Após o deploy, confirmar em produção que o login feito em `conta.aculta.org` vale em `revista420.aculta.org` (teste manual
   de navegador, registrado em `docs/portal/TESTES-NAVEGADOR.md`).

**Impacto:** o cookie é emitido para `.aculta.org`. Sessões obtidas no ambiente de teste não são aceitas em produção: os
usuários entram de novo. Não há migração de sessão entre ambientes (padrão adotado; pendente de confirmação do responsável).

## Barreira para alterações futuras

Quem mudar o cookie de sessão de um ambiente (no `services.homelab.yml`, no modelo de produção ou em `config/deploy.json`)
passa por duas barreiras, ambas somente leitura:

1. **Gate do repositório:** `php scripts/validate-session-cookie-policy.php`. Roda `aculta-deployer cookies` e falha se
   o cookie de um ambiente diverge de `config/deploy.json`. É independente do restante do `check`.
2. **`aculta-deployer check`:** inclui a mesma verificação de cookies ao final. Hoje o `check` já falha por pendências
   anteriores de fronteira e de perfil (domínios e aliases de produção), registradas à parte; a verificação de cookies não
   acrescenta falhas.

Se a mudança for intencional, altere `config/deploy.json` (`session_cookie`) no mesmo PR, com a transição descrita acima.
Sem isso, o gate falha.

## Dívidas conhecidas

- `web/sites/default/services.local.yml` está versionado com `cookie_domain: '.aculta.test'`. Esse valor não corresponde a
  nenhum ambiente declarado e o `settings.local.php` do Homelab não carrega esse arquivo. O comando `cookies` aponta a
  dívida. Ação: alinhar ou remover em commit próprio na branch autorizada; PR
  somente quando solicitado.

## Relação com outras regras

- Endereços de ambiente: `ENDERECOS-AMBIENTE.md` (esta política é um deles).
- Política entre purposes: `docs/portal/CROSS-DOMAIN-REQUEST-POLICY.md` (cada ambiente define o domínio-base compartilhado).
