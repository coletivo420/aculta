# Wiki420 — logomarcas e assets

Status: **assets versionados; integração validada em navegador (0.4.0-dev.6)**. Sem tag.

## Origem

Artes fornecidas pelo responsável do projeto, empacotadas em `wiki420-logomarcas.zip` (não versionado; todos os arquivos úteis estão no repositório).

- Horizontal: composição com globo de quebra-cabeça e wordmark, original 2048×699 RGBA. Usada no Domain Header.
- Empilhada: composição quadrada 1024×1024 RGBA. Usada em espaços compactos.
- Os originais citados no manifesto (`/mnt/data/...`) não vieram no pacote. Os masters em `source/` são as maiores versões rasterizadas recebidas, cortadas nas margens transparentes e reamostradas com LANCZOS. Não há vetorização.

## Arquivos

Pasta `assets/branding/wiki420/web/` (aplicação na web):

| Arquivo | Uso |
| --- | --- |
| `wiki420-horizontal-960w.webp` | Header, alta densidade e fallback principal (`src`) |
| `wiki420-horizontal-720w.webp` | Header, `srcset` para 2× |
| `wiki420-horizontal-480w.webp` | Header, `srcset` para 1× |
| `wiki420-stacked-256w.webp` | Compacto |
| `wiki420-stacked-512w.webp` | Compacto em alta densidade |
| `wiki420-globe-32.png`, `-48.png` | Favicon |
| `wiki420-globe-96.png` | Ícone de tela |
| `wiki420-globe-192.png`, `-512.png` | Ícone de app |

Pasta `assets/branding/wiki420/source/` (masters, não servidos pelo header):
`wiki420-horizontal-960w.png` e `wiki420-stacked-512w.png`.

Não entram: 240w e 320w (sem consumidor), o globo de 180 px (a tabela de aplicações não o pede) e os PNGs de 480/720 px da horizontal (WebP cobre todos os navegadores de interesse).

## Integração

- O Portal (`DomainPresentationBuilder`) escolhe a identidade Wiki pelo purpose `wiki`. Só nesse purpose monta `regions.brand_media` como render array `image` com `srcset` (480/720/960 px) e `sizes="18rem"`, `loading="eager"` e `fetchpriority="high"`.
- O tema consome `domain_presentation.regions.brand_media` em `ThemeHooks::preprocessPage()` e renderiza o link para `domain_presentation.identity.home_url`. Não inspeciona hostname, Domain ID ou purpose.
- Se o arquivo de 960 px não existir, o Portal não produz `brand_media`, e o branding textual anterior continua.
- Quando há `brand_media`, o bloco `system_branding_block` é removido do cabeçalho e o fallback textual não é montado (`ThemeHooks::preprocessPage()`); o cabeçalho tem um único link para a home. Nos demais purposes `brand_media` é nulo e o branding ACULTA permanece.
- A URL é pública (`base:`), não caminho físico.
- Cacheability: a presentation já carrega o cache de Domain; o Portal a anexa à página.
- Limite conhecido: o Portal conhece o caminho de `assets/branding/wiki420/web` no tema. A escolha de identidade é do Portal, mas a localização do arquivo é do tema; o acoplamento fica registrado para uma fase que defina um contrato de assets.

## Verificação (Homelab, 0.4.0-dev.6)

- Wiki (`wiki420.aculta.toca.net.br`): imagem com `srcset` no HTML; em 1× o Chromium carregou a variante de 480 px. A escolha em 2× não foi verificada no DOM.
- Um único link para a home no cabeçalho (fallback textual removido quando há marca).
- Cabeçalho: 159 px no desktop (1280 px, modo claro) contra 139 px na principal, +20 px; 141 px no mobile (390 px). Sem rolagem horizontal em 1280 e 390 px. Modo escuro: mesmos tokens e estrutura; altura não medida separadamente.
- Modo escuro: a marca fica legível sobre o carvão. Não houve transformação de cor. Razão de contraste não medida (risco residual).
- Isolamento de cache: requisições anônimas alternadas Wiki/principal em duas rodadas; a Wiki sempre mostra a logo, a principal nunca.
- Limitação: o zoom de 200% foi representado por largura de 390 e 820 px; não foi testado com zoom real do navegador.

## Regras

- Não substituir as logomarcas por imagens da internet.
- Não aplicar filtros CSS nem transformar cores da marca.
- Não converter raster em SVG.
- Não criar seletor por purpose no Twig ou no CSS.
