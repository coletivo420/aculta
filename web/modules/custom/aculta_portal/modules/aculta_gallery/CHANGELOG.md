# Changelog — ACULTA Gallery

## Estado atual: Dev — 2026-10-21

`0.1.0-dev.13` registra a reabertura do desenvolvimento para integração global
de galerias e a integração contextual do Fórum. RC depende de implementação,
gates, revisão visual e validação pré-deploy completos.

## 0.1.0-dev.13 — galerias disponíveis em todos os purposes — 2026-10-21

Classificação: MINOR (expande o acesso público transversal; integração Forum em andamento).

- Decisão do responsável: galerias `media_gallery` publicadas podem ser abertas em todos os purposes do Portal. Substitui D13, que havia limitado visualização e listagem à Revista.
- Removidas as restrições de visualização e listagem por purpose; o list builder usa o alias local da galeria, sem direcionar para a Revista.
- Mapa de caminhos atualizado para `/albuns/{titulo}` em todos os purposes. Acesso runtime ainda não validado; sem deploy.
- Gates e testes integrados pendentes antes de RC.

## 0.1.0-rc.4 — diálogo "Criar galeria" sem tamanho no módulo — 2026-10-19

- `CreateGallery` não envia `dialogSettings` (largura, altura e classe). O formulário do diálogo traz `data-portal-ui="gallery-dialog"`, e a largura é do tema (`dialog.css`), conforme `POPUP-PADRAO.md`.
- Mudança visual: a largura passa a ser 40rem (limitada à tela), no lugar do `width: auto` anterior. Validação no navegador: pendente.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-rc.3 — alinhamento de acesso/cache e VVJS de produto — 2026-10-18

- A View `aculta_album_fotos` filtra as linhas por Media com acesso de visualização e File de imagem presente; miniaturas
  e slides usam a mesma regra e ordem `delta` do campo do álbum. O filtro falha fechado se a relação esperada não existir.
- O palco agrega metadados cacheáveis do álbum, cada Media, cada File e cada `AccessResult`, inclusive resultados negados,
  para evitar cache compartilhado de slides/miniaturas entre contextos de acesso incompatíveis.
- A View `galeria_produto` passa de VVJB para VVJS. Mantém o campo de IDs recebido do produto, o modo `produto_galeria`,
  a finalidade de imagem, o conjunto de controles e a imagem sem autoplay.
- O importador parcial continua bloqueado pelo tema órfão `bootstrap5` no `core.extension` ativo (o tema não existe no
  código); para limitar a alteração à View, seu objeto foi salvo pela Config API após backup do YAML ativo em `/tmp`.
- Runtime de teste: cache reconstruído; VVJS de produto renderizou 7 linhas (7.995 bytes). No host magazine, o álbum
  renderizou 63 miniaturas e 63 slides; o palco trouxe 128 tags e o contexto `user.permissions`, sem dependências
  Media/File/AccessResult ausentes.
- RUNTIME STATUS: smoke test PASS no Runtime `aculta_runtime`; validação visual em navegador e aparelho ainda pendente.

## 0.1.0-rc.2 — slideshow VVJS no palco do álbum — 2026-10-18

- A View `aculta_album_fotos` passa de VVJB a VVJS; preserva o pager até 150 fotos, o formatador PhotoSwipe e as regras de acesso da galeria.
- As miniaturas chamam a API pública `goToSlide()`; o estado atual é sincronizado pelo evento público `vvj:slideChanged` de VVJ Core. Removido o `MutationObserver` acoplado às classes internas do VVJB.
- O tema mantém o layout ACULTA420, tokens semânticos, ícones Bootstrap Icons, moldura estável e miniaturas recolhíveis em telas estreitas.
- Os carrosséis da Home e Projetos seguem em VVJB. VVJL não substitui PhotoSwipe.
- Módulo e View aplicados ao Runtime de teste; veja o plano do tema para estado das demais entregas e validação visual pendente.
- RUNTIME STATUS: módulos VVJ habilitados e cache reconstruído; View convertida após atualização direcionada. Verificação visual em navegador ainda pendente.

## 0.1.0-rc.1 — release candidate da ACULTA Gallery — 2026-10-15

