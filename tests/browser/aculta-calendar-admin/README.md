# Revisão visual de ADM8 — hosts de incorporação do calendário

O script `adm8-hosts.py` revisa a tela administrativa somente leitura do
calendário no Firefox ESR visível em `DISPLAY=:0`, no servidor de desenvolvimento.

## Cobertura

- acesso autenticado à rota `/painel-administrativo/calendario/incorporacao`;
- cabeçalhos da tabela dos ambientes de teste e de produção;
- ausência de formulários e controles de edição;
- ausência de rolagem horizontal em 1280 e 390 px;
- capturas PNG e `resultado.json` gravados em `$SUITE_OUT`.

## Execução

Crie uma conta descartável com apenas a permissão `administer aculta calendar
embedding`. Guarde a senha fora do repositório em arquivo legível apenas pelo
usuário de teste, defina as variáveis e execute:

```sh
DISPLAY=:0 \
SUITE_OUT=/caminho/fora/do/repo/aculta-calendar-adm8 \
ADM8_TEST_USER=teste_adm8 \
ADM8_TEST_PASS_FILE=/caminho/fora/do/repo/senha-temporaria \
python3 tests/browser/aculta-calendar-admin/adm8-hosts.py
```

O script reutiliza `tests/browser/aculta-cpf-checkout/wdlib.py` e cria um perfil
temporário dentro de `$SUITE_OUT`, removido ao encerrar o WebDriver. O usuário e
seu papel temporários devem ser removidos depois da execução.

## Limitações

- Não testa edição da allowlist: a tela não oferece essa capacidade.
- Não cobre os hosts de produção por requisição externa nem a validação do
  domínio em Alpha/Beta.
- Não substitui a auditoria automatizada de acesso Kernel.
