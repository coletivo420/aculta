# Revisão visual do Painel de conteúdo

Firefox ESR visível no servidor de desenvolvimento `aculta.toca.net.br`. O
script usa link de uso único via Drush para o usuário de teste UID 5 (sem papel
administrator, com coleções de Fórum moderáveis), abre
`/painel-administrativo/colecoes-moderaveis`, confere que só aparecem suas coleções, testa
Tab/`:focus-visible` e mede o reflow em 1280, 390 e 320 CSS px. Não cria nem
altera conteúdo. Capturas e `summary.json` ficam fora do repositório em
`SUITE_OUT`.

```sh
SUITE_OUT=/caminho/fora-do-repositorio/aculta-content-admin \
ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
SUITE_UID=5 DISPLAY=:0 \
python3 -I tests/browser/aculta-content-admin/visual.py
```

O script captura o link de uso único somente em memória e não o imprime. A conta
UID 5 já existe no Runtime de teste; não remova nem altere os papéis dela. O
teste não substitui revisão com leitor de tela, zoom ou avaliação de cada fila
de destino; o acesso nativo das rotas é coberto pelo Kernel.
