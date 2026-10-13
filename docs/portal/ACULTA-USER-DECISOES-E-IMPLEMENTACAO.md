# aculta_user: decisões do responsável e implementação

Registro das decisões tomadas pelo responsável durante o desenvolvimento do submódulo `aculta_user` e do estado de cada
uma no código. O plano de produto continua em `docs/portal/ACULTA-USER-PLAN.md`; este arquivo acompanha o que foi
decidido depois e o que existe de fato.

Status: **Implementado** (código commitado e verificado por HTTP/CLI), **Implementado sem verificação visual** (código
commitado, sem teste em navegador: a ferramenta de navegador não estava disponível), **Em andamento** (código não
commitado ou não ligado), **Pendente** (decidido ou perguntado, sem código).

## 1. Decisões

### 1.1 Visibilidade e privacidade do perfil
| Decisão | Status | Onde está |
| --- | --- | --- |
| Apelido é público por padrão; o perfil inteiro pode ser tornado privado | Implementado | `PublicProfilePolicy`, `ProfileVisibilityForm`, `field_perfil_publico` |
| Foto, bio, e-mail, redes sociais e badges só aparecem se a pessoa escolher exibir (privados por padrão) | Implementado (foto, bio, e-mail, redes); badges pendente (U4) | `field_exibir_*` |
| Cidade e UF nunca são públicas | Implementado | `PublicProfilePolicy` (lista fechada de itens) |
| Todos os perfis são públicos por padrão (campo `field_perfil_publico` com valor padrão 1, exportado em `config/sync`) | Implementado; verificado (perfis novos nascem públicos) | `field.field.profile.participante.field_perfil_publico.yml` |
| Autores e editores (`content_editor`, `wiki_editor`) são obrigatoriamente públicos: controle bloqueado com o motivo | Implementado; verificado (único editor com flag público) | `PublicProfilePolicy::isObrigatorioPublico`, `ProfileVisibilityForm` |
| Perfil privado mostra somente foto e apelido (substitui o 404 da decisão 40) | Implementado; verificado por HTTP | `PublicProfilePolicy::publicItems`, `PublicProfileController` |
| Rota pública `/u/<apelido>` só no propósito `main` | Implementado | `PublicProfileController` |
| Caminho `/u/<apelido>` é exceção de slug registrada | Implementado | `FRIENDLY-PORTUGUESE-SLUGS.md` |

### 1.2 Cadastro e apelido
| Decisão | Status | Onde está |
| --- | --- | --- |
| Nome de usuário vira o apelido inicial; a pessoa troca depois | Implementado | `AccountCreationHooks`, `AccountNames` |
| Conta OAuth usa a parte local do e-mail, sem o domínio | Implementado | `SocialAuthFieldsSubscriber` (`social_auth.user.fields`) |
| Apelido: minúsculas, sem acento, 3 a 40 caracteres, único, fora das palavras reservadas | Implementado (validação no formulário; unicidade no banco não) | `ProfileVisibilityForm`, `AccountNames` |
| Contas existentes sem apelido recebem `pessoa-<uid>` | Implementado no Runtime (uid 1 e uid 5) | Ver seção 4 |

