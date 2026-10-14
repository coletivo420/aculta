# Changelog — ACULTA Gallery

Submódulo opcional do `aculta_portal`. Linha 0.1.x em `-dev.N` até o RC.

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
