# Modelos de imagem no texto

Status em 2026-10-16 (0.1.0-dev.14): **parcialmente implementado.** Runtime atualizado; Firefox confirmou a gravação do
modelo nos três editores e a persistência de `larga` ao reabrir. Upload real de 1600×800 resultou em 1200×600. CSS dos
quatro modelos aferido em fixture local a 1366/500 px e nos modos claro/escuro. Ainda faltam viewport útil de 390 px,
leitor de tela e uma revisão numa página pública com sessão válida. Próximos passos: [HANDOFF.md](HANDOFF.md), seção 6.

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
- O botão contextual “Editar legenda” grava `data-aculta-legenda`; o filtro converte o atributo em `<figcaption>`. A UI
  serializa corretamente; falta conferir renderização pública e persistência da legenda ao reabrir.
- Classe PHP `Plugin\CKEditor5Plugin\ImageModel` (configuração por editor: quais modelos aparecem).
- Filtro `aculta_imagem_modelo` que transforma o `<img>` em `<figure>` com `srcset`, `sizes` e `figcaption`.

Alternativa avaliada e não adotada: `ckeditor_media_resize` 1.1.1 (GPL-2.0-or-later). Exige migrar as imagens do texto
para mídia (embed) e não cobre legenda nem alinhamento. Se algum trecho for portado no futuro, registrar a atribuição
(GPL) no módulo.

## 5. Redução de largura no upload

O core recusava imagens acima do limite de dimensão (`FileImageDimensions`). Decisão: **aceitar e converter**.

- `max_dimensions` do core desligado nos editores com upload (`basic_html`, `full_html`, `revista_editorial`, `usuario_aculta`).
- `InlineImageHooks` (`file_insert` em `public://inline-images/`) chama `ImageCompressor::redimensionar()`, que roda
  `mogrify -resize 1200x>`: reduz só se a largura passar de 1200 px, mantendo a proporção.
- Depois, a compressão sem perda (`optipng`, `jpegoptim`, `gifsicle`).
- Não usa `ImageFactory`: a regra do projeto proíbe processamento de imagem por esse caminho.

## 6. Formatos e editores

| Formato | Botão de modelo | Modelos | Uso |
| --- | --- | --- | --- |
| `full_html` (HTML completo) | sim | todos | editores |
| `revista_editorial` | sim | todos | textos de revista |
| `basic_html` (HTML básico) | **não** | — | sem modelo até o formato próprio para usuários comuns e alunos |
| `usuario_aculta` (Usuário ACULTA) | sim | Pequena e Padrão | respostas do Fórum e formato dos usuários comuns |
| `webform_default` | não (sem imagem) | — | fora deste sistema |

Tamanhos provisórios do Fórum: Pequena (320 e 640 px) e Padrão (480 e 1160 px), com a mesma política de redimensionamento
(1200 px de largura no upload). A pesquisa sobre limites de fóruns (Discourse, citado na conversa) não encontrou valor
oficial. Os números da comunidade (690 × 500 px) não foram adotados. Medir em uso antes de revisar.

## 7. Fases

| Fase | Entrega | Estado |
| --- | --- | --- |
| P1 | Catálogo dos modelos e estilos `aculta_corpo_*` | **feito** (exportados no pipeline `aculta_derivados`) |
| P2 | Filtro PHP com `<figure>`, `srcset` e legenda (sem crédito) | **feito** (sem JavaScript na página pública) |
| P3 | Plugin CKEditor 5 com seletor de modelo | **botões e gravação testados no Runtime; upload e reabertura confirmados; faltam viewport de 390 px, tema e acessibilidade** |
| P3b | Campo contextual de legenda | **implementado e serializado; falta verificar renderização pública e reabertura** |
| P4 | CSS dos modelos no ACULTA420 (claro e escuro, mobile primeiro) | **implementado; fixture aferida a 1366/500 px em claro/escuro; falta revisar em página pública** |
| P5 | Acessibilidade: `alt` obrigatório, legenda com `figcaption`, teclado | pendente |
| P6 | Formato próprio para o HTML básico | pendente (decisão de perfis) |

## 8. Riscos

- Textos já publicados: imagens antigas continuam como estão. O modelo vale para imagens novas ou editadas, a não ser que
  haja migração.
- Respostas do Fórum já escritas em `basic_html`: ao editar, o formulário oferece só `usuario_aculta`. Testar antes de liberar.
- O filtro precisa rodar antes do filtro de limpeza de HTML do formato, senão remove o `<figure>`. No `usuario_aculta` e no
  `basic_html` a ordem é a do config (peso 20 para o modelo).
- O downcast do atributo para o `<img>` foi confirmado nos três editores no Firefox. A persistência após salvar/reabrir
  foi confirmada em `full_html`; o upload real foi confirmado em `revista_editorial`.

## 9. Decisões de 2026-10-17 (responsável)

- **Crédito removido** de todos os modelos. Fica só a legenda.
- **Destaque em 1200 px**, sem subir o limite de upload.
- **Formatos:** `full_html` e `revista_editorial` têm o botão. `basic_html` fica sem o botão até o formato próprio.
  `usuario_aculta` oferece só Pequena e Padrão, e é o único formato aceito nas respostas do Fórum.
- **Limite de 1200 px:** o editor aceita imagens maiores e reduz para 1200 px de largura.
- **Plugin em JavaScript simples**, sem toolchain npm.
- **Fórum:** tamanhos provisórios de Pequena e Padrão.
