# Roadmap — ACULTA QR Code

Seguir o ciclo Dev → RC → Alpha ([RELEASE-LIFECYCLE.md](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md)). Cada fase fecha com commits atômicos, verificações, CHANGELOG e linha correspondente no índice de estado do README raiz. Nenhuma fase é avançada automaticamente.

## 0.1.0 — auditoria, licenças e arquitetura (em andamento: 0.1.0-dev.1)

- Auditoria do código do ACULTA e dos seis projetos Drupal.org ([AUDITORIA.md](AUDITORIA.md)).
- Licenças e dependências ([LICENSING.md](LICENSING.md)).
- Arquitetura, entidades, domínio e decisões pendentes ([ARQUITETURA.md](ARQUITETURA.md)).
- Sem código, sem banco, sem dependência Composer.

Para fechar 0.1.0: o responsável responde as decisões 1 a 5 de `ARQUITETURA.md`, ou as aceita como recomendadas.

## 0.2.0 — motor de geração

- Serviço `generator` com Endroid 6.1.3 já fixado no lock: QR estático, SVG e PNG.
- Presets básicos com validação de contraste e margens.
- Sem entidade, sem rota pública, sem gravação de arquivo.
- Testes Unit de geração, presets e rejeição de protocolos.

## 0.3.0 — entidade e QR dinâmico

- Entidade `aculta_qr_code` com revisões, código opaco e validade.
- Serviços `manager`, `resolver` e `redirect`.
- Permissões por operação e Entity Access.
- Caminho público definido (decisão 2) e registrado no mapa de rotas.
- Testes Kernel de destino, validade, desativação, acesso e cache.

## 0.4.0 — painel e exportação

- Painel administrativo em `main`, com formulários, listagem e prévia.
- Personalização visual (cores, logo por arquivo, legenda, moldura).
- Exportação SVG, PNG e WebP; PDF conforme a decisão 1.
- Componentes no ACULTA420, com ajuste no tema autorizado por escopo.

## 0.5.0 — integração universal Drupal

- Formatador de campo, bloco, filtro Twig, token, Views e comando Drush, portados do padrão de `barcodes` e `qr_code_formatter` para QR.
- Atributos de plugin do Core 11 (não anotações).

## 0.6.0 — analytics e lote

- Contagem agregada conforme a decisão 4.
- Geração em lote por Batch API (decisão 5).
- Campanhas com agrupamento simples.

## 0.7.0 — integrações

- `aculta_links`: migrar a geração para o serviço central, mantendo as rotas existentes.
- `aculta_lms` (F11): QR de verificação de certificado.
- Commerce, eventos, Wiki, Fórum e conteúdos institucionais, quando o escopo for liberado.

## 0.8.0 — hardening

- Revisão de performance, cache, segurança, acessibilidade e dependências.
- Teste de SSRF, XSS em SVG e enumeração de códigos.

## 1.0.0 — revisão integral

- Auditoria funcional, de permissões e de licenças.
- Testes Unit, Kernel, funcionais e multidomínio.
- Revisão de documentação e preparação para produção.
