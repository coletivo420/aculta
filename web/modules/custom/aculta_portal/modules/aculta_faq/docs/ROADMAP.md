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

### Candidato RC — 2026-10-29

F1 satisfaz os critérios de release: conteúdo revisado e aprovado, seis
respostas publicadas no Runtime de teste, rotas protegidas, formato sanitizado,
Kernel, PHPCS, revisão visual/teclado e reflow aprovados. O gate módulo–tema
global que bloqueava a classificação agora passa no candidato publicado.

Revalidação deste candidato:

- Kernel: 3 testes / 45 assertions PASS; cobre XSS, acesso administrativo e
  negação das rotas de edição para usuário comum.
- PHP lint: 4/4; PHPCS Drupal/DrupalPractice: PASS.
- Firefox ESR anônimo no servidor de desenvolvimento: conteúdo integral,
  11/11 links alcançáveis por Tab com `:focus-visible`, sem overflow em
  1280/390/320 CSS px.
- `validate-module-theme-boundary.php`: 3397 verificações PASS;
  `validate-module-theme-map.php`: 214 checks PASS;
  `validate-portal-ui-docs.php`: 380 tokens, 300 usados e 149 atributos PASS;
  índice de status: 50 componentes PASS.
- O PHP funcional de `aculta_faq` em `src/` coincide com o checkout de teste
  anterior; nesta linha, a diferença publicada é de versão, documentação e
  cobertura Kernel. A revisão visual não altera nem cria conteúdo.

Classificação: `0.1.0-rc.1`, estado RC. O próximo passo é aguardar autorização
para deploy e validação Alpha; nenhuma operação de Alpha foi executada.

