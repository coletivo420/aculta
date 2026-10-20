# Status das extensões PHP

O relatório administrativo em ACULTA Portal > Status lista as extensões PHP
necessárias aos pacotes Composer de runtime do site e informa se cada extensão
está carregada na SAPI PHP que atende a requisição. Uma varredura global de
`composer.lock` mantém requisitos novos visíveis sem depender de atualização
manual da lista de extensões.

## Fonte dos requisitos

- A varredura percorre todos os pacotes de runtime em `composer.lock` e seus
  requisitos `require`, incluindo dependências transitivas. Assim, adicionar
  um pacote com um novo requisito `ext-*` inclui automaticamente essa extensão
  no painel.
- Linhas com atribuição explícita ligam requisitos às integrações que os usam.
  `ACULTA Image` mapeia `entity_usage`, `media_duplicates`, `imageapi_optimize`
  e `imageapi_optimize_binaries`; `ACULTA PDF Viewer` mapeia `pdfpreview` e
  `imagemagick`. A varredura global também registra qualquer requisito
  encontrado fora desses mapas.
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
- `PhpExtensionRequirements` resolve dependências transitivas e varre o
  conjunto global de pacotes de runtime, consolidando extensões compartilhadas.
- `php scripts/validate-php-extension-status.php` compara a lista detectada
  com todos os `ext-*` declarados no lockfile e verifica a presença do scanner
  global e dos mapas do Image e do PDF no painel. Execute esse gate sempre que
  alterar dependências Composer, o detector ou o relatório de Status.
- O gate arquitetural do Portal e o teste Unit
  `PhpExtensionRequirementsTest` verificam a implementação.
