# Views de perfil — especificação para criação no Runtime (Fase 3)

Status: campo de Views e tamanho do cartão implementados no código. A view em si
**não foi criada nem exportada**: não há `vendor/` nem Runtime neste checkout. Quem
criar a view é o agente do Homelab, que exporta para `config/sync` antes do RC
(uma importação de configuração sem a view exportada a apaga).

RUNTIME STATUS: DEFERRED.

## Componentes de código (já no branch)

- `Plugin\views\field\AuthorCard` (`aculta_user_author_card`): renderiza o cartão de
  autor de cada linha. Opções: variante (padrão `profile_compact`) e tamanho `sm`,
  `md`, `lg` (padrão `sm`). Só renderiza contas com `field_perfil_publico` verdadeiro.
- `AuthorCardBuilder::build()` aceita `$size` (último argumento, padrão `md`).
- Tema `aculta-user-author-card` repassa `size` ao SDC `aculta420:author-card`.

## Especificação da view "Perfis públicos" (a criar no Runtime)

| Item | Valor |
| --- | --- |
| Base | `users_field_data` |
| Filtros | `status = 1`; `uid > 1` (exclui anônimo e administrador de sistema, se houver) |
| Relacionamento | `profile` por `uid`, papel principal (mesmo critério de `PerfilDoPapel::principal`) |
| Filtro obrigatório | `field_perfil_publico = 1` no perfil relacionado |
| Filtro | `field_nickname` não vazio (sem apelido não há link público) |
| Campo | `aculta_user_author_card`, variante `profile_compact`, tamanho `sm` |
| Ordenação | `field_nickname` ascendente |
| Paginação | 24 por página |
| Acesso | pública; a view é servida só no propósito MAIN (`/membros` ou caminho aprovado) |
| Cache | tags do usuário e do perfil; contexto `user.roles` não é necessário (a política é pública) |

## Estilo de exibição (UI Styles)

Aplicar no display da view, em `row` ou `wrapper`, as classes do catálogo semântico:
`aculta-uistyle--surface-raised` e `aculta-uistyle--border-subtle`. Não usar cores
primitivas nem classes Bootstrap de cor no display.

## Verificação ao criar no Runtime

- A listagem não pode mostrar contas com `field_perfil_publico` falso, mesmo com
  apelido preenchido.
- Conta sem foto mostra o avatar do acervo; sem acervo, a inicial.
- Caminho da página e slug em português, sem termos técnicos (`membros`, não `members`).
- Rodar também com o tema Olivero, conforme o CLAUDE.md do repositório.

## Fora desta fase

- Views administrativas existentes (`profiles`, `user_admin_people`, `group_members`,
  `lms_course_students`) não mudam.
