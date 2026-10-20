# Auditoria de portabilidade — Drupal Linky

## Referência reproduzível

- Projeto: [Drupal Linky](https://www.drupal.org/project/linky).
- Tag estudada: `2.0.0-beta1`, commit
  `2d70712a854065428faf0228aef96066bd6812f2`.
- Branch `2.x` observada em 2026-10-20:
  `5bcb1e317bb401492aa99629b37c09aada2bcc75`.
- A release informa Drupal >=10.1 e a página do projeto indica que não há
  release estável suportada. Compatibilidade no Drupal 11 do ACULTA não foi
  executada.
- Licenciamento Drupal.org: GPL-2.0-or-later; confirmar cabeçalhos/arquivos da
  tag antes de reutilizar código.
- Documentação API consultada: [EntityAccessControlHandler 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityAccessControlHandler.php/class/EntityAccessControlHandler/11.x)
  e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).

## Arquitetura e avaliação

Na tag estudada, `src/Entity/Linky.php` declara entity `linky` com tabelas
`linky`/`linky_revision`, revisão padrão e metadados de revisão. Os campos base
são proprietário Drupal, campo `link` limitado a destinos externos, created,
changed e checked. `Linky::toUrl('canonical')` redireciona a URL canônica da
entidade ao destino; a rota interna de administração é transportada em option
própria do `Drupal\linky\Url`. `LinkyAccessControlHandler` exige permissão
genérica para view e oferece edit/delete próprios pelo proprietário com cache
por usuário/permissões. Isso requer revisão: não copiar sem adaptar o contrato de
visualização pública e access/cache.

O `.info.yml` depende de Dynamic Entity Reference e Core Link/User. O projeto
descreve Linkit e widget DER/IEF como integrações. Isso informa destino
reutilizável, histórico, referências e formulários, mas não resolve propriedade
de micropágina, composição nem analytics.

**Decisão:** PORTAR conceitos e avaliar arquivos selecionados; não adicionar
`drupal/linky` como dependência. Usar Entity API, Revision API e Field API
instaladas no Drupal. Validar dependências opcionais (Linkit/DER/IEF) e APIs
Drupal 11 na fase de implementação.

| Área upstream | Avaliação ACULTA |
| --- | --- |
| Content Entity de link e proprietário | PORTAR após comparar necessidade com Core; restringir tipo de URI conforme decisão. |
| Revisões e metadados | PORTAR o desenho se a política editorial precisar; validar UI/API Drupal 11. |
| Referência e widget DER/IEF | PORTAR ou adaptar; verificar módulos opcionais e licença. |
| Integração Linkit/filtro de texto | ADIAR; não necessária para a landing inicial. |
| Listagem/formulário administrativo | PORTAR comportamento em Form API/Views Drupal. |
| Resolução via `toUrl('canonical')` | DESCARTAR como implementado: canônico aponta ao destino e requer URL subclass própria; avaliar Redirect API/rota segura Drupal. |
| Access do Linky | PORTAR conceitos, reimplementar grants para página/link conforme ownership e visibilidade pública. |

Nenhum arquivo Linky foi copiado. A avaliação de código e change records Core
deve preceder implementação.
