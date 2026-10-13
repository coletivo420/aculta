## 0.3.0-dev.6 — botão "Sortear novo avatar" na linha de ações — 2026-10-13

- O sorteio fica como botão na linha de ações, ao lado de "Salvar foto do perfil", no lugar de "Remover foto" (personalizada). Regra de exibição inalterada (INV-FOTO-2).
- `aculta_user.info.yml` 0.3.0-dev.6. RUNTIME STATUS: DEFERRED (inspeção visual pendente; captura conferida no Chromium).

## 0.3.0-dev.5 — sortear atualiza o painel por AJAX; fechar o modal recarrega — 2026-10-13

- "Sortear outro avatar" troca por AJAX o avatar da visão geral e da prévia, sem recarregar.
- Ao fechar o modal depois de sortear, a página recarrega uma vez (INV-FOTO-8). Fechar sem sortear não recarrega.
- `aculta_user.info.yml` 0.3.0-dev.5. RUNTIME STATUS: DEFERRED (navegador verificado com Chromium; inspeção visual pendente).

## 0.3.0-dev.4 — foto de perfil: popup, estados, recorte e remoção (consolidado) — 2026-10-13

- Popup da foto é modal Drupal centralizado (`/avatar/foto`), com prévia do avatar atual e a orientação do popup anterior. Lápis de edição sobre o gatilho.
- Regras por estado (`docs/FOTO-PERFIL.md`): avatar do sistema sem "Remover", com "Sortear outro avatar" e campo de envio; foto personalizada com "Remover foto" (botão próprio, sorteia do acervo e recarrega o painel), sem sortear.
- Campo de envio sempre vazio: sem nome nem link da foto atual no popup.
- Recorte só logo após o envio, e só do arquivo recém-enviado. "Aplicar recorte" torna o arquivo permanente, troca a foto da conta e recarrega o painel (sem reabrir o popup: era o loop).
- Recorte sem abas ("Crop image"/tipo) e sem duplicação do campo (pre_render limpo); modal de recorte com 560 px e 75% da altura, com rolagem.
- Formulários de foto sem `readonly` nas dependências (rebuild AJAX do cache).
- Gate: `scripts/validate-aculta-user-photo.php` (INV-FOTO-1 a INV-FOTO-7). Roteiro: TESTES-NAVEGADOR seção P.
- `aculta_user.info.yml` 0.3.0-dev.4. RUNTIME STATUS: DEFERRED (teste de navegador do fluxo de foto pendente).

## 0.3.0-dev.3 — regras de estado da foto de perfil — 2026-10-13

- Avatar do sistema: sem "Remover"; campo de envio com "Escolha uma imagem"; "Sortear outro avatar" visível.
- Avatar personalizado: "Remover" visível; sem "Sortear outro avatar".
- "Recortar foto" removido do popup: o recorte abre só logo após o envio, e só para o arquivo recém-enviado.
- Regras em `docs/FOTO-PERFIL.md`; gate `scripts/validate-aculta-user-photo.php`; roteiro de teste em TESTES-NAVEGADOR seção P.
- `aculta_user.info.yml` 0.3.0-dev.3. RUNTIME STATUS: DEFERRED (navegador pendente; ver seção P).

## 0.3.0-dev.2 — sortear avatar no popup de foto (AJAX) — 2026-10-12

- Botão "Sortear outro avatar" no popup de foto do painel da conta. Por AJAX troca só o bloco da foto
  (`#aculta-user-avatar`); sem JavaScript, o envio comum volta ao painel. Nunca sorteia o mesmo avatar quando há outro.
- A cópia anterior do acervo é apagada quando nenhuma outra conta a usa.

## 0.3.0-dev.1 — cadastro, avatar, associado e relatório (fase 5, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Cadastro: o nome de usuário vira o apelido inicial do perfil (sem colidir; reservadas e formato validados). Conta
  OAuth recebe como nome de usuário a parte local do e-mail recebido, sem o domínio (`SocialAuthFieldsSubscriber`,
  evento `social_auth.user.fields`).
- Avatar do acervo: quem não tem foto recebe um sorteado (`AvatarPool`), copiado para `user_picture` como arquivo
  gerenciado; a escolha fica em `user.data` (`avatar_id`). A família `mushrooms` foi removida do acervo (10 arquivos
  inexistentes). A foto do provedor OAuth continua sendo importada pelo `social_auth`.
- Troca de foto: a foto anterior enviada pela pessoa é apagada quando não há outra referência (`PictureReplacementHooks`).
  Nunca apaga cópia do acervo (`public://pictures/acervo/`). No Runtime não há foto enviada por usuário hoje.
