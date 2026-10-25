# Perfil público, cartão de autor e painel da Minha conta

Submódulo: `aculta_portal/modules/aculta_user` (0.4.0-dev.2). Tema: ACULTA420.
Decisões e status: `docs/portal/ACULTA-USER-DECISOES-E-IMPLEMENTACAO.md`. Plano: `docs/portal/ACULTA-USER-PLAN.md`.

## 1. Regra de separação (tema x módulo)

Toda ferramenta funciona com outro tema. O módulo entrega dados, regras de privacidade, URLs e marcação neutra
com tokens `data-portal-ui`. O ACULTA420 mapeia os tokens em classes (`PortalUiHooks::TOKENS`) e estiliza.
Sem o ACULTA420, links, textos e fotos continuam funcionando.

| Parte | Dono | Onde |
| --- | --- | --- |
| Privacidade, dados, URL `main` | `aculta_user` | `PublicProfilePolicy`, `PublicProfileController`, `SocialNetworks` |
| Estrutura e tokens | `aculta_user` | `AccountDashboardHooks`, `SocialLinksCard`, `ArticleAuthorsHooks` |
| Aparência | ACULTA420 | `css/components/public-profile.css`, `portal-account.css`, `base.css` |

## 2. Privacidade (fonte única: `PublicProfilePolicy`)

- **Padrão:** todo perfil é público (`field_perfil_publico`, valor padrão 1, em `config/sync`).
- **Autores e editores são sempre públicos:** papéis `content_editor` e `wiki_editor`. O controle de privacidade
  fica bloqueado, com o motivo exibido (`isObrigatorioPublico`).
- **Perfil privado:** mostra somente foto e apelido, na página pública.
- **Foto:** sempre aparece no perfil público (não há opção de ocultar; o campo `field_exibir_foto` ficou sem uso).
- **Itens separados:** bio (`field_exibir_bio`), redes sociais (`field_exibir_redes`) e e-mail (`field_exibir_email`).
  Cada um só aparece se a pessoa escolheu exibi-lo, individualmente.
- **Nunca públicos:** CPF, RG, endereço, WhatsApp, data de nascimento, cidade e UF.

## 3. Página pública `/u/<apelido>` (purpose `main`)

Cabeçalho com foto (ou inicial do apelido), apelido (H2), bio, e as redes em botões com ícone e rótulo
(Instagram, Facebook, Reddit) e o e-mail como botão `mailto:` com rótulo "E-mail".
Rota declarada com `_aculta_domain_purpose: main`: em outros hosts responde 404.
Perfil privado: somente foto e apelido.

## 4. Cartão de autor (publicações da Revista)

- **Autor principal:** `node.uid`. **Coautores:** campo `co_authors` do módulo contrib `drupal/node_co_authors`
  (1.2.3, Drupal 11). Coautores recebem os direitos de conteúdo do autor, como aceito pelo responsável;
  só quem tem "administer nodes" edita o campo.
- **Onde aparece:** `node_view` (modo `full`), só em Notícias publicadas, no propósito `magazine`
  (`ArticleAuthorsHooks`).
- **Conteúdo de cada autor:** foto (só se exibida), nome (link para `/u/<apelido>` só com perfil público),
  papel ("Autor" ou "Coautor"), ícones das redes preenchidas (somente ícone, clicáveis, nova aba) e a bio
  (só se exibida; alinhada à borda da foto).
- **E-mail:** fica de fora do cartão; aparece só no perfil público.
- **Sem rede preenchida, sem ícone.** Sem bio exibida, sem texto.

## 5. Painel da Minha conta (Visão geral)

Ordem no topo: botão **Ver Perfil** (à direita, após o título), depois o cabeçalho com a foto e o nome.
- **Ver Perfil:** link para o perfil público no domínio `main`. Sem apelido, não aparece.
- **Bio:** caixa bloqueada (textarea desabilitado). Sem bio, o aviso "Você ainda não escreveu sua bio." fica dentro
  da caixa. O lápis "Editar bio" abre o modal. Salvar grava (rota JSON `aculta_user.bio_save`, token CSRF),
  atualiza a caixa e fecha o modal. Limpar só esvazia o campo. Salvar vazio volta ao aviso.
