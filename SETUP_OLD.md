# 🎮 HexRealm - RPG Game
## Przewodnik Instalacji i Konfiguracji

Kompletna instrukcja uruchomienia gry od podstaw krok po kroku.

---

## 📋 Wymagania Systemowe

### Oprogramowanie
- **XAMPP** (Apache + MySQL + PHP 7.4 lub wyższy)
  - Pobierz: https://www.apachefriends.org/
- **Przeglądarka** (Chrome, Firefox, Edge)
- **Edytor kodu** (opcjonalnie - VS Code, Sublime Text)

### Struktura projektu
- Katalog: `C:\xamp\htdocs\rpg\`
- Wszystkie pliki projektu powinny znajdować się w tym folderze

---

## 🚀 Instalacja Krok Po Kroku

### KROK 1: Przygotowanie środowiska

1. **Zainstaluj XAMPP:**
   - Uruchom instalator XAMPP
   - Wybierz komponenty: Apache, MySQL, PHP
   - Zainstaluj w domyślnej lokalizacji (C:\xampp)

2. **Skopiuj pliki projektu:**
   - Umieść wszystkie pliki gry w folderze: `C:\xamp\htdocs\rpg\`
   - Upewnij się, że struktura katalogów jest zachowana:
     ```
     C:\xamp\htdocs\rpg\
     ├── index.php
     ├── api.php
     ├── game.js
     ├── db.php
     ├── setup_game.php
     ├── setup_map.php
     ├── rpg_game(5).sql
     ├── assets/
     └── img/
     ```

3. **Uruchom XAMPP:**
   - Otwórz XAMPP Control Panel
   - Kliknij **Start** przy Apache
   - Kliknij **Start** przy MySQL
   - Poczekaj aż oba serwisy pokażą status "Running" (zielony)

   **⚠️ Problemy przy starcie?**
   - Apache nie startuje: Port 80 zajęty → Wyłącz IIS lub Skype
   - MySQL nie startuje: Port 3306 zajęty → Wyłącz inne instancje MySQL

---

### KROK 2: Konfiguracja bazy danych

1. **Otwórz phpMyAdmin:**
   - Wejdź na adres: `http://localhost/phpmyadmin`
   - Zaloguj się (domyślnie: użytkownik `root`, bez hasła)

2. **Utwórz nową bazę danych:**
   - Kliknij zakładkę **"Bazy danych"** (Databases)
   - W polu "Utwórz bazę danych" wpisz: `rpg_game`
   - Wybierz kodowanie: `utf8mb4_unicode_ci`
   - Kliknij **"Utwórz"** (Create)

3. **Zaimportuj strukturę i dane:**
   - Kliknij na utworzoną bazę `rpg_game` (w lewym menu)
   - Przejdź do zakładki **"Import"**
   - Kliknij **"Wybierz plik"** (Choose File)
   - Znajdź i wybierz plik: `rpg_game(5).sql`
   - Kliknij **"Wykonaj"** (Go) na dole strony
   - Poczekaj na komunikat o sukcesie

4. **Sprawdź poprawność importu:**
   - W lewym menu pod `rpg_game` powinieneś zobaczyć tabele:
     - `users`
     - `characters`
     - `world_maps`
     - `map_tiles`
     - `items`
     - `quests`
     - i inne...

---

### KROK 3: Konfiguracja połączenia

1. **Sprawdź ustawienia połączenia:**
   - Otwórz plik `db.php` w edytorze
   - Upewnij się, że parametry są poprawne:
   ```php
   $host = 'localhost';      // Host MySQL
   $db   = 'rpg_game';        // Nazwa bazy danych
   $user = 'root';            // Użytkownik (domyślnie root)
   $pass = '';                // Hasło (domyślnie puste w XAMPP)
   ```

2. **Jeśli zmieniłeś hasło MySQL:**
   - Edytuj zmienną `$pass` i wpisz swoje hasło

---

### KROK 4: Inicjalizacja gry

