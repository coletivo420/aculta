# Arquitetura do aculta_links

`aculta_links` é dono das micropáginas, seus itens e regras de publicação. O
tema renderiza a prévia com a apresentação pública, sem transformar rascunhos em
conteúdo público.

## Decisões vigentes

- **2026-10-20 — Prévia de micropágina é privada e não compartilhável em cache.**
  Situação: vigente. Motivo: permitir que o proprietário confira rascunhos e
  itens inativos sem publicá-los nem expô-los a outros visitantes. Origem:
  `0.4.0-dev.3`, [histórico arquivado](../history/changelog/aculta_links.md).
  A rota no purpose `links` exige autorização do proprietário e retorna
  `max-age: 0`.

## Fronteiras

- O módulo mantém propriedade, conteúdo e publicação de micropáginas.
- O ACULTA420 fornece a apresentação pública; a prévia não ignora a autorização
  nem altera o estado de publicação.
