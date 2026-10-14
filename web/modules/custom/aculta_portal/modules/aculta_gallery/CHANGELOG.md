# Changelog — ACULTA Gallery

Submódulo opcional do `aculta_portal`. Linha 0.1.x em `-dev.N` até o RC.

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
- Pendente: envio com imagens pelo navegador (Fase 10).
- RUNTIME STATUS: verificado no Runtime (incorporação, formato da Revista, renderização pela Revista e remoção). Testes de navegador do editor pendentes (Fase 10).

## 0.1.0-dev.4 — importação, organização e restrição à Revista — 2026-10-14

Classificação: MINOR (regras de capa e de publicação; sem dependência nova).

- Restrição D13 implementada: `GalleryPurposeAccessHooks` (hook OOP `entity_access`) nega a visualização de galeria fora do purpose `magazine`, com cache por `domain`. Verificado por HTTP real como visitante anônimo: host da Revista (`revista420.aculta.toca.net.br`) 200; host principal 403.
- Permissão `view published media gallery entities` concedida ao papel `anonymous` no Runtime. A restrição de purpose continua valendo por cima.
- Capa (`GalleryCoverHooks`, hook OOP `media_gallery_presave`): a capa é sempre uma das imagens da galeria. Fora das imagens vira a primeira; sem imagens fica vazia. Verificado: capa fora vira a primeira (PASS), remover referência mantém a mídia (PASS), sem imagens limpa a capa (PASS).
- Importação em lote pelo Core (`#multiple` da Media Library, decisão D4); reordenação pelo `tabledrag` do widget; remoção remove só a referência. `media_library_bulk_upload` não foi instalado.
- Validação de arquivo: extensões do campo de imagem (`png gif jpg jpeg webp`) pelo File API. Sem limite de tamanho próprio no campo; vale o limite do PHP. Batch API e Queue não foram adotadas: avaliar na Fase 7 com medição.
- Não verificado nesta fase: upload pela interface e reordenação por arrasto no navegador (dependem de teste de navegador, Fase 10).
- Dados de teste criados e apagados nesta fase (regra de teste em GUARDRAILS.md). Galeria 11 não foi alterada.
- RUNTIME STATUS: verificado no Runtime (regras de capa e desassociação, restrição D13 por HTTP). Upload e arrasto no navegador pendentes (Fase 10).

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
