# ACULTA Maps

Submódulo compartilhado para mapas e geocodificação. Consumidores devem usar
os serviços e render arrays públicos de `aculta_maps`; não devem integrar
diretamente com Google Maps, OpenStreetMap ou Nominatim.

O contrato será implementado nas microfases MP1–MP6. As chaves externas ficam
no `aculta_secrets` e nunca são armazenadas na configuração exportável. A
documentação de arquitetura e o estado por microfase estão em `docs/`.
