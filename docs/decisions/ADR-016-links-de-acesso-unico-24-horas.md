# ADR-016 — Prazo e ciclo de vida dos links de acesso único

**Status:** aceito em 2026-10-20 por decisão do responsável.
**Escopo:** links de acesso único de cadastro, confirmação de e-mail, recuperação de senha e fluxos equivalentes em todo o projeto.
**Origem:** `0.4.0-dev.226`, [histórico do Portal](../history/changelog/aculta_portal.md).

## Contexto

O Core, o Portal e extensões de confirmação de e-mail mantinham prazos próprios,
e o conteúdo cifrado de e-mails podia conservar URLs antigas para reenvio. A
validade infinita de confirmação de e-mail não era adequada para credenciais de
uso único.

## Decisão

1. Todo link de acesso único expira em 24 horas, independentemente do módulo que
   o emite.
2. Um novo pedido invalida o link anterior e cancela seu conteúdo armazenado;
   reenvio manual cria um pedido e token novos.
3. Somente a retentativa automática e o failover de transporte da mesma
   mensagem podem reutilizar o mesmo link, dentro do prazo.
4. O conteúdo persistido para reenvio não guarda URLs utilizáveis e é removido
   ao ser aceito/confirmado pelo transporte ou ao vencer o prazo.
5. Novos emissores entram no inventário e no gate global antes da entrega.

## Consequências

- Core, Portal e módulos de e-mail aplicam o limite de `86400` segundos;
  `SingleUseLinkPolicy::TTL`, a retenção de conteúdo e
  `scripts/validate-single-use-links.php` protegem o contrato.
- Reenvio de falha não pode reconstruir nem reutilizar conteúdo antigo fora da
  retentativa automática da mesma mensagem.
- A lista de emissores e os detalhes operacionais permanecem em
  [LINKS-DE-USO-UNICO.md](../portal/LINKS-DE-USO-UNICO.md).

## Alternativas consideradas

| Alternativa | Motivo para rejeitar |
| --- | --- |
| Prazo infinito para confirmação | Mantém credenciais utilizáveis sem limite temporal. |
| Prazos diferentes por módulo | Aumenta divergências e dificulta a revisão de emissores novos. |
| Reenvio manual do mesmo conteúdo | Reutiliza token antigo e impede revogação confiável pelo pedido novo. |

## Verificação no código

`SingleUseLinkPolicy::TTL` é `86400`; a política central de reenvio e remoção
fica em `SingleUseLinkMail`/`AcultaMailer`; o gate verifica os prazos e rejeita
validade fixa. A regra operacional e o inventário estão em
`docs/portal/LINKS-DE-USO-UNICO.md`.
