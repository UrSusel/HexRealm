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
├── SETUP.md                 # Ten plik (instrukcja)
│
├── assets/                  # Zasoby graficzne
│   ├── combat/              # Grafiki walki
│   ├── enemies/             # Grafiki przeciwników
│   ├── player/              # Grafiki gracza
│   ├── ui/                  # Elementy interfejsu
│   └── walking/             # Animacje chodzenia
│
└── img/                     # Dodatkowe obrazy
    └── winter/              # Grafiki zimowe
```

---

## 🔧 Administracja i Zarządzanie

### Backup bazy danych

**Zalecane: Regularne kopie zapasowe!**

1. **Eksport bazy:**
   - phpMyAdmin → `rpg_game` → zakładka "Export"
   - Metoda: "Szybka" (Quick) lub "Niestandardowa" (Custom)
   - Format: SQL
   - Kliknij "Wykonaj" (Go)
   - Zapisz plik z datą, np: `rpg_backup_2026-02-19.sql`

2. **Przywracanie z backupu:**
   - phpMyAdmin → `rpg_game` → zakładka "Import"
   - Wybierz plik backupu
   - Kliknij "Wykonaj"

### Regeneracja mapy

**⚠️ UWAGA: To nadpisze istniejącą mapę!**

1. Wykonaj backup bazy (patrz wyżej)
2. Wejdź na: `http://localhost/rpg/setup_map.php`
3. Ustaw nowe parametry (width, height)
4. Kliknij "Regenerate World"
5. Gracze nie stracą postaci, ale pozycje na mapie mogą wymagać korekty

### Zarządzanie graczami

- Tabela `users` - konta użytkowników
- Tabela `characters` - postacie graczy
- Edycja przez phpMyAdmin (ostrożnie!)

---

## 🐛 Rozwiązywanie problemów

### Gra nie ładuje się / biały ekran

**Możliwe przyczyny:**
1. Apache lub MySQL nie działają
   - *Rozwiązanie:* Uruchom oba w XAMPP Control Panel

2. Błędne połączenie z bazą
   - *Rozwiązanie:* Sprawdź parametry w `db.php`

3. Błędy PHP
   - *Rozwiązanie:* Sprawdź logi: XAMPP → Apache → Logs → error.log

4. Błędy JavaScript
   - *Rozwiązanie:* Otwórz konsolę przeglądarki (F12)

### Nie mogę się zalogować

**Sprawdź:**
1. Czy baza `rpg_game` istnieje i ma tabelę `users`
2. Czy hasło w `db.php` jest poprawne
3. Wyczyść cookies i cache przeglądarki (Ctrl+Shift+Del)
4. Spróbuj zarejestrować nowe konto

### Mapa się nie wyświetla

**Sprawdź:**
1. Czy uruchomiłeś `setup_map.php`
2. Czy tabela `map_tiles` ma dane (phpMyAdmin)
3. Konsola przeglądarki - sprawdź błędy API (F12, Network)

### Potwory nie pojawiają się

**Sprawdź:**
1. Czy `setup_game.php` został uruchomiony
2. Tabela `enemies` powinna mieć dane
3. Sprawdź konsolę przeglądarki pod kątem błędów

### Błąd "Port already in use" (port zajęty)

**Apache (port 80):**
- Wyłącz IIS (Internet Information Services)
- Wyłącz Skype (używa portu 80/443)
- Zmień port w XAMPP: Config → httpd.conf → szukaj "Listen 80"

**MySQL (port 3306):**
- Wyłącz inne instancje MySQL
- Sprawdź usługi Windows (services.msc)

### Wolna gra / Slow performance

**Optymalizacja:**
1. Zmniejsz rozmiar mapy (50x50 zamiast 100x100)
2. Wyczyść cache przeglądarki
3. Zwiększ limity PHP w `php.ini`:
   ```ini
   memory_limit = 256M
   max_execution_time = 60
   ```
4. Zrestartuj Apache po zmianach w php.ini

---

## ✅ Checklist instalacji

Użyj tej listy, aby upewnić się, że wszystko jest skonfigurowane:

