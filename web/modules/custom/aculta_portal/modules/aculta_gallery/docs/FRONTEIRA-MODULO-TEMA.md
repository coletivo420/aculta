# ACULTA Gallery — fronteira entre submódulo, tema e configuração

Documento de dono. Diz quem é dono de cada parte da galeria e por quê. A regra geral do
Portal está em `docs/portal/FRONTEIRA-MODULO-TEMA.md`; aqui está a aplicação ao álbum e
ao palco (versão 0.1.0-rc.2).

## 1. Três donos

| Dono | Onde está | O que faz |
| --- | --- | --- |
| **Submódulo `aculta_gallery`** | `web/modules/custom/aculta_portal/modules/aculta_gallery/` | Dados, acesso, rotas, views de conteúdo, hooks, formulário de incorporação, coleção administrativa, *tamanho* das imagens (estilos) e *quais* fotos aparecem em que ordem. |
| **Tema `aculta420`** | `web/themes/custom/aculta420/` | Aparência: cores (tokens), layout do palco, painel de miniaturas, botão do celular, comportamento visual do slideshow. Não conhece entidade, view nem regra de acesso. |
| **Configuração do site** | `config/sync/` | Views, estilos de imagem e campos exportados. Cada item tem um dono nomeado abaixo. |

Contrib (`media_gallery`, `photoswipe`, `vvjs`/`views_vvjs`, `entity_embed`, `embed`) é dependência
de terceiros: o submódulo a configura, o tema estiliza o markup dela, e nenhum dos dois o altera.

## 2. Quem é dono de cada peça

| Peça | Dono | Arquivo | Motivo |
| --- | --- | --- | --- |
| Entidade `media_gallery`, campos, pathauto `/albuns/[title]` | submódulo | `config/install/*.yml` | Dados e URL pública pertencem ao domínio da galeria. |
| Acesso só na Revista (D13) | submódulo | `src/Hook/GalleryPurposeAccessHooks.php` | Regra de negócio de purpose. |
| Coleção administrativa, título apontando para a Revista | submódulo | `src/ListBuilder/AcultaGalleryListBuilder.php`, `src/Hook/GalleryListBuilderHooks.php` | Link é função; a edição continua no MAIN (política administrativa). |
| View `aculta_album_fotos` (fotos do álbum, paginação, VVJS, PhotoSwipe) | submódulo (config) | `config/sync/views.view.aculta_album_fotos.yml` | Define o conteúdo do palco. |
| View `aculta_albuns` (listagem `/albuns`) | submódulo (config) | `config/sync/views.view.aculta_albuns.yml` | Lista de álbuns; filtro de purpose está no hook de listagem. |
| Palco e miniaturas (marcação) | submódulo | `src/Hook/GalleryViewHooks.php` | Monta `data-aculta-album-stage`, `data-aculta-album-thumbs` e `data-aculta-album-thumb`. |
| Estilo da foto grande no palco | submódulo (config) | `config/sync/image.style.media_gallery_stage.yml` | Largura do arquivo (1000 px) é decisão de conteúdo e peso. |
| Estilo da miniatura | submódulo (config) | `config/sync/image.style.media_gallery_thumb.yml` | Recorte quadrado de 160 px. |
| Estilo da imagem do lightbox | submódulo (config) | `config/sync/image.style.media_gallery_lightbox.yml` | Ampliação em 1600 px. |
| Cor da moldura do palco | tema | `css/tokens.css`, token `--aculta-media-stage-bg` | Cor é token; claro e escuro têm valores próprios. |
| Layout do palco, painel, botão do celular | tema | `css/components/album-slideshow.css` | Aparência. |
| Comportamento: clique na miniatura, miniatura ativa, botão que recolhe o painel | tema | `js/album-slideshow.js` | Comportamento visual. Usa só os atributos `data-*` do módulo. |
| Biblioteca do palco carregada em todas as páginas | tema | `aculta420.info.yml` (`aculta420/album-slideshow`) | Ver §4. |

## 3. Contrato entre módulo e tema

O módulo entrega marcação com atributos `data-*` (comportamento). O tema escolhe a aparência.

| Atributo | Quem põe | Quem usa | Significado |
| --- | --- | --- | --- |
| `data-aculta-album-stage` | módulo (`GalleryViewHooks`) | tema (CSS e JS) | Contêiner que junta o slideshow e o painel. |
| `data-aculta-album-thumbs` | módulo | tema (CSS) | Painel de miniaturas. |
| `data-aculta-album-thumb="N"` | módulo | tema (JS e CSS) | Miniatura N (1 a total de fotos). Corresponde ao slide N do VVJS. |
| `aria-current` na miniatura | tema (JS) | tema (CSS) | Miniatura do slide atual. |
| `data-aculta-album-toggle` | tema (JS) | tema (CSS) | Botão do celular que recolhe o painel. Criado pelo JS; o módulo não o conhece. |

Regra: o módulo não põe classe de design (`bi-`, `btn`, `col-`, etc.) nem CSS/JS. O tema não consulta
entidade, view, purpose nem nome de view do módulo.

## 4. Pontos de atenção (estado)

- **Biblioteca global (aberto, aceito).** O palco usa `aculta420/album-slideshow`, declarada em
  `aculta420.info.yml`, para não depender do nome da view do módulo. O CSS e o JS (poucos KB) carregam
  em todas as páginas do tema; o JS só age onde há `data-aculta-album-stage`. Se o peso incomodar,
  trocar por verificação no preprocess do tema (`#[Hook('preprocess_page')]`), sem nome de view.
- **Acoplamento com o VVJS (mitigado por gate).** O CSS do palco usa a marcação do `views_vvjs`
  (contrib). `scripts/validate-album-stage-contract.php` confere que seletores `.vvjs-*` existem no
  VVJS instalado, que o elemento `vvjs-slideshow` conserva a API pública `goToSlide()` e
  `getCurrentSlide()`, e que o evento VVJ Core `vvj:slideChanged` permanece disponível. Mudança
  upstream incompatível deve falhar no gate, sem editar o módulo contrib.
- **Altura e estabilidade no celular.** O palco VVJS usa moldura de altura estável e centraliza cada
  foto com `object-fit: contain`; o painel móvel de miniaturas continua recolhível. O enquadramento
  preserva a imagem, e a altura não salta ao mudar entre retrato e paisagem.
- **Miniaturas de 66 fotos.** O módulo gera as miniaturas no HTML, com `loading="lazy"`. Medição em
  `docs/ARQUITETURA.md` (fase 7).
- **Contrato `data-*` (verificado).** Coberto pelo gate da seção 3. Novo atributo entra nesta tabela
  antes do código.

## 5. Versionamento

- Módulo: `0.1.0-rc.2` (ver `CHANGELOG.md` do submódulo).
- Tema: versão atual registrada em `aculta420.info.yml`, marcada também em `CHANGELOG.md`,
  `docs/roadmap.md` e no gate de Foundation (regra em `web/themes/custom/aculta420/docs/versioning.md`).
