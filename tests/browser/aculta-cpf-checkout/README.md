# Navegador — CPF/checkout compartilhado Loja/LMS

Scripts de verificação manual automatizada usados na frente "ACULTA CPF ID +
checkout compartilhado Loja/LMS", no servidor de testes
`*.aculta.toca.net.br`. Falam WebDriver W3C direto com o geckodriver
(`wdlib.py`), sem Selenium.

## Pré-requisitos

- Firefox ESR instalado em `/usr/bin/firefox-esr` e `geckodriver` no PATH
  (os dois podem ser trocados por `FIREFOX` e `GECKODRIVER`);
- sessão X11 local acessível em `DISPLAY=:0` — Firefox visível, nunca headless
  nem Chromium;
- credenciais da conta `teste.comum` em variáveis de ambiente, carregadas de
  `secrets/teste_comum.env` (uid 5, ver `docs/operations/TESTE-COMUM.md`).

```bash
cd /home/piradopirata/projetos/aculta
set -a; . secrets/teste_comum.env; set +a
DISPLAY=:0 python3 -I tests/browser/aculta-cpf-checkout/multi-tab-checkout.py
```

As senhas nunca são impressas. `wdlib.py` cria um perfil temporário
`/tmp/aculta-ff-*` e o remove ao fechar a sessão.

## Scripts

### `wdlib.py`

Biblioteca mínima: sobe o geckodriver em uma porta livre, inicia o Firefox ESR
com `-profile` e `-no-remote`, e expõe `get`, `js`, `find`, `exists`, `type`,
`click`, `submit`, `title`, `url`, `source`, `new_tab`, `switch`, `handles` e
`close`. Use-a a partir de outro script da pasta.

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

Evidências (PNG e um HTML) em `$SUITE_OUT`, padrão `/tmp/aculta-cpf-checkout`.

### `olivero-smoke.py`

Verificação do tema **Olivero** nas páginas públicas, feita no **ambiente
local** e não no servidor de testes. O Apache local responde pelos mesmos
hostnames de teste; o perfil do Firefox recebe `network.dns.localDomains`
com a lista de hosts, então tudo resolve para 127.0.0.1.

O script não troca o tema: ele só mede. Ative o Olivero antes e garanta a
restauração com `trap`, para não deixar o tema global trocado:

```bash
cd /home/piradopirata/projetos/aculta
vendor/bin/drush theme:enable olivero -y
restore() { vendor/bin/drush config:set system.theme default aculta420 -y >/dev/null; }
trap restore EXIT
vendor/bin/drush config:set system.theme default olivero -y
vendor/bin/drush cache:rebuild >/dev/null
DISPLAY=:0 python3 -I tests/browser/aculta-cpf-checkout/olivero-smoke.py
```

Páginas medidas: raiz do Portal (`aculta.toca.net.br/`), raiz do Fórum
(`growroom420.aculta.toca.net.br/`, que serve a view `/discussoes`) e raiz
da Revista (`revista420.../`; `/revista` responde 301 para `/`). Saída em
`$SUITE_OUT`, padrão `/tmp/aculta-olivero`.

O resultado prova que o tema carrega e renderiza sem erro; não prova o
conteúdo completo — o banco local não tem tópicos de fórum nem matérias de
revista, e Portal/Revista dependem de blocos posicionados em regiões do tema
`aculta420`, que o Olivero não renderiza.

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
