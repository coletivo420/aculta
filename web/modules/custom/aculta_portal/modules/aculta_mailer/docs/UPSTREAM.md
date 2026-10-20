# Pesquisa upstream e decisões de reutilização

Data: 2026-10-20. Este documento separa o que foi **verificado no código instalado** do que é **referência de
conceito**. Itens marcados como "não verificado" não devem ser tratados como base de decisão até a checagem.

## 1. Verificado no código instalado (fonte de verdade para a versão em uso)

| Fonte | O que foi confirmado | Decisão |
|---|---|---|
| `web/core/lib/Drupal/Core/Mail/Plugin/Mail/SymfonyMailer.php` | Backend `symfony_mailer` do Core, com atributo `#[Mail]`. | Base de envio. O ACULTA usa o Symfony Mailer por meio do Core; não cria um transporte paralelo. |
| `web/modules/contrib/symfony_mailer` 2.0.2 | Pacote com camada de envio do Drupal; submódulos `mailer_policy`, `mailer_transport` e `mailer_override`. | Mantido enquanto o `aculta_mailer` não tiver equivalência. Não editar o contrib. |
| `mailer_policy/src/EmailAdjusterBase.php`, `EmailAdjusterManager.php`, atributo `#[EmailAdjuster]` | Adjusters como plugins (From, To, Cc, Bcc, Subject, Body, Transport, Priority, PlainEmail, SkipSending, InlineImages e outros). | Conceito portado: adjusters do `aculta_mailer` serão plugins próprios com a mesma granularidade. |
| `mailer_policy/src/Entity/MailerPolicy.php` | Política como Config Entity com hierarquia. | Não duplicar. Enquanto Mailer Policy estiver ativo, o `aculta_mailer` reutiliza essa entidade para o que ela já cobre. |
| `mailer_override/src/MailManagerReplacement.php` e `OverrideBase` | Substitui o gerenciador do Core; overrides por plugin. | Não portar a substituição global. A pilha ACULTA chamará uma API própria, e o override só permanece enquanto os consumidores do Core dependerem dele. |
| `email_confirmer` (módulo instalado, versão `8.x-1.0`) | Confirmação de endereço por token, usada por `EmailConfirmationRequester`. | Mantido como fonte de verdade da confirmação até a fase F9. A API `EmailConfirmationManagerInterface` consumirá esse módulo, sem ser acoplada a ele. |
| `web/modules/contrib/symfony_mailer/src/Attribute/MailerInfo.php` | Atributo para metadados de mailer. | Referência de padrão de atributos; o ACULTA usará atributos próprios. |

## 2. Referências conceituais (não verificadas nesta fase)

As referências abaixo vieram da proposta de arquitetura da PR (não da leitura do código). Cada uma deve ser checada na
fase que a usar.

| Referência | Conceito de interesse | Estado |
|---|---|---|
| camada de envio do Drupal Log | Logs como content entity com retenção | **Não instalado**; avaliar a entidade própria antes de F7. |
| Easy Email | Templates como entidades, preview, override de Core | **Não instalado**; referência de UX. |
| WP Mail SMTP | Relatórios, diagnóstico, failover, roteamento condicional | Referência de produto; sem código. |
| WordPress Email Templates | Preview e envio de teste | Referência de UX. |
| Joomla Mail Templates | Template por componente, idioma e layout | Referência de modelo. |
| Adobe Commerce / Magento | Separação header/body/footer | Referência de modelo. |
| Symfony Mailer (biblioteca) | Transportes por DSN, `Address`, `Email` | Base de envio, já presente via Core. |
| Drupal Queue API | Worker e retry | Core. |

## 3. Não verificado (pendente)

- Documentação oficial da camada de envio do Drupal 2.x sobre o modo de compatibilidade do Webform (DT-P09 já registra o teste).
- Issue "Circular reference in mailer_override MailerConfigOverride on Drupal 11.4" (citada em `EMAIL-TRANSPORT.md`):
  verificada apenas como citação; o Runtime de teste registrou o módulo ativo sem erro.
- Evolução do Symfony Mailer no Core (backend experimental) e o roadmap oficial de Mailer: a afirmação de que o contrib
  não será portado 1:1 foi lida na proposta e **não foi confirmada** nesta fase. Checar antes de F2.
- Código de Easy Email, camada de envio do Drupal Log, Email Confirmer 2.x e WP Mail SMTP: sem código lido nesta fase; nenhuma linha
  foi copiada.

## 4. Licenças e atribuição

Nenhum código de terceiros foi copiado nesta fase. Quando houver porte de código, a atribuição e a licença de origem
serão registradas aqui, no arquivo ou no cabeçalho do arquivo portado.

## 5. Decisões de porte

| Item | Decisão | Motivo |
|---|---|---|
| camada de envio do Drupal como transporte | Manter por ora | Já é o caminho de envio validado no Runtime de teste (`EMAIL-TRANSPORT.md` §9). |
| Mailer Policy | Reutilizar até equivalência | Evita duplicar Config Entity de política. |
| `mailer_override` | Não portar; reduzir uso | Substitui o Core globalmente, o que dificulta testes e consumidores. |
| Symfony Mailer | Manter como biblioteca | É a base do Core. |
| Logs, templates, brands, layouts | Próprios (quando fases chegarem) | Não há equivalente instalado que cubra o modelo ACULTA. |
