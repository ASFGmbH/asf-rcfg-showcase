# ASF RCFG Showcase

WordPress-Plugin zur Verwaltung von Showcase-Artikeln, Blaupausen und Bestandsartikeln für den 3D-Trauringkonfigurator.

## Ziel

Dieses Plugin verbindet sichtbare WooCommerce-Produkte mit Presets des 3D-Trauringkonfigurators.

Geplanter Ablauf:

1. Ein WooCommerce-Produkt dient als Showcase-/Bestandsartikel.
2. Am Produkt wird eine Preset-ID aus dem 3D-Konfigurator hinterlegt.
3. Auf der Produktdetailseite werden Eigenschaften und ein Button „Weiter konfigurieren“ angezeigt.
4. Beim Klick wird aus der Preset-ID eine neue Arbeitskopie erzeugt.
5. Der Nutzer wird mit dieser neuen Konfigurations-ID in den 3D-Konfigurator weitergeleitet.

## Abhängigkeiten

- WordPress
- WooCommerce
- 3D-Trauringkonfigurator
- RingPreisrechner Plugin v2

## Aktueller Funktionsstand

- WooCommerce-Produkte können als RCFG Showcase-Produkte markiert werden.
- Pro Produkt kann eine RCFG Template-ID hinterlegt werden.
- Auf aktivierten Produktseiten erscheint der Button „Weiter konfigurieren“.
- Beim Klick wird eine neue Arbeitskopie des hinterlegten Presets erzeugt.
- Die Weiterleitung erfolgt auf `/ringkonfiguration/?id=NEUE-ID`.