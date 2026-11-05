# Estrutura do ACULTA Studio, Minha conta e barra institucional (V2)

Documento de trabalho para quem vai construir a barra, o menu, o Studio e a
conta na V2. Consolida decisões já tomadas e separa o que ainda está em aberto.
Quando houver conflito com as ADRs, valem as ADRs; quando houver conflito com
a especificação visual, vale esta página e a ADR citada em cada item.

Fontes: [ADR-046](../../../../../docs/decisions/ADR-046-desenho-tema-v2-shell-e-fundacoes.md),
[ADR-047](../../../../../docs/decisions/ADR-047-menu-de-conta-na-barra-institucional.md),
[ADR-048](../../../../../docs/decisions/ADR-048-layouts-de-pagina-tema-v2.md),
[studio-visual-spec.md](studio-visual-spec.md) e [layouts-plan.md](layouts-plan.md).

## 1. Decisões fechadas

| Tema | Decisão | Fonte |
| --- | --- | --- |
| Variantes de layout | `stack`, `aside` e `full` em um SDC `layout-page`. O tema escolhe a variante. | ADR-048, 1 e 2 |
| Breakpoints | 40rem (cartões em duas colunas) e 60rem (`aside` em duas colunas). Base em 320 px. | ADR-048, 4 |
| Nome da área | "ACULTA Studio". | ADR-048, 6 |
| Rotas do Studio e da Conta | Studio em `/` e Conta em `/conta`, ambos em `conta.aculta.*`. `/studio*` não existe. Suítes mantêm caminho próprio. | ADR-048, 9; ADR-038 retificado |
| Popup de foto | Usa o sistema novo da V2 (`<dialog>` com Fetch), igual ao login. Não usa `core/drupal.dialog.ajax`. | ADR-048, 10 |
| Fórum e wiki | Mantêm o acabamento visual da V1. O layout muda a estrutura, não o acabamento. | ADR-048, 11 |
| Barra institucional | Hambúrguer geral e botão "Entrar" de destaque (seção 3). | ADR-047, emenda |
| Sub-navegação de Minha conta | Coluna vertical dentro do conteúdo, como na imagem do responsável. | ADR-048, 5; spec 4.3 |

## 2. Estrutura inicial do Studio

Todas as telas do Studio ficam no purpose de conta. Layout e conteúdo:

| Tela | Rota (purpose conta) | Variante | Blocos principais | Spec |
| --- | --- | --- | --- | --- |
| Dashboard | `/` | `aside` no desktop, `stack` no mobile | Cartão de identidade (avatar, nome, "Ver perfil público"); grade de suítes (Marley, Ganjalab, WE Sativa, Culturas, Green Stash) | 4.1 |
| Meu Perfil | a definir | `stack` | Avatar com popup de foto; formulário "Editar perfil público" | 4.2, 4.2.1 |
| Minha conta | `/conta` | `aside` no desktop, `stack` no mobile | Sub-navegação vertical; "Dados cadastrais (privados)" | 4.3 |
| Meu trabalho (Marley) | `/marley` | `stack` | Abas de status; tabela de rascunhos; botão "Criar" | 4.4 |
| Ganjalab | `/ganjalab` | `aside` | Aprendizagem | 4.5 |
| WE Sativa | `/we-sativa` | `aside` | Contribuições da wiki | 4.6 |
| Culturas | `/culturas` | `aside` | Eventos, locais e organizações | 4.7 |
| Green Stash | `/green-stash` | `stack` | Gestão da loja | 4.8 |

**Rota de Meu Perfil: a definir.** Minha conta é `/conta`, decidido em 2026-11-05.
Meu Perfil segue o padrão `/meu-perfil`, a confirmar (regra de URLs públicas em
português, `FRIENDLY-PORTUGUESE-SLUGS.md`).

### 2.1 Navegação do Studio: decidida

- **Dashboard:** navegação vertical à esquerda (imagem de 2026-11-05).
- **Minha conta:** a navegação do Studio segue à esquerda, e a sub-navegação da
  conta fica dentro do conteúdo. São duas colunas de navegação nessa tela; a
  decisão contraria a regra anterior e precisa ser lembrada ao construir.
- **Suítes:** abas horizontais no topo da página.
- **Mobile:** o menu `list` abre a navegação do Studio como painel.

### 2.2 Minha conta

