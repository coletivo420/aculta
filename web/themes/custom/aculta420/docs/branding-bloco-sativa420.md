# Branding Bloco Sativa 420 — kit de imagens

## Escopo e posição no site

Este kit reúne as artes institucionais do **Bloco Sativa 420**, para cards de projetos, páginas de projeto, conteúdo editorial e peças promocionais.

**As artes não fazem parte do cabeçalho principal do ACULTA.** Não são usadas no Domain Header, na Institution Bar, em nenhum purpose de domínio nem na identidade global do ACULTA420. Não criar Domain purpose próprio para o bloco.

## Origem, autoria e licença

- **Origem:** artes fornecidas pelo responsável do projeto na conversa de 2026-10-09 e empacotadas em `bloco-sativa420-assets.zip`. Não foram geradas nem modificadas além dos derivados descritos abaixo.
- **Autoria e licença: pendentes.** O pacote não comprova licença de uso nem créditos. O responsável deve confirmar autoria, licença e créditos antes de qualquer publicação externa. Esta pendência não é a mesma já registrada para Podplant420 e Baque Sativa, que o responsável informou como produzidos pelo coletivo420 (ACULTA).
- Novos usos ou versões das artes exigem registro desta documentação.

## Layout das variantes

Duas composições, ambas com fundo escuro incorporado:

| Família | Original | Proporção | Derivados WebP |
| --- | --- | --- | --- |
| Horizontal | `source/bloco-sativa420-horizontal-original.png`, 2048 × 682 | ≈ 3:1 | 640, 960, 1280 e 2048 px de largura |
| Quadrada | `source/bloco-sativa420-square-original.png`, 1024 × 1024 | 1:1 | 256, 512 e 1024 px |

Os derivados mantêm a proporção do original. Não há corte nem redimensionamento que deforme a arte.

## Desempenho (bytes do manifesto)

| Arquivo | Dimensões | Bytes |
| --- | --- | ---: |
| `web/horizontal/bloco-sativa420-horizontal-640w.webp` | 640 × 213 | 62.460 |
| `web/horizontal/bloco-sativa420-horizontal-960w.webp` | 960 × 320 | 116.128 |
| `web/horizontal/bloco-sativa420-horizontal-1280w.webp` | 1280 × 426 | 178.348 |
| `web/horizontal/bloco-sativa420-horizontal-2048w.webp` | 2048 × 682 | 322.212 |
| `web/square/bloco-sativa420-square-256w.webp` | 256 × 256 | 25.648 |
| `web/square/bloco-sativa420-square-512w.webp` | 512 × 512 | 62.654 |
| `web/square/bloco-sativa420-square-1024w.webp` | 1024 × 1024 | 142.474 |

Originais PNG (2,76 MB horizontal e 2,04 MB quadrado) não devem ser servidos em páginas. Servem apenas como fonte.

Processamento declarado no manifesto: redução LANCZOS e WebP com qualidade 88 e método 6, sem corte, filtro, vetorização ou recoloração.

## Alfa e fundo

- Todos os arquivos são opacos (alfa 255 em toda a faixa). O fundo escuro faz parte da arte.
- A arte **não é logotipo transparente**. Não aplicar sobre fundo claro sem decisão de design e sem validação visual.
- Não aplicar filtro CSS, inversão, recoloração ou sobreposição de fundo.

## Contraste e legibilidade

- A legibilidade do lettering e das faixas foi verificada visualmente, comparando original reduzido e derivado em 512 px (quadrado) e 640 px (horizontal). Não houve artefato ou perda visível do texto.
- Não foi medida razão de contraste numérica do lettering. A arte se apresenta sempre sobre o fundo escuro que ela traz.
- Sobre superfícies do site, a arte deve ocupar um bloco próprio com fundo escuro, sem texto sobreposto que dependa de contraste externo.

## Usos recomendados

- Cards de projetos e teasers (quadrado 256 ou 512).
- Página do projeto e seções editoriais (horizontal 960 a 1280, ou quadrado 512 a 1024).
- Peças promocionais e galerias.
- **Não** usar como logo do cabeçalho global.

## Acessibilidade e texto alternativo

- Alt contextual quando a arte identifica o bloco, por exemplo: `Bloco Sativa 420 — arte com participantes mascarados, folhagens e as cores verde, amarelo e vermelho`.
- `alt=""` quando a arte for decorativa e houver texto equivalente ao lado com o mesmo nome.
- Não repetir o nome do bloco em alt e em texto adjacente para leitor de tela.

## Responsividade

- Dimensione com `max-width: 100%` e `height: auto`, com `width` e `height` (ou `aspect-ratio`) no HTML ou no render array, para evitar CLS.
- Quando houver consumidor real com `srcset`, use as larguras reais dos derivados como descritor `w` (por exemplo `…640w.webp 640w`). Nenhum `srcset` é criado sem consumidor.
- Imagens fora da área crítica usam `loading="lazy"`.

## Cache

- Os arquivos são estáticos e versionados no repositório. Quando um arquivo mudar, altere o nome ou atualize o manifesto e o verificador, para que o cache do navegador não entregue a versão antiga.
- Imagens editoriais dinâmicas usam cache tags e contexts do Drupal, por meio da Media e do Image Style.

## Mídia editorial (fora do tema)

- A arte de um projeto ou de uma capa editorial pertence à **Media Library** (Field API `field_image`, Image Styles e Responsive Image), não a um caminho fixo do tema.
- Hoje o projeto "Carnareggae Bloco Sativa" (nó 2) não tem imagem definida (`field_image` vazio). A ligação com esta arte é feita pela Media, e não por edição de template.
- O tema não cria template, SDC nem CSS específico para o Bloco Sativa 420 nesta fase.

## Invariantes anti-regressão

- Os originais em `source/` não são alterados. Conferir SHA-256 contra `manifest.json` antes de qualquer reprocessamento.
- Os derivados não são reconvertidos sem defeito objetivo reproduzível.
- Nenhum filtro, recoloração, corte ou deformação é aplicado à arte.
- Nenhum caminho de produção, hostname ou lógica de Domain entra no tema por causa do bloco.
- Verificação determinística: `python3 scripts/verify-bloco-sativa420-assets.py`.

## Localização dos arquivos

- Kit: `web/themes/custom/aculta420/assets/branding/bloco-sativa420/`
  - `source/`: dois originais PNG.
  - `web/horizontal/`, `web/square/`: derivados WebP.
  - `manifest.json`: nome, tipo, dimensões, bytes e SHA-256 dos nove rasters.
  - `README.md`: resumo do pacote entregue.
- Verificador: `scripts/verify-bloco-sativa420-assets.py`.

## Limites desta entrega

- Nenhum template, SDC, CSS ou library consome o kit. Os arquivos estão prontos para uso.
- Autoria e licença pendentes (ver acima).
- Razão de contraste numérica não medida.
- QA visual em navegador e no runtime Drupal estão **DEFERRED**, para quando houver consumidor.