- [ ] XAMPP zainstalowany
- [ ] Apache uruchomiony (zielony w Control Panel)
- [ ] MySQL uruchomiony (zielony w Control Panel)
- [ ] Baza `rpg_game` utworzona w phpMyAdmin
- [ ] Plik `rpg_game(5).sql` zaimportowany
- [ ] Parametry w `db.php` poprawne
- [ ] `setup_game.php` uruchomiony (komunikat sukcesu)
- [ ] `setup_map.php` uruchomiony (mapa wygenerowana)
- [ ] Gra dostępna pod `http://localhost/rpg/`
- [ ] Możliwość rejestracji i logowania
- [ ] Możliwość stworzenia postaci
- [ ] Mapa świata się wyświetla
- [ ] Postać może się poruszać
- [ ] Funkcjonalność walki działa

---

## 📚 Dodatkowe informacje

### Technologie użyte w projekcie:
- **Backend:** PHP 7.4+, MySQL
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Graphics:** Canvas API (hex mapy)
- **Architecture:** REST API (api.php), Session-based auth

### Klasy postaci:

1. **Warrior (Wojownik)**
   - Wysoka obrona i HP
   - Bliski zasięg ataku
   - Idealny dla początkujących

2. **Mage (Mag)**
   - Potężne zaklęcia
   - Niskie HP, wysoki dmg
   - Gra na dystans

3. **Rogue (Łotrzyk)**
   - Szybki ruch
   - Wysokie obrażenia krytyczne
   - Wymaga strategii

### System questów:
- Questy fabulne (story quests)
- Zadania poboczne (side quests)
- Dzienne wyzwania (daily challenges)

### Mechaniki gry:
- Eksploracja świata hex-based
- System walki turowej
- Inwentarz i ekwipunek
- Crafting przedmiotów
- Progresja poziomów
- Umiejętności charakterystyczne dla klas

---

## 🆘 Pomoc i wsparcie

### Jeśli nic nie działa:

**Reset całkowity (OSTATECZNOŚĆ):**

1. Zatrzymaj Apache i MySQL w XAMPP
2. W phpMyAdmin: Usuń bazę `rpg_game` (DROP DATABASE)
3. Utwórz nową bazę `rpg_game` (utf8mb4_unicode_ci)
4. Zaimportuj `rpg_game(5).sql`
5. Uruchom `http://localhost/rpg/setup_game.php`
6. Uruchom `http://localhost/rpg/setup_map.php`
7. Wyczyść cache przeglądarki (Ctrl+Shift+Del)
8. Odśwież grę: `http://localhost/rpg/`

### Logi do sprawdzenia:

1. **Apache errors:**
   - `C:\xampp\apache\logs\error.log`

2. **PHP errors:**
   - Włącz w `php.ini`: `display_errors = On`

3. **MySQL errors:**
   - `C:\xampp\mysql\data\mysql_error.log`

4. **Browser console:**
   - F12 → Console (JavaScript errors)
   - F12 → Network (API errors)

---

## 🎮 Gotowe do gry!

Jeśli wszystkie kroki zostały wykonane poprawnie:

1. **Otwórz:** `http://localhost/rpg/`
2. **Zarejestruj się** i stwórz postać
3. **Zacznij przygodę** w HexRealm!

**Miłej zabawy!** 🚀✨

---

## 📞 Szybki Start - Krótka Wersja

Dla zaawansowanych użytkowników:

```bash
# 1. Zainstaluj XAMPP i uruchom Apache + MySQL

# 2. phpMyAdmin:
#    - Utwórz bazę: rpg_game (utf8mb4_unicode_ci)
#    - Import: rpg_game(5).sql

# 3. Sprawdź db.php (root, bez hasła)

# 4. Uruchom w przeglądarce:
#    http://localhost/rpg/setup_game.php
#    http://localhost/rpg/setup_map.php

# 5. Graj:
#    http://localhost/rpg/
```

---

**Dokument:** SETUP.md  
**Projekt:** HexRealm RPG  
**Wersja:** 2.0  
**Data aktualizacji:** 19 lutego 2026  
**Autor:** Setup Guide Team
