# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

F4 (UI Styles) implementada; accent por entidade na Revista depende de suporte do UI Styles. No Studio, `context-tabs`→`tabs` e `empty-state`→`empty-state` passaram em teste focal e render Drupal. Capturas ESR reais antes/depois do estado vazio em `/group/131` mostram o SDC nos dois modos e tamanhos, foco visível e sem overflow. A suíte ampla segue parcial: 34/48 checks OK, 22 skipped e 14 falhas. Eventos responde na raiz; a listagem atual usa EventCatalogue para ACL, datas futuras e recorrência, enquanto `aculta_event` não fornece Views data. Loja requer autorização; `price` fora do escopo.
Próximo passo: corrigir os checks visuais Studio desatualizados; projetar integração de View para Eventos que preserve ACL, ocorrências e filtros; concluir Revista somente com seleção por entidade suportada. Cada adoção ainda requer gates e capturas próprias.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
