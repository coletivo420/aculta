# Validação visual do ACULTA QR Code

Valida o painel administrativo no servidor de desenvolvimento, sua adaptação
em 1280, 390 e 320 px, a prévia SVG e os downloads SVG, PNG, WebP e PDF.

## Ambiente

- Firefox ESR visível em `DISPLAY=:0` via geckodriver.
- Site `https://aculta.toca.net.br`, servido pelo checkout
  `/home/piradopirata/projetos/aculta`.
- O módulo `aculta_qrcode` e `aculta_image` precisam estar ativos.
- `ACULTA_QR_ID` e `ACULTA_QR_CODE` apontam para uma entidade temporária
  `[TESTE]`, criada no Runtime com destino `https://example.invalid/qa-qr`.
- `SUITE_OUT` aponta para diretório fora do repositório; capturas e resumo ficam
  nele. O perfil Firefox temporário também é criado e removido nesse diretório.

## Execução

```sh
qr_login=$(php /home/piradopirata/projetos/aculta/vendor/drush/drush/drush.php uli --uid=1 --uri=https://conta.aculta.toca.net.br 2>/dev/null)
DISPLAY=:0 SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-qrcode \
ACULTA_QR_ID=ID ACULTA_QR_CODE=CODIGO ACULTA_TEST_LOGIN_URL="$qr_login" \
python3 tests/browser/aculta-qrcode/visual.py
unset qr_login
```

O comando fornece um link de sessão administrativa efêmero pelo Drush em
memória; não grave nem imprima o link. Depois do teste, remova a entidade `[TESTE]`
usando o ID e confirme o título antes da exclusão.

## Limites

O teste usa uma entidade efêmera real do Runtime de desenvolvimento. Não cria
arquivos permanentes no repositório nem acessa produção. O PDF pode depender do
motor Entity Print instalado no Runtime.
