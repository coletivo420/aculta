# TODO — aculta_portal

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-00 (parcial):** gerador `scripts/inventory-site-key-contracts.php` e CSV de ocorrências criado; 1.610 referências genéricas ainda precisam triagem semântica, e a matriz HTTP/State/sessão não foi fotografada.
- **RN-02 (em andamento):** `DomainRoutePolicy` agora oferece `allowsSharedSite()` e `siteKeyForDonationRoute()`; os dois subscribers de Portal e os testes focais passaram a chamá-los. Métodos antigos permanecem wrappers depreciados, e as options de rota persistidas continuam com nomes `purpose` até existir migração reversível. Migrar opções em lotes, com fail-closed e teste de paridade HTTP antes de remover a compatibilidade.
- **RN-01 (em andamento):** enum `SiteKey`, `SiteKeyRegistry`, `DomainSiteManagerInterface` e `DomainSiteManager` existem como adaptador ao resolver legado. Falta provar paridade HTTP/alias/rotas e migrar consumidores; preservar valores e manter `DomainPurposeManager` até zero chamadas.
- **US-02 UI Skins:** dependente de Composer/Sync/Runtime reconciliados e baseline visual Links. Não habilitar ou alterar configuração ativa nesta etapa.

Implementar adapters de inventário por módulo owner para posts, revisões e anexos; depois desenhar o executor explícito com confirmação, backup restaurável, trilha auditável e revalidação em cada lote. Homologar somente em banco isolado; não executar limpeza nem rebuild no Runtime.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_portal.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
