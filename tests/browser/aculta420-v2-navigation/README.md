# Navegação SDC do ACULTA420 V2

`navigation.py` valida a integração do componente `aculta420_v2:navigation-bar`
na posição original do bloco de menu dentro do cabeçalho do domínio, usando Firefox ESR visível em
`DISPLAY=:0`. A matriz cobre os dez purposes cujos hosts são resolvidos do
`deploy.json`, nas larguras 320, 767, 768 e 1440 CSS px, e verifica menu
mobile, `aria-expanded`, foco, Escape, landmarks, marca sem duplicação e
overflow. Uma segunda sessão desativa JavaScript e confirma que os links
continuam visíveis e operáveis.

Configure `SUITE_OUT` para um diretório persistente fora do repositório.
Hosts de teste vêm da configuração de deploy; `NAVIGATION_DOMAIN_ENV` pode ser
`test` (padrão) ou `production` quando houver autorização para consultar essa
origem.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" python3 -I tests/browser/aculta420-v2-navigation/navigation.py
```

O runner usa somente GET, não autentica e não submete formulários. Capturas e
`results.json` permanecem em `$SUITE_OUT`. Estados autenticados e leitor de tela
não fazem parte deste gate.

`admin-toolbar-layer.py` verifica, em Firefox visível, que a Institution Bar
permanece acima da sidebar administrativa Gin e que a sidebar começa abaixo da
altura medida da barra. O teste usa a folha original de Gin e uma fixture DOM
anônima; não valida uma sessão autenticada real.
