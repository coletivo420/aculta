# Matriz de reaproveitamento e portabilidade

| Componente/localização | Responsabilidade/consumidores | Estado e conflito | Decisão |
| --- | --- | --- | --- |
| `aculta_portal/src/Domain/DomainPurposeManager.php` | Resolve purpose, Domain e URLs; API transversal. | Compartilhado; remoção quebra vários módulos. | PRESERVAR no Portal; Links consome. |
| `aculta_portal/src/PathProcessor/PurposeFrontPathProcessor.php` | Mapeia cada raiz; `links` usa `/redes-sociais`. | Compartilhado; caminho público ativo. | PRESERVAR centralizado. |
| `config/sync/domain.record.links_aculta_org.yml` | Registro Domain para o host público. | Configuração presente; DNS produção não confirmado. | PRESERVAR e provisionar pela política existente. |
| `SocialLinksPagePresenter.php` | Botões MAIN/custom, canais, View e footer; hooks Portal consomem. | Código específico LINKS no módulo generalista. | PORTAR em fase própria com paridade. |
| `SocialLinksSettingsForm.php` e `SocialLinksFormCallbacks.php` | Configuração e botões dinâmicos Form API/AJAX. | Movidos ao `aculta_links` em 0.4.0-dev.6; acesso e fallback preservados. | PORTADO; teste Kernel e formulário no Runtime pendentes. |
| `aculta_portal.social_links` e `aculta_links.social_links` | Canais e botões configuráveis. | Valores atuais preservados na configuração legada; update `10002` cria chave nova e form dual-write temporário. | Migrar sem excluir a fonte legada; remover compatibilidade somente após rollback aceito. |
| `SocialLinksThemeSuggestions.php` e preprocess Portal | Escolhe template e injeta dados. | Mistura integração geral com LINKS. | PORTAR comportamento específico; manter contrato neutro comum. |
| View `aculta_projects`, display `social_links` | Cards editoriais no landing. | Compartilhada e fonte de verdade editorial. | REUTILIZAR via Views API. |
| `node.page.field_social_links` e displays | Campo Link multivalorado de páginas. | Uso no template dedicado não localizado. | INVESTIGAR, não remover/migrar. |
| `node--social-links.html.twig` | Adapta Node ao SDC de links. | Apresentação tema. | PRESERVAR; mudar contrato com revisão/gates. |
| SDC `social-links-page` | Layout mobile, tokens e apresentação. | Experimental; recebe dados Drupal. | ADAPTAR posteriormente sem lógica de domínio. |
| `aculta_user/src/Social/*` e perfil | Redes pessoais, edição e acesso/visibilidade. | Identidade e dados pessoais compartilhados. | PRESERVAR; integrar somente de forma explícita. |
| Drupal Core Link fields | Links editoriais em projetos/destaques/páginas. | Recurso compartilhado. | PRESERVAR; não substituir campos globalmente por Linky. |
| Linky `2.0.0-beta1` | Link entity/referências/revisões/DER. | Beta sem stable suportada. | PORTAR conceitos/arquivos após verificação; não instalar como dependência. |
| LinkStack `v4.8.0` | UX de dashboard/editor/temas/analytics. | Laravel; licença da tag inconsistente. | PORTAR comportamento depois de seleção/licença por arquivo; auth DESCARTAR. |

Antes de migração, pesquisar consumidores; quando necessário, inventariar dados
do Runtime em modo read-only conforme `docs/architecture/environments.md`.
