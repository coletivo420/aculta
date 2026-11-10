# TODO — aculta_portal

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-03 State concluído:** migração direta para `active_site_keys`, sem fallback; Kernel isolado 2/17 e PHPCS PASS. Post-update versionado; Runtime não executado por exigir `updatedb`. `DomainSiteManager` agora implementa resolução, URLs e troca/restauração de Domain por `SiteKey`, sem delegar no resolver legado; a migração de consumidores segue.

- **RN-00 (parcial):** gerador `scripts/inventory-site-key-contracts.php` e CSV de ocorrências criado; 1.610 referências genéricas ainda precisam triagem semântica, e a matriz HTTP/State/sessão não foi fotografada.
- **RN-02 (em andamento):** options de rota e a costura de teste usam somente `site_key`; `DomainRoutePolicy` rejeita nomes removidos e valores inválidos, sem fallback nem wrappers. Unit/Kernel e gates HTTP no Runtime passaram. State migrou para `active_site_keys` sem fallback. `ContentSiteKeyResolver` já usa `DomainSiteManagerInterface`; falta migrar subscriber, sessão e demais consumidores funcionais, depois provar cache/ACL completos.
- **RN-01 (em andamento):** enum `SiteKey`, `SiteKeyRegistry`, `DomainSiteManagerInterface` e `DomainSiteManager` existem como adaptador ao resolver. Falta provar paridade HTTP/alias/rotas e migrar consumidores; preservar valores e `DomainPurposeManager` apenas para as responsabilidades funcionais necessárias.
- **US-02 UI Skins:** dependente de Composer/Sync/Runtime reconciliados e baseline visual Links. Não habilitar ou alterar configuração ativa nesta etapa.

Migrar os consumidores restantes de `DomainPurposeManager` para `DomainSiteManager`/`site_key`, migrar sessão e apresentação, e integrar UI Skins após confirmar API e pacote instalados. Depois, implementar adapters de inventário por módulo owner para posts, revisões e anexos; depois desenhar o executor explícito com confirmação, backup restaurável, trilha auditável e revalidação em cada lote. Homologar somente em banco isolado; não executar limpeza nem rebuild no Runtime.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_portal.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.

