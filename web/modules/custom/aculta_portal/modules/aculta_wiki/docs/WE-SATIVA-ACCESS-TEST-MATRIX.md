# WE SATIVA — Matriz de acesso e aceite da fundação

**Versão:** WES-UX 0.2-A1 · **Estado:** cenários especificados, NÃO executados.

| Contexto | Ação | Comportamento exigido |
| --- | --- | --- |
| Anônimo + verbete publicado | Ler | Wiki pública sem ação de escrita privilegiada |
| Anônimo + contribuir | Abrir tarefa | Só orientação/login se existir fluxo seguro; nunca conceder update do Node |
| Contribuinte com acesso de criação, sem update do Node | Criar novo | Usar form de criação autorizado; não sobrescrever verbete publicado |
| Contribuinte sem update do Node | Sugerir correção | Não oferecer submissão até haver mecanismo de proposta real e seguro |
| Autor autorizado | Editar próprio verbete | Entity Access em cada GET/POST, forma nativa, nova revisão quando devido |
| Revisor autorizado | Moderar | Somente transições disponíveis no workflow atual |
| Conta revogada | Abrir link antigo | Revalidar acesso; nada em cache público ou formulário editável |
| Purpose/domínio incorreto | Seguir link de contribuição | URL canônica permitida e isolamento do Domain; sem fallback amplo |
| Revisão pendente + publicação existente | Editar | Preservar versão publicada e distinguir revisão de atualização |
| Duas sessões editando | Salvar | Analisar conflito; não declarar locks ou autosave inexistentes |
| Falha de submissão | Validar | Form API conserva dados e liga erros aos campos |
| Sem JavaScript | Continuar | Formulário e navegação HTTP normais, sem dependência do wizard JS |
| Mobile 320/390 + teclado virtual | Editar | Uma coluna, campos legíveis, ações não encobertas |
| Light/Dark + zoom 200/400% | Ler e editar | Mesma geometria; sem overflow horizontal e com foco visível |

## Checklist para futura implementação

- [ ] Mapear permissões exportadas, node access, text formats e Content Moderation de `wiki_entry`.
- [ ] Identificar render array e posição do link `Contribuir` no template/SDC sem alterar a shell.
- [ ] Escolher rota canônica e tipo de proposta baseado no backend, não apenas na UX.
- [ ] Verificar que todos os links têm acesso cacheável e retorno validado.
- [ ] Confirmar título H1 único e retorno à página de origem com foco correto.
- [ ] Construir story SDC determinística, validar schema e UI Patterns Library.
- [ ] Testar CKEditor 5 e Media Library somente se o formato/permissão real os habilitar.
- [ ] Documentar resultados do homelab e manter Autosave Form fora do escopo.

Nenhum checkbox deve ser marcado apenas com esta especificação. O ciclo segue Dev → RC → Alpha.
