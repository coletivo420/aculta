# TODO — aculta_portal

Estado: Dev · versão 0.5.0-dev.1 (`aculta_portal.info.yml`) · status: `docs/operations/status/aculta_portal.yml`.

## Próximo passo

- **RN-00 a RN-05 (ADR-054/055, planejamento aprovado):** inventariar e renomear contratos funcionais `purpose` para `site_key` no Portal, preservando IDs Domain, regras de acesso, URLs e dados. Coordenar com UI Skins como motor visual. Documento: `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`. Nenhum código ou Runtime alterado nesta entrega.

Implementar adapters de inventário por módulo owner para posts, revisões e anexos; depois desenhar o executor explícito com confirmação, backup restaurável, trilha auditável e revalidação em cada lote. Homologar somente em banco isolado; não executar limpeza nem rebuild no Runtime.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_portal.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