- Papel `associado`, sem permissões; só o administrador concede.
- Campos do perfil, sem cifra: `field_rg`, `field_orgao_emissor`, `field_profissao`, `field_data_nascimento`. O CPF/CNPJ
  continua no campo único existente (`field_documento`, cifrado pelo módulo de documento).
- Relatório `/admin/aculta/associados` (permissão `administer aculta associados`); RG mascarado na tela. Exportação CSV
  em `/admin/aculta/associados/exportar`, somente com `export aculta associados` (administrador).
- Pendências: botão "sortear outro avatar" em Minha Conta; campos de Outros Dados no formulário da conta; estado e data
  de adesão (não decidido: foi usado só o papel); endereço no relatório; teste de Kernel (ao final da série).

## 0.2.0-dev.2 — Atividade em Minha Conta (fase 4, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Rota `/atividade/{aba}` (wiki, forum, revista, comentarios, conquistas), só no propósito `account`. Cada aba lista
  só o que a própria pessoa fez e ainda está publicado; sem atividade de outra pessoa. Abas são links com a aba atual
  marcada (sem JavaScript). Vazio: mensagem curta.
- Wiki: verbetes publicados em que a pessoa tem revisão, com link para o histórico público do verbete.
- Item "Atividade" no menu da conta (`AccountShellBuilder`), depois de "Cursos". Aparece só se a rota existir: o Portal
  não depende do submódulo.
- Conquistas: placeholder até a fase 6 (badges).
- Pendências: flags de tópico seguido e salvo na aba Fórum; explicação dos limites de participação na aba; nome do
  item de menu e slugs das abas (decisão 20, usados os padrões do plano); verificação no Runtime (DEFERRED).

## 0.2.0-dev.1 — participação no Fórum (fase 3, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Regras de participação (`ParticipationRules`, derivadas de contagens, sem contador persistido): comentário de Fórum
  de quem não tem comentário publicado vai para a fila de moderação (`comment_presave`); os seguintes são publicados.
- Criação de tópico de Fórum exige comentários publicados (padrão 10) e fica bloqueada enquanto o primeiro comentário
  aguarda aprovação (`entity_create_access`). Mensagem de bloqueio no próprio acesso; a explicação na Atividade fica
  para a fase 4.
- Painel `/admin/config/aculta/portal/participacao` (permissão `administer aculta participacao`), com
  `aculta_user.settings`: limite ligado/desligado, mínimo, primeiro comentário moderado, bloqueio e papéis isentos
  (padrão: `content_editor` e `administrator`).
- Discussão vinculada: campo `field_discussao` (referência a tópico de Fórum) em `article`, `activity`, `project`,
  `document` e `wiki_entry`. A página mostra os últimos 5 comentários publicados e o link para a discussão completa no
  propósito `forum` (via `DomainPurposeManager`). Sem tópico, não há bloco.
- Pendências: configuração e campo ainda não aplicados no Runtime (`config:import` exige autorização); matriculados
  (fase LMS); cartão de pré-visualização (E6), que depende de apresentação no ACULTA420; testes (ao final da série).

# Changelog — aculta_user

## 0.1.0-dev.1 — perfil público por apelido (U1, RUNTIME STATUS: DEFERRED) — 2026-10-12

- Rota pública `/u/{apelido}` (exceção de slug registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`). Só serve no
  propósito `main`; perfil inexistente, privado ou fora do propósito responde 404.
- Visibilidade por item (`PublicProfilePolicy`): apelido público por padrão; foto, bio, e-mail e redes sociais
  só aparecem se a própria pessoa escolher. Cidade, UF, WhatsApp, nome completo e endereço nunca são públicos.
- Formulário `/configuracoes/perfil` (apelido, perfil público e escolhas por item). Apelido com formato, palavras
  reservadas e unicidade validados no formulário.
- Campos do perfil `participante` em `config/install`: `field_perfil_publico`, `field_exibir_foto`,
  `field_exibir_bio`, `field_exibir_email`, `field_exibir_redes`, `field_bio`, `field_redes_sociais`.
- `field_nickname` passa a ser público (`profile_private: false` em `config/sync`).
- Teste unitário de `PublicProfilePolicy` (5 testes).
- `RoleSyncHooks` (`#[Hook]` user_insert/user_update): concede `wiki_contributor` a quem tem `email_confirmed` (decisão 28). Não remove o papel quando `email_confirmed` sai (não decidido). Teste unitário (3 casos).
- Pendências: vínculo do link a `/configuracoes` (bloco do Portal); apresentação no ACULTA420 (sem `data-portal-ui`
  nesta entrega); badges (U4); teste de Kernel da rota e da privacidade; execução no Runtime (`drush en` e
  `config:import` exigem autorização).
