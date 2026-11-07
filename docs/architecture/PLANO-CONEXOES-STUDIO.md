# Plano: bloco Conexões e página de conexões no Studio (conta)

Status: planejado em 2026-11-07. Execução por outro agente, após aprovação.

## Escopo

1. **Bloco "Conexões"** no painel de conta (Studio Dashboard), logo abaixo do calendário na coluna da direita no desktop.
2. **Página de conexões** dentro da Minha Conta do Studio, com busca e lista das conexões.

Textos em português. A imagem de referência serve só como guia de estrutura; estilos vêm do tema aculta420_v2 (tokens, faixa de destaque `--studio-stripe`, raios e botões `--button-*`).

## Bloco Conexões (desktop)

- Título "CONEXÕES", vazio com a mensagem "Você ainda não tem conexões" e botão "Encontrar conexões".
- Com conexões: mostra as três primeiras conexões aceitas (avatar, nome, "Ver perfil") e o link "Ver todas".
- Caixa com faixa superior `--studio-stripe`, cantos `--radius-lg`, como as demais caixas do painel.
- Mobile: posição a definir (ver decisões abertas).

## Página de conexões

Duas colunas, como na referência:

- **Suas conexões:** campo de filtro (texto) sobre as conexões aceitas. Cada conexão tem "Ver perfil" e "Desfazer amizade".
- **Buscar novas conexões:** campo de busca com resultados interativos a partir de 3 caracteres, o mesmo limite da busca geral (`SearchPageController::MIN_CHARS = 3`). Cada resultado mostra o estado: "Adicionar", "Pendente" ou "Conectado".

Rota: a definir (ver decisões abertas). Acesso só para usuário autenticado.

## Reaproveitamento (verificado no código)

| Peça | Onde está | Uso |
| --- | --- | --- |
| Serviço de amizade | `aculta_user/src/Social/FriendshipManager.php` | `request`, `accept`, `decline`, `cancel`, `remove`, `areFriendsWith`, `getRelationshipWith`, `getIncomingRequests` |
| Estados | `aculta_user/src/Social/FriendshipStatus.php` | `pending`, `accepted`, `declined`, `cancelled`, `removed` |
| Entidade | `aculta_user/src/Entity/AcultaFriendship.php` | Registro da relação, com controle de acesso próprio |
| Ação de formulário | `aculta_user/src/Form/FriendshipActionForm.php` | Confirmação das ações |
| Perfil público | `aculta_user/src/PublicProfilePolicy.php`, `PublicProfileLink.php` | "Ver perfil" só quando o perfil é público ou o usuário é conexão |
| Busca preview | `aculta_portal/src/Search/SearchPreviewController.php` | Padrão htmx de resultados interativos |
| Busca geral | `aculta_portal/src/Search/SearchPageController.php` | Mínimo de 3 caracteres |
| Padrão de provedor | `aculta_studio` (`StudioExperienceManager`, `AccountController`) | Bloco e página ligados ao painel de conta |
| Tema | `aculta420_v2` (`studio.css`, `layout.css`, `js/account-carousel.js`) | Caixas, botões e faixa de destaque |

**Opigno:** não está instalado. O inventário do repositório (`docs/modules/OPIGNO-ACULTA-INVENTORY.md`) recomenda usá-lo só como referência funcional, sem adoção. Não é necessário para conexões: a amizade já existe em `aculta_user`.

## Regras

- Busca e resultados não expõem e-mail, dados privados nem perfis não públicos. O nome e o avatar vêm do resolvedor de identidade pública.
- "Desfazer amizade" chama `remove()`, com confirmação antes de executar.
- Ações de convite (`request`, `accept`, `decline`, `cancel`) passam pelas transições já validadas no serviço; a interface não cria estados novos.
- Sem escrita fora das ações de amizade. Nenhuma migração de dados.

## Fases

### Fase 1 — dados e serviço
- Consultas: conexões aceitas do usuário, e busca de usuários por nome público com mínimo de 3 caracteres e limite de resultados.
- Estado de cada resultado: conectado, pendente (enviado ou recebido) ou sem relação.
- Aceite: testes de kernel para anônimo (lista vazia), usuário sem conexões, perfil não público (não aparece com dados privados) e estados corretos.

### Fase 2 — componentes
- Componente SDC do bloco Conexões (desktop, coluna direita, abaixo do calendário).
- Página de conexões com as duas colunas.
- Aceite: probes de schema SDC e de fundações passam; sem estouro horizontal em 320 px.

### Fase 3 — interação
- Busca com htmx, a partir de 3 caracteres, com resultados que trocam de estado sem recarregar.
- "Desfazer amizade" com confirmação; "Ver perfil" respeitando a política de perfil público.
- Aceite: ações mudam o estado na tela; ação sem permissão é negada no servidor.

### Fase 4 — validação
- Kernel de privacidade e de transições; Unit do bloco.
- Captura em Firefox ESR (`DISPLAY=:0`), autenticado e anônimo, claro e escuro, 320 e 1440 px, em `tests/browser/capturas/conexoes-studio/<data>/` com `manifest.json` e `results.json`.

### Fase 5 — entrega
- Commit em português com o trailer da sessão, push em `origin/dev`, confirmação com `git ls-remote`.
- Sincronizar o checkout compartilhado e limpar cache dos hosts.
- Atualizar `docs/status-layouts-e-shell.md` e o README do componente.

## Decisões abertas

- **Rota da página:** `/conexoes` no purpose de conta, ou outro caminho?
- **Mobile:** o bloco aparece antes ou depois de "Suas experiências"?
- **Desfazer amizade:** confirmação em janela ou em linha?
- **Limite de resultados** na busca (sugestão: 10).
