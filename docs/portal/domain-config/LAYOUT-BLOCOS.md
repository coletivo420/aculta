# Layout de Blocos por domínio

Toda a operação é feita no **MAIN** (`aculta.toca.net.br` no desenvolvimento),
em `/painel-administrativo/estrutura/block`.

## Fluxo para editar um bloco em um domínio

1. Abra o Layout de Blocos no MAIN.
2. No bloco desejado, clique em **Configurar**.
3. No topo do formulário, em **Domínio do override de blocos**, escolha o
   domínio (ex.: `ACULTA - Conta`) e clique em **Alternar domínio**.
   - O formulário recarrega com `?config_domain=<id>`.
   - A opção vazia significa **Global (sem override)**: usa o valor base.
   - O domínio padrão MAIN não aparece; ele é o global.
4. Na primeira vez, clique em **Enable domain configuration**. Isso registra o
   nome `block.block.<id>` para o domínio escolhido.
5. Altere o que precisa — título administrativo, região, peso, visibilidade —
   e clique em **Save block**.
6. O valor é gravado na coleção `domain.<id>` como diff; o bloco global e os
   demais domínios não mudam.

## Fluxo para posicionar/reordenar no domínio

A lista de blocos também aceita o domínio:

1. Abra `/painel-administrativo/estrutura/block/list/aculta420_v2?config_domain=<id>`.
2. A coluna de região mostra a região do override para o domínio escolhido.
3. Altere a região ou o peso e salve. O override é gravado na coleção do
   domínio.
4. Sem `config_domain`, a mesma tela mostra o valor global.

## Remover apenas o override

No formulário do bloco, com o domínio selecionado, clique em
**Remove domain configuration** e confirme em **Apagar configuração**.

- Remove o arquivo da coleção do domínio e o registro em
  `domain_config_ui.settings`.
- O bloco global continua existindo e volta a valer naquele domínio.
- Não use a operação **Delete** do bloco para remover um override: ela apaga a
  entidade global.

## Desativar em um domínio

O `status` do bloco é uma chave do override. Use a operação de ativação/
desativação do Layout de Blocos com o domínio selecionado, ou grave
`status: false` no diff do domínio. O bloco global e os outros domínios
permanecem ativos. Não apague o bloco global para desativá-lo em um domínio.

## Criar blocos novos

A criação de uma **nova entidade** `block.block.*` é global: o ID é gerado no
momento da criação e o registro de override depende do nome final. O fluxo
homologado desta entrega é criar o bloco no MAIN e, em seguida, criar overrides
de região/peso/visibilidade por domínio. Criar uma entidade de bloco exclusiva
de um domínio não é suportado por esta ponte.

## Visibilidade por purpose

Os blocos existentes usam condições como `aculta_domain_purpose`. Ao criar um
override de um bloco para um domínio, a condição de visibilidade também pode ser
ajustada no formulário. O override é um diff: só as chaves alteradas são
gravadas.

## Verificação rápida

- Formulário com domínio: título/região/peso do domínio.
- Formulário sem domínio: valor global.
- Lista com domínio: região do override.
- Front-end do domínio: bloco na região do override.
- Outro domínio: valor global.
- Após remover o override: front-end volta ao global.
