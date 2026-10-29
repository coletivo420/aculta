# ACULTA Painel de conteúdo (`aculta_content_admin`)

Versão: **0.1.0-rc.1**. ADM5 foi validada no Runtime de desenvolvimento com
Firefox ESR autenticado: a conta de teste viu somente seis coleções com acesso
nativo, todos os links receberam foco visível e o painel passou nos viewports
1280/390/320 px. Kernel 4/56 assertions, PHPCS e PHP lint passaram; ver
[roadmap](docs/ROADMAP.md) e [changelog](CHANGELOG.md).

Expõe `/painel-administrativo/colecoes-moderaveis` somente quando a pessoa tem acesso a pelo menos uma coleção moderável. O painel
descobre Views de página em caminhos administrativos cujo tipo-base é entidade de conteúdo e preserva o acesso nativo de
cada View. Listas servidas por controllers entram em `ModeratableCollectionRegistry::CONTROLLER_COLLECTIONS`; cada rota
passa pelo `access_manager` antes de virar link. A allowlist contém apenas filas/coleções, nunca ações, telas de
configuração ou relatórios operacionais.

O painel não replica registros, campos nem ações em lote e não oferece exportação. A permissão da coleção de origem é a
única fonte de autorização para visualizar e abrir cada lista.

