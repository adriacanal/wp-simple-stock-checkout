# Changelog

Totes les modificacions rellevants d’aquest projecte es documenten aquí.

---

## [1.0.1] - 2026-02-12

### Documentation
- Add CHANGELOG.md

## [1.0.0] - 2026-02-12

### Added
- Sistema complet de reserves amb TTL configurable
- Expiració automàtica via WP-Cron
- Conciliació de pagaments via import CSV
- Moviments manuals d’estoc amb log
- Activació/desactivació de variants des de l’admin
- Internacionalització (ca, es, en)
- Compatibilitat PHP 8.4

### Security
- Validació de nonces en accions admin
- Capability checks a totes les operacions sensibles
- Tokens UUID validats
- Transaccions SQL per reserves, expiracions i pagaments

### Fixed
- Avís de càrrega anticipada de traduccions (WP 6.7+)
- Compatibilitat `fgetcsv()` amb PHP 8.4

---

[1.0.1]: https://github.com/adriacanal/wp-simple-stock-checkout/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/adriacanal/wp-simple-stock-checkout/releases/tag/v1.0.0
