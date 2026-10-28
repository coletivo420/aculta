# Revisão visual da Frente B

`visual.py` verifica a View pública de álbuns, consumidora real da variante
`patterns/card` compact, nos viewports CSS de 1280 px e 320 px; captura também
modo escuro com `prefers-color-scheme: dark`, `data-theme="dark"` e o atributo
de tema Bootstrap usado pelo site. Registra overflow horizontal, cartões
compactos, alvos de interação menores que 44 px e links vazios sem nome.
O script também usa a conta administrativa de teste documentada (UID 205 por
padrão), via link único do Drush mantido somente em memória, verifica a rota
protegida da UI Patterns Library e captura os estados salvo/não salvo da Flag
nativa no tópico do Fórum. A Flag é restaurada ao estado inicial no `finally`.

## Ambiente e execução

- Servidor de testes ACULTA (`*.aculta.toca.net.br`), checkout em
  `/home/piradopirata/projetos/aculta`.
- Firefox ESR visível em `DISPLAY=:0`, controlado por geckodriver e perfil
  temporário em `/tmp`; nunca Chromium/headless.
- Requer Drush local e a conta de teste administrativa documentada.
- `SUITE_OUT`: diretório absoluto fora do repositório para PNG e JSON; padrão
  `/tmp/aculta-visual-b7`.
- `VISUAL_ADMIN_UID`: UID da conta administrativa de teste; padrão `205`.
- `ALBUM_URL` e `STORY_URL`: permitem apontar às rotas de teste canônicas.

```sh
DISPLAY=:0 SUITE_OUT=/tmp/aculta-visual-b7 \
  python3 tests/browser/aculta-visual/visual.py
```

## Limitações

As páginas são lidas por GET. Para fotografar os dois estados do bookmark, a
suíte aciona e restaura a Flag do tópico de homologação para a conta de teste;
não altera conteúdo nem a senha da conta. Ela não valida combinações de cor ou
os demais SDCs individualmente. A rota do provider da UI Patterns devolveu a
página genérica de erro do Drupal, então as stories de seção, layout, contador,
selos e marcas não puderam ser homologadas visualmente. O link único não é
impresso, salvo ou incluído nos artefatos.
