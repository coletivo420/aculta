# Domain Config no ACULTA

Configuração independente por domínio no Runtime de desenvolvimento do ACULTA,
com foco no **Layout de Blocos** (`/painel-administrativo/estrutura/block`).

- **Estado:** habilitado e homologado no servidor de desenvolvimento em 2026-11-09.
- **Versões:** Drupal 11.5.0-beta1, PHP 8.5.12, Domain 4.0.0-alpha2,
  Domain Config UI 4.0.0-alpha2, Domain Config Entity UI 4.0.0-alpha1 (Domain
  Extras), Domain Config Switcher (Domain Extras).
- **Domínio base/global:** MAIN (`aculta_org`). Os overrides são criados para os
  demais domínios; sem override, vale o valor global.
- **Ponte ACULTA:** `aculta_domain_config`, submódulo de `aculta_portal`, permite
  escolher o domínio do override a partir do MAIN, sem alterar a negociação do
  request.

Arquivos:

- [INVENTARIO.md](INVENTARIO.md) — estado encontrado, versões, backups e órfãos.
- [ARQUITETURA.md](ARQUITETURA.md) — como o override é resolvido e editado.
- [LAYOUT-BLOCOS.md](LAYOUT-BLOCOS.md) — operação do dia a dia no Layout de Blocos.
- [TESTES.md](TESTES.md) — testes A–H e evidências executadas.
- [OPERACAO.md](OPERACAO.md) — cache, exportação/importação, rollback e limitações.
