# Inventário — Domain Config (2026-11-09)

## Ambiente

| Item | Valor |
| --- | --- |
| Servidor | Runtime de desenvolvimento (`*.aculta.toca.net.br`) |
| Banco | `aculta_runtime` (MySQL/MariaDB, driver `mysql`) |
| Drupal | 11.5.0-beta1 |
| PHP | 8.5.12 |
| Tema público | `aculta420_v2` (`base theme: false`) |
| Config Sync | `config/sync` |
| Backup prévio | `/mnt/disco1/tmp/aculta-backups/aculta_runtime-20261109-183554.sql` (85 MB) |

Restauração (não executada nesta entrega): `drush sql:cli < <backup>` com o
arquivo acima. O dump contém dados do Runtime; não versionar.

## Pacotes Domain

| Pacote | Versão | Estado |
| --- | --- | --- |
| `drupal/domain` | 4.0.0-alpha2 | já instalado |
| `drupal/domain_config` | 4.0.0-alpha2 (metapacote) | adicionado ao Composer |
| `drupal/domain_config_ui` | 4.0.0-alpha2 (metapacote) | adicionado ao Composer |
| `drupal/domain_config_entity_ui` | 4.0.0-alpha1 (Domain Extras) | adicionado ao Composer |
| `drupal/domain_extras` | 4.0.0-alpha1 | instalado pelo Composer |

O `composer update` dos pacotes Domain reconciliou o lock com o requisito
`drupal/ui_patterns: 2.0.x-dev@dev` já presente no `composer.json`. O
`ui_patterns` permaneceu na referência `a7e9c28c` e as alterações locais de
compatibilidade em `web/modules/contrib/ui_patterns` (3 arquivos) foram
preservadas. O pacote `drupal/ui_icons` deixou de ser necessário e saiu.

## Módulos habilitados

Já existentes: `domain`, `domain_alias`, `domain_config`, `domain_source`.

Habilitados nesta entrega:

- `domain_config_ui` — formulários de configuração por domínio.
- `domain_config_entity_ui` — suporte experimental a entidades de configuração
  (somente `block` habilitado em `domain_config_entity_ui.settings`).
- `domain_config_switcher` — seletor de domínio para formulários
  `ConfigFormBase`; usado como API de domínios selecionáveis.
- `aculta_domain_config` — ponte do ACULTA para o Layout de Blocos.

Não habilitados: `domain_config_language`, `domain_early_negotiation`,
`domain_menu_extras` e os demais submódulos do Domain Extras.

## Domínios cadastrados

| ID | Host canônico | Papel |
| --- | --- | --- |
| `aculta_org` | `aculta.org` | MAIN / global (padrão) |
| `apoio_aculta_org` | `apoio.aculta.org` | Apoio |
| `conta_aculta_org` | `conta.aculta.org` | Conta / Studio |
| `cursos_aculta_org` | `cursos.aculta.org` | Cursos / GanjaLab |
| `eventos_aculta_org` | `eventos.aculta.org` | Eventos / Culturas |
| `forum_aculta_org` | `growroom420.aculta.org` | Fórum |
| `links_aculta_org` | `links.aculta.org` | Redes sociais |
| `loja_aculta_org` | `loja.aculta.org` | Loja / Green Stash |
| `revista420_aculta_org` | `revista.aculta.org` | Revista |
| `wiki420_aculta_org` | `wiki420.aculta.org` | Wiki / WE Sativa |

## Coleções de configuração existentes

`domain.apoio_aculta_org`, `domain.conta_aculta_org`,
`domain.cursos_aculta_org`, `domain.links_aculta_org`,
`domain.revista420_aculta_org` e `domain.wiki420_aculta_org`, além de
`language.pt-br`.

## Layout de Blocos

- 62 objetos `block.block.*` no total; o tema `aculta420_v2` tem blocos em
  `institution`, `header`, `navigation`, `highlighted`, `help`, `breadcrumb`,
  `content`, `sidebar_first` e `footer`.
- Administração no MAIN: `/painel-administrativo/estrutura/block`.
- Rotas administrativas nos subdomínios respondem 404 por política; por isso a
  edição de override é feita no MAIN com o seletor do `aculta_domain_config`.

## Órfãos removidos do Runtime

O `core.extension` do Runtime tinha dois módulos habilitados sem código no
repositório, o que bloqueava qualquer `drush en`:

- `aculta_forum_lms`
- `aculta_mail_confirmer_user`

As entradas foram removidas apenas do `core.extension` do Runtime. Nenhum
conteúdo, bloco ou regra de acesso foi alterado. O Config Sync não continha esses
nomes.

O `drush updatedb:status` também registra `jquery_ui_resizable` em
`system.schema` sem o módulo no site. É uma inconsistência prévia, não bloqueia
a instalação e não foi alterada nesta entrega.

## Config Sync

O Runtime já tinha divergências prévias em `drush config:status` (blocos locais
do tema administrativo, `turnstile.settings`, configuração local etc.). Esta
entrega **não** executou `drush cex` sobre o diretório inteiro para não
misturar trabalho de outras sessões. Os arquivos relevantes desta entrega são
exportados à parte, em [OPERACAO.md](OPERACAO.md#config-sync).
