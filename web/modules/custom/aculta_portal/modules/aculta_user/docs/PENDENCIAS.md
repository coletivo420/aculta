# aculta_user: estado de finalização e pendências

Data de referência: 2026-10-21. Registro operacional da frente aberta; a decisão de cada item fica no dono indicado.

## Concluído no módulo

- Foto de perfil: regras por estado, sorteio só na prévia, remoção, recorte e importação OAuth como foto personalizada
  (`FOTO-PERFIL.md`, INV-FOTO-1 a 9, gate `validate-aculta-user-photo.php`).
- Atividade em abas no padrão de Meus Dados e Segurança (`ActivityController`, 0.3.0-dev.8).
- Mapa de rotas: rotas do módulo registradas em `docs/architecture/ROUTES-SITEMAP.md` (perfil, bio, redes, memorial,
  associados, avatar e endpoints AJAX/POST).

## Pertence a outro módulo (não duplicar aqui)

- **Conquistas e badges:** administração (concessão, revogação e histórico com "Obtida em") e a aba "Conquistas" da
  Atividade são do `aculta_game` (`AchievementGrantForm`, `AchievementAdminController`, `AchievementPresentation`).
  O `aculta_user` mantém o serviço `BadgeService`, a entidade `aculta_badge_award` e os gatilhos. Uma tela própria
  no `aculta_user` foi removida por duplicar a do `aculta_game`.
- **Tooltip "Obtida em DATA" na lista de conquistas do público:** não existe na apresentação do `aculta_game`
  (a data só aparece na administração). Decisão de dono: `aculta_game` (apresentação) ou `aculta_user` (cartão).

## Decidido pelo responsável (2026-10-21)

- **Associado é situação, não papel.** Pedido de adesão pela pessoa; só o administrador concede, suspende ou desliga
  (`docs/ASSOCIADO.md`).
- **Badges:** só administradores concedem (`administer aculta game`; nenhum papel recebe essa permissão).
- **Tooltip "Obtida em DATA":** fica no `aculta_game` (missões concluídas, `MissionProgress`).
- **Convite e adição pelo administrador:** sim (situação `convidado`; adicionar direto como ativo).
- **Não mesclar** esta frente: o branch é compartilhado com outro agente e a PR #297 segue aberta.

## Pendente de decisão do responsável

2. **Badges automáticas na primeira versão:** quais regras entram? (sugestão do plano: verbetes publicados, respostas
   no Fórum, tempo de participação).

## Pendente técnico (sem decisão de produto)

- Testes de Kernel de rota, privacidade e fluxo de recorte: não executados. Banco de teste dedicado
  `aculta_simpletest` (procedimento em `docs/operations/BANCO-DE-TESTE.md`).
- Inspeção visual humana do popup de foto, do recorte e da aba Atividade: pendente (a rodada automatizada no X11 passou; ver TESTES-NAVEGADOR, seção R).
- Google Login: importação OAuth da foto está configurada no código, mas o login não está operacional no servidor de
  testes (`client_id` vazio; `client_secret` na configuração do runtime; risco do módulo `group` em gravação posterior).
  Ver `docs/integrations/GOOGLE.md`.
- Estado de release: `aculta_user` continua em Dev. RC exige os itens 1 a 5 decididos, testes de Kernel e revisão visual.
