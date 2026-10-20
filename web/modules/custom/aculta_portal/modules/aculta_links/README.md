# ACULTA Links (`aculta_links`)

Submódulo Drupal para consolidar a página pública e as ferramentas de links do
purpose `links` em micropáginas gerenciáveis. Versão declarada:
`0.4.0-dev.6`. O módulo possui entidades revisionáveis de destinos, micropáginas
e itens ordenados, painel administrativo, editor de composição e rota pública
`/pagina/{slug}` no purpose `links`. A raiz do purpose preserva a página
editorial encontrada pelo alias `/redes-sociais`; se o Node/alias não existir,
apresenta os mesmos botões configurados e projetos pela View existente. O caminho
interno `/redes-sociais` continua inacessível diretamente.

A configuração e o formulário administrativo dos canais sociais também estão
no submódulo. Os valores são migrados sem apagar `aculta_portal.social_links`, e
o formulário mantém as duas configurações sincronizadas durante a janela de
rollback. O presenter e os hooks de render ainda estão no Portal enquanto a
paridade de migração é concluída.

## Estado atual e preservação

O purpose `links` já está configurado como Domain e serve a página raiz
`links.aculta.org/` por meio do processador de raiz do Portal. A implementação
atual é uma página social institucional, com botão para o purpose `main`, canais
sociais configuráveis, botões adicionais e uma View de projetos. Ela deve
continuar funcionando durante toda a portabilidade. Não houve migração nem
remoção de configuração ou conteúdo nesta fase.

As redes sociais pessoais pertencem ao `aculta_user` e aos seus contratos de
perfil e privacidade. Conteúdo de projetos, eventos, cursos e mídia continua sob
as entidades Drupal que o fornecem. O futuro `aculta_links` poderá consumir
essas APIs; não passa a ser dono desses dados.

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

Nenhum código upstream foi copiado e nenhuma dependência Composer foi
adicionada. `aculta_link` usa o campo Link do Core; `aculta_link_page` guarda
slug, título, apresentação e publicação; `aculta_link_item` ordena referências
entre página e destino. As três entidades têm revisões Drupal. Access mantém
destinos e rascunhos privados por proprietário. O editor oferece prévia privada
de rascunhos e itens inativos, sem cache compartilhado. A rota pública usa status publicado, validação de slug/destino e o SDC
`social-links-page` via template ACULTA420. O módulo foi habilitado no Runtime
de teste em 2026-10-20; router confirmado sob purpose `links`; slug inexistente
retorna 404 e dashboard anônimo retorna 403. Micropáginas oferecem três presets
de aparência controlados pelo ACULTA420. A configuração exportável foi
atualizada, sem importação global. A migração da landing atual e a revisão visual
com conteúdo continuam pendentes.
