# TODO — aculta420_v2

Estado: Dev · versão 1.5.0 (`aculta420_v2.info.yml`) · status em `docs/operations/status/aculta420_v2.yml`.

## Próximo passo

- **ADR-054/055 US-00–14/RN-00–05:** UI Skins ativo no Runtime; mapa/scopes seguem ausentes. Import parcial de seis itens foi autorizado e revisado, aguarda autorização para executar o post-update Portal prévio. Views categorias usa `forum-category`; Links e estilos aguardam homologação.
- Composer: Skins ^1.2, Icons ^2.1; Field Group dev SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`, **BLOCKED_UPSTREAM** (sem cobertura de segurança e Core 12). UI Styles CKEditor está no Sync/editor Revista, mas desativado no Runtime; Patterns CKEditor está ativo preexistente e `LEGACY_OBSOLETE`, sem uso no código.
- Inventário: 381 itens/83 Twig; 16 fallbacks mapeados a adapters SDC. `aculta_section` usa `layout-section`; pagamento usa `surface`; botão usa `button`; status do carrinho usa `status-messages`. UI Icons estão no Sync, validação Runtime pendente. Cinco overrides de categorias retirados do código; cutover autorizado, aguardando post-update autorizado. Entity Status e demais domínios pendentes.
- Planos e critérios: plano UI Suite, migração Skins e contrato SiteKey em `docs/architecture/`.

## Marcos e pendências

- Superfícies SDC integradas a UI Styles/Patterns/Library; FST-08 tem oito SDCs. Gates SDC PASS; captura visual pendente.
- ADR-054: P4 em 22/24 displays; piloto Links publicado; P6/P7 entregues; P8/P9 cortados em 1.5.0. Homologação e revisão visual pendentes.
- Faltam capturas Firefox/P3, Revista, F2/F3, editor, UI Patterns autenticado e Loja; fronteira módulo/tema ainda tem 64 itens.
- Dívidas: `price` aguarda contrato Loja; `card-cultural` aguarda `domain_presentation.accent`.

## Leitura e entrega

Consulte `CHANGELOG.md` e roadmap; ao concluir fase, atualize este TODO e o status YAML no mesmo commit, sem histórico.