### 1.3 Avatar e foto
| Decisão | Status | Onde está |
| --- | --- | --- |
| Quem não tem foto recebe avatar sorteado do acervo | Implementado (cadastro novo em `user_presave`; contas antigas por backfill) | `AvatarPool`, `AccountCreationHooks` |
| O arquivo do avatar vai para `user_picture` como arquivo gerenciado | Implementado | `AvatarPool::assignEntry` |
| A escolha fica em `user.data` (`avatar_id`) | Implementado | `AvatarPool` |
| Família `mushrooms` removida do acervo | Implementado (10 arquivos inexistentes) | `meta/avatars.json`, `avatar-families.json` |
| Acervo fica no submódulo `aculta_user`, não no tema | Implementado | commit `613c28f` |
| Foto do Google importada quando houver | Já existia no `social_auth`; mantido | `UserManager` do contrib |
| Botão "Sortear outro avatar" no popup da foto, por AJAX (troca só a imagem) | Implementado (verificado por HTTP) | `AvatarRerollController`, `PhotoEditorHooks` |
| Sorteio nunca repete o avatar atual quando há outro | Implementado | `AvatarPool::pick` |
| Foto do acervo: sem "remover foto" e sem recorte | Implementado (verificado por HTML) | `PhotoEditorGuard::adjustForm` |
| Foto do acervo: campo de envio disponível; salvar sem enviar não apaga o avatar | Implementado (verificado por HTTP) | `PhotoEditorGuard` (entity builder) |
| Remover foto personalizada volta a conta ao sorteio do acervo | Implementado (verificado no CLI) | `PictureReplacementHooks::userPresave` |
| Foto anterior enviada pela pessoa é apagada quando é trocada ou removida | Implementado (verificado no CLI) | `PictureReplacementHooks::userUpdate` |
| Cópias do acervo nunca são apagadas como "foto antiga" | Implementado | `PictureReplacementHooks` (prefixo `pictures/acervo/`) |
| Recorte em popup separado, só para foto personalizada enviada | **Bloqueado** (ver 1.3.1); o recorte inline do widget continua funcionando | `AvatarCropForm` (não commitado, sem rota) |
| Após o recorte, volta ao popup da foto para salvar | **Implementado** (sem verificação em navegador) | `AvatarCropForm`, `photo-flow.js` |
| Ao enviar a foto personalizada, o modal de recorte abre automaticamente | **Implementado** (sem verificação em navegador) | `photo-flow.js` |
| Excluir arquivos temporários de upload e cópias do acervo sem uso | **Implementado no cron** (uploads temporários de foto com mais de 1 h e cópias do acervo sem conta; verificado no CLI, cron real não disparado) | `AvatarAssetCleanup`, `AvatarCronHooks` |
| Limpeza de arquivos não usados após o recorte | **Implementado** (derivados apagados no recorte; no cron, fotos personalizadas sem uso e recortes órfãos) | `AvatarCropForm::flushDerivatives`, `AvatarAssetCleanup` |

#### 1.3.1 Recorte em popup separado
Decidido: ao enviar uma foto personalizada, o popup de recorte abre automaticamente; ao aplicar o recorte, ele fecha
e a pessoa volta ao popup da foto, onde clica em "Salvar". O recorte só vale para foto personalizada.

Implementado (proposta aprovada pelo responsável):
- Recorte por POST comum (sem AJAX): `AvatarCropForm` é rota `_form` (`/avatar/recortar`). O envio grava o recorte pela
  API do `image_widget_crop`, guarda o arquivo como pendente em `user.data`, apaga os derivados do estilo
  `aculta_avatar` e redireciona para o painel com `?aculta_recorte=1` (verificado: 303, recorte gravado, pendente gravado).
- Retorno: o popup da foto mostra o arquivo pendente no campo da foto e reabre sozinho (biblioteca
  `aculta_user/photo-flow`, anexada à página do painel por `PhotoFlowPageHooks`; verificado que o script é servido).
- Recorte inline do widget removido para foto personalizada; botão "Recortar foto" (link modal) reabre o recorte.
- Limite mínimo do recorte: o tipo `aculta_avatar_1x1` tinha `hard_limit` 2048 px, incompatível com um avatar de
  256 px (a imagem do acervo tem 512 px). Ajustado para 256 px no `config/sync` e no Runtime. **Confirmar.**
- Causa do recorte que não funcionava: o canvas de recorte usava o estilo `aculta_avatar`, que já aplica o recorte;
  recortava-se sobre uma imagem já recortada. Corrigido: o canvas usa `crop_thumbnail` (só redimensiona, 400 px).
  Verificado: o modal mostra o canvas de `crop_thumbnail` (200) e o POST grava o recorte (303).
- Abertura automática ao enviar: `js/photo-flow.js` abre o modal `/avatar/recortar?fid=N` quando o campo da foto recebe
  um arquivo diferente da foto salva, uma vez por arquivo e por sessão. **Não verificado em navegador** (não disponível
  nesta sessão).

