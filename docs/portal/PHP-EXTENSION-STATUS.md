# Status das extensões PHP

O relatório administrativo em ACULTA Portal > Status lista as extensões PHP
necessárias às integrações do Portal e informa se cada extensão está carregada
na SAPI PHP que atende a requisição.

## Fonte dos requisitos

- As integrações são as mesmas declarações `MODULE_REQUIREMENTS` que alimentam
  a tabela de módulos e bibliotecas do Status.
- Para cada pacote Composer associado à integração, o Portal percorre o grafo de
  `require` dos pacotes em `composer.lock` e coleta os requisitos `ext-*`.
- Só entram dependências de runtime em `packages`. `packages-dev` e sugestões
  opcionais (`suggest`) ficam de fora.
- Cada linha mostra a integração, os módulos Drupal relacionados e os pacotes
  que declaram diretamente a extensão.

## Estado no ambiente

O controlador consulta `get_loaded_extensions()` durante a requisição. `OK`
significa que a extensão está carregada naquele processo PHP. `Erro` significa
que não foi carregada; a página não distingue uma extensão ausente do sistema
de uma extensão instalada, mas desativada na configuração PHP.

O relatório mostra o estado da SAPI que serviu a página. Ele não infere o
estado do CLI, PHP-FPM de outro pool, worker de fila ou outro ambiente. O
relatório não cobre dependências externas que não sejam pacotes Composer
(`ImageMagick`, Ghostscript e binários de otimização, por exemplo), nem
requisitos não expressos como `ext-*` no Composer.

## Implementação e validação

- `PortalRequirementsController` apresenta a tabela no relatório existente,
  sem rota ou permissão nova.
- `PhpExtensionRequirements` resolve dependências transitivas a partir do
  lockfile e consolida extensões compartilhadas entre integrações.
- O gate arquitetural do Portal e o teste Unit
  `PhpExtensionRequirementsTest` verificam a implementação.
