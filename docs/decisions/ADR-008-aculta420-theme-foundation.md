# ADR-008: ACULTA420 como nova fundação do tema

Status: Accepted  
Data: 2026-10-07

## Contexto

O tema histórico `aculta` passou por uma refatoração estrutural ampla e tornou-se
a base de um Component Design System. A continuidade sob o mesmo provider
misturaria identidade histórica, documentação transitória e uma API que ainda
não havia sido versionada formalmente.

No Drupal, o machine name de um tema é parte da API: nomeia pasta/arquivos,
hooks, libraries, settings e o namespace SDC `provider:component`.

## Decisão

A nova fundação nasce como:

- nome humano: **ACULTA420**;
- machine name: `aculta420`;
- versão inicial: `0.1.0`;
- diretório: `web/themes/custom/aculta420`;
- libraries: `aculta420/*`;
- SDC namespace: `aculta420:*`;
- settings: `aculta420.settings`;
- hooks: `aculta420_preprocess_*`.

Bootstrap5 permanece base estrutural/comportamental. `aculta_portal` permanece
camada de integração.

## Compatibilidade de deploy

A decisão inicial considerou um shim temporário para facilitar a transição de
ambientes. Durante o fechamento da Foundation 0.1.0 essa exceção foi retirada.

A versão final da fundação **não mantém provider legado, alias ou shim no
repositório**. O estado suportado parte de `aculta420` instalado e configurado
como tema público.

Ambientes históricos que ainda dependam do provider anterior devem ser migrados
operacionalmente antes de receber o estado final da 0.1.0; essa compatibilidade
não é responsabilidade do runtime atual.

A troca de provider continua exigindo janela controlada/maintenance mode quando
aplicável.

## IDs preservados

Não renomeamos automaticamente:

- `.aculta-*`;
- `--aculta-*`;
- `aculta_portal`;
- Domain/content/view/menu/block IDs `aculta_*`.

Esses identificadores representam marca, conteúdo/configuração ou outros
subsistemas e não o provider do tema.

## Consequências

### Positivas

- API do design system começa limpa e versionada;
- namespace SDC e libraries ficam coerentes;
- documentação passa a refletir estado atual;
- futuras releases têm SemVer próprio.

### Custos

- config sync precisa migrar theme dependencies/settings;
- deploy de ambiente histórico exige sequência controlada;
- consumidores históricos do namespace anterior precisam migrar fora do runtime atual.

## Referências

- documentação ACULTA420: `web/themes/custom/aculta420/docs/`;
- SDC API: https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/api-for-single-directory-components
- troubleshooting de extensão ausente: https://www.drupal.org/docs/updating-drupal/troubleshooting-database-updates
