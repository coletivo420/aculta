# Navegador — CPF/checkout compartilhado Loja/LMS

Scripts de verificação manual automatizada usados na frente "ACULTA CPF ID +
checkout compartilhado Loja/LMS", no servidor de desenvolvimento
`*.aculta.toca.net.br`. Falam WebDriver W3C direto com o geckodriver
(`wdlib.py`), sem Selenium.

## Pré-requisitos

- Firefox ESR e geckodriver (sobrescreva com `FIREFOX` e `GECKODRIVER`);
- para revisão visual, Firefox visível em `DISPLAY=:0`; para matrizes repetitivas
  `wdlib` também aceita Firefox, Chrome e Chromium headless, selecionados
  explicitamente por `browser=` e `headless=True`;
- `$SUITE_OUT` em armazenamento persistente fora do repositório e de `/tmp`;
- credenciais da conta `teste.comum` em variáveis de ambiente, carregadas de
  `secrets/teste_comum.env` (uid 5, ver `docs/operations/TESTE-COMUM.md`).

```bash
cd /home/piradopirata/projetos/aculta
set -a; . secrets/teste_comum.env; set +a
DISPLAY=:0 SUITE_OUT=/home/piradopirata/aculta-suite-out/cpf-checkout python3 -I tests/browser/shop/aculta-cpf-checkout/multi-tab-checkout.py
```

As senhas nunca são impressas. `wdlib.py` cria um perfil descartável dentro de
`$SUITE_OUT/profiles` (ou do caminho informado por
`ACULTA_FF_PROFILE_ROOT`) e remove-o ao fechar a sessão. Ele rejeita perfis em
`/tmp`; os artefatos também ficam no `$SUITE_OUT` persistente. A interface é
mantida compatível com `Driver()` visível por padrão; novos runners podem
selecionar `Driver(browser="firefox|chrome|chromium", headless=True)`.

## Scripts

### `wdlib.py`

Biblioteca mínima: sobe geckodriver ou ChromeDriver em uma porta livre e inicia
Firefox ESR, Chrome ou Chromium com perfil descartável. Exibe por padrão; o
modo headless exige `headless=True`. Expõe `get`, `js`, `find`, `exists`, `type`,
`click`, `submit`, `title`, `url`, `source`, `new_tab`, `switch`, `handles` e
`close`. `Driver(bidi=True)` também negocia WebDriver BiDi e disponibiliza
`websocket_url` para recursos como viewport móvel sem depender do tamanho mínimo
da janela do gerenciador X11. Use-a a partir de outro script da pasta.

### `login-probe.py`

Mapeia a tela de login (`conta.aculta.toca.net.br/entrar`), os seletores do
formulário, a página `/dados` e os campos da página de produto da Loja. É o
script de descoberta de seletores; não depende de login prévio.

### `login-captcha.py`

Aguarda o token do Cloudflare Turnstile preencher sozinho (cerca de 4 s) e só
então envia o formulário. Submeter antes disso devolve "A verificação de
segurança não foi concluída corretamente". O script não tenta resolver o
desafio; apenas espera o widget. Útil para validar o acesso de teste.

### `multi-tab-checkout.py`

Fluxo principal da frente:

1. login na conta de teste e impressão dos cookies de sessão apenas por nome,
   expiração e flags (sem valor);
2. aba A: carrinho em `aculta.toca.net.br/carrinho`, com o item e o total;
3. aba B: checkout do mesmo pedido em `/carrinho/finalizar`, listando campos
   fiscais e a mensagem de erro de cada passo, sem concluir pagamento;
4. de volta à aba A: confirma que carrinho e sessão continuam válidos;
5. aba C: curso `cursos.aculta.toca.net.br/group/1`, procurando CTAs de
   matrícula/compra.

Evidências (PNG e um HTML) em `$SUITE_OUT`, informado pelo operador em
armazenamento persistente fora do repositório e de `/tmp`.

## Limitações conhecidas do ambiente de teste

- **Gateway desabilitado:** o checkout para em `informacoes-do-pedido` com
  "Não existem gateways de pagamento disponíveis para este pedido". Para
  exercitar o pane fiscal, os pedidos desta frente tiveram o total zerado. O
  script registra a mensagem e não contorna o bloqueio.
- **Turnstile no login:** o token leva alguns segundos; não envie o formulário
  antes de `cf-turnstile-response` estar preenchido.
- **Barra administrativa:** a conta `teste.comum` abre a barra do painel, que
  entra no `innerText`; as verificações usam `main` para evitar ruído.
- **URLs que não existem:** `loja.aculta.toca.net.br/cart` responde "A página
  não foi encontrada"; o carrinho fica em `aculta.toca.net.br/carrinho` e o
  checkout em `/finalizar-compra/{id}/{passo}`. O LMS não fica em
  `aculta.toca.net.br/lms`, e sim em `cursos.aculta.toca.net.br`.
