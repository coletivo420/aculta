# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## Reconstrução do ACULTA Forum

- O roadmap em `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md` é a sequência canônica de fases/versionamento; decisões anteriores incompatíveis ficam históricas em `web/modules/custom/aculta_portal/modules/aculta_forum/docs/DECISOES.md`.
- Drupal Forum contrib continua fonte de verdade no código atual até a fase explícita de migração/retirada. Não remover Composer, módulo, dados ou configuração antes de cumprir `web/modules/custom/aculta_portal/modules/aculta_forum/docs/MIGRATION-REMOVAL.md` e gates de acesso/rollback.
- Growroom420 recebe apenas tópicos públicos independentes. Publicações, LMS e Wiki associam comentários ao contexto original; publicações e verbetes não geram tópicos públicos automáticos.
- `aculta_user` é o único dono de view-models/cartões de identidade e autoria. `aculta_forum` não cria builders/renderers de perfil nem consulta diretamente dados privados de User/Profile. ACULTA420 detém toda a apresentação.
- LMS exige membership e acesso atual pelo Group/Entity Access; role global de estudante não basta. A Wiki não é parte do Fórum e não deve ser reimplementada dentro dele (ver seção Wiki).
- Imagens e avisos continuam sob `aculta_image` e `aculta_messenger`; não criar canais paralelos.
