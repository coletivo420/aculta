# ACULTA User (`aculta_user`)

Submódulo do ACULTA Portal para integrações de perfil, participação e experiência
de usuário. O Drupal User e Profile continuam como fontes de verdade.

## Documentação

- Aceites legais e consentimentos opcionais são mantidos no ledger `aculta_user_agreement`, com classes, arquivos, hooks, serviços, rotas e métodos prefixados por `aculta_user_agreement`. O contrib Agreement foi desinstalado; dados legados disponíveis são marcados como estado final sem histórico anterior. Ver entrada corrente no [CHANGELOG](CHANGELOG.md) e tratamento no [inventário LGPD](../../../../../../docs/compliance/LGPD-INVENTARIO.md). O ledger é append-only na aplicação e HMAC torna alterações detectáveis; não oferece imutabilidade contra acesso direto ao banco e à chave privada. Registros duram enquanto a conta existir e são removidos na exclusão da conta; o update 10010 também eliminou registros legados de contas já inexistentes.
- A pessoa pode consultar e alterar o consentimento opcional de rastreamento de e-mail em `/configuracoes/rastreamento-de-e-mail`. A captura de IP depende da configuração correta de proxy confiável em cada ambiente.
- A auditoria administrativa é somente leitura em `/painel-administrativo/aculta/termos/aceites`, protegida por `administer aculta user agreements`; apresenta IP/User-Agent e o estado da cadeia HMAC. O acesso não concede operações para alterar ou excluir eventos.

- [Classes e hooks](docs/CLASSES-E-HOOKS.md)
- [Hierarquia de funções](docs/HIERARQUIA-DE-FUNCOES.md)
- [Amizades](docs/AMIZADES.md)
- [Foto de perfil](docs/FOTO-PERFIL.md)
- [Troca de e-mail](docs/TROCA-DE-EMAIL.md)
- [Cartões de autoria e identidade](docs/AUTHOR-CARDS.md)
- [Variantes de Author Card](docs/AUTHOR-CARD-VARIANTS.md)
- [Contrato da API de cartões](docs/AUTHOR-CARD-API.md)
- [Port do Author Pane](docs/AUTHOR-PANE-PORT.md)
- [Privacidade e acesso dos cartões](docs/PRIVACY-AND-ACCESS.md)
- [CHANGELOG](CHANGELOG.md)

Para mudanças coordenadas com a apresentação, consulte a
[fronteira módulo–tema](../../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md)
e o [mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Política compartilhada de participação — Fórum e Wiki420

O subkernel **`aculta_user` é o proprietário definitivo das regras comuns de primeira participação e moderação**, estendendo `src/ParticipationRules.php` sem mover a política para `aculta_portal`. A função atual `requiresFirstDiscussionModeration()` é específica das discussões do Fórum; a extensão para Wiki usará histórico próprio de verbetes publicados e contrato compartilhado no User, sem confundir comentários com verbetes. Todo `email_confirmed` pode criar verbetes; o **primeiro verbete** exige moderação e somente `content_editor` aprova/publica, conforme workflow e acesso efetivos. A aprovação do primeiro não concede publicação direta posterior. O Fórum e a Wiki continuam donos de suas filas, revisões, adapters e storage de domínio. Implementação sujeita a inventário DI/chamadores, ADR, testes e homelab. [Política e roadmap USER-PART 0.1](../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md) · [Plano de moderação Wiki](../aculta_wiki/docs/WE-SATIVA-MODERATION-PLAN.md). Autosave Form permanece futuro.


## Inventário transversal USER-PART 0.1-A

[Inventário comparativo Fórum, Fórum LMS e Wiki](docs/USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md): preserva os contratos existentes de `ParticipationRules`, `ParticipationHooks`, `ParticipationEditWindow`, moderação contextual Group e workflow Wiki. A centralização priorizada é de **políticas e interfaces em `aculta_user`**; queries, filas e transições de cada domínio permanecem nos kernels respectivos. Próximo marco USER-PART 0.1-B: contrato/ADR e testes de caracterização antes de PHP funcional. Nenhum teste Runtime foi executado.

## Contrato público de associado ativo — F-GROUP 0.1

Consumidores que precisam da situação de associado — hoje o Fórum restrito, via
Group — usam `aculta_user.active_associate`
(`Drupal\aculta_user\Associado\ActiveAssociateInterface`), alias do serviço
`aculta_user.associado_marker`:

- `isActiveAssociate(AccountInterface $account): bool` — verdadeiro somente para
  conta não anônima com situação `ativo` em `aculta_associado`.
- `associateCacheTags(AccountInterface $account): array` — tags
  `aculta_associado_list` e `user:<uid>`, para o consumidor declarar
  cacheability.

A situação continua sendo escrita apenas por este submódulo. Consumidores não
criam papel `associado`, não gravam a situação em storage próprio e não a
duplicam em Group: membership de Group é consequência da situação, não a fonte.
Eventos de `aculta_associado` (ativação, suspensão, desligamento, readmissão e
alteração administrativa) são a base da reconciliação feita pelo consumidor.
Detalhes da frente no Fórum em
[`../aculta_forum/docs/RESTRICTED-FORUMS.md`](../aculta_forum/docs/RESTRICTED-FORUMS.md).

## Auditoria transversal XK 0.1 — 2026-10-22

XK-02/03/04/11: User detém identidade, situação real de associado ativo, amizades, preferências pessoais e política de participação. Expor serviço autoritativo de associação para Group no Fórum; especificar eventos de ativação/suspensão/desligamento, reconciliação e cache; não tratar `associado` como papel. Messenger entrega avisos, User não duplica inbox. Política primeira contribuição Fórum/Wiki permanece em ParticipationRules.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B: contratos compartilhados de primeira participação e elegibilidade de associado ativo ficam no User. Preservar ParticipationRules e ParticipationEditWindow; caracterizar Fórum e exclusões LMS antes de acrescentar lookup da primeira publicação Wiki. Consulta de situação da associação deve ser autoritativa; Group do Fórum é consumidor, não dono dessa situação.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## Decisão normativa: ACULTA Studio (2026-10-22)

ACULTA Studio presents account-oriented user experiences. aculta_user stays the owner of identity, profiles, roles and first-participation policy; the GanjaLab/WE SATIVA user experience belongs to aculta_studio, not a second implementation in User.

[Contrato e roadmap transversal](../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**
