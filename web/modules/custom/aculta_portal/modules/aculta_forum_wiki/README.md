# ACULTA Forum Wiki

Adapter opcional entre `aculta_forum` e `aculta_wiki`. Registra o provider
`wiki` no serviço tagueado de contextos de discussão e oferece uma única
discussão por verbete, no campo compartilhado `field_aculta_discussion` do Node
`wiki_entry`.

O adaptador não reimplementa a Wiki: a revisão editorial continua no fluxo do
`wiki_entry`, nenhum tópico público do Fórum é criado e nenhuma rota é
registrada. A rota administrativa própria da Wiki ainda não existe; criá-la
exige um filtro de contexto instalável e access validado, como nas demais
filas.

## Contrato

| Item | Valor |
| --- | --- |
| Tipo do contexto | `wiki` |
| Host | Node `wiki_entry` com `field_aculta_discussion` |
| `stableId` | `wiki:node:{uuid}` |
| URL canônica | URL do próprio verbete |
| Leitura | Entity Access do verbete e do campo |
| Escrita | Leitura + `post comments` + campo aberto |
| Moderação | `administer comments` |
| Políticas | `editorial_entity_access` (leitura, descoberta, notificação); `editorial_opt_in` |

As políticas são declarativas: nenhum consumidor ramifica por elas. As strings
de atenção descritivas ficam em `../aculta_forum/docs/CONTEXTS.md`.

## Configuração

O `aculta_wiki` não tem `config/install`, então a instância do campo, o `hidden`
do formulário e a exibição `full` do `wiki_entry` vivem apenas em
`config/sync`. O dono dessa configuração é decisão aberta do responsável (ver
`docs/modules/WIKI-SUBMODULO.md`).

O módulo está listado em `config/sync/core.extension.yml` e continua
desabilitado no Runtime. Habilitar exige importar a configuração em um ambiente
com backup verificado; nenhuma operação de Runtime foi feita por este
adaptador.

## Testes

`tests/src/Unit/WikiDiscussionContextProviderTest` cobre bundles aceitos,
composição de acesso e cache, campo fechado, permissão ausente, restrição
preservada do host e do campo, e verbete não persistido.

```
vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/aculta_portal/modules/aculta_forum_wiki/tests/src/Unit
```

Resultado medido: 5 testes, 28 asserções, OK.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-05/11: manter discussão contextual no Node wiki_entry e integração de moderação/avisos sem criar tópico público no fórum. Política de identidade e primeira participação no User, avisos no Messenger.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**
