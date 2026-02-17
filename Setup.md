# RPG Game Setup Guide

Instrukcja uruchomienia gry od podstaw krok po kroku.

## 1. Przygotowanie lokalnego środowiska

### Wymagania
- XAMPP (Apache + MySQL + PHP 7.4+)
- Folder projektu: `C:\xamp\htdocs\rpg\`

### Konfiguracja bazy danych

#### Opcja A: Świeża instalacja (pierwsza konfiguracja)

1. **Otwórz phpMyAdmin** - `http://localhost/phpmyadmin`
2. **Utwórz nową bazę danych:**
   - Klikni "Nowa baza danych"
   - Nazwa: `rpg_game`
   - Kolacja: `utf8mb4_unicode_ci`
   - Kliknij "Utwórz"

3. **Załaduj schemat bazy:**
   - Idź do tabeli `rpg_game` → "Import"
   - Wybierz plik: `rpg_fresh_database.sql`
   - Kliknij "Go"

4. **Uruchom inicjalizację gry:**
   - Otwórz w przeglądarce: `http://localhost/rpg/setup_game.php`
   - Czekaj na komunikat o powodzeniu
   - Gra została wstępnie skonfigurowana ze wszystkimi klasami, questami, itemami, etc.

#### Opcja B: Migraracja istniejącej bazy (aktualizacja)

Jeśli baza już istnieje i chcesz dodać nowe funkcje:

1. **Zatrzymaj grę** - poinformuj graczy o maintenance (opcjonalnie)
2. **Załaduj migrację do bazy:**
   - phpMyAdmin → `rpg_game` → "Import"
   - Plik: `add_daily_quest_column.sql`
   - Kliknij "Go"
3. **Sprawdź w phpMyAdmin:**
   - Tabela `daily_challenges` powinna istnieć
4. **Restart aplikacji** - odśwież przeglądarkę

---

## 2. Konfiguracja połączenia z bazą

W pliku [db.php](db.php) zmień ustawienia jeśli potrzeba:

```php
$host = 'localhost';      // Host MySQL
$db   = 'rpg_game';        // Nazwa bazy
$user = 'root';            // Użytkownik MySQL
$pass = '';                // Hasło MySQL (domyślnie puste w XAMPP)
```

---

## 3. Generator mapy świata

**Pierwsza konfiguracja świata:**

1. Otwórz w przeglądarce: `http://localhost/rpg/setup_map.php`
2. Ustaw parametry świata:
   - **Szerokość mapy** (width): np. 50-100 kafelków
   - **Wysokość mapy** (height): np. 50-100 kafelków
   - Opcja **regeneracji** - jeśli chcesz zmienić układ terenu
3. Kliknij "Generate World"
4. Czekaj na generację (może trwać chwilę)
5. Mapa zostanie zapisana w bazie, a gracze mogą już ją eksplorować

**Zmiana mapy później:**
- Ponownie otwórz setup_map.php
- Zmień parametry i kliknij "Regenerate"
- Uwaga: Istniejące kafelki zostają nadpisane, ale gracze nie tracą postaci

---

## 4. Uruchomienie gry

### Start serwera XAMPP

W kontrolpanelu XAMPP:
- Kliknij **Start** obok "Apache"
- Kliknij **Start** obok "MySQL"
- Czekaj na zielone "Running"

### Dostęp do gry

**Główna gra:**
- `http://localhost/rpg/index.php`
- lub `http://localhost/rpg/`

### Pierwsze kroki w grze

1. **Rejestracja konta:**
   - Kliknij "Nowe konto"
   - Wprowadź login i hasło
   - Zaakceptuj T&C

2. **Stworzenie postaci:**
   - Wybierz klasę (Warrior, Mage, Rogue)
   - Nazwa postaci
   - Kliknij "Create Character"

3. **Tutorial mapy:**
   - Gra zaczyna się od losowego miasta
   - Możesz się poruszać, zbierać itemy, atakować wrogów
   - Po zakończeniu tutoriala - pełny dostęp do funkcji

---

## 5. Struktura plików

```
rpg/
├── index.php                    # Główna aplikacja
├── api.php                      # API backend (POST requests)
├── game.js                      # Logika gry (JavaScript)
├── db.php                       # Konfiguracja bazy danych
├──
├── setup_game.php               # Inicjacja tabeli i danych
├── setup_map.php                # Generator świata (powinny być widoczne tylko dla admina)
│
├── rpg_fresh_database.sql       # Dump bazy (schemat + dane)
├── add_daily_quest_column.sql   # Migracja (dodanie daily challenges)
│
├── assets/                      # Grafiki (combat, enemies, player, ui, walking)
├── img/                         # Obrazy interfejsu
└── SETUP.md                     # Ten plik
```

---

## 6. Aktualizacje i migracje

### Procedura bezpiecznej aktualizacji:

```
1. Backup bazy (Export w phpMyAdmin)
2. Załaduj SQL migracji (add_daily_quest_column.sql)
3. Sprawdź logi błędów (jeśli są)
4. Restart serwera (Stop/Start w XAMPP)
5. Testuj funkcjonalność w grze
```

### Dodawanie nowych migracji:

Utwórz nowy plik SQL (np. `add_guilds_table.sql`):

```sql
-- Migration: Add new feature
ALTER TABLE characters ADD COLUMN new_column INT DEFAULT 0;
UPDATE characters SET new_column = 1;
```

Załaduj jak opisano wyżej.

---

## 7. Debugowanie

### Sprawdzenie błędów bazy:

- Otwórz **phpMyAdmin** → `rpg_game`
- Sprawdzź czy wszystkie tabele istnieją:
  - `users`
  - `characters`
  - `world_maps`
  - `map_tiles`
  - `daily_challenges` (jeśli migrowano)

### Sprawdzenie logów PHP:

- W XAMPP kliknij "Apache" → "Logs" → "error.log"
- Szukaj błędów dotyczących bazy lub API

### Sprawdzenie konsoli przeglądarki:

- F12 (Dev Tools)
- Zakładka "Console" - błędy JavaScript
- Zakładka "Network" - błędy API (setup_game.php, api.php)

---

## 8. FAQ

**P: Gra nie ładuje się**
O: 
- Sprawdź czy Apache i MySQL są włączone
- Sprawdź console przeglądarki (F12)
- Czy db.php ma prawidłowe dane dostępu?

**P: Nie mogę zalogować się**
O:
- Czy baza ma tabelę `users`?
- Czy `setup_game.php` został uruchomiony?
- Wyczyść cookies przeglądarki

**P: Mapa nie wyświetla się**
O:
- Uruchomi `setup_map.php` i wygeneruj świat
- Sprawdź czy tabela `map_tiles` ma dane

**P: Chcę zresetować wszystko**
O:
1. phpMyAdmin → `rpg_game` → "Drop" (usuń bazę)
2. Utwórz nową poprzez import `rpg_fresh_database.sql`
3. Uruchom `setup_game.php` → `setup_map.php`
4. Gotowe! ✅

---

**Opracowano:** 17.02.2026  
**Wersja:** 1.0
