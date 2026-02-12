# WP Simple Stock Checkout

**WP Simple Stock Checkout** és un plugin de WordPress per gestionar
**estoc limitat amb reserves temporals** i **pagament extern** (TPV,
Stripe, Redsys, Bizum, etc.) **sense WooCommerce**.

Pensat per a **AFAs, escoles, associacions i entitats** que necessiten
vendre productes simples amb control d'estoc i una operativa clara.

------------------------------------------------------------------------

## ✨ Funcionalitats principals

-   Gestió de **variants amb SKU**
-   Control d'estoc:
    -   `stock_total`
    -   `stock_reserved`
    -   `stock_sold`
-   **Reserves temporals atòmiques** (evita sobreventa)
-   **Expiració automàtica** de reserves (WP-Cron cada 5 minuts)
-   Redirecció a **passarel·la de pagament externa**
-   **Conciliació de pagaments via CSV**
-   **Vendes manuals** (parades físiques) amb log d'estoc
-   Activació / desactivació de variants
-   Internacionalització (ca / es / en)

------------------------------------------------------------------------

## 🚫 Què NO fa aquest plugin

Aquest plugin **no és un e-commerce complet**. Intencionadament:

-   ❌ No té carret
-   ❌ No gestiona enviaments
-   ❌ No calcula impostos
-   ❌ No processa pagaments directament
-   ❌ No substitueix WooCommerce

Si necessites un e-commerce complet → WooCommerce.\
Si vols una solució lleugera i controlada → aquest plugin.

------------------------------------------------------------------------

## 🧠 Model d'estoc

Per cada variant:

Disponible = stock_total - stock_sold - stock_reserved

------------------------------------------------------------------------

## ⚙️ Requisits

-   WordPress 6.7+
-   PHP 8.0 -- 8.4
-   MySQL / MariaDB

------------------------------------------------------------------------

## 📦 Instal·lació

1.  Copia la carpeta `wp-simple-stock-checkout` a `wp-content/plugins/`
2.  Activa el plugin des del panell d'administració
3.  Configura les opcions a `Stock Checkout → Settings`

El plugin utilitza un autoloader PSR-4 intern i no requereix Composer en
producció.

------------------------------------------------------------------------

## 🌐 Frontend -- Pàgines necessàries

### Pàgina de reserva

\[wpssc_reserve success_page="/reserve/"\]

### Pàgina de finalització

\[wpssc_reservation\]

------------------------------------------------------------------------

## 📦 Importació de variants (CSV)

Header esperat:

sku,model,color,size,price,stock_total,is_active

------------------------------------------------------------------------

## 💳 Conciliació de pagaments (CSV)

Ruta: Stock Checkout → Payment reconciliation

Plantilla recomanada:

paid_at;amount;currency;token;reference;payer_email;notes

------------------------------------------------------------------------

## 📁 Carpeta examples/

Inclou: - variants-sample.csv - payments-sample.csv

------------------------------------------------------------------------

## 🌍 Traduccions

Inclou: - Català (ca) - Castellà (es) - Anglès (en)

Ubicació: languages/

Textdomain: wp-simple-stock-checkout

------------------------------------------------------------------------

## 🤝 Contribucions

Les contribucions són benvingudes:
- issues
- pull requests
- millores de documentació

------------------------------------------------------------------------

## 🔐 Seguretat

-   Capability checks
-   Nonces en accions admin
-   Tokens UUID validats
-   Transaccions SQL per reserves i pagaments

------------------------------------------------------------------------

## 📄 Llicència

Vegeu el fitxer [LICENSE](LICENSE) per a més informació.

------------------------------------------------------------------------

## 📄 Changelog

Vegeu el fitxer [CHANGELOG.md](CHANGELOG.md) per l’historial de versions.
