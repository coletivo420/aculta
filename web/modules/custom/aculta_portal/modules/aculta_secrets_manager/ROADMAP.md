# Roadmap

## Feito (0.1.0-dev.1)

- Abas Produção/Testes com estado real só no ambiente ativo.
- Escopo por ambiente no núcleo e negação no controller.
- No-store nas rotas do painel.

## Pendente antes de RC

- Testes Functional (instalação, AJAX dos popups, CSRF com sessão real, teclado). O Kernel de rotas e
  permissões passa no banco de teste dedicado (ver CHANGELOG).
- Decisão do responsável: manter a permissão `administer aculta secrets` única nesta versão. Separar em
  consultar, gerenciar e revelar fica para uma versão futura, com atualização de perfis e sem concessão
  automática.
- Revisão visual no Firefox ESR (DISPLAY=:0) em largura de celular, com Olivero e ACULTA420.
- Procedimento de rotação e recuperação (ver docs/operations/SECRETS.md).
- Guia de migração Portal → submódulo para instalações existentes.

### Revalidação em 2026-10-29

- O módulo no checkout de desenvolvimento (`355e48a89`) é idêntico ao candidato
  nesta branch. HTTP com sessão real passou 9/9; Firefox ESR passou 16/16 no
  painel, 7/7 nos popups sem envio e 28/28 em foco/alvos e larguras 1280/390/320.
- As capturas foram inspecionadas manualmente: a tabela fica ilegível em 320 px,
  com conteúdo quebrado verticalmente; em 1280 px uma coluna também aparece
  comprimida em caracteres/itens verticais. Os asserts atuais de ausência de
  rolagem não verificam largura mínima/legibilidade. Evidência privada em
  `/home/piradopirata/private/aculta/test-evidence/secrets-manager-20261029`.
- Unit: 13/56 PASS. Kernel Routes não iniciou as asserções: erro `Unavailable
  module: commerce_shipping`, 0 assertions. O módulo não está disponível no
  checkout de teste; não foi instalada dependência nem alterada a fixture.
- PHP lint, PHPCS Drupal/DrupalPractice com extensão PHP e `node --check` passam.
- A fase permanece Dev. O ajuste de layout e reteste foram concluídos em Dev.8;
  CSS servido pelo candidato, Olivero e teste Functional de instalação/rollback
  continuam pendentes. Não houve alteração funcional no Runtime.

### Dev.7 — revisão de legibilidade da tabela (2026-10-29)

- Corrigidas as larguras das cinco colunas e da célula de credencial; em 1280 px
  as cinco colunas cabem na área do painel, e em 390/320 px a rolagem fica
  confinada ao contêiner. Não há fragmentação vertical dos valores mascarados.
- Firefox ESR com viewport CSS via WebDriver BiDi: 28/28 verificações PASS em
  1280/390/320 px. O script injetou o CSS candidato no HTML real do painel do
  servidor de desenvolvimento; inspeção das capturas confirmou a legibilidade.
  Isso não comprova o CSS carregado do candidato nem substitui a revisão em
  Olivero.
- Unit 13/56 PASS; PHP lint, PHPCS Drupal/DrupalPractice, `node --check`, parse
  Python e `git diff --check` PASS. Kernel Routes continua bloqueado antes das
  asserções por `commerce_shipping` ausente (1 erro, 0 assertions). Functional
  de instalação/rollback permanece pendente. Não é candidato RC.


### Dev.8 — harness de rotas Kernel (2026-10-31)

- Ajustado o conjunto de módulos do teste para instalar `commerce_checkout`, que fornece
  o resolver de etapas exigido pelo serviço de checkout usado na rota. `commerce_shipping`
  não é dependência desse teste e não está instalado neste checkout.
- Unit + Kernel Routes: 14 testes / 60 assertions PASS, 58 deprecações e nenhuma falha.
- CSS servido pelo candidato/Olivero e Functional de instalação, rollback e CSRF ainda
  precisam de validação antes de avaliar RC.

### Dev.7 — fixture Kernel das rotas (2026-10-31)

- Corrigida a lista de módulos do teste para incluir `commerce_checkout`, serviço
  requerido pelo resolver de checkout usado pelo painel. `commerce_shipping` não
  é necessário para essa rota e não está disponível no checkout.
- Unit + Kernel Routes: 14/60 PASS; 58 depreciações de Core/contrib, sem falhas.
- CSS candidato verificado por Firefox ESR/X11 no painel servido: 31/31 em
  1280/390/320 px; inclui largura legível das cinco colunas, linhas, scroll
  interno, ausência de overflow da página e captura começando pela primeira
  coluna. A captura 320 px foi inspecionada manualmente. O teste injeta o CSS
  candidato na sessão do browser; não comprova CSS servido pelo servidor.
- Permanecem pendentes a validação do CSS servido pelo candidato, Olivero e
  testes Functional de instalação/rollback antes de avaliar RC.

### Dev.8 — legibilidade responsiva (2026-10-31)

- A tabela de requisitos mantém largura mínima de 56rem com cinco colunas
  proporcionais e rolagem confinada ao seu contêiner em telas estreitas.
- Firefox ESR/X11: 31/31 verificações em 1280/390/320 px com CSS candidato
  injetado sobre o painel servido. Captura mobile inspecionada; sem scroll
  horizontal residual antes da captura.
- Não houve alteração de Runtime nem de configuração. Olivero, CSS servido
  pelo candidato e Functional de instalação/rollback permanecem pendentes.

## Decisão de fronteira

UI administrativa renderizada no Claro mantém CSS mínimo no próprio submódulo, com seletores de classe e `data-portal-ui`. O CSS de abas e segredos não será migrado para o tema; classes equivalentes permanecem para o ACULTA420.
