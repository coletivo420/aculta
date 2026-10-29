# Roadmap — ACULTA FAQ (`aculta_faq`)

## F1 — FAQ e blocos editoriais

- [x] Submódulo próprio, FAQ com pergunta/resposta e blocos editoriais reutilizáveis.
- [x] Formato restrito a negrito, itálico, listas e links; XSS coberto por teste Kernel.
- [x] Painel administrativo com permissão própria e negação para usuário comum.
- [x] Revisar tecnicamente o conteúdo em `docs/CONTEUDO-PERGUNTAS-FREQUENTES.md` contra rotas e controllers atuais; o texto inclui locais, mapa e links de rota.
- [x] Revisão editorial e aprovação da minuta para o candidato (responsável, 2026-10-29).
- [x] Cadastrar as seis respostas aprovadas no Runtime de teste, após autorização operacional específica; repetir revisão visual com o conteúdo final.
- [x] Revalidar teclado, conteúdo e reflow em Firefox ESR anônimo a 1280/390/320px.

Conteúdo revisado tecnicamente em 2026-10-28 a partir dos fluxos atuais de
eventos, calendário, locais e mapa. Não reproduz conteúdo editorial da FAQ do
OpenCulturas. O responsável aprovou e autorizou cadastrar as seis respostas em
2026-10-29. Nodes 567–572 foram publicados no Runtime de teste; revisão Firefox
ESR final passou 6/6 verificações, incluindo 11/11 destinos de link únicos
alcançáveis por Tab com `:focus-visible`, em 1280/390/320px. A fixture visual
temporária anterior foi removida.

### Gate de release

F1 está completa no módulo. O status permanece Dev porque o gate global
`validate-module-theme-boundary.php` ainda reprova por referências em
Events/Calendar e uma classe de teste da Revista, sem ocorrência no `aculta_faq`.
Após o gate global ficar verde, reexecutar o status index e classificar o módulo
como RC; não há trabalho funcional restante nesta fase.

