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
