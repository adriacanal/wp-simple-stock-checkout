# WP Simple Stock Checkout

**WP Simple Stock Checkout** és un plugin de WordPress per gestionar **estoc limitat amb reserves temporals** i **pagament extern** (TPV, Stripe, Redsys, etc.) sense utilitzar WooCommerce.

Està pensat per a entitats, escoles, AFAs/AMPAs, associacions o esdeveniments que necessiten vendre productes simples amb control d’estoc i una operativa clara.

---

## ✨ Funcionalitats principals

- Gestió d’estoc limitat per variants (SKU)
- Reserves temporals per evitar sobreventa
- Redirecció a passarel·les de pagament externes
- Registre de vendes manuals (parades, caixa física)
- Expiració automàtica de reserves (cron)
- Importació i exportació d’estoc via CSV
- Totalment independent de WooCommerce

---

## 🚫 Què **NO** fa aquest plugin

Aquest plugin **no és** un e-commerce complet. Intencionadament:
- ❌ No té carret
- ❌ No gestiona enviaments
- ❌ No calcula impostos
- ❌ No processa pagaments directament

Si necessites això, WooCommerce és la millor opció.
Si no, aquest plugin és més lleuger, simple i fàcil de mantenir.

---

## 🧠 Arquitectura (resum)

- **WordPress (DB)**
  → estoc, reserves, comandes, moviments manuals

- **Passarel·la externa**
  → cobrament (TPVEscola, Stripe Checkout, Redsys, etc.)

- **Flux típic**
  1. L’usuari selecciona variant i quantitat
  2. El plugin reserva estoc i genera un codi de comanda
  3. Redirecció a pagament extern
  4. Conciliació posterior (manual o via import CSV)

---

## 🧾 Casos d’ús habituals

- Venda de samarretes o marxandatge d’una AFA
- Entrades per esdeveniments petits
- Productes solidaris
- Venda amb parades físiques + web
- Campanyes puntuals amb estoc limitat

---

## ⚙️ Requisits

- WordPress 6.0 o superior
- PHP 8.0 o superior
- MySQL / MariaDB (estàndard WordPress)

---

## 📦 Instal·lació

1. Copia la carpeta `wp-simple-stock-checkout` a:
```wp-content/plugins/```
2. Activa el plugin des del panell d’administració
3. Configura les opcions a:
```WP Simple Stock Checkout → Configuració```

---

## 📦 Importació de variants (CSV)

Ves a **Stock Checkout → Import Variants** i puja un arxiu CSV
amb el següent header:

sku,model,color,size,price,stock_total,is_active

Exemple d'arxiu: `examples/variants-sample.csv`

---

## 🛠 Estat del projecte

Aquest plugin està en desenvolupament actiu.

Roadmap aproximat:
- v0.1 — Core + reserves + redirecció
- v0.2 — Gestió de variants (admin)
- v0.3 — Venda manual i moviments d’estoc
- v0.4 — Import/export CSV
- v1.0 — Versió estable per producció

---

## 🤝 Contribucions

Les contribucions són benvingudes:
- issues
- pull requests
- millores de documentació

---

## 📄 Llicència

GPL v2 o posterior.
Vegeu el fitxer [LICENSE](LICENSE) per a més informació.
