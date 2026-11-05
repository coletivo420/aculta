# Testes visuais de navegador — índice do projeto

Infraestrutura compartilhada de navegador para o projeto inteiro: módulos, produtos, purposes e consumidores. O harness genérico não pertence ao tema nem ao Studio. Informe as URLs da área sob teste; use uma suíte específica só quando um contrato não puder ser medido pelo harness genérico. Antes de criar código, adapte a execução genérica passando as URLs.

Regras gerais (ACL, tema de validação, Olivero, caminhos, Git) estão em `AGENTS.md` e `CLAUDE.md`. Este arquivo traz só o que vale para esta pasta.

## Regras desta pasta

- Uma pasta por frente: `tests/browser/<frente>/`, com `README.md` local (o que cobre, ambiente, variáveis, execução, limitações).
- Artefatos (PNG, HTML, logs) vão para `$SUITE_OUT`, fora do repositório. O repositório guarda o script, não a evidência. Evidência com dado de conta não é versionada.
- Sem segredo no script: credenciais vêm de `secrets/*.env` ou do ambiente. Nada de senha, token ou link de uso único impresso.
- Declare o ambiente: servidor de desenvolvimento (`*.aculta.toca.net.br`) ou local (`127.0.0.1`). Não confunda os dois.
- Se o teste alterar estado compartilhado, restaure com `trap` no mesmo comando e documente no README local.
- Perfil temporário do Firefox: `ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"`. O `wdlib` rejeita perfis sob `/tmp`.
- Reuse `wdlib.py` (WebDriver W3C do projeto) antes de escrever camada de driver nova.

## Ambiente de execução

- Testes X11 rodam contra o servidor de desenvolvimento. O código testado é o commit do checkout desse servidor; confirme com `git rev-parse --short HEAD` nele.
- Trocar o commit desse checkout exige autorização do responsável, backup de alterações não commitadas e `drush cr`.
- Navegador: Firefox ESR visível em `DISPLAY=:0` para revisão humana, capturas de aceite e zoom real. Firefox headless e Chrome/Chromium headless valem só para matrizes repetitivas, com o motor declarado no resultado (autorização de 2026-10-31). Procedimento em `docs/operations/NAVEGADOR-TESTES.md`.

## Scripts por frente

| Pasta | Script principal | Cobre | README local |
| --- | --- | --- | --- |
| `aculta420-v2-navigation/` | `navigation.py` | SDC `navigation-bar` nos purposes, foco, Escape, alvos de toque, fallback sem JS | sim |
| `aculta-cpf-checkout/` | `login-probe.py`, `login-captcha.py`, `multi-tab-checkout.py` | CPF ID e checkout compartilhado Loja/LMS | sim |
| `aculta-wiki/` | `visual.py`, `keyboard.py`, `we-sativa.py` | páginas públicas, teclado da busca, workspace autenticado | sim |
| `aculta-conta-raiz/` | (roteiro) | raiz da Conta sem sessão: login sem rolagem horizontal em 390 e 1280 px | sim |
| `aculta-qrcode/` | `visual.py` | painel, prévia e downloads de QR code; fixture temporária | sim |
| `aculta-image-admin/` | `visual.py` | opções de processamento de imagem; conta temporária | sim |
| `aculta-forum-lms/` | `h5p-discussions.py` | link de discussão em H5P; fixture removida no `finally` | sim |
| `aculta-game/` | `smoke.mjs` | área de conta, perfil público, texto alternativo das conquistas | sim |
| `aculta-mercadopago/` | `buyer-login.py`, `checkout-smoke.py` | login do comprador sandbox e chegada ao checkout; não paga | sim |
| `aculta-theme-mobile-first/` | vários (`author-card.py`, `events-catalogue.py`, `account-shell.py`, `support-form.py`, `navigation.py`, `institution-bar.py`, `account-address.py`, `purpose-shell-keyboard.py`, `maps-events-address.py`, `account-orders.py`, `institutional-layout.py`) | cartões, navegação, Institution Bar, formulários e tabelas em 320–1440 px | sim |

Scripts de frentes que ainda não seguem a convenção de pasta, mantidos onde a frente os deixou:

| Script | Cobre |
| --- | --- |
| `scripts/check-browser-hosts.sh` | carregamento dos hosts de teste (documentado em `docs/operations/NAVEGADOR-TESTES.md`) |
| `scripts/test-account-address-browser.py` | formulário de endereço e CEP em Minha conta (frente `aculta_portal_cep`) |

Ao evoluir um script desses, mova-o para `tests/browser/<frente>/`.

## Status e registros datados

Resultados de rodadas, evidências privadas e status DEFERRED de cada script ficam no README local da frente e no histórico:

- Registro transversal de 2026-10-31 (harness genérico em dez raízes públicas, Firefox ESR e Chrome): `docs/history/portal/TESTES-NAVEGADOR-RODADAS.md`, seções F a V, e `docs/history/portal/TESTES-BROWSER-README-ARQUIVO-2026-11-05.md`.
- Roteiros e rodadas do Portal: `docs/portal/TESTES-NAVEGADOR.md` (regras) e o arquivo de rodadas acima.
