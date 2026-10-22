# GanjaLab e GanjaLab420 — contrato de identidade pública
**Data:** 2026-10-22 · **Versão:** 0.1.0 · **Estado:** transição parcial de nomenclatura.

## Nomes aprovados
- **GanjaLab** = interface de criação, produção editorial, edição e gestão de conteúdos (antigo nome público ACULTA Studio).
- **GanjaLab420** = experiência da pessoa usuária/estudante, inicialmente focada nos cursos, atividades e aprendizagem.
- **ACULTA** = plataforma/ecossistema; **ACULTA420** = tema e design system compartilhado.

Uma mesma pessoa pode acessar ambas de acordo com as permissões e o contexto da tarefa. GanjaLab e GanjaLab420 não são perfis isolados, nem novos ambientes de autenticação. As ferramentas Revista, Cursos, Wiki, Fórum e Galerias mantêm os nomes atuais nesta etapa; nomes canábicos de subferramentas serão decididos separadamente.

## O que mudou neste ciclo

O rótulo público em `aculta_studio.info.yml` e os títulos das rotas `aculta_studio.*` são **GanjaLab**. URLs `/studio`, machine name `aculta_studio`, PHP namespace, IDs de plugins, serviços, marcadores `data-portal-ui`, `data-aculta-studio`, arquivos JS/CSS, permissões e schemas permanecem idênticos. Comentários históricos e documentos que usam “Studio” continuam válidos como referência ao módulo técnico; telas devem usar “GanjaLab” conforme migração dos rótulos.

**GanjaLab420:** primeira entrega é documental; nenhuma rota, menu, nome interno ou interface foi modificada, nem foi criado módulo novo. A arquitetura e jornada estão em [GanjaLab420 — experiência do estudante](GANJALAB420-LEARNING-UX.md).

## UX e arquitetura

- Criadores no **GanjaLab** → [conceito visual Studio](STUDIO-VISUAL-CONCEPT.md), [Revista](STUDIO-REVISTA-EDITOR-VISUAL.md) e [Course Builder](STUDIO-LMS-COURSE-BUILDER-VISUAL.md).
- Estudantes no **GanjaLab420** → [jornadas LMS e telas de aprendizagem](GANJALAB420-LEARNING-UX.md).
- Design e apresentação permanecem no ACULTA420, dados e acesso no Drupal Core/Group/LMS/providers funcionais.
- Não renomear código/rotas, não criar storage paralelo e não acoplar marca a privilégios de usuário.

## Checklist para migração futura de demais rótulos

Inventariar primeiro menus, breadcrumbs, templates, blocos, links de conta, traduções e testes que mostrem “Studio”/“Estúdio”; distinguir texto público de identificadores técnicos; planejar mudança controlada com compatibilidade e testes. Não substituir globalmente strings; evitar mudanças de API e de domínio nesta fase. Documentar todo novo componente de GanjaLab420 antes de desenvolvê-lo.
