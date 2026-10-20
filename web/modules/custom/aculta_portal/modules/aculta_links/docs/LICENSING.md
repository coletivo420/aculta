# Origem e licenciamento

| Fonte | Referência | Licença observada | Estado |
| --- | --- | --- | --- |
| Drupal Linky | `2.0.0-beta1`, commit `2d70712a854065428faf0228aef96066bd6812f2` | Ecossistema Drupal.org GPL-2.0-or-later; confirmar cada arquivo/cabeçalho antes de copiar. | Nenhum arquivo copiado. |
| LinkStack | `v4.8.0`, commit `ae6837e8c0cc2cd0e77f26952ceca97d0eebec35` | `composer.json`: GPL-3.0-or-later; `LICENSE` nessa tag: AGPL-3.0. | CONFLITO não resolvido; nenhum código/asset copiado. |
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

Fontes consultadas: [metadados Composer da tag LinkStack](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.0/composer.json),
[LICENSE da tag LinkStack](https://raw.githubusercontent.com/LinkStackOrg/LinkStack/v4.8.0/LICENSE),
[página do Linky](https://www.drupal.org/project/linky) e
[guia oficial de licenciamento Drupal](https://www.drupal.org/docs/user_guide/en/understanding-gpl.html).
