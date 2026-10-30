# Claude Code — ACULTA

Plataforma Drupal 11 da ACULTA (PHP conforme `docs/architecture/environments.md`). Código em `web/modules/custom/aculta_portal/` (módulos e submódulos) e no tema `web/themes/custom/aculta420/`. Documentação em `docs/`.

## Antes de começar qualquer tarefa

1. Leia `AGENTS.md`, o contrato comum de todos os agentes.
2. Localize as regras do seu escopo em `docs/operations/AGENT-POLICY-INDEX.md`. Não leia `docs/operations/AGENT-GUARDRAILS-FULL.md` por padrão: é arquivo histórico.
3. Para entender o projeto, leia `docs/operations/MAPA-PARA-AGENTES.md` (módulos por área, decisões, ambientes, onde está cada coisa).
4. Confirme o estado no código e no git antes de afirmar status. Planos e roadmaps podem estar desatualizados.
5. Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` (D-01 a D-43) e as ADRs em `docs/decisions/`. Quando um plano conflitar com uma decisão registrada, a decisão vale; registre o conflito.
6. Em qualquer alteração visual do ACULTA420, siga `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md` e `web/themes/custom/aculta420/AGENTS.md`: mobile-first global em todos os purposes; fidelidade Next somente dentro do Studio; preservar Institution Bar e Domain Header. Não oculte a marca institucional por presumir Domain Header em todos os purposes; permita reflow da barra desde 320px.

## Regras que prevalecem (decisões do responsável)

- **Branch:** trabalhe em um único branch de trabalho. Ele será definido pelo responsável; até lá, use o branch atual do checkout. **Não crie branch, PR, nem troque de branch sem ordem explícita.**
- **Commits:** um commit por entrega, só com os arquivos dessa entrega. Não inclua alterações de outra sessão, mesmo que estejam no mesmo working tree. Use o trailer `Claude-Session`.
- **Push:** só com pedido explícito do responsável. Publicação por fast-forward; nunca force push.
- **Estados de release:** Dev (desenvolvimento) → RC → Alpha → Beta. O Alpha começa depois do RC, na migração para `*.aculta.org`. O Beta é a produção no Hostinger, depois de Alpha validado. Só se avança para Alpha quando todos os módulos, temas e subtemas estiverem em RC. Merge em `main` não é deploy.
- **Ambientes:** o servidor de desenvolvimento é `*.aculta.toca.net.br` (com `.br`). Ele não é o Alpha: o Alpha só existe depois do RC, em `*.aculta.org`. Produção é `*.aculta.org`. `127.0.0.1` simula o servidor de desenvolvimento, mas não é ele. Não invente hosts.
- **Banco do servidor de desenvolvimento (`aculta_runtime`):** em toda troca de head, rode antes `updatedb:status` e `config:status` (somente leitura). Havendo pendência, faça snapshot com hash, depois `updatedb` e `config:import` só do `config/sync`. Nunca `pm:uninstall` nem `DROP` sem ordem explícita. Testes Kernel usam só `aculta_simpletest`.
- **Endereços, país exclusivo Brasil e CEP centralizado:** o país é sempre o Brasil (`BR`) em todo o projeto ACULTA; não exiba opção para selecionar país em local algum. Qualquer formulário de endereço usa o contrato, widget e biblioteca centralizados em `aculta_portal_cep` (`aculta_portal_cep/cep-address`). Valide e confirme `country_code = BR` no servidor ao validar e salvar (campo oculto ou JS não dispensam validação de servidor). Número, S/N e complemento são sempre preservados na busca de CEP. ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`.
- **Credenciais:** em produção, as chaves ficam no banco, criptografadas, e são provisionadas pelo `aculta_deployer` após o deploy. Arquivos em `secrets/` são só do servidor de desenvolvimento. Nunca grave credencial em arquivo versionado.
- **Mercado Pago:** o prefixo do token (`APP_USR-`) não identifica o ambiente. Decida pelo modo configurado e pelo `live_mode` do pagamento.
- **Pesquisa na web:** liberada, sem pedir permissão. Não publique nem envie conteúdo para fora sem confirmação.
- **Testes de Portal, Revista e Fórum:** rode também com o tema Olivero, além do ACULTA420. Tudo que é do módulo deve funcionar sem o tema.
- **Navegador de testes:** Firefox ESR visível em `DISPLAY=:0`, via geckodriver. Não use Chromium nem headless. Perfil temporário e artefatos vão para `$SUITE_OUT`, fora do repositório. Scripts de teste visual ficam em `tests/browser/<frente>/` com `README.md`.
- **Dúvida ou ordem fora deste texto:** pare e pergunte ao responsável. Faça antes o que for independente.

## Ao terminar

- Relate só os testes que você realmente executou, com o resultado.
- Diga o que ficou pendente e por quê.
- Liste os arquivos alterados e os commits, com hash.
