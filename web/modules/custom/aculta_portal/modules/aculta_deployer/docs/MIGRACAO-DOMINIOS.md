# Migração de domínios: teste → produção

Escopo: transição de `*.aculta.toca.net.br` (servidor de testes) para `*.aculta.org` (produção), para todos os domínios registrados no Portal. A tabela canônica é `config/deploy.json`, em `domain_transition`. O comando `aculta-deployer domains` confere, somente leitura, essa tabela contra `config/sync/domain.record.*.yml`.

## Domínios

| Domínio (Portal) | Host de teste | Host de produção | Estado |
| --- | --- | --- | --- |
| aculta_org | aculta.toca.net.br | aculta.org | alinhado |
| apoio_aculta_org | apoio.aculta.toca.net.br | apoio.aculta.org | alinhado |
| conta_aculta_org | conta.aculta.toca.net.br | conta.aculta.org | alinhado |
| revista420_aculta_org | revista.aculta.toca.net.br | revista.aculta.org | alinhado |
| wiki420_aculta_org | wiki420.aculta.toca.net.br | wiki420.aculta.org | alinhado |
| loja_aculta_org | loja.aculta.toca.net.br | loja.aculta.org | alinhado |
| forum_aculta_org | growroom420.aculta.toca.net.br | growroom420.aculta.org | alinhado no mapeamento; teste DNS/TLS ativo; DNS de produção aguarda origem |
| cursos_aculta_org | cursos.aculta.toca.net.br | cursos.aculta.org | alinhado |

## Decisão registrada

Em 2026-10-28, o responsável decidiu que os hosts da Revista passam a ser `revista.aculta.toca.net.br` no Homelab e `revista.aculta.org` em produção. `deploy.json` e os registros de domínio/alias em `config/sync` refletem essa decisão. O DNS de produção será configurado no Cloudflare pelo responsável.

## Passos no RC (não executar antes)

1. `aculta-deployer domains`: todas as linhas devem estar "ok" e não pendentes.
2. `aculta-deployer transition --target=production --out=DIR`: gera o build e o plano (`transition-plan.json`). Não altera Runtime.
3. Importar a configuração parcial no Runtime de produção conforme o plano, com backup antes (`config:export`).
4. DNS dos hosts `*.aculta.org` (fora do repositório, painel do provedor).
5. Certificados dos hosts de produção (fora do repositório).
6. Verificação pós-deploy: `aculta-deployer robots --env=production` e `aculta-deployer sitemap --env=production`.

## Rollback

Importar novamente o backup com `config:import --partial --source=DIR_BACKUP` e limpar caches. O build anterior é preservado (builds nunca sobrescrevem).

## Fora do repositório

DNS, VirtualHosts e certificados de cada ambiente são configurados fora do Git. Credenciais não entram aqui (ver `GUARDRAILS.md`).

### ACULTA Fórum (F1, 2026-10-15)

- Teste: Cloudflare A direto, sem proxy, `growroom420.aculta.toca.net.br` →
  `45.191.204.31`; VirtualHost Apache e Let's Encrypt ativos; Domain e alias do
  Runtime atualizados preservando seus IDs. O host anterior responde 404 sem
  redirect.
- Produção: `growroom420.aculta.org` está alinhado no Deployer e no Domain de
  configuração, mas não recebeu DNS. A conexão TLS direta ao IP de origem
  Hostinger falhou antes de o novo hostname ser provisionado; validar o domínio
  na origem e o handler/certificado antes de criar o registro Cloudflare.

## Loja (SHOP)

- Teste: `loja.aculta.toca.net.br`. Produção: `loja.aculta.org`.
- A página do produto só responde no host da loja (rota `entity.commerce_product.canonical`, purpose `shop`).
- Na transição, o host de produção precisa existir no DNS e com certificado antes do deploy do RC.
