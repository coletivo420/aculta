# Origem e licenciamento

| Fonte | Referência | Licença observada | Estado |
| --- | --- | --- | --- |
| Drupal Linky | `2.0.0-beta1`, commit `2d70712a854065428faf0228aef96066bd6812f2` | Ecossistema Drupal.org GPL-2.0-or-later; confirmar cada arquivo/cabeçalho antes de copiar. | Nenhum arquivo copiado. |
| LinkStack | Release mais recente consultada: `v4.8.6`, commit `dca586e8b8946a779cb212d21791cff6308fa7c0`; auditoria anterior em `v4.8.0` | `composer.json`: GPL-3.0-or-later; `LICENSE` da tag declara AGPL-3.0. | CONFLITO não resolvido por arquivo; nenhum código/asset copiado. |
| Endroid QR Code | `endroid/qr-code` 6.1.3; `bacon/bacon-qr-code` 3.1.1 e `dasprid/enum` 1.0.7 fixados em `composer.lock` | Endroid MIT; BaconQrCode BSD-2-Clause; enum BSD-2-Clause. | Instalado via Composer para gerar SVG local sem serviço externo. |
| Drupal/Portal | Repositório ACULTA e Core instalado | Guia oficial Drupal.org indica GPL-2.0-or-later para Core e projetos Drupal.org. | Confirmar licenças locais por arquivo antes de distribuir. |

Antes de portar LinkStack, consultar upstream para determinar escopo da licença;
conferir individualmente código, CSS, JS, fontes, ícones, imagens, temas, blocos
e dependências. AGPL prevê disponibilização da fonte correspondente em uso
público por rede de versão modificada, além de avisos de origem/modificação,
licença e interface aplicáveis. Compatibilidade da obra combinada com Drupal e
fronteira dos módulos não é presumida aqui.

Preservar copyright e histórico de origem de cada arquivo. Registrar origem,
commit, alterações, licença e dependências. Não copiar até resolver o conflito
entre `composer.json` e `LICENSE` e revisar licenças de terceiros. Isto não é
parecer jurídico.

Fontes consultadas: [release v4.8.6](https://github.com/LinkStackOrg/LinkStack/releases/tag/v4.8.6),
[metadados Composer da tag LinkStack v4.8.6](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.6/composer.json),
[LICENSE da tag LinkStack v4.8.6](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.6/LICENSE),
[advisory GHSA-mg7g-g2wv-6mhr](https://github.com/advisories/GHSA-mg7g-g2wv-6mhr),
[página do Linky](https://www.drupal.org/project/linky) e
[guia oficial de licenciamento Drupal](https://www.drupal.org/docs/user_guide/en/understanding-gpl.html).
