# ACULTA Wiki — visual smoke test

Confere páginas públicas da Wiki no Firefox ESR visível usando o WebDriver
`wdlib.py`. O padrão aponta ao Runtime de testes em
`https://wiki420.aculta.toca.net.br`; a suíte só faz navegação e GETs, sem login,
formulários ou mudanças persistentes. Os resultados não homologam o Runtime nem
substituem teste de acessibilidade completo.

## Execução

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-wiki-visual DISPLAY=:0 \
  python3 -I tests/browser/aculta-wiki/visual.py
```

Requisitos: Firefox ESR em `/usr/bin/firefox-esr`, `geckodriver` no `PATH`, o
pacote Python `websockets` e sessão X11 em `DISPLAY=:0`. `SUITE_OUT` define onde
ficam PNGs e resumo; não versione esses artefatos. `WIKI_BASE_URL` pode apontar
a outra instalação de teste HTTP, mas o script não altera resolução de DNS nem
tema.

`WIKI_CSS_OVERRIDE=1` carrega a folha Wiki atual por GET no browser para
validar CSS recém-editado quando o Runtime ainda serve um agregado antigo. Isso
valida a folha candidata sobre a página servida; não prova que o agregado ou a
configuração do Runtime foi atualizado.

O script valida raiz, listagem e busca em desktop e larguras móveis, verifica
presença de `main`/`h1`, erros visíveis e rolagem horizontal, e confirma que o
caminho interno `/wiki` responde 404 por GET. Testa apenas o tema ativo no
servidor. Olivero, leitores de tela, teclado e WCAG 2.2 AA precisam de sessões
separadas.

## Teclado

`keyboard.py` percorre a página de busca com Tab real via WebDriver no Firefox
ESR, em 1280 e 320 CSS px. Confere foco visível, alcance do campo e do botão de
pesquisa; digita um termo sem enviar o formulário. Continua sendo somente GET,
sem login ou escrita persistente.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-wiki-keyboard DISPLAY=:0 \
  python3 -I tests/browser/aculta-wiki/keyboard.py
```

Para aplicar a folha Wiki atual por GET do browser quando o agregado do Runtime
estiver desatualizado, defina também `WIKI_CSS_OVERRIDE=1`; isso mede o CSS
candidato, não a publicação do agregado.