Causa do modal que não abria ao escolher o arquivo (corrigida): o upload da foto era um POST AJAX para a própria página
do painel. Nessa requisição, o Drupal reconstrói a página inteira e o arquivo enviado se perdia (o campo `fids` voltava
vazio), e o script antigo dependia de um contêiner que não era trocado. Correção: o formulário da foto virou rota própria
(`/avatar/foto`, `UserPictureForm`), que faz upload, remoção e salvamento; o script observa o campo `fids`.
Verificado pela web: upload (fids 42), recorte (303, pendente), retorno com o arquivo no campo, salvamento (foto
permanente, pendente limpo, papéis preservados). Não verificado em navegador: o disparo do modal pelo script.

Pendente: teste em navegador da abertura automática e do fechamento do popup; teste de Kernel do fluxo.

### 1.4 Associado e dados
| Decisão | Status | Onde está |
| --- | --- | --- |
| Só o administrador torna uma pessoa associada | Implementado (papel `associado`, sem permissões; concessão pelo administrador) | `user.role.associado`, relatório |
| Papel `associado` basta (sem estado nem data de adesão) — usado por falta de resposta explícita | Implementado; **estado e data de associação pedidos e pendentes** (ver 2.1) | — |
| RG não é cifrado | Implementado (`field_rg`, texto simples) | `config/install` |
| CPF/CNPJ continua no campo único existente, cifrado pelo módulo de documento | Mantido | `aculta_associado_id` (campo `field_documento`) |
| Só o administrador exporta o relatório | Implementado (permissão `export aculta associados`) | `AssociadosController` |
| RG mascarado na tela; completo só na exportação | Implementado | `AssociadosController::mask` |
| Data de nascimento preenchida pela pessoa | Campo existe; formulário "Informações Básicas" é o `/dados` já existente | `field_data_nascimento` |

### 1.5 Participação no Fórum
| Decisão | Status | Onde está |
| --- | --- | --- |
| Primeiro comentário de quem não tem comentário publicado vai para a fila | Implementado (código; verificação em navegador pendente) | `ParticipationHooks::commentPresave` |
| Criar tópico exige 10 comentários publicados | Implementado (limite configurável) | `ParticipationHooks::entityCreateAccess` |
| Bloqueio de publicações enquanto o primeiro comentário aguarda | Implementado | `ParticipationRules` |
| Painel `/admin/config/aculta/portal/participacao` | Implementado | `ParticipationSettingsForm` |
| Editor de conteúdo e administrador ficam isentos | Implementado | `papeis_isentos` |
| Discussão vinculada: tópico de Fórum ligado a artigo, atividade, projeto, documento e verbete | Implementado (campo `field_discussao`) | `DiscussionHooks` |
| Tipos com discussão vinculada (decisão 24) | Usado o padrão do plano; **confirmação pendente** | — |

### 1.6 Atividade em Minha Conta
| Decisão | Status | Onde está |
| --- | --- | --- |
| Seção Atividade com abas wiki, fórum, revista, comentários e conquistas | Implementado (verificado por leitura de código; rota no propósito `account`) | `ActivityController`, `aculta_user.routing.yml` |
| Cada aba só lista o que é da própria pessoa e está publicado | Implementado | `ActivityController` |
| Slugs `/atividade/*` e item de menu "Atividade" (decisão 20) | Usado o padrão do plano; **confirmação pendente** | `AccountShellBuilder` |
| Item "Atividade" depende da rota do submódulo (o Portal não depende dele) | Implementado | `AccountShellBuilder::routeExists` |

### 1.7 Troca de e-mail e confirmação
| Decisão | Status | Onde está |
| --- | --- | --- |
| Confirmação de e-mail é etapa anti-bot, não permissão editorial | Documentado | `EMAIL-CONFIRMATION-POLICY.md` |
| Papel `email_confirmed` é irreversível (sem remoção pela administração) | Implementado | `EmailConfirmedRoleGuard`, `EmailConfirmedRoleHooks` |
| `skip CAPTCHA` só no papel `email_confirmed`; `authenticated` não muda | Mantido (confirmado pelo responsável) | `user.role.email_confirmed` |
| `wiki_contributor` acompanha `email_confirmed` em todos os casos | Implementado | `RoleSyncHooks` |
| Troca de e-mail só pelo formulário de Minha Conta | Implementado | `EmailChangeHooks`, `EmailChangePolicy` |
| Troca dispara nova confirmação; papel permanece | Mantido pelo módulo `email_confirmer_user` (ligado no Runtime) | seção 9 de `EMAIL-CONFIRMATION-POLICY.md` |
| Troca de e-mail feita pelo administrador é aceita sem confirmação (bypass por `is_admin`) | Decidido: aceito | seção 9 |
| Endereço antigo continua valendo até a confirmação do novo | Decidido: manter o comportamento do módulo | seção 9 |

