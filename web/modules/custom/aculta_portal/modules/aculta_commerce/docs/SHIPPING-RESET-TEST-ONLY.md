# ACULTA COM-SHIP — reset limpo de legado em desenvolvimento/testes

**Decisão do responsável em 2026-10-26:** dados históricos de pedidos, remessas, métodos e configurações do Commerce Shipping podem ser descartados durante a **implementação em ambiente de teste comprovadamente descartável**. Não há requisito de preservação, conversão, migração de IDs ou rollback linha a linha desses dados.

**Esta autorização NÃO cobre produção, pagamentos reais, dados fiscais, contas, perfis/endereço, produtos, estoque, matrícula, doações, auditorias legais ou bases compartilhadas não classificadas.**

## Preferência: banco efêmero do zero

O cenário preferencial é instalar Drupal + Commerce + `aculta_commerce` em banco MariaDB isolado, com Config Sync **adequado ao shipping próprio** e fixtures sintéticas. Essa opção evita apagar tabelas compartilhadas e prova instalação limpa.

## Se o servidor de testes precisar ter legado apagado

1. **Identificar o alvo:** hostname/URI, raiz Drupal, branch e SHA, banco, usuário do banco, se é runtime de testes e quem o utiliza. Rejeitar ambiente rotulado produção ou destino desconhecido. Confirmar que jobs/cron/consumidores externos não estão ativos e que o cenário escolhido é realmente descartável.
2. **Inventário sem dados pessoais:** executar os preflights locais e via Drush com bootstrap; levantar apenas contagens por tipo: `commerce_order`, `commerce_shipment`, `commerce_shipping_method`, `commerce_payment`, referências e filas. Descobrir se pedidos `default` contêm itens da Loja, LMS ou doações antes de decidir escopo.
3. **Backup privado:** criar snapshot completo em diretório privado, fora do webroot/Git, com acesso mínimo, prova de leitura e procedimento de restauração testado em clone. Não publicar SQL/credenciais no log ou no repositório.
4. **Dry-run explícito:** registrar quais entidades e configurações serão removidas, por tipo e contagem. Separar dados a preservar e referências cruzadas. Não deletar por prefixo genérico; `commerce_order.default` não é prova de origem exclusivamente Loja devido ao consolidador.
5. **Reset controlado:** usar Entity API/instalação limpa, não SQL solto. Isolar gateways, notificações, métricas, webhooks, integrações externas e event subscribers para que a exclusão de dados de teste não cause efeitos externos. Remover pedidos/remessas e componentes legados apenas na ordem segura identificada. Nunca excluir `commerce_store` única, clientes, profiles e conteúdo original por cascade acidental.
6. **Uninstall do contrib:** **somente após** eliminar dependências em pane, entidades, plugins, configs e consumidores e instalar o motor próprio em clone. Avaliar update hooks e field storage de `commerce_shipping`; um uninstall antecipado pode remover dados e quebrar Drupal.
7. **Config Sync e Composer:** remover plugins/traits/settings antigos, importar somente no banco efêmero e testar. Mudanças em `composer.json`, `composer.lock` e `config/sync/` exigem integração coordenada com o agente da PR #298.
8. **Provas:** configuração atual sem referências órfãs ao contrib, Drupal boot e cache rebuild, plugins próprios descobertos, remessa nova e rate verdadeiro, pedidos físicos e não físicos, checkout, pagamento sandbox simulado, ACL, Domain SHOP e logs sem PII.
9. **Rollback:** restaurar snapshot do mesmo banco/test site antes de retestar, não tentar recriar manualmente IDs legados. Registre o ponto de retorno.

## Ferramentas já adicionadas

### Inspeção do Config Sync, sem bootstrap

```bash
php web/modules/custom/aculta_portal/modules/aculta_commerce/tests/scripts/shipping_sync_preflight.php
```

Isso **não** diz quais métodos de envio estão ativos no banco.

### Inspeção somente leitura do Runtime, com bootstrap Drupal

```bash
vendor/bin/drush --root=web --uri=https://HOST_DE_TESTE \
  php:script web/modules/custom/aculta_portal/modules/aculta_commerce/tests/scripts/shipping_runtime_preflight.php
```

O script apresenta só contagens, traits e panes. Não calcula rates, não exibe endereços, tokens ou IDs de pedidos e não executa writes. Substituir HOST_DE_TESTE após conferir a documentação de ambientes.

### Teste do contrato puro de quotes

```bash
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/aculta_portal/modules/aculta_commerce/tests/src/Unit/Shipping/ShippingQuoteSelectorTest.php
```

Executar com o root/flags PHPUnit padronizados do projeto; não declarar PASS antes de rodar.

## Critério de conclusão

- Legacy reset/snapshot demonstrados apenas no banco descartável escolhido;
- Commerce Shipping 3.0.5 não é dependência de execução;
- checkout **próprio** `aculta_commerce` e suas entidades/plugins funcionam sem o contrib;
- rates são valores de métodos reais elegíveis, nunca zero de fallback;
- pedidos de Loja, LMS e Doações funcionam segundo as próprias regras;
- documentação, teste Kernel/Functional/Browser e gates passam.

Até esses critérios passarem, manter o contrib instalado sem alterações e não afirmar que o porte está completo.
