# ACULTA Mailer

Plataforma própria de e-mail transacional do ACULTA, integrada ao `aculta_portal`.

- Arquitetura: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Auditoria do estado atual: [docs/AUDIT.md](docs/AUDIT.md)
- Pesquisa e decisões de porte: [docs/UPSTREAM.md](docs/UPSTREAM.md)
- Roadmap e fase atual: [docs/ROADMAP.md](docs/ROADMAP.md)
- Histórico de entregas: [CHANGELOG.md](CHANGELOG.md)

Estado: `aculta_mailer` é o backend `system.mail` da configuração versionada e fornece o transporte SMTP/API,
entrega, filas e registro. A remoção do contrib de envio foi concluída no Runtime de teste; produção e Alpha
seguem o ciclo de validação/autorização descrito no [roadmap](docs/ROADMAP.md).
