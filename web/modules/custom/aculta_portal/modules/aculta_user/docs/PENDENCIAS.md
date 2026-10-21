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

## Pendente de decisão do responsável

1. **Associado (estado):** é só o papel `associado` (hoje: marcador `AssociadoMarker`), ou também há situação
   (ativo, suspenso, desligado) e data de adesão? Quem perde o papel mantém o histórico? (plano, pergunta 14).
2. **Adesão:** só o administrador torna alguém associado, ou haverá pedido de adesão? (plano, pergunta 15).
3. **Badges manuais:** quem concede (só administrador, ou também editores de Wiki e de Fórum)? (plano, pergunta 18).
4. **Badges automáticas na primeira versão:** quais regras entram? Sugestão do plano: verbetes publicados, respostas
   no Fórum e tempo de participação. (plano, pergunta 19).
5. **Dono do tooltip "Obtida em":** `aculta_game` ou `aculta_user` (ver acima).

## Pendente técnico (sem decisão de produto)

- Testes de Kernel de rota, privacidade e fluxo de recorte: não executados. Banco de teste dedicado
  `aculta_simpletest` (procedimento em `docs/operations/BANCO-DE-TESTE.md`).
- Inspeção visual humana do popup de foto, do recorte e da aba Atividade: pendente.
- Google Login: importação OAuth da foto está configurada no código, mas o login não está operacional no servidor de
  testes (`client_id` vazio; `client_secret` na configuração do runtime; risco do módulo `group` em gravação posterior).
  Ver `docs/integrations/GOOGLE.md`.
- Estado de release: `aculta_user` continua em Dev. RC exige os itens 1 a 5 decididos, testes de Kernel e revisão visual.
