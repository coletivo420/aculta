# ACULTA User (`aculta_user`)

Submódulo do ACULTA Portal para integrações de perfil, participação e experiência
de usuário. O Drupal User e Profile continuam como fontes de verdade.

## Documentação

- Aceites legais e consentimentos opcionais são mantidos no ledger do `aculta_user`. Ver entrada corrente no [CHANGELOG](CHANGELOG.md) e tratamento proposto no [inventário LGPD](../../../../../../docs/compliance/LGPD-INVENTARIO.md). O ledger é append-only na aplicação e HMAC torna alterações detectáveis; não oferece imutabilidade contra acesso direto ao banco e à chave privada. Registros duram enquanto a conta existir e são removidos na exclusão da conta.
- A pessoa pode consultar e alterar o consentimento opcional de rastreamento de e-mail em `/configuracoes/rastreamento-de-e-mail`. A captura de IP depende da configuração correta de proxy confiável em cada ambiente.
- A auditoria administrativa é somente leitura em `/painel-administrativo/aculta/termos/aceites`, protegida por `administer aculta consent ledger`; apresenta IP/User-Agent e o estado da cadeia HMAC. O acesso não concede operações para alterar ou excluir eventos.

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
