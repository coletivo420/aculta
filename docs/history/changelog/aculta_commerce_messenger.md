# Changelog

## 0.1.0-dev.1

- Added the Store order placement producer and shared Messenger consumer.
- Persisted mandatory site notices in Message and added access revalidation,
  unread aggregation, daily digest and preference-controlled direct e-mail.
- Kept Commerce guest receipts and failure fallback; suppressed duplicate native
  receipts only after a registered customer's event is accepted.