- Coluna vertical à esquerda do formulário, dentro do conteúdo (não é a navegação do Studio).
- Itens, nesta ordem: Dados cadastrais, Endereço, Documentos, E-mail e segurança,
  Senha, Contas conectadas, Meus pedidos, Meu apoio, Preferências.
- Item ativo: fundo `--color-brand-highlight`, texto `--color-on-highlight`, peso 600,
  `aria-current="page"`.
- Mobile: a mesma lista vira linha horizontal com rolagem no topo do conteúdo.
- Campos privados (telefone e data de nascimento) aparecem no material; a regra
  de exibição e de salvamento está pendente (spec, seção 9).
- Páginas de conta e de perfil privado: `noindex` por rota e sem cache público (ADR-048, 8).

## 3. Barra institucional: menu hambúrguer e botão de entrar

Decisões de 2026-11-05 (ADR-047, emenda).

### 3.1 Estrutura

- **Barra institucional:** logotipo, busca, carrinho, mensagens, sino, e à direita
  o botão **"Entrar"** (visitante) e o **hambúrguer** (`list`). Sem seletor
  "Multidomínio" (decidido em 2026-11-05).
- **Alvo de toque de 35 px** e **ícones de 30 px** nos gatilhos da barra (ADR-047, item 10).
  Exceção à regra de 44 px registrada no `AGENTS.md` da V2.
- **Sem rótulos de conta na barra**, em nenhuma largura. "Minha conta" e "Sair"
  ficam só dentro do hambúrguer.
- O hambúrguer da barra tem o mesmo comportamento do menu hambúrguer que hoje fica
  no cabeçalho do domínio principal. A conta entra nele; não é um menu separado.

### 3.2 Visitante

- **Botão "Entrar" de destaque** na barra (`--color-brand-highlight`, texto
  `--color-on-highlight`).
- **Dentro do hambúrguer:** "Entrar" como texto comum, sem destaque.
- Os dois abrem o mesmo popup de login (`<dialog>` da V2).
- Sem JavaScript, os dois vão para a página de entrada do Portal.

### 3.3 Autenticado

- O botão "Entrar" da barra desaparece.
- O hambúrguer mostra, no topo, o **avatar** e o **nome de usuário**, sem clique e sem edição.
- Logo abaixo, já expandidos e sem recolher: **"Minha conta"** e **"Meu perfil"**.
- **"Sair"** por último, separado por um fio fino.
- O avatar não é botão. O nome nunca aparece na barra.
- Sem nome fornecido pelo Portal, o cabeçalho mostra só o avatar.

### 3.4 Contrato com o Portal

O `aculta_user` entrega ao tema, por contrato neutro: `authenticated`,
`aculta_account_avatar` (sem inicial), `aculta_account_menu` (itens com access
e cache) e `aculta_account_name` (nome de exibição definido pela política do Portal).
O tema não lê usuário, Profile, `user_picture` nem e-mail.

### 3.5 Conteúdo do menu (por agora)

Decidido em 2026-11-05 (ADR-047, item 9). O menu tem só dois blocos:

1. **Sites (purposes):** a lista dos purposes publicados, com o nome de cada um
   vindo do Portal. Cada item é um link comum, com o site atual marcado em
   `aria-current`. Sem JavaScript, a lista funciona igual.
2. **Sessão:** "Entrar" (visitante) ou, logado, o cabeçalho com avatar e nome, "Minha
   conta", "Meu perfil" e "Sair" (seções 3.2 e 3.3).

Não há item de busca, de notificação nem de idioma no menu. Itens novos exigem decisão.

Os links de sites são navegação entre purposes: sem AJAX (ADR-049, item 5).

## 4. O que importar da V1

Importar significa reproduzir o comportamento ou a aparência na V2, com as
classes e tokens da V2. Não copiar CSS, Bootstrap, jQuery nem Twig da V1.

### 4.1 Popup de foto de perfil

- **Comportamento (fica no Portal, `aculta_user`):** gatilho no avatar; modal
  "Alterar foto do perfil"; campo "Foto" (PNG, JPG, GIF ou WebP até 10 MB);
  botões "Sortear novo avatar" (só acervo, não grava), "Remover foto" (só pessoal)
  e "Salvar foto do perfil"; recorte "Recortar foto" com "Aplicar recorte" e
  "Cancelar", aberto uma vez por arquivo por sessão.
