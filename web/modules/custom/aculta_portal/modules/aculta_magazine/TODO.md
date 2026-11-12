# TODO — aculta_magazine

Estado: Dev · versão 0.5.0-dev.20 (`aculta_magazine.info.yml`) · status: `docs/operations/status/aculta_magazine.yml`.

## Próximo passo

- Refatorar usos reais restantes de `\\Drupal::` em classes do módulo; os callbacks de capa Studio e HTMX já resolvem hooks como serviços.

A escolha global `revista_accent` passou pelo formulário UI Styles e chega como classe ao wrapper do node teaser e ao SDC, com precedência sobre o Portal. Firefox ESR validou os quatro teasers publicados em claro/escuro e 320/1440 px, sem overflow. Consulta: cinco nós Revista, zero accents próprios; node 602 vazio. O campo legado fica instalado sem widget, allowlist ou leitura; não remover sem plano não destrutivo.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_magazine.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
