# ACULTA Links (`aculta_links`)

Submódulo Drupal para consolidar a página pública e as ferramentas de links do
purpose `links` em micropáginas gerenciáveis. Versão declarada: `0.1.0-dev.1`.
Esta fundação é documental e permanece desabilitada; não registra rotas,
entidades, permissões, serviços nem altera a página atual.

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

## Limites desta fundação

Não há código upstream copiado, dependências Composer novas, esquema de dados,
instalação no Runtime ou alteração de rota. A classificação de cada recurso e
os bloqueios de licenciamento estão registrados na matriz. A implementação
funcional começa somente nas fases seguintes, com a migração preservadora da
página atual como primeiro passo.
