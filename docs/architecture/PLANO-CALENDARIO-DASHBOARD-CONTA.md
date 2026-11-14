# Plano: calendário no painel de conta (Studio Dashboard)

Status: aprovado pelo responsável em 2026-11-06. Execução pendente.

## Decisões aprovadas

- **Cursos:** datas de início e fim do curso e datas de atividades e aulas agendadas, somente para alunos matriculados.
- **Meus eventos:** eventos em que o usuário está inscrito e eventos que ele organiza, sem duplicar.
- **Botão "+":** aparece para qualquer usuário autenticado. Abre o formulário de proposta existente em `aculta_events` (`EventContributionSubmissionForm`). Nenhum evento fica público sem aprovação editorial.
- **Celular:** o card aparece logo abaixo do cabeçalho, antes de "Suas experiências".
- **Desktop:** o card fica na coluna da direita, com topo alinhado ao conteúdo, como o menu lateral.

## Restrições

- Não alterar o motor de `aculta_calendar` (RC 0.1.0-rc.2). Só consumir os serviços já existentes.
- Não publicar nada no Runtime nem em Beta. Só o servidor de desenvolvimento (`*.aculta.toca.net.br`).
- Não usar `--purpose-*` em botões. Botões usam `--button-*`. Faixa superior das caixas usa `--purpose-sub-2`.
- Não usar force push nem merge em `main`. Push somente em `origin/dev`.

## Fases

### Fase 1 — provedores de dados (`aculta_studio`)
- Três provedores de filtro: `cursos`, `eventos`, `meus_eventos`.
- `cursos`: matrículas ativas do usuário, com datas de curso e de atividades/aulas.
- `eventos`: eventos publicados visíveis ao usuário.
- `meus_eventos`: união de inscrições e organização do usuário, sem duplicados.
- Aceite: testes unitários e de kernel. Anônimo recebe lista vazia. Usuário sem matrícula não vê cursos. "Meus eventos" não repete o mesmo evento.

### Fase 2 — componente visual (`aculta420_v2`)
- SDC `studio-calendar`: cabeçalho "Calendário" com mês e setas, filtro segmentado (Cursos / Eventos / Meus eventos), grade de dias, dia selecionado com `--purpose-accent`, resumo do dia abaixo, botão "+".
- Caixa com faixa superior `--purpose-sub-2` e raio `--radius-lg`.
- Desktop: coluna direita, sticky. Celular: abaixo do cabeçalho, antes de "Suas experiências".
- Aceite: probe de schema SDC e probe de fundações passam. Sem estouro horizontal em 320 px.

### Fase 3 — interação
- Filtro e troca de mês por htmx, sem recarregar a página. Parâmetros por GET.
- Botão "+" leva ao formulário de proposta de `aculta_events`.
- Aceite: troca de filtro e de mês atualiza só o card. O "+" aparece só para autenticados.

### Fase 4 — validação
- Testes unitários e de kernel de Fase 1.
- Captura em Firefox ESR (`DISPLAY=:0`), autenticado e anônimo, claro e escuro, 320 e 1440 px, em `tests/browser/capturas/studio-calendario/<data>/` com `manifest.json`.
- Verificar que proposta de usuário comum não publica evento sem aprovação.

### Fase 5 — entrega
- Commit em português com o trailer da sessão. Push em `origin/dev`. Confirmar o hash com `git ls-remote`.
- Atualizar `docs/status-layouts-e-shell.md` e o README do componente.
