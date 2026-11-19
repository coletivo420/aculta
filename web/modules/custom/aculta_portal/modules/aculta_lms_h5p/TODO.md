# TODO — aculta_lms_h5p

Estado: RC · versão 0.4.0-rc.3 (`aculta_lms_h5p.info.yml`) · status: `docs/operations/status/aculta_lms_h5p.yml`.

## Estado atual

`getNumContent()` valida os IDs do contrato H5P e consulta pela Query API do Drupal, sem concatenar valores ao SQL. A revalidação PHPStan e CI aguarda o próximo run.

## Próximo passo

Confirmar no CI a consulta parametrizada de IDs H5P e acompanhar a homologação com as turmas e conteúdos reais em `*.aculta.toca.net.br`.

## Leitura

- Leia só este arquivo, o código alvo e o `CHANGELOG.md` do componente se ele existir, pelo trecho necessário.

## Ao concluir uma fase

Atualize versão, estado e próximo passo aqui e em `docs/operations/status/aculta_lms_h5p.yml` (quando houver), no mesmo commit. Remova o que foi concluído; não acrescente histórico neste arquivo.
