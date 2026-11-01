# Podplant420 — Política de assets visuais (ACULTA420)

Status: **assets integrados e validados offline** (SHA-256, decodificação, transparência, dimensões e duplicatas). **Nenhum template, CSS ou library os consome nesta fase**; QA visual em navegador e runtime Drupal estão **DEFERRED**. A inclusão desses arquivos não altera cabeçalhos, Institution Bar, Domain Header nem Domain purpose.

## Origem e propriedade

Quatro PNG RGBA de 1024×1024 foram fornecidos diretamente pelo responsável do projeto em 2026-10-09: Branco Quadrado, Preto Quadrado, branco retângulo, Preto retângulo. Autoria e licenças: as artes são produzidas pelo coletivo420 (ACULTA), que detém as licenças de uso, conforme informado pelo responsável do projeto em 2026-10-09. Não houve busca ou geração de novas marcas. O ZIP `podplant420-logomarcas.zip` mencionado nas instruções não estava disponível nesta sessão; foram utilizadas **as quatro imagens anexadas**, e os derivados aqui empacotados. A identidade de marca não foi redesenhada.

## Estrutura e variantes

`assets/branding/podplant420/web/stacked/`: WebP RGBA 128, 256 e 512 px, em on-dark/on-light. `assets/branding/podplant420/web/horizontal/`: WebP RGBA 240 e 480 px, em on-dark/on-light (runtime do tema). Pacote de handoff fora do tema, em `handoff/podplant420/`: originais PNG intocados em `source/originais/` (4), masters PNG em `source/masters/` (4) e WebP horizontais 720/960 px (4). Total: **22 imagens** (10 runtime + 12 handoff). Inventário com SHA-256 em `handoff/podplant420/asset-inventory.json`, com caminhos relativos à raiz.

Somente os 10 WebP de runtime ficam em `assets/branding/podplant420/web/`, dentro do tema. O pacote de handoff (originais, masters e 720/960 px) fica fora do tema, em `handoff/podplant420/`, porque nenhum template, CSS ou library o consome nesta fase e o tema não deve carregar binários sem consumidor. Um consumidor futuro que precisar de 720/960 px copia o arquivo para o tema no mesmo PR que o referencia.

| Família | Variantes | Usos previstos |
| --- | --- | --- |
| `stacked-on-dark` | WebP 128/256/512 | cards, thumbnails de identidade sobre fundo escuro |
| `stacked-on-light` | WebP 128/256/512 | cards sobre superfície clara |
| `horizontal-on-dark` | WebP 240/480 (runtime); 720/960 (handoff) | vitrines, seções e chamadas largas sobre escuro |
| `horizontal-on-light` | WebP 240/480 (runtime); 720/960 (handoff) | vitrines, seções e chamadas largas sobre claro |

O sufixo `w` significa largura de arquivo, não densidade do dispositivo. Conservar a proporção intrínseca da composição (`object-fit: contain`; não esticar, cortar elementos da logo ou adicionar fundo opaco). O logo quadrado contém margens compositivas; não gerar favicon nem ícone isolado sem aprovação de identidade e consumidor.

## Responsividade, acessibilidade e cor

Oferecer `srcset`/`sizes` somente em um consumidor real e contextualizado; deixar o navegador decidir resolução. Evitar preload e `fetchpriority=high` em cards/itens fora do LCP. Usar `loading=lazy` fora da região crítica, dimensões explícitas ou `aspect-ratio` para prevenir CLS. Definir `alt` contextual, por exemplo `Podplant420` quando a imagem agrega identificação; usar `alt=""` se o link ou título adjacente já nomear o projeto e a marca for decorativa. Não duplicar rótulos para leitor de tela. Registrar crédito/licença em Media para conteúdo editorial, não em markup improvisado.

`on-dark` é a marca **clara** para fundo escuro, `on-light` a marca **escura** para fundo claro. Não usar CSS `filter` ou recolorir a arte, inclusive o caule verde. Em light/dark, manter posição, fonte, tamanhos e alinhamento; apenas a escolha da variante pode mudar pelo estado semântico do consumidor. Validar legibilidade em fundos reais do design system. Não declarar contraste numérico sem medir.

## Drupal 11+ e arquitetura

Branding estático institucional permanece no tema; episódios, vídeos, convidados e thumbnails de conteúdo pertencem a Media/File API, com Image Styles e Responsive Image, alt, licença, origem e cache tags adequadas. Não criar catálogo editorial JSON, módulo novo, ou centenas de binários dentro do tema. O Portal prepara render arrays para SDCs neutros; componentes consomem props/slots sem branching por hostname/Domain purpose. Não tocar em Institution Bar/Domain Header e não criar purpose Podplant420 nesta fase.

## Cache e operação

Assets estáticos versionados: fingerprint/revisão de deploy e caching HTTP ordinário do servidor, sem cache invalidation manual para imagens imutáveis; quando substituir um asset, atualizar nome/URL ou política de cache. Em conteúdo dinâmico, cache tags/contexts/max-age vêm das entidades/Render API; não perder cacheability no presenter. Nenhuma carga global de library se não houver consumidor.

## Anti-regressão e validação

- Preserve quatro originais (compare SHA-256 com manifesto); não reprocessar imagens já aprovadas sem defeito reproduzível.
- Verificar integridade de decodificação, transparência, dimensões, proporção, peso e inexistência de duplicatas byte a byte.
- Não introduzir SVG falso, CSS de inversão de marca, URLs absolutas, layout diferente por modo de cor, dependência no cabeçalho ou caminhos locais de trabalho.
- Não declarar screenshot, runtime Drupal, gates PHP/Twig/SDC ou contrastes como PASS se não executados no homelab.
- O pacote inclui um validador local em `scripts/verify-podplant420-assets.py`; rodá-lo antes da PR.
- Ao integrar, atualizar README/índice e CHANGELOG **sem alterar versão do tema unicamente por estes assets** sem conferir a política vigente em `docs/versioning.md`.

## Necessidades editoriais ainda não atendidas

Não foram fornecidas capas de episódios, imagens de convidados, screenshots, artes de divulgação ou thumbnails de vídeos. Criar registros de pendência no fluxo editorial em vez de gerar imagens fictícias. Confirmar licença e créditos com o responsável.
