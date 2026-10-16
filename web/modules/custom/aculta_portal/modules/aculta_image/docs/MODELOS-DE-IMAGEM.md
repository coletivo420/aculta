# Modelos de imagem no texto

Status em 2026-10-16 (0.1.0-dev.22): **implementado e verificado no Runtime.** Firefox confirmou seletor, persistência
do modelo e da legenda, upload convertido de 1600×800 para 1200×600 e renderização pública. Os quatro modelos foram
medidos a 1366, 500 e 390 px, em temas claro e escuro; Firefox/Orca/AT-SPI verificaram teclado e semântica acessível.
Pendências de operação e dependências externas: [HANDOFF.md](HANDOFF.md), seção 6.

## 1. Objetivo

Ao inserir uma imagem no texto de uma publicação, quem edita escolhe um **modelo** de exibição, como nos grandes veículos
digitais. O sistema gera os tamanhos (`srcset`) e o HTML a partir do modelo. Quem edita não escolhe pixels.

## 2. Padrão observado nos veículos (HTML público, 2026-10-17)

| Veículo | Larguras servidas (srcset) | Observação |
| --- | --- | --- |
| BBC | 240, 320, 480, 640, 800, 1024, 1536 | corpo usa até 1024; 1536 para destaque |
| Guardian | 465, 645, 725, 965, 1065, 1225, 1920 | imagem de matéria até 1920 |
| Folha | 320, 480, 768, 1024, 1200, 2400 | 2400 para largura total |

Padrões comuns: legenda por imagem; imagem de corpo na largura da coluna ou um pouco acima; destaque em largura maior que
a coluna; `srcset` com dois ou mais tamanhos; imagem de compartilhamento em 1200×630 com recorte.

Estas medições são de páginas públicas e não são documentação oficial dos veículos.

## 3. Modelos do ACULTA (implementados)

Coluna de leitura da Revista: `68ch`, cerca de 580 px (`revista-post.css`). Retina exige cerca de 1160 px.

| Modelo | Largura de exibição | Tamanhos gerados (srcset, 1x/2x) | Uso |
| --- | --- | --- | --- |
| Pequena | 50% da coluna (cerca de 290 px) | 320 e 640 | ao lado de texto |
| Padrão | coluna (cerca de 580 px) | 480 e 1160 | imagem do corpo, centralizada |
| Larga | cerca de 900 px, sai da coluna | 800 e 1200 | foto que precisa de respiro |
| Destaque | largura total do conteúdo | 1024 e 1200 | abertura de seção dentro do texto |

**Destaque em 1200 px, não 1536.** O limite de upload de 1200 px vale para todos os modelos (decisão do responsável). Um
destaque acima de 1200 px exigiria original maior, o que não foi autorizado.

Legenda: **sim, em todos os modelos.** Crédito: **removido** de todos os modelos (decisão do responsável, 2026-10-17).

Os estilos são calculados a partir do original enviado. Se o original for menor que a largura do estilo, o Drupal não
amplia a imagem (`upscale` falso), e o `srcset` usa o maior tamanho disponível. Originais maiores que 1200 px são
reduzidos no upload (ver seção 5).

## 4. Arquitetura adotada

**Código próprio no `aculta_image`** (decisão de 2026-10-17, recomendação mantida):

- Plugin CKEditor 5 `acultaImage.ImageModel` em JavaScript simples, sem build. Usa os globais `CKEditor5.dll` e
  `CKEditor5.image` que o core expõe. Grava o modelo em `data-aculta-modelo`.
- O botão contextual “Editar legenda” grava `data-aculta-legenda`; o filtro converte o atributo em `<figcaption>`. Firefox
  no Runtime confirmou gravação, persistência ao reabrir o editor e renderização pública.
- Classe PHP `Plugin\CKEditor5Plugin\ImageModel` (configuração por editor: quais modelos aparecem).
- Filtro `aculta_imagem_modelo` que transforma o `<img>` em `<figure>` com `srcset`, `sizes` e `figcaption`.

Alternativa avaliada e não adotada: `ckeditor_media_resize` 1.1.1 (GPL-2.0-or-later). Exige migrar as imagens do texto
para mídia (embed) e não cobre legenda nem alinhamento. Se algum trecho for portado no futuro, registrar a atribuição
(GPL) no módulo.

## 5. Redução de largura no upload

