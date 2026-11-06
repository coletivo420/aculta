# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

Eventos: View pública da raiz usa `EventCatalogue` para IDs/props, preservando ACL, recorrência, ordenação e filtros; os cards usam `card-cultural`. Kernel Events passou: 155 testes/3.616 assertions (33 depreciações Core/contrib). Gates V2 aplicáveis passaram. Firefox ESR capturou listagem e detalhe em claro/escuro a 320/1440 px, foco visível e zero overflow em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. Sem baseline anterior da listagem.

Studio: `/conta` e `/meu-perfil` validados no Runtime; PHPUnit 8/296 e Firefox ESR 65 verificações aplicáveis (22 ignoradas). Capturas pareadas em `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; `empty-state` tem comparação focal; `context-tabs` e `profile-*` seguem pendentes. Revista depende de opção por entidade no UI Styles. Loja aguarda autorização; `price` fora do escopo. Gate Portal falha por divergências preexistentes de versão/status.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
