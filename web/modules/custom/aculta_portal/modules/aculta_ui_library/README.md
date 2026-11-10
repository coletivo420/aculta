# ACULTA UI Library

Catálogo administrativo dos blocos reais do tema `aculta420_v2`, com prévia
condicionada a acesso. Complementa a UI Patterns Library (que cobre SDCs e
stories) sem duplicar o catálogo de componentes.

## Rotas

| Rota | Caminho | Permissão |
| --- | --- | --- |
| `aculta_ui_library.blocks` | `/painel-administrativo/aculta/ui-library/blocos` | `administer blocks` |
| `aculta_ui_library.block_preview` | `/painel-administrativo/aculta/ui-library/blocos/{block}` | `administer blocks` |

As duas rotas são administrativas (`_admin_route`), existem somente no
domínio principal (`_aculta_domain_purpose: main`) e não são cacheadas
(`no_cache`). Em subdomínios respondem 404; no MAIN, visitante recebe 403.

## Modelo de segurança

- A prévia usa o plugin original do bloco, com a configuração ativa do
  domínio principal.
- Além da permissão da rota, o controlador verifica `BlockPluginInterface::access()`
  para o usuário atual.
- Contextos obrigatórios ausentes produzem "Prévia indisponível" com o nome
  do contexto; nenhum dado fictício é usado e nenhuma restrição é contornada.
- O render array do catálogo, da prévia e do estado indisponível usa
  `max-age 0` e o contexto `user.roles`, para não gravar conteúdo privado em
  cache público nem compartilhar prévias entre perfis.
- Falhas do plugin são convertidas em estado seguro de indisponibilidade, sem
  expor detalhes internos.


## Auditoria pendente de segurança - 2026-11-10

**Atenção:** o código atual de `UiLibraryPreviewController::preview()` usa
`if (!$plugin->access($account, TRUE))`. A API retorna `AccessResultInterface`
quando o segundo argumento é TRUE; essa condição não verifica `isAllowed()`
e pode permitir o `build()` mesmo com acesso do plugin negado. A rota permanece
restrita a administradores no domínio MAIN, mas esse controle externo não
substitui a verificação do plugin. **SEC-01/P0 está pendente de correção e
testes**; o gate atual não comprova a autorização individual do plugin.

Plano e critérios: [UI Patterns - otimização universal](../../../../../../docs/architecture/ACULTA-UI-PATTERNS-OTIMIZACAO-UNIVERSAL.md).
Esta nota não representa correção implementada nem homologação.

## Gate

`php scripts/validate-aculta-ui-library.php` verifica rotas, acesso por perfil
(visitante, autenticado sem permissão e administrador), catálogo com blocos
reais, `max-age 0` e ausência de `\Drupal::` no controlador.

## Limites

- Não substitui a UI Patterns Library nem o Layout Builder.
- Não renderiza blocos de outros temas.
- Não altera configuração de bloco; apenas lê a configuração ativa.
