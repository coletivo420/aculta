# QA Browser isolado - Studio Marley / CGU

Esta suíte Playwright testa rotas reais do frontend, sem executar a bateria na
instalação compartilhada. Os testes não alteram produção e não cadastram contas.

## Pré-requisitos

- Checkout/worktree isolado e ambiente HTTP **dedicado a testes**.
- Dependências existentes de `tests/browser` instaladas e navegador Playwright.
- Perfis pré-criados no site de teste: `common`, `forum_only`,
  `revista_only`, `wiki_only`, `revista_wiki`, `forum_wiki`,
  `forum_revista`. As combinações devem corresponder a grants reais, não
  apenas nomes de papéis.
- Denúncias sintéticas sobre Fórum público, Fórum privado, Revista, Wiki e
  uma denúncia **descartável** para escrita. Em todas, o servidor recebe o
  purpose original e o título privado deve continuar inacessível.
- Arquivos Playwright `storageState` locais. **Não versionar cookies ou
  credenciais** e não inserir dados reais no exemplo de manifest.

Copie `fixture.example.json` para um local privado do worktree, configure
`accountOrigin`, `forumOrigin`, caminhos absolutos dos estados, IDs de
denúncias e marcadores sintéticos. O campo `isolated: true` é obrigatório.

## Execução somente leitura

```bash
cd tests/browser
ACULTA_QA_MARLEY_FIXTURE=/caminho/privado/marley-fixture.json \
  npx playwright test --config qa-marley/playwright.marley.config.mjs --grep-invert 'POST inválido|decisão válida'
```

## Escrita/CSRF, apenas em banco descartável

Depois de criar um **novo relatório pendente** e garantir que o ambiente
é descartável, executar com permissão explícita:

```bash
cd tests/browser
ACULTA_QA_MARLEY_FIXTURE=/caminho/privado/marley-fixture.json \
ACULTA_QA_MARLEY_ALLOW_MUTATION=yes \
  npx playwright test --config qa-marley/playwright.marley.config.mjs
```

Se os estados, marcadores ou a autorização estiverem ausentes, os testes ficam
`SKIPPED`. Isto NÃO equivale a PASS. Não reutilizar relatório já decidido
em outra execução: o motor CGU rejeita decisão repetida.

A suíte é propositalmente isolada em um config próprio para não ampliar o
runner principal nem executar POST acidentalmente. A validação HTTP não
substitui PHPUnit Kernel/Functional e testes de auditoria multiusuário.

## O que precisa ser registrado

Para cada teste: PASS/FAIL/SKIP, ambiente/commit, perfis e grants anônimos,
papéis combinados, visibilidade de conteúdo, isolamento do purpose,
Form API CSRF, redirects, ausência de PII e persistência de decisão.
Não registrar cookies, senhas ou identidades de denunciantes em relatórios.
