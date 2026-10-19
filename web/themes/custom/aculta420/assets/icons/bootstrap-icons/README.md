# Bootstrap Icons

- Versão upstream: 1.11.0.
- Origem: `drupal/bootstrap5` (`dist/icons/1.11.0`) instalado no projeto antes da remoção da dependência.
- Uso: fonte de ícones preservada ao remover a dependência do tema base antigo.
- Arquivos servidos pelo ACULTA420: CSS da fonte, WOFF/WOFF2 e licença MIT original.

A cópia é mantida no tema filho para que ícones `bi bi-*` não dependam da presença
de outro tema. O aviso de copyright e a licença estão em `LICENSE`.

## SVGs de `icons/`

Os SVGs de `icons/` são gerados do pacote oficial `bootstrap-icons@1.11.0` (npm, licença MIT), não desenhados à mão.

- Integridade do tarball conferida contra o registro: `sha512-bLTbtACfUqwZf6f/xUYUb7bTRZC68QaQwwy9h1b96NPKfnwqzSatHqDypW6R2CBW7zUE7lP+O93GdZuPY3RIHA==`.
- Arquivos de origem: `icons/plus-circle.svg` e `icons/dash-circle.svg` do pacote (equivalentes aos `plus-sign` e `minus-sign` do Bootstrap 3).
- Única transformação: `fill="currentColor"` trocado por `fill="#0c3c29"` (cor da marca), porque `<img>` não herda a cor do CSS. O restante do arquivo é o original.

### Regra

Sempre que possível, gerar o ícone a partir do pacote original da mesma versão, e não desenhar à mão. Se o ícone não existir no pacote, registrar a exceção e a origem no próprio README.

### Como regenerar

```sh
mkdir -p /tmp/bi && cd /tmp/bi
npm pack bootstrap-icons@1.11.0
tar -xzf bootstrap-icons-1.11.0.tgz
for n in plus-circle dash-circle; do
  sed 's/fill="currentColor"/fill="#0c3c29"/' package/icons/$n.svg > <tema>/assets/icons/bootstrap-icons/icons/$n.svg
done
```

Os SVGs são usados como `src` de `input[type=image]` nos botões de adicionar e remover do Webform (`PortalUiHooks`).
