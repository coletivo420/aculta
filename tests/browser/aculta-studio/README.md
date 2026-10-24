# Revisão visual e de navegação do ACULTA Studio

Suíte no Firefox ESR visível na sessão X11 local (`DISPLAY=:0`), pelo geckodriver (WebDriver W3C e BiDi). Não usar headless para validação. Confere a seção "Estúdio" no menu da conta, as abas e o painel, a navegação AJAX
(troca de aba sem recarregar a página), `aria-current`, largura de 320 a 1440 px emulada pelo BiDi, contraste claro/escuro,
navegação por teclado, ausência de rolagem horizontal e de erros de JavaScript.

Somente no servidor de teste, com a conta `teste.comum` (login único por Drush, sem senha no repositório). O
Studio precisa estar habilitado no Runtime e a conta precisar de uma aba visível (permissão `create revista content`).

```sh
DISPLAY=:0 geckodriver --port 4444 &
DISPLAY=:0 tests/browser/aculta-studio/run-visual.sh
```

Variáveis: `STUDIO_PAGE` (padrão `https://conta.aculta.toca.net.br/studio/marley`), `STUDIO_LOGIN_URI`, `STUDIO_USER`,
`SUITE_OUT` (padrão `/tmp/aculta-studio-visual`), `STUDIO_USER_ID` (padrão `5`) e `GECKODRIVER`. O geckodriver cria perfil temporário por sessão. O wrapper usa `trap` para restaurar a preferência de cor mesmo quando a suíte falha; não executar diretamente o `.mjs`.

Os seletores usam atributos de comportamento `data-aculta-studio*`, porque o tema troca os tokens `data-portal-ui`
por classes na renderização.
