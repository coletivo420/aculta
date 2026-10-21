# ACULTA Links (`aculta_links`)

Submódulo Drupal para consolidar a página pública e as ferramentas de links do
purpose `links` em micropáginas gerenciáveis. Versão declarada:
`0.4.0-dev.21`. O módulo possui entidades revisionáveis de destinos, micropáginas
e itens ordenados, painel administrativo, editor de composição e rota pública
`/pagina/{slug}` no purpose `links`. A raiz do purpose preserva a página
editorial encontrada pelo alias `/redes-sociais`; se o Node/alias não existir,
apresenta os mesmos botões configurados e projetos pela View existente. O caminho
interno `/redes-sociais` continua inacessível diretamente.
Páginas publicadas têm download de QR Code SVG em
`/pagina/{slug}/codigo-qr.svg`; a carga codifica a URL canônica do purpose
`links` sem armazenar imagem ou medir visitas.
Metatag fornece título, descrição, canonical Domain, Open Graph e Schema.org
por página; rotas privadas de edição e preview são `noindex`.
Destinos publicados também recebem um código curto aleatório e permanente,
disponível em `/redirecionar/{codigo}` no purpose `links`.
Destinos podem ser uma URL/caminho validado ou uma referência Node escolhida
com autocomplete Core; resolução pública conserva Domain, publicação e access.
O dashboard oferece QR SVG desse caminho curto, sem registrar leituras ou
persistir imagens.
Cada micropágina pode optar por exibir o apelido e o perfil público do titular;
`aculta_user` aplica suas regras de visibilidade e entrega URL canônica e cache.
A opção inicia desativada; avatar, e-mail, bio e redes não são herdados.
GETs aceitos pelo redirect curto são agregados por destino/dia via Queue API;
o painel soma até 90 dias, sem identificadores de visitante. Page views e
origem de tráfego ainda não são métricas oferecidas. A coleta inicia desligada
e só pode ser ativada por administrador em `/painel-administrativo/aculta/links/configuracoes`.

A configuração e o formulário administrativo dos canais sociais também estão
no submódulo. Os valores são migrados sem apagar `aculta_portal.social_links`, e
o formulário mantém as duas configurações sincronizadas durante a janela de
rollback. Presenter e hooks específicos da interface pública também pertencem
ao submódulo; Portal continua responsável pelas APIs compartilhadas de Domain.

## Estado atual e preservação

O purpose `links` já está configurado como Domain e serve a página raiz
`links.aculta.org/` por meio do processador de raiz do Portal. A implementação
atual é uma página social institucional, com botão para o purpose `main`, canais
sociais configuráveis, botões adicionais e uma View de projetos. Ela deve
continuar funcionando durante toda a portabilidade. Não houve migração nem
remoção de configuração ou conteúdo nesta fase.

As redes sociais pessoais pertencem ao `aculta_user` e aos seus contratos de
perfil e privacidade. Conteúdo de projetos, eventos, cursos e mídia continua sob
as entidades Drupal que o fornecem. O `aculta_links` consome
essas APIs; não passa a ser dono desses dados.

Itens podem ter janela opcional de disponibilidade. Os campos `available_from`
e `available_until` são revisionáveis, editados no fuso padrão de `system.date`
e armazenados em UTC. A validação exige que o fim seja posterior ao início. A
prévia privada mostra itens agendados; a página pública só os inclui dentro da
janela. A cache expira na próxima transição, sem depender de cron.

## Documentação

- [Estado e inventário](docs/CURRENT-STATE.md)
- [Arquitetura](docs/ARCHITECTURE.md)
- [Modelo de entidades proposto](docs/ENTITY-MODEL.md)
- [Integração com Domains](docs/DOMAIN-INTEGRATION.md)
- [Integração com ACULTA420](docs/DESIGN-SYSTEM.md)
- [Permissões e papéis](docs/PERMISSIONS.md)
- [Segurança](docs/SECURITY.md)
- [Compatibilidade e portabilidade Linky](docs/LINKY-PORTABILITY.md)
- [Compatibilidade e portabilidade LinkStack](docs/LINKSTACK-PORTABILITY.md)
- [Matriz de portabilidade](docs/PORTABILITY-MATRIX.md)
- [Licenças](docs/LICENSING.md)
- [Funcionalidades](docs/FEATURES.md)
- [Testes e critérios](docs/TESTING.md)
- [Roadmap](docs/ROADMAP.md)
- [Changelog](CHANGELOG.md)

## Estado da implementação

Nenhum código upstream foi copiado. `endroid/qr-code` gera SVG localmente
(MIT; BaconQrCode e dasprid/enum BSD-2-Clause). `aculta_link` usa o campo Link do Core; `aculta_link_page` guarda
slug, título, apresentação e publicação; `aculta_link_item` ordena referências
entre página e destino. As três entidades têm revisões Drupal. Access mantém
destinos e rascunhos privados por proprietário. O editor oferece prévia privada
de rascunhos e itens inativos, sem cache compartilhado. A rota pública usa status publicado, validação de slug/destino e o SDC
`social-links-page` via template ACULTA420. O módulo foi habilitado no Runtime
de teste em 2026-10-20; router confirmado sob purpose `links`; slug inexistente
retorna 404 e dashboard anônimo retorna 403. Updates 10003–10007 foram aplicados
após backup verificado, sem importação global da configuração. Smoke de
referência Node confirmou destino canônico Domain; a fixture foi removida. A
produção não foi alterada. Micropáginas oferecem três presets controlados pelo
ACULTA420. A revisão visual e acessível integral com conteúdo permanece
pendente.