### 1.8 Badges (troféus)
Estado técnico: entidade `aculta_badge_award` instalada no Runtime (`aculta_user_update_10001`, rodado com `drush updb`,
única atualização pendente); serviço `BadgeService` (concede uma vez, revoga só manuais); hooks de avaliação ativos
(primeiro comentário, primeira edição da Wiki, perfil básico, pedido concluído, curso concluído). Verificado: concessão
idempotente no CLI. Pendente: tela de concessão e revogação do administrador, exibição com "Obtida em: DATA" e
verificação dos gatilhos com dados reais.

| Decisão | Status |
| --- | --- |
| Troféus são concedidos e revogados só pelo administrador (manuais); automáticas nunca são revogadas | Decidido; código em andamento (não commitado) |
| Apoio e pagamento entram na regra de Apoiador (exceção à regra inicial do plano) | Decidido; código em andamento (não commitado) |
| Primeiro curso realizado usa o status `passed` do `lms_course_status` | Decidido; código em andamento (não commitado) |
| Perfil básico: apelido, nome, sobrenome e WhatsApp preenchidos (Minha Conta > Meus dados > Informações Básicas) | Decidido; código em andamento (não commitado) |
| Primeiro comentário no Fórum, primeira edição na Wiki e Associado (manual) | Decidido; código em andamento (não commitado) |
| Data "Obtida em" registrada; ao passar o mouse, mostra "Obtida em: DATA" | Decidido; entidade `aculta_badge_award` em andamento (não commitada, não instalada no Runtime) |

### 1.9 Revista: caixa de autor
Estado: decisão registrada, sem código. Detalhe da arquitetura em `ACULTA-USER-PLAN.md`, seção C1.

| Decisão | Status |
| --- | --- |
| Caixa de autor da publicação da Revista é programada no `aculta_user` (opção 1: presenter + contrato `data-portal-ui="author-card"` + componente no ACULTA420), sem módulo contrib de layout ou de exibição | Decidido (2026-10-13); documentado, não implementado; fase U2, PR próprio |
| A caixa mostra só nome de exibição (apelido), foto, bio e link para `/u/<apelido>`, cada um sob as regras de `PublicProfilePolicy` | Decidido; documentado |
| O autor é `node.uid`; `field_editorial_author` não entra na caixa | Decidido; documentado |
| Link para o perfil é construído no purpose `main` (perfil público só existe lá) | Decidido; documentado |

## 2. Pendências de decisão
1. Estado da associação (ativa, inativa, suspensa) controlado pelo administrador, com mensagem verde/amarela/vermelha
   para a pessoa, e data de associação preenchida pelo administrador (pedido pelo responsável; não implementado).
2. Confirmação dos tipos de discussão vinculada (decisão 24) e dos slugs da Atividade (decisão 20).
3. Discussão do LMS por atividade (fase 7): o LMS 1.2.3 instalado não tem campo de comentário em `lms_activity`. As
   opções (a), (b) ou (c) estão em aberto.
4. Tipo de publicação da Revista: a Revista publica hoje com o tipo `article` ("Notícia"). Falta decidir se a
   publicação será um tipo novo "blog" (configuração, sem módulo) ou se `article` continua. Afeta a caixa de autor, que
   só é programada no tipo que o responsável escolher.
5. Mapeamento dos campos da publicação: "chamada" para `field_subtitle` e "resumo" para `field_summary` (ambos existem
   em `article`). Falta confirmar, porque o tipo "blog" pode ter campos próprios.