- **Redes sociais:** uma caixa por rede (Instagram, Facebook, Reddit). Sem link, mostra "Não informado" abaixo
  do nome. Com link, o nome vira link clicável. O lápis abre o modal de edição (`aculta_user.redes_form`);
  Salvar grava por JSON (`aculta_user.redes_save`). Link de outro site mostra aviso e mantém o modal aberto.
  O e-mail não faz parte desta caixa.
- **Modais:** acesso só por modal (`AccountModalAccess`): AJAX ou `_wrapper_format=drupal_modal`, ou o envio
  do formulário. Visita direta responde 403.
- **Título dos modais:** escala do H3 do tema (`base.css`, `.ui-dialog-title`).

## 6. Avatares

Todo avatar (estilo `aculta_avatar`) tem proporção 1:1, não encolhe com o contêiner e não é esticado
(`base.css`). Verificado em visão geral, perfil público, cartão de autor e modal de foto.

## 7. Links de perfil

Todo link para `/u/<apelido>` é gerado pela rota com o apelido atual, no propósito `main`, e a cache acompanha
o perfil. Regra e gate em `docs/portal/GUARDRAILS.md` (seção "Links de perfil") e `scripts/validate-profile-links.php`.

## 7b. Verificação

- Gates: `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-public-slugs`,
  `validate-environment-addresses`, `validate-aculta420-design-foundations`.
- Navegador (Chromium headless, conta de teste): modal da bio (Limpar não envia nem fecha; Salvar grava e fecha),
  modal de redes (gravar, limpar, link inválido), botão Ver Perfil, perfil privado, cartão de autor com ícones
  e bio, medidas de avatar sem distorção.
- Pendente: inspeção visual humana das telas acima.

## 8. Configurações (abas)

Minha conta > Configurações tem duas abas, no mesmo padrão de Meus Dados (`account-data-nav`):
- **Modo de cor** (`/configuracoes`): claro, escuro ou automático, por conta.
- **Privacidade** (`/configuracoes/privacidade`): perfil público e o que aparece no perfil público (foto,
  bio, redes sociais, e-mail como botão). Autores e editores têm o perfil público bloqueado, com o motivo.
  Apelido fica em Meus dados; bio e redes ficam na visão geral.

O Portal monta as abas (`aculta_portal.settings_tabs`) e chama o alter `aculta_portal_settings_tabs`; o
`aculta_user` acrescenta "Privacidade". A rota antiga `/configuracoes/perfil` responde 404 (sem redirecionamento
de slug, como no restante do projeto).

## 9. Avatares de sorteio

O link "Sortear novo avatar" leva token CSRF válido: requisição com o link do modal responde 200, sem token 403.

## 10. Pendências

- Tipo de publicação "blog" da Revista e mapeamento de chamada/resumo (decisão do responsável).
- Inspeção visual humana das telas (painel, perfil público, cartão de autor, abas).
- Versão do `aculta_portal` (módulo pai) não marcada nesta entrega; o `aculta_user` está em 0.4.0-dev.2 (versão atual em `aculta_user.info.yml`).

## Exceção registrada: autoria nos verbetes da Wiki (2026-10-20)

- O AGENTS diz que a autoria de verbete publicado é pública, e o tipo `wiki_entry` está com `display_submitted: true`.
- Mesmo assim, os templates `node--wiki-entry--full` e `node--wiki-entry--teaser` do ACULTA420 **não exibem** a autoria (decisão de apresentação anterior, mantida).
- Decisão do responsável em 2026-10-20: manter a apresentação oculta e registrar a exceção. Para exibir a autoria, o tema precisa de autorização explícita e nova versão; a identidade continua vindo do `aculta_user`.
- O gate `validate-aculta-portal-drupal11.php` verifica a configuração (`display_submitted: true`), não o template. A diferença entre configuração e apresentação fica registrada aqui.
