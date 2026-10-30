# ACULTA Eventos (`aculta_events`)

## Contrato obrigatório para campos de endereço

Por decisão D-54/D-55 do responsável, toda captura/edição de endereço de
eventos e locais deve reutilizar `AccountAddressForm` ou adaptador central de
`aculta_portal`, com `aculta_portal_cep/cep-address`. O país é sempre `BR`, sem
seletor. Preserve os campos e entidades próprios de evento/local; não crie
formulário paralelo nem replique consulta, endpoint ou cliente de CEP. Isso
também vale para etapas de revisão de endereços importados que exibam um
formulário à pessoa.

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

Endereços dos locais são Brasil-only e usam o formulário central de `aculta_portal`
com `aculta_portal_cep`; o assistente OpenStreetMap adapta o widget para as tags
OSM e mantém confirmação explícita antes de qualquer escrita externa. Decisão
D-55 e contrato em [`ACCOUNT-ADDRESS.md`](../../../../../../docs/portal/ACCOUNT-ADDRESS.md).

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

## Incremento de Locais - planejado em 2026-10-29

[Plano de implementação](../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md) e [roadmap](docs/ROADMAP.md).
Entidade existente preservada; faltam CRUD nativo, seleção/criação inline no
editor de Data, detalhe com agenda e navegação. A integração com aculta_index
inclui canônicos, relações Evento > Local, ACL, consultas paginadas e manutenção
incremental. O RC acima é evidência histórica; o novo escopo está Dev/planejado,
sem mudança de manifesto ou teste Runtime nesta revisão.


## Fluxo planejado de Local no formulário Activity

O cadastro/seleção descrito em [ADR-033](../../../../../../docs/decisions/ADR-033-endereco-busca-e-locais.md) ainda não está implementado: modal Drupal/AJAX para localizar por nome, selecionar Local existente ou cadastrar outro; cadastro começa por CEP via adaptador central. Busca por logradouro fica restrita a Events/Maps e aguarda aprovação. Os campos de endereço avulsos de Activity devem ser substituídos somente com plano de migração de dados.