## 3. Verificações feitas
| Item | Como foi verificado | Resultado |
| --- | --- | --- |
| Gate Drupal 11+ | `php scripts/validate-aculta-portal-drupal11.php` | PASS (675) |
| Gate de slugs | `php scripts/validate-public-slugs.php` | PASS (338 rotas) |
| Gate fronteira módulo/tema | `php scripts/validate-module-theme-boundary.php` | PASS (771) |
| Gate de endereços | `php scripts/validate-environment-addresses.php` | PASS |
| Gate de fundação do tema | `drush scr scripts/validate-aculta420-foundation.php` | **FAIL, anterior a esta série** (DT-T19: durações fora de `tokens.css`) |
| Testes unitários do submódulo | `vendor/bin/phpunit ... aculta_user/tests/src/Unit` | 8 testes passam |
| Testes unitários do Portal | `vendor/bin/phpunit ... aculta_portal/tests/src/Unit` | 68 testes passam |
| Testes de Kernel de e-mail | `SIMPLETEST_DB` com SQLite isolado | 3 testes passam |
| Páginas do site (home, conta, `/u/`) | HTTP | 200 |
| Painel com avatares, visualização e link de sortear | HTTP (HTML) | verificado |
| Sorteio pela web | HTTP (requisição AJAX do link com token CSRF) | verificado: `replaceWith` sobre o avatar; foto troca no banco |
| Remover foto personalizada volta ao acervo e apaga o arquivo | CLI | verificado |
| Salvar foto sem enviar mantém a foto e os papéis | HTTP | verificado |
| Popup fecha ao salvar; visual do popup | **Não verificado** (sem navegador) | pendente |
| Testes de Kernel da rota e da privacidade do perfil | — | **pendentes** |
| Fluxo de recorte e abertura automática | — | **pendentes** (navegador; o lado servidor está verificado) |

## 4. Alterações no Runtime (homelab)
Todas com backup prévio em `scratchpad` (fora do repositório).
- `drush en aculta_user` (módulo habilitado).
- `config:import --partial` das configurações do submódulo (fases 3 a 5), com correção de duas dependências: `core`
  listado como módulo e o padrão `aculta_produto`, que apontava para `commerce_product.type.produto_loja`
  (o nome correto é `commerce_product.commerce_product_type.produto_loja`).
- Tabelas de campo criadas: `node__field_discussao`, `profile__field_rg`, `profile__field_orgao_emissor`,
  `profile__field_profissao`, `profile__field_data_nascimento`, entre outras.
- Apelidos provisórios `pessoa-1` e `pessoa-5`.
- Papel `email_confirmed` concedido a `teste.comum` (uid 5) junto com `wiki_contributor`, pela regra de decisão 28.
- Avatar do acervo atribuído às duas contas sem foto.
- Permissões de escrita (ACL) nas pastas `web/sites/default/files/pictures` e `pictures/acervo` para os usuários e
  grupos do processo PHP do site (`aculta`, `www-data`), porque o acervo tinha sido criado pelo usuário de CLI.
- Links de login único gerados para as contas de teste, usados para simular a sessão (uid 1 e uid 5).
- O cache de container (`cache_container`) foi limpo uma vez para o PHP-FPM recarregar o container.

## 5. Commits e branches
| Branch | Commit | Conteúdo |
| --- | --- | --- |
| `aculta-user-u1` | `c6b001b` | fase 1 (perfil público), e-mail, decisões 2 e 45 |
| `aculta-user-papeis` | `0fc367a` | papéis de Wiki e moderação do Fórum (passo 2), gate de papéis |
| `aculta-user-u0` | `db52531` | relatório de verificação U0 (sem merge) |
| `aculta-user-fase3-participacao` | `8586d4d` | fase 3 (participação no Fórum) |
| `aculta-user-fase4-atividade` | `018d5bb` | fase 4 (Atividade) |
| `aculta-user-fase5-cadastro` | `613c28f` | fase 5 (cadastro, avatar, associado, relatório) e mudança do acervo |
| `aculta-user-participacao` | `f4df815` | pilha completa das fases 3 a 5 e correções de popup, avatar e 500 |
| `docs/aculta-user-correcoes-slugs` | `c2386f0` | correções do plano |

Nenhum PR foi aberto. Nenhuma tag foi criada. Nada foi mesclado em `main`.

## 6. Não commitado (em andamento)
- `src/Badge/` e `src/Entity/` (entidade `aculta_badge_award`, catálogo e serviço de badges): não versionados. Não
  estão instalados no Runtime; os hooks de avaliação estão fora da árvore, em `scratchpad`.
- `src/Form/AvatarCropForm.php` (popup de recorte): sem rota, sem verificação.