- **Regras de estado:** acervo sem "Remover" e sem recorte; nunca inicial
  ([avatar.md](../../../../../docs/architecture/avatar.md)).
- **Aparência (da V1, `dialog.css`):** largura de `min(560px, 100vw − 2rem)`;
  altura máxima de 75vh com rolagem interna.
- **Mecanismo novo:** `<dialog>` com Fetch, como o login da V2 (ADR-048, 10).
  Não usar `core/drupal.dialog.ajax`.
- **Pendência:** decidir se o envio de arquivo e o sorteio, que usam AJAX do Drupal
  dentro do formulário, são tratados como no login ou por `Drupal.ajax` dentro do
  `<dialog>`. Confirmar no `core/misc/ajax.js` do 11.5 se há dependência de jQuery.

### 4.2 Fórum e Wiki

Referências na V1: `css/components/wiki.css`, `css/components/forum-index.css`,
`css/components/discussion.css`, `components/patterns/wiki-entry/` e
`components/content/forum-category/`.

Valores a reproduzir (ADR-048, 11):

- Wiki: container de até 76rem.
- Discussão do fórum: largura máxima de 58rem.
- Cartões do fórum: raio `--bs-border-radius-lg`, fundo de superfície elevada.
- Contadores de respostas: pílula com raio de 999px e fundo de superfície secundária.
- Ações: botão amarelo de interação, texto verde escuro, altura mínima de
  2,75rem, raio `--bs-border-radius`.
- A variante `aside` (wiki e tópico do fórum) mantém essa aparência dentro da
  coluna principal e da barra lateral.

Verificação: capturas V1 × V2 em 320, 390, 960 e 1440 px, claro e escuro, em `$SUITE_OUT`.

### 4.3 Já existe na V2 (não reimportar)

- Verbete (`wiki-entry`), categoria (`forum-category`), tópico (`forum-topic`) e
  comentário (`discussion-comment`) como SDCs próprios da V2.
- Modal de login em `<dialog>` (`multidomain-bar.js`).

### 4.4 Perfil público (base: V1)

Decidido em 2026-11-05: o perfil público da V1 é a base, adaptado aos tokens e
aos componentes da V2. O perfil público é diferente da edição de perfil.

**Referência na V1:** `PublicProfileController` (`aculta_user`), tokens
`public-profile*` em `PortalUiHooks::TOKENS` e `css/components/public-profile.css`.

**O que a V1 mostra (fonte da regra):**

- Cartão com largura máxima de 48 rem, borda, raio de 1 rem e cabeçalho com fundo
  de superfície discreta.
- Avatar redondo de 6,5 rem com borda de 3 px. Sem avatar, o acervo; nunca inicial.
- Nome em `h2`.
- Biografia em texto corrido, com quebras de linha, largura máxima de 60 caracteres.
- Redes e e-mail como pílulas com ícone. O e-mail só aparece se a política do Portal
  permitir.
- Conquistas em grade de cartões (quando houver), com ícone, nome e descrição.
- Ação de amizade (formulário do Portal) só para visitante logado que não é o dono.
- Perfil privado mostra só foto e apelido. Sem apelido, responde 404.

**Adaptação para a V2:**

- Cores e raios pelos tokens da V2 (`--color-surface-raised`, `--color-text-primary`,
  `--radius-lg` para o cartão; pílulas para redes e para a ação de amizade).
- Ícones pelo componente `icon` da V2 (Phosphor), no lugar das classes `bi-*` da V1.
- Layout: uma coluna no mobile; a partir de 36 rem, cabeçalho em linha (avatar e nome).
- Somente leitura. Nenhum formulário de edição nesta tela.

**Edição é outra tela (Meu Perfil, `/meu-perfil`).** Ali ficam foto (popup, seção 4.1),
apelido, biografia, redes e privacidade. Esta tela é privada: `noindex` e sem cache público.

**Rota.** Edição: `/meu-perfil` (decidido). Pública: a V1 usa `/u/<apelido>`. Proposta:
`/perfil/<apelido>`, pelo mesmo critério de `/meu-perfil` (URLs em português, sem
abreviação). Antes da versão estável não há redirecionamento, então trocar não quebra nada.
**A confirmar.**

**Purpose.** A página pública é servida no purpose principal (regra da V1). O botão
"Ver perfil público" do Studio, que fica na conta, é um link comum para outro purpose.

### 4.5 Mensagens e avisos (base: V1)