O core recusava imagens acima do limite de dimensão (`FileImageDimensions`). Decisão: **aceitar e converter**.

- `max_dimensions` do core desligado nos editores com upload (`usuario_aculta`, `revista_editorial`).
- `InlineImageHooks` (`file_insert` em `public://inline-images/`) chama `ImageCompressor::redimensionar()`, que roda
  `mogrify -resize 1200x>`: reduz só se a largura passar de 1200 px, mantendo a proporção.
- Depois, a compressão sem perda (`optipng`, `jpegoptim`, `gifsicle`).
- Não usa `ImageFactory`: a regra do projeto proíbe processamento de imagem por esse caminho.

## 6. Formatos e editores

| Formato | Botão de modelo | Modelos | Uso |
| --- | --- | --- | --- |
| `revista_editorial` | sim | todos | textos editoriais |
| `usuario_aculta` (Usuário ACULTA) | sim | Pequena e Padrão | tópicos e respostas do Fórum; permissão direta para `email_confirmed`, herdada por `estudante`, `associado`, `lms_teacher` e `content_editor` |
| `webform_default` | não (sem imagem) | — | fora deste sistema |

Tamanhos provisórios do Fórum: Pequena (320 e 640 px) e Padrão (480 e 1160 px), com a mesma política de redimensionamento
(1200 px de largura no upload). A pesquisa sobre limites de fóruns (Discourse, citado na conversa) não encontrou valor
oficial. Os números da comunidade (690 × 500 px) não foram adotados. Medir em uso antes de revisar.

## 7. Fases

| Fase | Entrega | Estado |
| --- | --- | --- |
| P1 | Catálogo dos modelos e estilos `aculta_corpo_*` | **feito** (exportados no pipeline `aculta_derivados`) |
| P2 | Filtro PHP com `<figure>`, `srcset` e legenda (sem crédito) | **feito** (sem JavaScript na página pública) |
| P3 | Plugin CKEditor 5 com seletor de modelo | **feito**: seletor, gravação/reabertura, upload e renderização pública confirmados; quatro modelos aferidos a 390 px |
| P3b | Campo contextual de legenda | **feito**: gravação, persistência ao reabrir e `<figcaption>` público confirmados |
| P4 | CSS dos modelos no ACULTA420 (claro e escuro, mobile primeiro) | **feito**: página pública aferida a 1366/500/390 px, claro e escuro, sem overflow |
| P5 | Acessibilidade: `alt` obrigatório, legenda com `figcaption`, teclado | **feito**: Firefox e Orca/AT-SPI verificaram texto alternativo, legenda e operação por teclado |
| P6 | Formato próprio para usuários e confirmação de e-mail | **feito**: permissão direta em `email_confirmed`; herança efetiva para estudante, associado, professor e editor de conteúdo |

## 8. Riscos

- Textos já publicados: imagens antigas continuam como estão. O modelo vale para imagens novas ou editadas, a não ser que
  haja migração.
- As referências `basic_html` e `full_html`, inclusive revisões, foram migradas para os formatos ACULTA pelo update `aculta_portal_update_11009`.
- O filtro precisa rodar antes do filtro de limpeza de HTML do formato, senão remove o `<figure>`. Os formatos ACULTA
  configurados usam peso 20 para o filtro do modelo.
- O downcast do atributo para o `<img>` e a persistência após salvar/reabrir foram confirmados no Firefox. O upload real
  foi exercitado em `revista_editorial`; a revisão visual pública anônima verificou os quatro modelos.

## 9. Decisões de 2026-10-17 (responsável)

- **Crédito removido** de todos os modelos. Fica só a legenda.
- **Destaque em 1200 px**, sem subir o limite de upload.
- **Formatos:** os formatos próprios são `revista_editorial` e `usuario_aculta`. Este último oferece Pequena e Padrão e é aceito nos tópicos e respostas do Fórum. A permissão direta está em `email_confirmed`; a configuração vigente de herança inclui `estudante`, `associado`, `lms_teacher` e `content_editor`, mas não `gerente_loja`. Os valores existentes de `basic_html` e `full_html` foram migrados antes da remoção dos formatos Core.
- **Limite de 1200 px:** o editor aceita imagens maiores e reduz para 1200 px de largura.
- **Plugin em JavaScript simples**, sem toolchain npm.
- **Fórum:** tamanhos provisórios de Pequena e Padrão.
