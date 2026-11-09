# Assets institucionais ACULTA420 V2

## Phosphor Icons

`icons/phosphor/regular/` e `icons/phosphor/duotone/` contêm os 3.024 SVGs
oficiais do Phosphor Icons Core 2.1.1 (1.512 por peso), obtidos do repositório
`phosphor-icons/core` no commit
`2b75f3ad12b420c9504ef05df8d2564a28f8500e`. A licença MIT está em
`icons/phosphor/LICENSE`. Os nomes Duotone mantêm o sufixo `-duotone.svg`.
O SDC `icon` incorpora o SVG por `source()` no HTML renderizado: não usa sprite
remoto, `<use>` cross-origin nem requisição por CDN, e a cor segue
`currentColor`. O componente valida o nome antes de resolver o arquivo.

Ícones inline fazem parte do cache da página Drupal; a invalidação da página
atualiza também seus vetores. Assets CSS/JS seguem as Drupal libraries, versão
de library e agregação do Drupal. Use cache longo imutável somente para URLs
agregadas/identificadas por conteúdo; arquivos de caminho estável precisam de
TTL menor ou purge no deploy. Não há hostname de CDN fixado no tema.

O shell recebe contagens e dados do carrinho do Portal com contextos de cache
`cart`, `user` e `session`; Messenger pode tornar a resposta personalizada com
max-age zero para contas. Um reverse proxy deve respeitar `Cache-Control`,
cache contexts e invalidação, e nunca compartilhar uma resposta privada ou
com cookie de sessão/carrinho entre pessoas. Purge por tags requer a integração
Drupal/CDN do ambiente; o tema não presume nem anuncia headers `Surrogate-Key`
ou `Purge-Tags` por conta própria.

## Origem e uso

- `logos/aculta420-horizontal-black.png`, `logos/aculta420-horizontal-white.png`,
  `logos/aculta420-square-black.png` e `logos/aculta420-square-white.png` são
  cópias de, respectivamente, `logo-aculta420-horizontal-preto.png`,
  `logo-aculta420-horizontal-branco.png`, `logo-aculta420-quadrada-preta.png`
  e `logo-aculta420-quadrada-branca.png` em
  `web/themes/custom/aculta420/assets/branding/aculta420/`.
- `logos/aculta420-horizontal-white-900x300.png` copia
  `logo-aculta420-horizontal-branco-900x300.png` da V1.
- `logos/aculta420-horizontal-white-78x26.webp` e
  `logos/aculta420-horizontal-white-156x52.webp` copiam as densidades 1×/2×
  do logo usado na barra institucional legada.
- Os três arquivos `logos/wiki420-horizontal-*.webp` e as variantes
  `wiki420-stacked-*.webp` e `wiki420-globe-*.png` foram copiados da identidade
  Wiki420 da V1. O Domain Header usa os logos horizontais por `srcset`; os
  demais tamanhos ficam disponíveis para contextos responsivos futuros.
- `icons/aculta420-favicon.ico` é o favicon da V1.
- `icons/apple-touch-icon.png` foi criado a partir do bitmap de 32×32 do favicon
  da V1, ampliado com interpolação e centralizado em uma tela de 180×180 px com
  fundo `#f2f7f0` para uso em dispositivos Apple.
- `logos/aculta420-square-black-192.png` e
  `logos/aculta420-square-black-512.png` foram gerados do logo quadrado preto
  de alta resolução e são os ícones do `icons/site.webmanifest`.

O inventário da V1 não contém `site.webmanifest`, apple-touch-icon, um logo
separado do Coletivo 4e20 nem banners/texturas institucionais apropriados para
esta migração. O manifest é novo; ele usa apenas os assets ACULTA disponíveis.
As cores semânticas de marca permanecem nos tokens do tema, reconciliados com
a paleta institucional existente. O seletor explícito de modo claro/escuro
permanece o mecanismo de preferência.

## Fluent Emoji (Microsoft)

`icons/fluent-emoji/flat/` e `icons/fluent-emoji/color/` contêm o pacote curado
de SVGs oficiais do Microsoft Fluent Emoji, obtidos do repositório
`microsoft/fluentui-emoji`. A licença MIT está em
`icons/fluent-emoji/LICENSE`. Os arquivos são incorporados inline via SDC com
`source()` no HTML renderizado, sem requisições externas nem dependências de
terceiros em tempo de execução.
