# ADR-039 — Sugestões de edição para Organizações sem titular

- **Status:** Protótipo técnico em `dev`; superado como caminho final por orientação do responsável em 2026-11-01. A alternativa ainda precisa ser definida. Não ativar no Runtime.
- **Data:** 2026-11-01.
- **Decisores:** responsável pelo produto.
- **Contexto:** Organizações culturais públicas podem existir sem titular reivindicado. Dar edição direta a todas as contas que criam Eventos permite alterar sua identidade pública sem revisão e mistura essa capacidade com a titularidade.
- **Protótipo implementado:** Usuários com capacidade efetiva de criar Eventos submetem campos públicos de uma Organização sem titular como sugestão. O envio não altera a entidade pública; uma fila administrativa permite aprovar ou rejeitar com autoria e decisão registradas. O responsável depois orientou que sugestões de Eventos terão outro caminho. Este código é apenas protótipo, não define o fluxo futuro e não autoriza ativação.
- **Escopo de campos:** Nome, descrição, imagem/logotipo, links oficiais, localidade, UF e opção de ocultar localidade. A proposta nunca altera titularidade, estado de reivindicação, publicação, autoria, slug ou vínculos de acesso.
- **Revalidação:** A aprovação só aplica a proposta se a Organização continuar sem titular e não tiver mudado desde o envio. Caso contrário a proposta é marcada desatualizada, sem aplicação. Uma decisão já tomada não pode ser repetida.
- **Separação de capacidades:** A capacidade de criar Evento concede somente a possibilidade de sugerir esses campos. Edição direta de Organização reivindicada continua reservada ao titular, membros autorizados e administradores. Titularidade continua sujeita ao fluxo próprio de reivindicação; aprovar uma edição não cria vínculo de titular.
- **Ownership:** `aculta_events` é dono da entidade Organização, do armazenamento da proposta, da fila e de sua aprovação. A proposta não usa fila UGC, não cria organização ou perfil paralelo e não altera permissões de Eventos.
- **Rotas do protótipo:** Fila e revisão administrativa foram implementadas no MAIN em `/painel-administrativo/eventos/organizacoes/sugestoes`, protegidas por `administer aculta events`. O caminho e a interação precisam ser reavaliados conforme a orientação mais recente.
- **Runtime:** A entidade requer update hook versionado. O update, as rotas e a experiência não foram homologados; não aplicar no Runtime até substituir este fluxo.

## Consequências

- A página pública permanece estável até decisão administrativa.
- No protótipo, propostas concorrentes são serializadas e revalidadas contra a versão de origem antes da aplicação.
- Organizações já reivindicadas não recebem sugestões por este fluxo; seus membros usam a gestão autorizada.
- A fila administrativa e cada revisão ficam restritas a administradores, incluindo os valores propostos.
