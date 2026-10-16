# Modelos de imagem no texto (revisão do plano, 2026-10-17)

Status: **planejamento**. Nada foi implementado nesta revisão. Decisão de arquitetura pendente (seção 4).

## 1. Objetivo

Ao inserir uma imagem no texto de uma publicação, o editor escolhe um **modelo** de exibição, como nos grandes veículos
digitais. Cada modelo tem largura, enquadramento, alinhamento, legenda e crédito. O sistema gera os tamanhos (srcset) e
o HTML a partir do modelo; o editor não escolhe dimensão em pixels.

## 2. Padrão observado nos veículos (HTML público, 2026-10-17)

| Veículo | Larguras servidas (srcset) | Observação |
| --- | --- | --- |
| BBC | 240, 320, 480, 640, 800, 1024, 1536 | corpo usa até 1024; 1536 para destaque |
| Guardian | 465, 645, 725, 965, 1065, 1225, 1920 | imagem de matéria até 1920 |
| Folha | 320, 480, 768, 1024, 1200, 2400 | 2400 para largura total |

Padrões comuns: legenda e crédito por imagem; imagem de corpo na largura da coluna ou um pouco acima; destaque em
largura maior que a coluna; `srcset` com dois ou mais tamanhos; imagem de compartilhamento em 1200×630 com recorte.

## 3. Modelos propostos para o ACULTA

Coluna de leitura da Revista: `68ch`, cerca de 580 px (`revista-post.css`). Retina exige cerca de 1160 px.

| Modelo | Largura de exibição | Tamanhos gerados (srcset, 1x/2x) | Uso |
| --- | --- | --- | --- |
| Pequena | 50% da coluna (cerca de 290 px) | 320 e 640 | ao lado de texto (com alinhamento à esquerda ou à direita) |
| Padrão | coluna (cerca de 580 px) | 480 e 1160 | imagem do corpo, centralizada |
| Larga | cerca de 900 px, sai da coluna | 800 e 1200 | foto que precisa de respiro |
| Destaque | largura total do conteúdo | 1024 e 1536 | abertura de seção dentro do texto |

Opções por imagem: **legenda** e **crédito** (campos de texto), **alinhamento** (esquerda, centro, direita, só em
Pequena), e **link** opcional para a imagem ampliada (galeria da lightbox).

Limite de upload e de dimensão: já padronizados (10 MB; 1200 px de largura no upload). O modelo **Destaque** pode
exigir um original maior; a decisão sobre isso está na seção 5.

## 4. Decisão de arquitetura (pendente)

**Opção A: `ckeditor_media_resize` 1.1.1 (comunidade).** Estável, suporta Drupal 11.4, com cobertura de segurança.
Redimensiona imagens incorporadas como mídia, com estilos. Exige migrar as imagens do texto para o formato de mídia
(embed), e não cobre o modelo com legenda, crédito e alinhamento. Os uploads diretos atuais ficariam com o comportamento
antigo até a migração.

**Opção B: código próprio no `aculta_image` (recomendada).** Um plugin de CKEditor 5 com um seletor de modelo na barra
de imagem, e um filtro de texto que transforma o modelo em `<figure>` com `srcset` explícito (sem o problema de ordem
alfabética da issue #3523451). Funciona com os uploads diretos atuais, sem migrar acervo. Custo maior: JavaScript do
plugin (registrado por `hook_ckeditor5_plugin_info`), filtro PHP, CSS dos modelos no tema e testes nos três editores.

Recomendação: **B**, pela mesma razão da compressão: controle total do HTML, sem dependência de mídia, e alinhada à
política de código próprio para imagens do texto.

## 5. Estilos necessários (B)

Criar, com pipeline `aculta_derivados` e catálogo:

- `aculta_corpo_320` (320 px) e `aculta_corpo_640` (640 px): modelo Pequena;
- `aculta_corpo_480` (480 px) e `aculta_corpo_1160` (1160 px): modelo Padrão;
- `aculta_corpo_800` (800 px) e `aculta_corpo_1200` (1200 px): modelo Larga;
- `aculta_corpo_1024` (1024 px) e `aculta_corpo_1536` (1536 px): modelo Destaque.

1536 px está acima do limite de 1200 px de upload. Duas saídas: (a) o modelo Destaque usa no máximo 1200 px; (b) elevar o
upload para 1536 px só para o modelo Destaque. Decisão pendente.

Os estilos são calculados a partir do original enviado. Se o original for menor que a largura do estilo, o Drupal não
amplia a imagem (`upscale` falso), e o srcset usa o maior tamanho disponível.

## 6. Fases (se a opção B for aprovada)

| Fase | Entrega | Aceite |
| --- | --- | --- |
| P1 | Catálogo dos modelos e estilos `aculta_corpo_*` | estilos exportados; gate passa |
| P2 | Filtro PHP que transforma o modelo em `<figure>` com srcset, legenda e crédito | HTML válido; sem JavaScript na página pública |
| P3 | Plugin CKEditor 5 com seletor de modelo, legenda, crédito e alinhamento | seleção grava o modelo; edição reabre com o modelo certo, nos três editores |
| P4 | CSS dos modelos no ACULTA420 (claro e escuro, mobile primeiro) | teste visual em 390 px e 1280 px |
| P5 | Acessibilidade: `alt` obrigatório, legenda ligada com `figcaption`, teclado | revisão com teclado e leitor de tela |

## 7. Riscos

- Mudança no HTML de textos já publicados: imagens antigas continuam como estão; o modelo vale só para imagens novas
  ou editadas, a não ser que haja migração.
- Legenda e crédito exigem campo novo no editor: alterar a configuração de três formatos de texto.
- O filtro precisa rodar antes do de limpeza de HTML do formato, senão remove o `<figure>`.


## Decisões de 2026-10-17 (responsável)

- **Crédito removido** de todos os modelos. Fica só a legenda (`figcaption`).
- **Formatos:** o HTML completo e o Revista — editorial têm o botão de modelo. O HTML básico ficará sem o botão até o formato próprio para usuários comuns e alunos. O formato `usuario_aculta` (Usuário ACULTA) oferece só **Pequena** e **Padrão**, e é o único formato aceito nas respostas do Fórum.
- **Fórum:** tamanhos provisórios de Pequena (320 e 640 px) e Padrão (480 e 1160 px), com a mesma política de redimensionamento dos editores. A pesquisa sobre limites de fóruns (Discourse) não confirmou valores oficiais; os números da comunidade não foram adotados.
- **Limite de 1200 px:** o editor aceita imagens maiores e reduz para 1200 px de largura (`ImageCompressor::LARGURA_MAXIMA`), em vez de recusar o upload. A validação de dimensão do core foi desligada nos editores com upload para isso.
