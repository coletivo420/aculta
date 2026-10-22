# Claude Code — ACULTA

Siga `AGENTS.md` como contrato comum (Claude, Codex e DeepSeek). Use `docs/operations/AGENT-POLICY-INDEX.md` para localizar regras específicas; não carregue o arquivo histórico integral. Preserve a PR atual; modelo fixo é selecionado externamente por sessão. Evite leituras, logs e instruções repetidas.

## Preferências do responsável (prevalecem sobre regras conflitantes)

- **Deploy:** produção só recebe deploy quando todos os módulos, temas e subtemas estiverem em RC. Merge em `main` não é deploy.
- **Ambientes:** produção é `*.aculta.org`; servidor de testes é `*.aculta.toca.net.br` (com `.br`). Testes locais em `127.0.0.1` simulam o servidor de testes, mas não são o servidor de testes. Não confundir nem inventar hosts.
- **Credenciais:** em produção as chaves ficam no banco, criptografadas, provisionadas pelo `aculta_deployer` após o deploy. Arquivos em `secrets/` são só do servidor de testes.
- **Pesquisa na web:** liberada. Pesquisar quando ajudar a resolver a tarefa, sem pedir permissão antes. Continua valendo não publicar nem enviar conteúdo para fora sem confirmação.
- **Testes de Portal, Revista e Fórum:** rodar também com o tema Olivero, além do ACULTA420. Tudo que é do módulo deve funcionar sem o tema.
- **Mercado Pago:** o prefixo do token não identifica o ambiente (`APP_USR-` pode ser de teste). Decidir pelo modo configurado e pelo `live_mode` dos pagamentos.
- **Navegador de testes:** Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), via geckodriver. Não usar Chromium headless: tem travamentos conhecidos. Detalhes em `AGENTS.md`, seção Testes de navegador.
