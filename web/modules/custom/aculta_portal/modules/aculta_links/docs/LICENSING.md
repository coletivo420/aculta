# Origem e licenciamento

| Fonte | Referência | Licença observada | Estado |
| --- | --- | --- | --- |
| Drupal Linky | `2.0.0-beta1`, commit `2d70712a854065428faf0228aef96066bd6812f2` | Ecossistema Drupal.org GPL-2.0-or-later; confirmar cada arquivo/cabeçalho antes de copiar. | Nenhum arquivo copiado. |
| LinkStack | Release mais recente consultada: `v4.8.6`, commit `dca586e8b8946a779cb212d21791cff6308fa7c0`; auditoria anterior em `v4.8.0` | README da tag e `LICENSE` declaram AGPL-3.0; `composer.json` contém metadata divergente `GPL-3.0-or-later`. | Adotar AGPL-3.0 como classificação conservadora do código original. Nenhum código/asset copiado; desenvolvimento ACULTA reimplementa comportamento em Drupal. |
| Endroid QR Code | `endroid/qr-code` 6.1.3; `bacon/bacon-qr-code` 3.1.1 e `dasprid/enum` 1.0.7 fixados em `composer.lock` | Endroid MIT; BaconQrCode BSD-2-Clause; enum BSD-2-Clause. | Dependência gerenciada pelo `aculta_qrcode`; Links não chama Endroid diretamente e consome o serviço central. |
| Drupal/Portal | Repositório ACULTA e Core instalado | Guia oficial Drupal.org indica GPL-2.0-or-later para Core e projetos Drupal.org. | Confirmar licenças locais por arquivo antes de distribuir. |

O README da tag v4.8.6 informa que o projeto usa AGPL-3.0 desde a versão 4.0.0,
e tanto esse README quanto o arquivo `LICENSE` da tag apontam para AGPL-3.0. O
campo `license` do `composer.json` é divergente e não substitui os avisos
explícitos do projeto. Até uma correção upstream, a classificação operacional
para código original LinkStack é AGPL-3.0. A lista comunitária de temas e os
repositórios de blocos são projetos separados e não herdam automaticamente a
licença do núcleo.

Para evitar incorporar uma obra sob AGPL a esta aplicação Drupal enquanto a
compatibilidade do conjunto não foi revisada, o ACULTA Links reimplementa os
fluxos e padrões funcionais com APIs Drupal, sem copiar código, templates, CSS,
JavaScript, ícones, fontes, imagens, temas ou blocos do LinkStack. Uma futura
portabilidade literal fica fora do escopo até haver revisão formal da fronteira
de licenciamento. Assim, a evolução funcional pode continuar sem depender da
resolução da metadata upstream.

Se futuramente houver cópia autorizada de código AGPL, a distribuição e o uso
interativo em rede exigem cumprir as obrigações da licença, inclusive fonte
correspondente, avisos de modificações e notices na interface aplicáveis. A
compatibilidade de uma obra combinada com Drupal continua sem parecer jurídico.

Preservar copyright e histórico de origem de cada arquivo quando houver
portabilidade autorizada. Registrar origem, commit, alterações, licença e
dependências. A decisão atual é não copiar arquivos LinkStack; este documento
não é parecer jurídico.

Fontes consultadas: [release v4.8.6](https://github.com/LinkStackOrg/LinkStack/releases/tag/v4.8.6),
[README v4.8.6, que declara AGPL-3.0 desde 4.0.0](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.6/README.md),
[metadados Composer da tag LinkStack v4.8.6](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.6/composer.json),
[LICENSE da tag LinkStack v4.8.6](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.6/LICENSE),
[advisory GHSA-mg7g-g2wv-6mhr](https://github.com/advisories/GHSA-mg7g-g2wv-6mhr),
[página do Linky](https://www.drupal.org/project/linky) e
[guia oficial de licenciamento Drupal](https://www.drupal.org/docs/user_guide/en/understanding-gpl.html).
