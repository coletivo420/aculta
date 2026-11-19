# Studio — Conta, Perfil e frontend (regra de agente)

Leia só quando a tarefa tocar Conta, Meu Perfil ou a apresentação do Studio. Regras gerais: `../AGENTS.md`.

## Conta e Perfil

- Não reintroduza a experiência `pessoal`. Use somente `/conta` e `/meu-perfil`, separadas.
- O legado da Conta fica em `/conta-legada` de forma permanente. Não restabeleça o legado na raiz nem crie alias temporário. Não retire `AccountShellBuilder`. Não misture Form API cadastral com editor público nem duplique User/Profile.
- Portal é dono de cadastro, endereço, documentos e segurança. User é dono da identidade pública.
- CPF/CNPJ não é duplicado nem aparece em Meu Perfil. O link de documentos só aparece se `aculta_id_cpf.account_document` estiver disponível.
- Perfil público: o editor de Meu Perfil é privado e separado da página pública.

## Frontend e acessibilidade

- O Studio tem página, shell, JavaScript (`js/studio-navigation.js`) e hooks próprios. Não usa o shell, o menu ou o `account-navigation.js` da Minha Conta, e não aparece no menu da conta.
- Apresentação: tokens `data-portal-ui` (`studio-*`) e atributos `data-aculta-studio*` de comportamento. Classes e aparência ficam no ACULTA420.
- Troca de nível move o foco para o título, usa região `aria-live` para carregamento e erros, mantém voltar e avançar do navegador e funciona com links sem JavaScript.
- Ações de edição, aprovação, exclusão e publicação mantêm verificação de acesso, CSRF e confirmação do Drupal.