1. **Uruchom skrypt setup_game.php:**
   - Otwórz przeglądarkę
   - Wejdź na: `http://localhost/rpg/setup_game.php`
   - Skrypt automatycznie:
     - Utworzy/zaktualizuje tabele bazy danych
     - Wygeneruje klasy postaci (Warrior, Mage, Rogue)
     - Doda questy, przedmioty, umiejętności
     - Przygotuje wszystkie dane startowe
   - Poczekaj na komunikat sukcesu (może potrwać 10-30 sekund)

2. **Potwierdź wykonanie:**
   - Powinieneś zobaczyć komunikat: "Setup completed successfully" lub podobny
   - Jeśli widzisz błędy, sprawdź:
     - Czy baza danych `rpg_game` istnieje
     - Czy dane w `db.php` są poprawne
     - Logi błędów w XAMPP (Apache → Logs → error.log)

---

### KROK 5: Generowanie mapy świata

1. **Otwórz generator mapy:**
   - Wejdź na: `http://localhost/rpg/setup_map.php`

2. **Ustaw parametry świata:**
   - **Szerokość (Width):** 50-100 kafelków (zalecane: 80)
   - **Wysokość (Height):** 50-100 kafelków (zalecane: 80)
   - Im większa mapa, tym dłużej trwa generowanie

3. **Wygeneruj świat:**
   - Kliknij **"Generate World"**
   - Proces może trwać od kilku sekund do kilku minut (zależy od rozmiaru)
   - Nie zamykaj przeglądarki podczas generowania!
   - Poczekaj na komunikat o zakończeniu

4. **Weryfikacja:**
   - Sprawdź w phpMyAdmin tabelę `map_tiles`
   - Powinna zawierać rekordy (width × height wpisów)

**⚠️ Uwaga:** Ponowne uruchomienie `setup_map.php` nadpisze istniejącą mapę!

---

## 🎯 Uruchomienie gry

### Dostęp do gry

1. **Otwórz grę w przeglądarce:**
   - Adres: `http://localhost/rpg/`
   - lub: `http://localhost/rpg/index.php`

2. **Pierwsze logowanie:**
   - Kliknij **"Nowe konto"** (Register)
   - Podaj login i hasło
   - Zaakceptuj regulamin
   - Kliknij **"Zarejestruj"**

3. **Tworzenie postaci:**
   - Wybierz klasę:
     - **Warrior** - wysoka obrona, bliski zasięg
     - **Mage** - potężna magia, niskie HP
     - **Rogue** - szybki, wysokie obrażenia krytyczne
   - Wpisz nazwę postaci
   - Kliknij **"Create Character"**

4. **Zacznij grać:**
   - Postać pojawi się na mapie w losowym mieście
   - Użyj myszy do poruszania się (klikaj na kafelki)
   - Eksploruj świat, walcz z potworami, zdobywaj przedmioty!

---

## 📁 Struktura plików

```
rpg/
│
├── index.php                # Główny plik gry (frontend)
├── api.php                  # Backend API (obsługa requestów)
├── game.js                  # Logika gry JavaScript
├── db.php                   # Konfiguracja połączenia z bazą
│
├── setup_game.php           # Skrypt inicjalizacji bazy i danych
├── setup_map.php            # Generator mapy świata
│
├── rpg_game(5).sql          # Dump bazy danych (struktura + dane)
│
├── assets/                  # Zasoby graficzne
│   ├── combat/              # Grafiki walki
│   ├── enemies/             # Grafiki przeciwników
│   ├── player/              # Grafiki gracza
│   ├── ui/                  # Elementy interfejsu
│   └── walking/             # Animacje chodzenia
│
├── img/                     # Dodatkowe obrazy
│   └── winter/              # Grafiki zimowe
│
└── SETUP.md                 # Ten plik (instrukcja)
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
- Sprawdz czy wszystkie tabele istnieją:
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
- Uruchom `setup_map.php` i wygeneruj świat
- Sprawdź czy tabela `map_tiles` ma dane

**P: Chcę zresetować wszystko**
O:
1. phpMyAdmin → `rpg_game` → "Drop" (usuń bazę)
2. Utwórz nową poprzez import `rpg_fresh_database.sql`
3. Uruchom `setup_game.php` → `setup_map.php`
4. Gotowe! ✅

---

**Opracowano:** 19.02.2026  
**Wersja:** 1.1
