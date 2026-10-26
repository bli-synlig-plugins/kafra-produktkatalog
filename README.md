# Kafra Produktkatalog

Lett AJAX-basert filtermeny (widget) for WooCommerce sin vanlige produktkatalog — kategorier, attributter, søk og tilpassede produktkort.

**Versjon:** 1.4.1  
**Forfatter:** Benjamin Pedersen

## Krav

- WordPress
- [WooCommerce](https://woocommerce.com/)

## Installasjon

1. Last ned release-zip fra [Releases](https://github.com/bli-synlig-plugins/kafra-produktkatalog/releases), eller klon dette repoet.
2. Legg mappen `kafra-produktkatalog` i `wp-content/plugins/`.
3. Aktiver **Kafra Produktkatalog** under **Plugins**.

## Oppsett

1. **WooCommerce → Produktfilter**: velg hvilke taksonomier som skal vises i filteret og på produktkort, AJAX, søkefelt, og regler for attributtvisning.
2. **Utseende → Widgeter**: legg **Kafra produktfilter** i f.eks. **Shop Sidebar** (eller annen sidebar som brukes på butikk/kategorisider).

Filteret oppdaterer produktlisten uten full sideinnlasting når AJAX er aktivert. Egendefinert arkivmal kan brukes for produktkort (`templates/archive-product.php`).

## Lisens

Ingen lisens er angitt i repoet ennå.
