# ACULTA Eventos (`aculta_events`)

Eventos, datas e locais do purpose `eventos` (ADR-015). Usa o `aculta_calendar` para recorrência, agenda e `.ics`, e o `aculta_maps` para o mapa de locais. Ingressos pagos ficam fora da v1 e seguem plano próprio.

- Roadmap e fases: [docs/ROADMAP.md](docs/ROADMAP.md)
- Modelo de dados: [docs/MODELO-DE-DADOS.md](docs/MODELO-DE-DADOS.md)
- Plano de implantação: [docs/PLANO-IMPLANTACAO.md](docs/PLANO-IMPLANTACAO.md)
- Ingressos pagos (plano, fora da v1): [docs/PLANO-INGRESSOS-PAGOS.md](docs/PLANO-INGRESSOS-PAGOS.md)
- Caracterização do carrinho (P1 de ingressos): [docs/CARACTERIZACAO-CARRINHO-P1.md](docs/CARACTERIZACAO-CARRINHO-P1.md)
- Visual: [docs/VISUAL.md](docs/VISUAL.md)
- Origem e atribuição do OpenCulturas: [docs/ORIGEM-OPENCULTURAS.md](docs/ORIGEM-OPENCULTURAS.md)
- Histórico de alterações: [CHANGELOG.md](CHANGELOG.md)

## Validação visual (2026-10-29)

O smoke do purpose `eventos` passou 129/129 verificações no candidato atual
`a4f878d55` (events dev.3), servido no checkout de teste, em 1280/390/320 px
reais, com modo escuro. Script e instruções:
[tests/browser/eventos](../../../../../../tests/browser/eventos/README.md).

O dev.4 recusa `UNTIL` anterior ao início da série no formulário de recorrência,
com a mesma regra no expansor como guarda de dados.

O RC `0.1.0-rc.1` (candidato `89efeb5b7`) aplica a D-52: o mapa mantém apenas a
atribuição do provedor e os links para a Política de Privacidade, sem o texto
inline de envio de IP; o smoke confirmou 129/129 verificações e o mapa sem o
texto antigo. O OAuth do OSM foi validado no Runtime e o ensaio de escrita
fictícia foi concluído com limpeza (nó `14331309082`, changesets `191197897`,
`191197899` e `191197900`). Próxima etapa: validação Alpha após autorização de
release.
