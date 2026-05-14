# ANOVEX Label System v2 — Nasazení (verze 3.0)

## ⚠️ DŮLEŽITÉ: Před nasazením vymaž databázi

Setup.php používá `DROP TABLE IF EXISTS` takže staré tabulky smaže automaticky.
**Ale jistota je jistota** — pokud chceš čistý start, smaž tabulky ručně.

### Postup:

### 1. Vymazání databáze (přes phpMyAdmin)

1. Otevři https://dbadmin.r4.active24.cz
2. Přihlas se (uživatel `vlada73`, heslo databáze)
3. Klikni na databázi `anovex_labels` v levém menu
4. Klikni na záložku **"SQL"**
5. Vlož a spusť:

```sql
DROP TABLE IF EXISTS `translations`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `stacks`;
DROP TABLE IF EXISTS `size_presets`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;
```

(Setup tyhle příkazy spustí sám, ale tady to máš pro jistotu jako manuální kontrolu.)

### 2. Nahrání souborů na hosting

Nahraj **celý obsah** zip souboru do `/sub/labels/` (nebo kde běží `labels.anovex.eu`).

**POZOR:** Nepřepisuj soubor `config/db.php` pokud v něm máš heslo k databázi!
Zkontroluj že obsahuje:
```php
define('DB_PASS', 'tvoje_heslo');
```

### 3. Spuštění setup.php

1. Otevři https://labels.anovex.eu/setup.php
2. Vyplň jméno, email, heslo (min 8 znaků) — **toto bude tvůj admin účet**
3. Klikni "Vytvořit admina a spustit setup"
4. Setup vytvoří tabulky + 7 výchozích stacků (LONGEVITY BASE, MEN'S PRIME, WOMEN'S PRIME, DEEP SLEEP, CORE VITALITY, SHARP MIND, SELECT)

### 4. Smazání setup.php

Po úspěšném setupu **smaž** soubor `setup.php` z hostingu (bezpečnost).

### 5. Přihlášení

Otevři https://labels.anovex.eu — přesměruje tě na login. Přihlas se.

### 6. Vložení produktů

Po přihlášení vidíš sidebar:
1. **Načíst z PDF etikety (C2B)** — vložíš API klíč Anthropic, přetáhneš PDF, formulář se vyplní
2. **Import SKU z C2B Excelu** — nahraje xlsx, spáruje SKU podle EAN
3. **Formulace / Řada** — vyber stack
4. **Produkty ve stacku** — klikni "+" pro nový produkt, pak nahraj PDF parserem

### Co bylo změněno proti původní verzi:

- ✅ Renderer přepsán 1:1 podle původního funkčního HTML
- ✅ Strom života = SVG logo soubor (8 barevných variant podle stacku)
- ✅ Základní zákonné věty se přidávají automaticky (UPOZORNĚNÍ + UCHOVÁVÁNÍ) — žádné zdvojování
- ✅ PDF parser přes Anthropic API (do polí jen specifické věty navíc, základní vynechá)
- ✅ Sidebar pořadí: PDF parser → SKU import → Formulace/Řada → ...
- ✅ Hodnoty `size_presets` přesně podle původního HTML

### Stacky a barvy log:

| Stack | Série | Logo barva |
|---|---|---|
| LONGEVITY BASE | premium | gold (`#c9a84c`) |
| MEN'S PRIME | premium | red (`#c13a3a`) |
| WOMEN'S PRIME | premium | orange (růžová `#c4547a`) |
| DEEP SLEEP | formula | blue (`#4a7fc1`) |
| CORE VITALITY | formula | orange (`#e8832a`) |
| SHARP MIND | formula | purple (`#8b5fc1`) |
| SELECT | select | select (`#e0c97a`) |

Pokud potřebuješ jinou barvu loga pro stack, nastavíš v editaci stacku (logo_color).
