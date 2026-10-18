# Base local de CEPs (aculta_portal_cep)

## Decisão

- Em 2026-10-18 o responsável aceitou o risco de licença de usar a base de CEPs do DNE dos Correios, contida na imagem Docker pública `brasilcep/api`, para consulta local.
- Não há banco de dados para os CEPs: a base fica em arquivos privados, divididos por prefixo (três primeiros dígitos).
- A base não entra no Git. Fica em `private://aculta_portal_cep/shards`, no diretório privado do ambiente.
- A consulta local tem prioridade: só quando o CEP não está na base, o Portal consulta ViaCEP e depois BrasilAPI.

## Tamanho e formato

- Importação de 1.388.509 CEPs em 872 arquivos, com cerca de 92 MB no disco do Runtime de teste.
- Formato de cada linha, separado por TAB: `cep`, `logradouro`, `bairro`, `cidade`, `UF`, `código IBGE`, ordenado por CEP.
- Verificado contra a ViaCEP em quatro CEPs (São Paulo, Rio de Janeiro, Brasília): logradouro, bairro, cidade e UF iguais.

## Como atualizar

1. Gerar o TSV a partir da imagem (fora do repositório). O script de exportação ficou no diretório de trabalho do responsável, e a origem é a imagem `brasilcep/api` baixada de forma anônima do registry Docker.
2. Importar no ambiente: `php vendor/drush/drush/drush.php -l <host> aculta-portal-cep:import <arquivo.tsv>`.
3. Conferir uma amostra contra a ViaCEP antes de considerar a base atualizada.

## Limites

- A base é uma foto da data de extração (imagem de 17/06/2026). CEPs criados depois não estão nela e são resolvidos pelas APIs.
- Não há processo de atualização automática. Repetir o passo acima quando for preciso.
- O uso é de consulta interna. Redistribuir a base ou publicá-la está fora da decisão e exige nova decisão do responsável.

## Status e versão (0.4.0-dev.148)

- A importação grava `private://aculta_portal_cep/manifest.json` (versão = data da importação, quantidade de CEPs e arquivo de origem). O status do Portal calcula a idade pela data de hoje: até 90 dias é OK, de 91 a 180 é atenção, acima disso ou sem base é erro.
- As APIs (ViaCEP e BrasilAPI) são verificadas no cron, no máximo uma vez por dia, com um CEP fixo; o status mostra o último resultado. Resultado com mais de 2 dias é atenção (cron possivelmente parado).
- Em produção, a base deve ser reimportada ao menos a cada 180 dias; o status acusa erro quando passa desse prazo.
- Permissões: a base precisa ser legível pelos processos PHP-FPM do site. A importação deixa a pasta `0755` e os arquivos `0644` (`CepLocalStore::makeReadable()`). Sem isso, o status mostra "Sem base importada" mesmo com a base presente, porque o processo web não enxerga a pasta.

## Configuração (0.3.0-dev.6)

- Tela: Configurações > ACULTA Portal > Consulta de CEP (`/painel-administrativo/configuracoes/aculta/portal/cep`).
- Banco padrão: primeira fonte consultada. Ordem das demais: peso da tabela (arrastar). Cada fonte pode ser desativada.
- Fontes: `local` (base de arquivos), `viacep`, `brasilapi`. O código usa `CepSources` e os padrões em `CepSources::DEFAULTS`.
- O contrib `cep_autocomplete` não tinha opções administrativas; nada foi portado dele.
