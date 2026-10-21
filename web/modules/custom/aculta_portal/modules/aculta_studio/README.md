# ACULTA Studio (`aculta_studio`)

Espaço de trabalho por abas na conta, para autores, editores, moderadores e educadores. É uma ferramenta transversal:
agrega serviços e ações dos donos das áreas, e não armazena conteúdo, permissões, discussões nem galerias próprias.

- Rota: `/studio` no domínio de conta (`conta.aculta.*/studio`), com `_aculta_domain_purpose: account` e permissão
  `access aculta portal`. Visitante recebe 403.
- Abas: plugins `#[StudioTab]` em `Plugin/StudioTab`. Cada aba declara se o dono está disponível (`isAvailable()`),
  a permissão de exibição (`access()`) e as ações (`actions()`), cada uma com sua própria checagem de acesso.
- Presenter: `StudioPresenter::visibleTabs()` mostra só abas com dono ativo e permissão concedida, em ordem de peso.
- Aba atual: argumento `aba` (id do plugin). Sem argumento ou com id desconhecido, a primeira aba visível.
- Navegação AJAX como na Minha conta: a seção "Estúdio" entra no shell da conta (hook `aculta_portal_account_sections_alter`,
  só com aba visível). As abas ficam dentro de `#portal-account-body`, e o `account-navigation.js` troca o conteúdo
  por `drupal_ajax`, atualizando URL, menu e título.

## Abas

| Aba | Dono | Permissão | Ações |
| --- | --- | --- | --- |
| Revista (`revista`) | `aculta_magazine` | `create revista content` | Nova publicação |

Galerias, Fórum, Wiki, Cursos, Projetos, Biblioteca, Métricas e Eventos ainda não têm aba. Cada uma depende de
uma permissão real do dono (não de nome de papel) e entra como novo plugin.

## Apresentação e fronteira com o tema

O módulo não emite classes de design. Marca as partes com tokens semânticos `data-portal-ui`:
`studio-shell`, `studio-tabs`, `studio-tab`, `studio-panel`, `studio-heading`, `studio-actions`, `studio-action` e
`studio-empty`. O ACULTA420 traduz esses tokens em `PortalUiHooks::TOKENS` e estiliza a aba ativa em
`css/components/studio.css` (pelo `aria-current="page"`). Ver `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Limites

- Sem Runtime de desenvolvimento validado além da aba Revista; revisão visual no Firefox/X11 pendente.
- Sem Kernel de acesso das abas futuras; cada aba nova precisa do seu teste de acesso.
- Não substitui Views, Form API, Entity Access nem Workflows dos donos: as ações apenas os invocam.
- Estado e próximos passos: `docs/operations/status/aculta_studio.yml` e o roadmap em `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`.