Classificação: RC (fecha a Fase 10, validação de release no servidor de testes). Versão declarada pelo responsável; tag Git não criada (somente com pedido explícito).

- Fase 10 concluída no servidor de testes em duas rodadas de navegador (`docs/portal/TESTES-NAVEGADOR.md`, seção O):
  - permissões (visualização na Revista, 403 no principal, edição só pelo administrador), palco (66 slides, teclado, toque, sem erros de JavaScript), lightbox, diálogo "Criar galeria" (upload e "Criar e inserir"), inserção no artigo e renderização na Revista;
  - defeitos encontrados e corrigidos: lightbox `1 / 1` (PR #274); grupo "Imagem" fechado escondendo o campo obrigatório e o botão de mídia (PR #278); balão de dica sobre o menu do editor interceptando "Criar galeria" (PR #279).
- Reordenação por arrasto: não existe nesta versão; a ordem é o campo de peso do widget. Fora do escopo da RC.
- Pendente após a RC: teste com toque em aparelho real; rodada de regressão quando o servidor de testes for atualizado com os merges (configuração e código).
- RUNTIME STATUS: verificado no servidor de testes (duas rodadas de navegador, verificações por HTTP e banco). Inspeção visual humana pendente.

Submódulo opcional do `aculta_portal`. Linha 0.1.x em `-dev.N` até o RC.

## 0.1.0-dev.12 — palco do álbum e links para a Revista — 2026-10-14

Classificação: MINOR (nova apresentação do álbum e novos estilos de imagem; sem dependência nova). A versão no `aculta_gallery.info.yml` estava presa em `0.1.0-dev.5` e foi corrigida junto.

- Links da coleção administrativa de galerias (título): gerados no purpose `magazine` por `AcultaGalleryListBuilder` (`DomainPurposeManager::pathUrl`), trocado no `entity_type_alter` (`GalleryListBuilderHooks`). Verificado: títulos apontam para `revista420` com o alias do álbum.
- Edição e exclusão continuam no painel administrativo do MAIN. A política de domínio administrativo (`ADMIN-DOMAIN-POLICY.md`) não permite GET de `/painel-administrativo` no subdomínio: um link de edição na Revista seria redirecionado ao MAIN. Pendência de decisão do responsável.
- Palco do álbum (visual próximo ao original): foto grande com rotação automática de 3 s (VVJB), painel lateral de miniaturas em duas colunas, clique na miniatura leva ao slide, controles de play/pausa e contador. Celular: palco em largura total (400 px) e miniaturas recolhidas por botão.
- O slideshow percorre todas as fotos do álbum (paginação "algumas", limite de 150 fotos); antes mostrava só 24.
- Cor do palco: o verde escuro saiu. Token `--aculta-media-stage-bg`: areia quente `#e8dfcc` no claro (família do creme da marca, combina com o verde escuro e com a página) e preto-grafite `#0d0b0a` no escuro (mais escuro que a página). Moldura neutra, sem competir com a foto. Pesquisa de combinação: verde-floresta com creme e verde-limão como acento (fontes na conversa de 2026-10-14).
- Estilos de imagem: `media_gallery_stage` (1000 px de largura, palco) e `media_gallery_thumb` (160 px, recorte quadrado).
- Miniaturas geradas pelo módulo com `data-aculta-album-thumb` e `data-aculta-album-stage` (comportamento); aparência e JS de apresentação ficam no tema (`album-slideshow.css`, `album-slideshow.js`).
- Peso medido (antes do palco): HTML ~51 KB e 24 miniaturas ~381 KB com `loading="lazy"`. As 66 miniaturas do palco e do painel são carregadas com lazy loading; a foto grande só é baixada quando o slide aparece.
- Diferenças em relação ao original: não há botão de tela cheia no palco (o clique abre o PhotoSwipe), não há seta de rolagem do painel, e a altura no celular (400 px) e o recolhimento do painel abaixo de 480 px são aproximações.
- Verificado no Runtime (Firefox, 1366 px e 390 px): palco com 66 slides, clique na miniatura 12 leva ao slide 12 (`aria-current`), botão de miniaturas no celular.
- RUNTIME STATUS: verificado no Runtime (visual e navegação do palco; links da coleção por HTTP). Teste com toque real em aparelho ainda pendente.

## 0.1.0-dev.11 — acesso às galerias segue o padrão da Revista — 2026-10-14

- Permissões de galeria removidas do papel `content_editor` (Runtime e `config/sync`). Só o administrador acessa a coleção e cria ou edita galerias, como já acontece com artigo e blog. Supera a decisão R3, registrada em `docs/ARQUITETURA.md` §4.1.
- Verificado pelo gerenciador de acesso: administrador acessa; editor e conta sem papel editorial não acessam.

## 0.1.0-dev.10 — multidomínio e hardening — 2026-10-14

- Multidomínio (D13), verificado nos dois hosts: visualização do álbum (200 na Revista, 403 no host principal), listagem (vazia no host principal, sem títulos nem links), incorporação em artigo (renderiza só na Revista). Sitemap: nenhuma URL de álbum (decisão pendente de indexação da Revista).
- Gate `scripts/validate-aculta-gallery.php` (PASS): sem service locator em `src/`, `accessCheck()` explícito em toda EntityQuery, hooks OOP, D13 nos dois pontos, diálogo com permissão de criação.
- Gates de projeto relevantes: `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-environment-addresses` e `validate-account-profile-autocreate` PASS.
- Pré-existente, fora deste módulo: `validate-public-slugs` reprova (20 falhas, linha de base DT-P20) também no `origin/main`. Não alterado aqui.
- Pendências para a release: testes funcionais de permissão (Kernel/Functional), teclado e toque no navegador, responsividade, e o item de `srcset` (depende de breakpoints do tema).

## 0.1.0-dev.8 — desempenho com galerias grandes (medido) — 2026-10-14

Medição no servidor de testes (página anônima do álbum, Firefox e curl). Álbuns de teste com 50, 500 e 2.000 referências a fotos existentes, apagados depois:

| Referências | Primeiro acesso | Com cache | Tamanho da página | Miniaturas com `loading="lazy"` |
| --- | --- | --- | --- | --- |
| 50 | 1,47 s | 0,016 s | 27,7 KB | 12 |
| 500 | 1,54 s | 0,015 s | 28,8 KB | 12 |
| 2.000 | 1,97 s | 0,015 s | 28,8 KB | 12 |

- Paginação do servidor: 12 fotos por página. A página 166 do álbum de 2.000 responde 200.
- Miniaturas com largura e altura (sem salto de layout), estilo `media_gallery_image` (não o original), carregamento preguiçoso.
- Limite conhecido: o formatador carrega as referências do campo antes de paginar, por isso o primeiro acesso cresce com o tamanho do álbum. Com cache, a resposta fica em torno de 15 ms.
- Pendências: `srcset` (imagem responsiva do Core, que depende dos grupos de breakpoint do tema); paginação por Views para não carregar todas as referências (decisão a tomar com o responsável).

## 0.1.0-dev.7 — catálogo público de álbuns (em andamento) — 2026-10-14

- View `aculta_albuns` em `/albuns`: lista só álbuns publicados, ordenada pela data de publicação, 12 por página (paginação do servidor), com capa, título, data e crédito. Exibida em grade de três colunas.
- Restrição de visibilidade (D13) também na listagem: `GalleryListingHooks` (hook `views_query_alter`) zera o resultado fora do purpose `magazine`. Verificado: no host principal a listagem não traz títulos nem links de álbum.
- Cache da listagem desligado, para não servir a mesma página a outro host.
- Pendências desta fase: visual dos cards (proporção da capa e alinhamento da grade) depende de CSS e componente do tema (SDC da grade e do item); filtros por projeto, categoria e data; contagem de imagens por álbum; metatags e noindex para álbum não publicado.
- RUNTIME STATUS: verificado no Runtime (listagem, D13 nos dois hosts, capa e crédito).

## 0.1.0-dev.6 — PhotoSwipe e apresentação (em andamento) — 2026-10-14

- Documentação de hooks, classes e contrato com o tema: `docs/TEMA-E-HOOKS.md`.
- Lightbox: legenda pelo `alt` da mídia (submódulo `photoswipe_dynamic_caption`, contrib, habilitado no Runtime); imagem grande em estilo próprio de 1600 px (`media_gallery_lightbox`); crédito e data do evento na página do álbum.
- Pendências desta fase: exportar para o `config/sync` as alterações de exibição do Runtime; verificar a legenda no navegador; criar os SDCs do ACULTA420 com consumidor real; corrigir o cartão da listagem com galeria incorporada.
- RUNTIME STATUS: parcial (configuração aplicada no Runtime; navegador pendente).

## 0.1.0-dev.5 — galerias incorporadas em posts — 2026-10-14

Classificação: MINOR (nova dependência de módulo: `entity_embed` e `embed`; sem pacote novo no Composer).

- Habilitados `embed` e `entity_embed` (já instalados desde a Fase 1).
- Botão de incorporação `galeria` (`embed.button.galeria`, módulo `aculta_gallery`): incorpora somente entidades `media_gallery`.
- CKEditor 5 do `full_html`: botão `galeria` na toolbar e filtro `entity_embed` ativo (repo e Runtime).
- Verificado de ponta a ponta: artigo de teste com a galeria 11 no corpo, aberto pela URL da Revista (200), com o título da galeria renderizado e sem tag `drupal-entity` sobrando. Artigo de teste apagado depois.
- Remover a incorporação não apaga a galeria nem as imagens (66 antes e depois). Verificado.
- Limitações registradas:
  - O botão aparece em todo o `full_html`, não só em artigos e blogs. A restrição D13 à Revista ainda não está aplicada na inserção (só na visualização). Precisa de formato de texto dedicado; decisão pendente.
  - Criar galeria dentro do CKEditor não é suportado (L5). Só se incorpora galeria já criada.
  - Legenda opcional e escolha de layout no diálogo do CKEditor: não verificados. Layout depende dos componentes da Fase 5.
- Formato de texto dedicado `revista_editorial` (Revista — editorial): único com o botão `galeria` e o filtro `entity_embed`. Permissão para o editor de conteúdo. Campos de texto fora de artigo e blog excluem o formato. `full_html` perde o botão e o filtro.
- Verificado: artigo no formato da Revista renderiza a galeria pela URL da Revista (200); artigos de teste apagados.
- Pesquisa de criação de galeria no editor e alternativas: `docs/ARQUITETURA.md` §4.2.
- Botão "Criar galeria" no CKEditor 5 (`acultaGalleryCreate`), portado do CKEditor Media Gallery (GPL-2.0-or-later) sobre a entidade `media_gallery`, Entity Embed e PhotoSwipe. Origem e adaptações em `THIRD-PARTY.md` e `docs/ARQUITETURA.md` §4.3.
- Verificado: plugin descoberto; diálogo monta com widget de imagens reaproveitado; envio sem imagem devolve erro e não cria galeria.
- Teste no navegador (Firefox, servidor de testes): fluxo completo verificado (ver `docs/ARQUITETURA.md` §4.3). Correções feitas: método inexistente no plugin (`getFilterFormatId`), serviços do formulário guardados como propriedades comuns, e widget de imagens trocado para autocompletar do Core (a Media Library não funciona dentro do diálogo por causa do modal único do Drupal).
- Upload de fotos novas dentro do diálogo: elemento de arquivo do Core (vários arquivos, sem modal). Validação: extensões png/gif/jpg/jpeg/webp, imagem real e até 10 MB por arquivo. Cada foto vira uma mídia de imagem com texto alternativo descritivo e entra na galeria junto com as imagens já escolhidas. Verificado no navegador e no banco (galeria de teste com 2 mídias, removida depois).
- RUNTIME STATUS: verificado no Runtime (incorporação, formato da Revista, renderização pela Revista e remoção). Testes de navegador do editor pendentes (Fase 10).

## 0.1.0-dev.4 — importação, organização e restrição à Revista — 2026-10-14

Classificação: MINOR (regras de capa e de publicação; sem dependência nova).

- Restrição D13 implementada: `GalleryPurposeAccessHooks` (hook OOP `entity_access`) nega a visualização de galeria fora do purpose `magazine`, com cache por `domain`. Verificado por HTTP real como visitante anônimo: host da Revista (`revista420.aculta.toca.net.br`) 200; host principal 403.
- Permissão `view published media gallery entities` concedida ao papel `anonymous` no Runtime. A restrição de purpose continua valendo por cima.
- Capa (`GalleryCoverHooks`, hook OOP `media_gallery_presave`): a capa é sempre uma das imagens da galeria. Fora das imagens vira a primeira; sem imagens fica vazia. Verificado: capa fora vira a primeira (PASS), remover referência mantém a mídia (PASS), sem imagens limpa a capa (PASS).
- Importação em lote pelo Core (`#multiple` da Media Library, decisão D4); remoção remove só a referência. `media_library_bulk_upload` não foi instalado.
- Validação de arquivo: extensões do campo de imagem (`png gif jpg jpeg webp`) pelo File API. Sem limite de tamanho próprio no campo; vale o limite do PHP. Batch API e Queue não foram adotadas: avaliar na Fase 7 com medição.
- Não verificado nesta fase: upload pela interface no navegador (depende de teste de navegador, Fase 10).
- Dados de teste criados e apagados nesta fase (regra de teste em GUARDRAILS.md). Galeria 11 não foi alterada.
- RUNTIME STATUS: verificado no Runtime (regras de capa e desassociação, restrição D13 por HTTP). Upload no navegador pendente (Fase 10).

## 0.1.0-dev.3 — álbuns independentes — 2026-10-14

Classificação: MINOR (campos editoriais, alias público e rota técnica; sem dependência nova).

- Campos de álbum (config exportável): capa (`field_gallery_cover`, mídia de imagem), data do evento, data de publicação, créditos, direitos de uso, categoria editorial (`editorial_category`), tags (`revista_tags`), projeto (nó `project`) e `path` (alias).
- Formulário editorial (`core.entity_form_display.media_gallery.media_gallery.default`): autocompletar para autor, projeto, categoria e tags, para não carregar listas grandes.
- Alias público por padrão `/albuns/[title]` (`pathauto.pattern.aculta_gallery_album`, tipo do plugin `media_gallery`). Sem o campo `path` o pathauto não gera alias automático: o campo foi adicionado e é exportado.
- Rota técnica: `CanonicalGalleryRouteSubscriber` responde 404 ao caminho cru `/media_gallery/{id}` (R4, recomendação adotada). O alias continua respondendo. A primeira versão bloqueava pelo nome da rota e bloqueava também o alias; corrigido.
- Papéis: `content_editor` publica (R3). Só administrador e editor.
- Verificação no Runtime: álbum de teste criado e removido; alias 200 no host da Revista e no host principal; `/media_gallery/{id}` 404.
- Primeira galeria real importada (ID 11, "Marcha da Maconha de Goiânia 2025 – Registros Antiproibicionistas"): 66 fotos originais do site antigo como mídias de imagem, capa definida, créditos da autora, datas de evento e de publicação. Dados no Runtime, não no repositório.
- Regras e gates adicionados nesta fase: `docs/portal/GUARDRAILS.md` (usuários de teste de agentes são excluídos ao fim dos testes), `scripts/validate-account-profile-autocreate.php` (perfil participante nasce no `user_insert`, com get-or-create nos demais criadores) e `scripts/validate-agent-test-users-rule.php`.
- Limpeza no Runtime (decisões do responsável): perfis sem conta removidos; pedidos 6 e 7 (concluídos, do `teste.comum`) excluídos. Os pagamentos 1 e 2 permanecem no banco sem pedido, e as cobranças no Mercado Pago não foram estornadas.
- Pendente (Fase 8): galerias só na Revista (D13) ainda não está aplicado no código. Hoje a publicação é só por `status` e papel. Sem campo de purpose.
- Pendente (Fase 6/7): página pública do álbum ainda sem permissão para anônimos (`view published`); listagem, capa visual e paginação real por Views não implementadas.
- RUNTIME STATUS: verificado no Runtime (instalação, campos, alias, rota técnica, importação). Testes funcionais de permissão e visuais pendentes (Fases 9 e 10).

## 0.1.0-dev.2 — fundação do módulo — 2026-10-14

Classificação: MINOR (feature nova em linha aberta; nova dependência Composer aprovada).

- Dependências instaladas (`composer update` restrito; simulação prévia: 5 instalações, 0 atualizações, 0 remoções):
  - `drupal/media_gallery` 3.3.0 (habilitado);
  - `drupal/photoswipe` 5.0.11 (habilitado como dependência do Media Gallery);
  - `photoswipe/photoswipe` 5.4.4 (biblioteca JS, `web/libraries/photoswipe`, por repositório de pacote);
  - `drupal/entity_embed` 1.7 e `drupal/embed` 1.10 (**instalados, não habilitados**; habilitação na 0.1.0-dev.5);
  - nenhuma vulnerabilidade reportada pelo `composer audit`.
- `aculta_gallery.info.yml` (0.1.0-dev.2); dependência real declarada: `media_gallery`.
- Configuração inicial exportável: `aculta_gallery.settings` (`items_per_page: 24`; consumido a partir da Fase 2).
- Schema: `config/schema/aculta_gallery.schema.yml`.
- Override de campo base `core.base_field_override.media_gallery.media_gallery.images`: as imagens da galeria ficam restritas ao tipo `image`. (Um `field.field` foi tentado primeiro e falhou: campo base não se sobrescreve assim.)
- Papéis: `content_editor` recebe as permissões de galeria que publicam (`access media gallery overview`, `add`, `edit any`, `delete any`, `view published`, `view unpublished`). Administrador tem tudo por `is_admin`. Nenhum outro papel publica galerias.
- `config/sync`: `core.extension.yml` (`aculta_gallery`, `media_gallery`, `photoswipe`), os cinco itens instalados pelo contrib (`core.entity_view_display.media_gallery.media_gallery.default`, `core.entity_view_mode.media_gallery.full_gallery`, `image.style.media_gallery_image`, `photoswipe.settings`, `views.view.media_galleries`), `aculta_gallery.settings`, `core.base_field_override...images` e `user.role.content_editor` (permissões).
- Decisão D13: galerias publicadas somente na Revista (ver `docs/ARQUITETURA.md` §4.1). Implementação na Fase 8.
- Verificação no Runtime (`aculta_runtime`): instalação do `aculta_gallery`, `cr` e `updatedb:status` sem pendências; effective field `images` com `target_bundles = {image}`, cardinalidade ilimitada.
- Pré-existente (não desta fase): o Runtime já tinha 148 divergências de config antes da instalação. Mantive fora da PR. Após a instalação, a única divergência nova é a descrição da view `views.view.user_admin_people` (runtime e sync diferem na descrição), que já existia e não foi alterada por este trabalho. Registrar como dívida.
- Gates: `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-public-slugs`, `validate-environment-addresses` e `validate-aculta-portal-conta-root`: PASS.
- RUNTIME STATUS: verificado no Runtime (instalação e cache). Testes funcionais de permissão pendentes (Fase 9).

## 0.1.0-dev.1 — modelagem e contratos de integração — 2026-10-14

Classificação: PATCH (documentação, sem código, sem dependência instalada).

- Novo `docs/ARQUITETURA.md`: avaliação de Media Gallery 3.3.0, PhotoSwipe 5.0.11, Entity Embed 1.7 e Media Library Bulk Upload 1.0.6 com evidência do código do tarball e do Core instalado (11.4.8); decisões D1–D12; limitações L1–L10; riscos e perguntas R1–R8; plano de fases e versões.
- Principais constatações:
  - Media Gallery traz entidade `media_gallery` própria (álbum e galeria). Não se cria entidade nova.
  - A entidade não tem revisões nem Content Moderation (publicação só por `status`).
  - A paginação do contrib é aplicada depois da renderização do campo inteiro; não atende a galeria grande sem Views paginada.
  - A rota canônica `/media_gallery/{id}` é em inglês e numérica; o alias em português é obrigatório.
  - O Core 11.4.8 já oferece upload múltiplo na Media Library; `media_library_bulk_upload` não entra por padrão.
- Nenhum arquivo de runtime. Nenhum `info.yml` ainda (entra na 0.1.0-dev.2).
- RUNTIME STATUS: não aplicável (sem código).
- Próxima fase: 0.1.0-dev.2 (fundação), após aceite das decisões R3, R4, R5 e R6.
