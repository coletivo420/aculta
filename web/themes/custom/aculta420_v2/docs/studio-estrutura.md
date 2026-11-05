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

- **Barra institucional:** logotipo, "Multidomínio", busca, carrinho, mensagens,
  sino, e à direita o botão **"Entrar"** (visitante) e o **hambúrguer** (`list`).
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

## 5. Pendências

Decisões de 2026-11-05 estão em `studio-visual-spec.md`, seção 9. Resta:

1. **Como trocar de domínio sem o seletor "Multidomínio".** Candidato: listar os domínios dentro do hambúrguer.
2. **Rota de Meu Perfil (padrão `/meu-perfil`).** Minha conta é `/conta`, decidido. Confirmar a de Meu Perfil.
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
