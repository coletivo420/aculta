# ACULTA Studio (`aculta_studio`)

Espaço de trabalho por abas na conta, para autores, editores, moderadores e educadores. É uma ferramenta transversal:
agrega serviços e ações dos donos das áreas, e não armazena conteúdo, permissões, discussões nem galerias próprias.

- Rota: `/studio` no domínio de conta (`conta.aculta.*/studio`), com `_aculta_domain_purpose: account` e permissão
  `access aculta portal`. Visitante recebe 403.
- Áreas: plugins `#[StudioTab]` em `Plugin/StudioTab`. Cada área declara se o dono está disponível (`isAvailable()`),
  a permissão de exibição (`access()`) e as seções (`sections()`): cada seção do submenu tem as próprias abas, e cada
  aba lista as ações, cada uma com o acesso da rota do dono.
- Presenter: `StudioPresenter::visibleTabs()` mostra só abas com dono ativo e permissão concedida, em ordem de peso.
- Aba atual: argumento `aba` (id do plugin). Sem argumento ou com id desconhecido, a primeira aba visível.
- Três níveis, cada um com navegação AJAX própria (`js/studio-navigation.js`, biblioteca `aculta_studio/navigation`):
  áreas (`aba`), seções do submenu (`secao`) e abas da seção (`sub`). A raiz `#aculta-studio-root` é substituída pela
  resposta `drupal_ajax`; URL, `aria-current` e voltar/avançar acompanham a troca.
- Página própria: sem o shell, o menu nem o `account-navigation.js` da Minha conta.

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