Decidido em 2026-11-05: mensagens e avisos reproduzem o comportamento e a aparência
da V1. A V2 já tem gavetas laterais à direita (26,25 rem, até 100 vw), iguais às da V1.

**Referência na V1:** `messenger-trigger.twig`, `alerts-trigger.twig`,
`js/messenger-drawers.js`, `js/alerts-dismiss.js`, `js/counters.js`,
`css/components/messenger-drawer.css`, `css/components/unread-count.css`,
`css/components/portal-cart.css` (gatilhos e gaveta) e `docs/messenger.md`.

**Comportamento e aparência a reproduzir:**

| Item | V1 | Situação na V2 | Ação |
| --- | --- | --- | --- |
| Gatilho | botão de 2,75 rem, ícone `bi-chat-dots` (mensagens) e `bi-bell` (avisos) | ícones Phosphor `chat-circle-dots` e `bell-simple` | ícones de **30 px** (ADR-047, item 10); manter o nome do ícone só se o Phosphor tiver a mesma leitura |
| Contador | badge redondo no canto, com `translate(45%, -45%)`; some com zero; "99+" acima de 99 | igual, com cor de erro | manter; conferir o estado com zero |
| Rótulo do gatilho | "N conversas não lidas. Abrir mensagens" / "N avisos ativos ou não lidos. Abrir avisos" | igual | manter |
| Gaveta | lateral à direita, 26,25 rem, até 100 vw | dialog lateral, mesma largura | manter |
| Corpo | rolagem interna; rodapé fora da rolagem | rolagem no corpo; rodapé no fim | manter |
| Rodapé | botão "Ver todas as mensagens" e "Ver todos os avisos", largura total | igual | manter |
| Carregamento | estado `loading` com `aria-busy`; erro com `role="alert"` e texto próprio | só `aria-busy` | incluir o estado de erro com texto |
| Filtros de avisos | grupos recolhíveis (`details`) por origem e leitura, com resumo de 2,75 rem de altura | ausentes | **implementar** |
| Conversas | lista com nome, data, avatar, não lida em destaque; conversa abre na gaveta com "← Todas as conversas" | ausentes na V2 | **implementar** (depende do módulo de mensagens) |
| Foco e Escape | Escape fecha; foco volta ao gatilho | `<dialog>` nativo | manter e testar |
| Resposta antiga | `AbortController` evita que resposta antiga substitua a gaveta (B4-02) | ausente na V2 | **implementar** no módulo de AJAX do Studio (ADR-049) |

Cada item de "ação" entra no mesmo módulo JavaScript do Studio (ADR-049, item 2),
e não em um arquivo novo.

## 5. Pendências

Decisões de 2026-11-05 estão em `studio-visual-spec.md`, seção 9. Resta:

1. ~~Como trocar de domínio sem o seletor "Multidomínio".~~ **Decidido em 2026-11-05:** o seletor sai da barra; a troca é feita pela lista de sites no menu (seção 3.5).
2. ~~Rota de Meu Perfil.~~ **Decidido em 2026-11-05:** `/meu-perfil`. Pendente só a rota pública do perfil (seção 4.4): `/u/<apelido>` na V1, ou `/perfil/<apelido>` por proposta.
3. **Popup de foto: envio e sorteio.** Decidido: Fetch próprio no `<dialog>`, com AJAX do Studio (ADR-049).
4. **Papel Estudante e Educador.** Verificado no LMS: "Educador(a)" é papel contextual por curso (`EducatorFeedbackPolicy`). A Ganjalab deve usar o papel do curso, não do Profile. Confirmar.
5. **Campos privados (telefone e data de nascimento).** Padrão: mostrar e editar. Confirmar a necessidade.
6. **Mudança no `aculta_user`:** entregar `aculta_account_name` (PR própria no Portal).
7. **Mudança no `aculta_user` para o popup de foto** (PR própria, com gate de foto).

## 6. Validação antes de marcar como concluído

- Gates do tema, conforme [gates.md](gates.md), no Homelab.
- Capturas em 320, 390, 768, 959, 960 e 1440 px, claro e escuro, zoom de 200%,
  com três cenários de avatar: foto, acervo e sem foto.
- Navegação por teclado no hambúrguer e no popup; `aria-current` na sub-navegação.
- Visitante e autenticado: o "Entrar" da barra e o do menu somem após o login.
- Sem rolagem horizontal da página em 320 px.
