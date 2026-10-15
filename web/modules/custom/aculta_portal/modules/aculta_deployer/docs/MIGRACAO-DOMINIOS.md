# Migração de domínios: teste → produção

Escopo: transição de `*.aculta.toca.net.br` (servidor de testes) para `*.aculta.org` (produção), para todos os domínios registrados no Portal. A tabela canônica é `config/deploy.json`, em `domain_transition`. O comando `aculta-deployer domains` confere, somente leitura, essa tabela contra `config/sync/domain.record.*.yml`.

## Domínios

| Domínio (Portal) | Host de teste | Host de produção | Estado |
| --- | --- | --- | --- |
| aculta_org | aculta.toca.net.br | aculta.org | alinhado |
| apoio_aculta_org | apoio.aculta.toca.net.br | apoio.aculta.org | alinhado |
| conta_aculta_org | conta.aculta.toca.net.br | conta.aculta.org | alinhado |
| coletivo420_aculta_org | revista420.aculta.toca.net.br | revista420.aculta.org | pendente de RC (decisão) |
| wiki420_aculta_org | wiki420.aculta.toca.net.br | wiki420.aculta.org | alinhado |
| loja_aculta_org | loja.aculta.toca.net.br | loja.aculta.org | alinhado |
| forum_aculta_org | growroom420.aculta.toca.net.br | growroom420.aculta.org | alinhado no mapeamento; teste DNS/TLS ativo; DNS de produção aguarda origem |
| cursos_aculta_org | cursos.aculta.toca.net.br | cursos.aculta.org | alinhado |

## Decisão pendente

O host de teste da revista passou a ser `revista420.aculta.toca.net.br`. A produção hoje é `coletivo420.aculta.org`. A tabela assume `revista420.aculta.org`, mas isso precisa ser confirmado pelo responsável antes do RC. Se a decisão for manter `coletivo420.aculta.org`, o destino da linha muda em `deploy.json` e o registro de domínio em `config/sync` continua igual.

## Passos no RC (não executar antes)

1. Confirmar a decisão acima e atualizar `domain_transition` se necessário.
2. `aculta-deployer domains`: todas as linhas devem estar "ok" e não pendentes.
3. `aculta-deployer transition --target=production --out=DIR`: gera o build e o plano (`transition-plan.json`). Não altera Runtime.
4. Importar a configuração parcial no Runtime de produção conforme o plano, com backup antes (`config:export`).
5. DNS dos hosts `*.aculta.org` (fora do repositório, painel do provedor).
6. Certificados dos hosts de produção (fora do repositório).
7. Verificação pós-deploy: `aculta-deployer robots --env=production` e `aculta-deployer sitemap --env=production`.

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
