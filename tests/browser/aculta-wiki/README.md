# ACULTA Wiki — visual smoke test

Confere páginas públicas da Wiki no Firefox ESR visível usando o WebDriver
`wdlib.py`. O padrão aponta ao Runtime de testes em
`https://wiki420.aculta.toca.net.br`; a suíte só faz navegação e GETs, sem login,
formulários ou mudanças persistentes. Os resultados não homologam o Runtime nem
substituem teste de acessibilidade completo.

Todos os scripts acrescentam um parâmetro `aculta_check` único para evitar
servir uma entrada antiga do cache de página após mudança de código; continuam
somente GET. Larguras padrão: 320, 360, 390, 768, 1024, 1280 e 1440 CSS px.

## Execução

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-wiki-visual
DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" SUITE_OUT="$SUITE_OUT" \
  python3 -I tests/browser/aculta-wiki/visual.py
```

Requisitos: Firefox ESR em `/usr/bin/firefox-esr`, `geckodriver` no `PATH`, o
pacote Python `websockets` e sessão X11 em `DISPLAY=:0`. `SUITE_OUT` define onde
ficam PNGs e resumo; não versione esses artefatos. `WIKI_BASE_URL` pode apontar
a outra instalação de teste HTTP, mas o script não altera resolução de DNS nem
tema.

`WIKI_CSS_OVERRIDE=1` injeta no browser as folhas `wiki.css` e
`patterns/wiki-entry/wiki-entry.css` candidatas do checkout/worktree e valida
o layout sobre as páginas servidas. No verbete público, o teste mede a grade
de uma coluna com aside primeiro e margem compacta das abas abaixo de 768px;
em 768px restaura a grade de duas colunas e a margem anterior. Defina
`WIKI_CSS_ROOT` se o script estiver sendo executado de outro checkout. As
larguras incluem as fronteiras 575/576 e 767/768 CSS px. Isso valida a folha
candidata nos três caminhos públicos; não prova que o agregado ou a configuração
do Runtime foi atualizado, nem cobre o workspace autenticado do Studio.

## WE SATIVA autenticado

`we-sativa.py` autentica no Runtime de teste com uma URL de login único gerada
por Drush para a conta autorizada UID 5 (ajustável por variável), abre a home
Wiki, segue o CTA para `/studio/we-sativa/inicio`, testa o reflow nas oito
larguras (320/360/390/640/768/1024/1280/1440 px) e percorre “Meu
Trabalho” até o formulário Node nativo e seu retorno. Não envia formulários nem
altera preferências ou conteúdo; grava capturas e resumo fora do repositório.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-wiki-we-sativa
DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" SUITE_OUT="$SUITE_OUT" \
  python3 -I tests/browser/aculta-wiki/we-sativa.py
```

Variáveis: `WIKI_HOME_URL` (padrão `https://wiki420.aculta.toca.net.br/`),
`WIKI_STUDIO_PAGE` (padrão
`https://conta.aculta.toca.net.br/studio/we-sativa/inicio`),
`WIKI_STUDIO_LOGIN_URI` (padrão `https://conta.aculta.toca.net.br`),
`WIKI_STUDIO_UID` (padrão `5`; passe vazio para usar `WIKI_STUDIO_USER`) e
`WIKI_STUDIO_USER` (padrão vazio; usado quando `WIKI_STUDIO_UID` é vazio) e
`SUITE_OUT` (padrão `/home/piradopirata/aculta-suite-out/aculta-wiki-we-sativa`). Requer
Drush funcional e Firefox ESR visível. A conta precisa ter confirmação de e-mail,
acesso ao Studio e criação Wiki. A rodada autentica e cria uma sessão de navegador;
não reconstrói caches/configuração, não publica nem grava conteúdo. O resultado
avalia somente o tema ativo no Runtime de teste; a rodada dev.117 também cobriu
Olivero e modo escuro. Leitor de tela e WCAG 2.2 AA completa permanecem fora
do escopo do script.

