# Roadmap — ACULTA QR Code

Seguir o ciclo Dev → RC → Alpha ([RELEASE-LIFECYCLE.md](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md)). Cada fase fecha com commits atômicos, verificações, CHANGELOG e linha correspondente no índice de estado do README raiz. Nenhuma fase é avançada automaticamente.

## 0.1.0 — auditoria, licenças e arquitetura (em andamento: 0.1.0-dev.1)

- Auditoria do código do ACULTA e dos seis projetos Drupal.org ([AUDITORIA.md](AUDITORIA.md)).
- Licenças e dependências ([LICENSING.md](LICENSING.md)).
- Arquitetura, entidades, domínio e decisões pendentes ([ARQUITETURA.md](ARQUITETURA.md)).
- Sem código, sem banco, sem dependência Composer.

Decisões 1 a 5 de `ARQUITETURA.md` registradas pelo responsável em 2026-10-20 (lote: Batch API).

## 0.2.0 — motor de geração (concluída em 0.2.0-dev.1)

- Feito: serviço `generator` com Endroid 6.1.3 (já fixado no lock), QR estático em SVG e PNG.
- Feito: presets `aculta_padrao` e `aculta_impressao` com contraste mínimo de 4,5:1 e margens validadas.
- Feito: validação de URL e texto, rejeição de `javascript:`, `data:`, `file:` e credenciais.
- Feito: testes Unit (25 testes, 38 asserções) e contrato em [API.md](API.md).
- Sem entidade, rota pública ou gravação de arquivo.
- Pendente antes de RC: PHPUnit e PHPCS no PHP 8.5 do servidor.

## 0.3.0 — entidade e QR dinâmico (em andamento: 0.3.0-dev.1)

Feito em 0.3.0-dev.1: entidade `aculta_qr_code`, manager, validade, revisões, redirecionamento `/qr/{codigo}` e Kernel 8/60. Pendente antes de RC: registro do caminho no mapa (feito), validação HTTP e do purpose no Runtime.

Itens originais:

- Entidade `aculta_qr_code` com revisões, código opaco e validade.
- Serviços `manager`, `resolver` e `redirect`.
- Permissões por operação e Entity Access.
- Caminho público definido (decisão 2) e registrado no mapa de rotas.
- Testes Kernel de destino, validade, desativação, acesso e cache.

## 0.4.0 — painel e exportação (em andamento: 0.4.0-dev.1)

Feito em 0.4.0-dev.1: painel administrativo (lista, criação, edição, exclusão e downloads), exportação SVG/PNG/WebP/PDF pelo exportador, com regras de arquivo do `aculta_image`. Pendente: personalização visual, logo por arquivo do `aculta_image`, prévia e componentes ACULTA420 (tema, exige autorização explícita). Validação no Runtime e download PDF/PNG com `aculta_image` ativo: DEFERRED.

Itens originais:

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
