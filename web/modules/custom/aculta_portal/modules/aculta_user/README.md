# ACULTA User (`aculta_user`)

Em dev.74, a API `PublicMemberIdentityInterface` expõe somente o apelido aprovado
pela política pública para consumidores como a busca de conversas do Messenger;
nomes internos de conta não são fallback de identidade pública.

## Atualização de ownership — Conta e Perfil separados (dev.72)

`/studio/perfil` é a experiência independente de identidade comunitária.
Bio, redes, foto/avatar, visibilidade e perfil público continuam controlados
pelo ACULTA User. Suas rotas Studio de edição são
`/studio/perfil/bio`, `/studio/perfil/privacidade` e
`/studio/perfil/redes/{rede}`, com Form API original.

`/studio/conta` pertence à administração de cadastro/segurança do Portal.
**Minha Conta antiga em `/conta-legada` permanece permanentemente e não deve perder
seus formulários/modal/JS**. `/minha-conta` direto responde 404.
Não há `/studio/pessoal/*` nem aliases. Testes Runtime pendentes.
[Contrato](../../../../../../docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md).
Registros P0/P2-A abaixo são históricos.


Submódulo do ACULTA Portal para integrações de perfil, participação e experiência
de usuário. O Drupal User e Profile continuam como fontes de verdade.

## Situação STUDIO-P2-A (2026-10-24) — uso dos serviços User pelo Studio

A rota privada `/studio/pessoal/perfil`, implementada em
`aculta_studio` dev.12, compõe `AccountBioCard` e
`SocialLinksCard` existentes, o editor modal de foto e o contrato
`aculta_user.public_profile_link`. **Os handlers de gravação,
`PublicProfilePolicy`, entidades User/Profile, apelido e visibilidade
continuam integralmente do User e Portal.** Sem atualização de versão
do User nesta fase (nenhum código User modificado). O fallback no-JS
dos modais e a integração direta da privacidade/apelido ainda estão
pendentes de P2-B; P2-A não foi homologada em Runtime.

## Interface de perfil no Studio — contrato documental STUDIO-P0

A experiência **Meu Espaço** será futuramente a interface de edição do
perfil, sem mudar a propriedade dos dados e das autorizações:
`aculta_user` mantém `PublicProfilePolicy`, o perfil público
`/u/{apelido}` em `main`, apelido, biografia, redes, foto/avatar,
amizades e controles de visibilidade; User/Profile continuam as entidades
fontes. A rota `/studio/pessoal/perfil` existe na P2-A como composição parcial; a migração completa dos formulários ainda não ocorreu.

O Studio deverá compor os formulários **existentes** — inclusive
`UserPictureForm`, `AvatarCropForm`, `ProfileVisibilityForm`,
bio e redes — preservando sorteio pendente/recorte e políticas de
autores/editores. A conta pessoal no purpose `account` pode editar apenas
a própria identidade; conseguir editar **não** torna dados públicos.
Não expor endereço, documentos, e-mail privado nem informações de membros
na Author Card. Atividades pessoais continuam autorizadas pelo User e
entidades hospedeiras; não duplicar armazenamento no Studio.

**P0 foi documentação apenas; P2-A acrescentou uma interface de composição no Studio.** Nenhum arquivo funcional User ou fluxo
de foto foi migrado. Referências:
[plano STUDIO-P0–P7](../../../../../../docs/architecture/ACULTA-STUDIO-PERSONAL-EXPERIENCE.md),
[privacidade dos cartões](docs/PRIVACY-AND-ACCESS.md) e
[contrato da foto](docs/FOTO-PERFIL.md).

## Estado atual

- `0.4.0-dev.70`: o contrato `PublishedParticipationLookupInterface` agora
  despacha evidência agregada por contexto e falha conservadoramente quando o
  provider falta, duplica ou falha. O adapter `wiki_entry` é owner da Wiki;
  os consumidores existentes do Fórum não migraram. User Unit 123/439,
  caracterização Kernel 9/129 e Fórum Kernel 1/39 passaram.

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
- [Caracterização da participação compartilhada — USER-PART 0.1-C1](docs/USER-PART-0.1-C1-CHARACTERIZATION.md)
- [CHANGELOG](CHANGELOG.md)

Para mudanças coordenadas com a apresentação, consulte a
[fronteira módulo–tema](../../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md)
e o [mapa módulo–tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Política compartilhada de participação — Fórum e Wiki420

O subkernel **`aculta_user` é o proprietário definitivo das regras comuns de primeira participação e moderação**, estendendo `src/ParticipationRules.php` sem mover a política para `aculta_portal`. A função atual `requiresFirstDiscussionModeration()` é específica das discussões do Fórum; a extensão para Wiki usará histórico próprio de verbetes publicados e contrato compartilhado no User, sem confundir comentários com verbetes. Todo `email_confirmed` pode criar verbetes; o **primeiro verbete** exige moderação e somente `content_editor` aprova/publica, conforme workflow e acesso efetivos. A aprovação do primeiro não concede publicação direta posterior. O Fórum e a Wiki continuam donos de suas filas, revisões, adapters e storage de domínio. Implementação sujeita a inventário DI/chamadores, ADR, testes e homelab. [Política e roadmap USER-PART 0.1](../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md) · [Plano de moderação Wiki](../aculta_wiki/docs/WE-SATIVA-MODERATION-PLAN.md). Autosave Form permanece futuro.


## Inventário transversal USER-PART 0.1-A

[Inventário comparativo Fórum, Fórum LMS e Wiki](docs/USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md): preserva os contratos existentes de `ParticipationRules`, `ParticipationHooks`, `ParticipationEditWindow`, moderação contextual Group e workflow Wiki. A centralização priorizada é de **políticas e interfaces em `aculta_user`**; queries, filas e transições de cada domínio permanecem nos kernels respectivos. Próximo marco USER-PART 0.1-B: contrato/ADR e testes de caracterização antes de PHP funcional. Nenhum teste Runtime foi executado.

## Caracterização USER-PART 0.1-C1

[Caracterização das políticas compartilhadas](docs/USER-PART-0.1-C1-CHARACTERIZATION.md):
`ParticipationRules`, `ParticipationEditWindow`, `EditWindowExemptionInterface`
e os hooks consumidores estão fixados por testes Unit e Kernel (C1-A a C1-D,
commits `512fe60d`, `f9e579c0`, `e6a4dc4d` e `aa55a84f`). Nenhuma classe de
produção, configuração de serviço ou permissão foi alterada.
`PublishedParticipationLookupInterface` segue **documentada, não implementada**.
Nenhum Runtime foi executado.

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