O script valida raiz, listagem e busca em desktop e larguras móveis, verifica
presença de `main`/`h1`, erros visíveis e rolagem horizontal, e confirma que o
caminho interno `/wiki` responde 404 por GET. Também descobre na busca o link
canônico de um verbete publicado e repete as verificações na página do verbete
(um `h1` titulado, sem overflow e sem erro). Testa apenas o tema ativo no
servidor. Olivero foi homologado em dev.117 com os mesmos scripts; leitores de
tela, teclado e WCAG 2.2 AA completa precisam de sessões separadas. O modo
escuro deve ser testado pelo padrão `aculta_portal.appearance.default_mode`
(restaurado para `light`); `WIKI_DARK=1` apenas passa
`ui.systemUsesDarkTheme` ao Firefox e não força o contrato do site. Use
`WIKI_SKIP_SCREENSHOTS=1` quando o `/tmp` estiver sem espaço.

## Moderação no ACULTA Studio

`moderation.py` autentica por URL única do Drush e abre
`/studio/marley/wiki/moderacao/propostas`. Verifica o listing compartilhado,
estado vazio e reflow nas oito larguras. Quando há proposta pendente, abre o
formulário de decisão e confere o Form API, a nota e os links de comparação,
sem submeter. A rodada dev.117 usou uma proposta temporária como fixture
(41/41) e removeu entidade e revisões ao final. Use `WIKI_STUDIO_UID=1` no
Runtime de teste, porque a conta UID 5 é editora de verbetes, mas não tem
`moderate wiki proposals`; a configuração ativa dos papéis não contém as
permissões de proposta presentes em `config/sync`.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-wiki-moderation
DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" SUITE_OUT="$SUITE_OUT" WIKI_STUDIO_UID=1 \
  python3 -I tests/browser/aculta-wiki/moderation.py
```

## Sem JavaScript

`no-js.py` abre Firefox ESR com `javascript.enabled=false` e inspeciona o HTML
servido pelo Drupal para home, listagem, busca e um verbete descoberto na busca.
Exige um único `h1` por página, `h1` com título no verbete, formulário nativo com
`name="q"`, contagem e links de resultados renderizados no servidor. Não
autentica nem altera o Runtime. Resultado medido em dev.117: 16/16.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-wiki-no-js
DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" SUITE_OUT="$SUITE_OUT" \
  python3 -I tests/browser/aculta-wiki/no-js.py
```


## Teclado

`keyboard.py` percorre a página de busca com Tab real via WebDriver no Firefox
ESR, em 1280 e 320 CSS px. Confere foco visível, alcance do campo e do botão de
pesquisa; digita um termo sem enviar o formulário. Continua sendo somente GET,
sem login ou escrita persistente.

Antes do host, o script carrega o `search-preview.js` do worktree numa fixture
DOM em ambas as larguras: verifica o role searchbox, a região live nomeada, as
setas sem roubo de foco e a ordem Tab dos links ao submit. A fixture testa o
comportamento local sem provar o render array/configuração completa do Drupal.
Use `WIKI_CANDIDATE_ONLY=1` para executar apenas essa parte; o padrão também
roda a integração pública no host definido por `WIKI_BASE_URL`.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-wiki-keyboard
DISPLAY=:0 ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles" SUITE_OUT="$SUITE_OUT" \
  python3 -I tests/browser/aculta-wiki/keyboard.py
```

O fixture local usa o mesmo contrato de anexação do `WikiSearchFormBuilder`
(`data-portal-ui="search-preview-input"`). `WIKI_CANDIDATE_ONLY=1` verifica
somente o behavior do checkout em uma fixture; não prova a integração carregada
na página pública. Em execução da página, compare os atributos servidos com o
contrato da branch e registre divergências de versão do Runtime sem atribuí-las
à CSS candidata. Se a prévia não carregar na página pública, o harness registra
os checks de integração que falharam e continua para a próxima largura, sem
procurar links que não foram renderizados nem abortar com erro WebDriver.

Para aplicar a folha Wiki atual por GET do browser quando o agregado do Runtime
estiver desatualizado, defina também `WIKI_CSS_OVERRIDE=1`; isso mede o CSS
candidato, não a publicação do agregado.
