# ARQ-08 - Fechamento no agente local (2026-10-29)

**Estado:** implementação parcial; **nenhuma homologação Drupal/Firefox foi executada nesta rodada remota**. Este roteiro **não** autoriza novos bundles, tipos de conteúdo, campos `field_subtitle`/`field_summary` ou serviços editoriais redundantes. A única publicação é `node:revista`.

## Trabalho já feito na PR #299

- `RevistaFormHooks` abre as propriedades secundárias quando a validação reporta erros tanto no caminho agrupado quanto no caminho original Form API; conserva a ordem dos erros do Core e não foca wrappers não interativos.
- `RevistaFormHooksTest` contempla erros agrupados/originais, precedência do primeiro erro, elementos sem widget e controles ocultos/sem acesso.
- Contrato existente `STUDIO-INTEGRATION-CONTRACT.md`: `node.add`, `entity.node.edit_form`, `entity.node.version_history`, `entity.node.preview` e `content_moderation_control` são recursos do Core; criar uma nova camada editorial só com consumidor real.

## Fila de conclusão, sem reconstruir o existente

| Fase | Pendente | Aceite/evidência |
| --- | --- | --- |
| **08A** | executar PHPUnit Unit/Kernel, PHPCS e browser sobre criação, edição e erro secundário no Form API; verificar integração `StudioListingBuilder`/RevistaTab com o purpose correto | conta autora, coautora, revisora e sem permissão; no-JS/AJAX, preview pelo formulário nativo, transições e revisões; sem novo editor |
| **08B** | conferir `field_subtitle`, `field_summary`, `field_media_image`, displays de `revista` e SDCs já existentes em listagem/full | paridade desktop/mobile/light/dark; sem campos ou novos SDCs desnecessários; casos sem imagem |
| **08C** | testar `revista_sessoes` e relações `MagazineContentIndexProvider` com o `aculta_index` real | catálogo paginado, ACL, conteúdo despublicado, cache contexts/tags/max-age, módulo Index habilitado/ausente; não criar catálogo duplicado |
| **08D** | homologar `ArticleAuthorsHooks`, `PostPresentation`, `PublicationDiscussionHooks`, SEO e anexos existentes | autores/coautores, perfil privado, comentários POST e moderação, metatag/JSON-LD com/sem imagem, anexos, duas contas, testes HTTP/Firefox |

**Não declarar concluído** com apenas código disponível ou testes escritos. Marcar por subfase `PASS`, `FAIL`, `BLOCKED` ou `DEFERRED` e guardar logs, URLs e commits. **Não alterar** `aculta_events`, `aculta_calendar`, `aculta_maps` nem a frente de salvos, pois outros agentes trabalham nesses caminhos. Se a integração exigir `aculta_studio`/tema `aculta420` sob outra frente, registrar handoff e coordenar antes da edição. No mesmo branch `refactor/opencultures-codigo-visual` e PR #299, sem nova branch/PR, merge ou force-push.

### Riscos para o teste local

1. Validadores podem devolver caminhos Form API originais ou prefixados; confirmar render/HTML do formulário e localização dos erros, não só Unit.
2. Os testes Unit utilizam árvores reduzidas. Widgets compostos (Media, metatags, Entity Autocomplete) precisam ser testados com Drupal real.
3. Preview não deve receber link GET inventado: preservar tokens de formulário e `NodePreviewAccessCheck`.
4. Discussões `field_aculta_discussion` têm smoke de render anterior, mas submissão real/visibilidade do comentário não estão homologadas no roadmap.
5. A camada visual global mobile-first é de outra frente: usar SDCs compartilhados, sem CSS exclusivo que contorne o tema.
