# SFC-01 / SFC-02 - Browser isolado de segurança

Suíte independente de **somente leitura**, fora do runner padrão do projeto.
Nenhum POST e nenhuma escrita em Drupal são executados aqui. O fixture
`isolated=true` é obrigatório e significa um site de testes descartável.

## Preparar

- Implantar a branch `fix/forum-cgu-sfc01-sfc02-20261121` em instalação
  **isolada** e criar contas sintéticas com grants exatos.
- `forumModerator`: somente `moderate aculta forum comments`, sem
  `administer comments`, com leitura de comentário Fórum público.
- `forumMoverMember` e `forumMoverNonMember`: ambos possuem
  `move aculta forum topics`, mas só o primeiro tem membership válida no
  Group restrito e associação ativa. **Não adicionar** CGU scope no segundo.
- `adminComments`: administrador de comentários para verificar Growroom.
- Preparar tópico privado e público do Fórum, relatório CGU com parecer
  sintético, Comments nos contextos Wiki/Revista/LMS, e Comment Growroom.
- Gerar arquivos `storageState` privados e nunca versionar cookies ou senhas.
- Copiar `fixture.example.json` para fora do Git e preencher origens, IDs,
  estados e marcadores com fixtures exclusivamente sintéticas.

## Rodar

```bash
cd tests/browser
ACULTA_SECURITY_QA_FIXTURE=/caminho/seguro/sfc01-sfc02-fixture.json \
  npx playwright test --config qa-security-forum-cgu/playwright.config.mjs
```

Os testes ausentes ficam SKIPPED. Isto **não** significa aprovados.

**Gates ainda obrigatórios no Homelab:** testes Kernel do acesso real
Domain/Group; POST Form API com CSRF válido e revogação entre GET e POST;
retenção de cache/contagens por conta; regressão de moderação LMS por
educador, sem conceder permissões gerais. Nenhum token real deve aparecer
no log. Testar também com duas sessões simultâneas para impedir TOCTOU.

Esta suíte não executa as lacunas SFC-03/04/05/06/07/08/09.
