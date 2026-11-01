# ADR-003: sessão compartilhada

Status: Accepted

Os subdomínios compartilham a sessão Drupal para SSO. Login/logout continuam no Core. Cookie deve ser Secure, HttpOnly e SameSite=Lax. Todos os hosts que o recebem fazem parte do mesmo trust boundary.
