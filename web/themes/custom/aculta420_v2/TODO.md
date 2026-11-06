# TODO — aculta420_v2

Estado: em desenvolvimento · versão 1.3.0 (`aculta420_v2.info.yml`) · status: sem status YAML.

## Próximo passo

F4 (UI Styles) implementada; accent por entidade na Revista depende de suporte do UI Styles. No Studio, `/conta` e `/meu-perfil` foram validados no Runtime após rebuild; PHPUnit 8 testes/296 assertions, gate SDC 40 componentes, Firefox ESR 65/65 checks (22 ignorados, zero falhas). Capturas antes/depois em claro/escuro, 320/1440 px e foco: `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`. `empty-state` tem captura focal real antes/depois; `context-tabs` ainda precisa de comparação visual; `profile-*` sem mapeamento SDC. Eventos responde na raiz; a listagem atual usa EventCatalogue para ACL, datas futuras e recorrência, enquanto `aculta_event` não fornece Views data. Loja requer autorização; `price` fora do escopo.
Próximo passo: validar separadamente os SDCs de Studio com consumer real; projetar View pública de Eventos que preserve ACL, ocorrências e filtros; concluir Revista somente com seleção por entidade suportada. Cada adoção ainda requer gates e capturas próprias.

## Dívidas

- Componente `price` (ADR-046): aguarda definição do contrato e autorização explícita da frente de Commerce/Loja.
- Faixa de acento (`card-cultural`): em modo DRAFT até entrega da variável neutra `domain_presentation.accent` (D1) pelo Portal.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.
- Roadmap completo, grande: `web/themes/custom/aculta420_v2/docs/roadmap.md`. Leia só a seção pelo título: `grep -n '^## ' web/themes/custom/aculta420_v2/docs/roadmap.md`.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta420_v2.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
