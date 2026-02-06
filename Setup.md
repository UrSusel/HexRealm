# HexaQuest RPG

A browser-based, multiplayer tactical RPG featuring a hexagonal grid map, turn-based combat, and procedural world generation. Built with vanilla PHP, JavaScript, and MySQL.

## 📋 Prerequisites

To run this game, you need a local web server environment with PHP and MySQL.
*   **XAMPP** (Recommended for Windows)
*   **WAMP** or **MAMP**
*   **LAMP** stack (Linux)

**Requirements:**
*   PHP 8.0 or higher
*   MySQL / MariaDB
*   Apache / Nginx

## 🚀 Installation & Setup

### 1. Clone the Repository
Navigate to your web server's document root (e.g., `C:\xampp\htdocs\` for XAMPP) and clone this repository:

```bash
cd c:\xampp\htdocs
git clone https://github.com/yourusername/rpg-game.git rpg
```

### 2. Database Configuration
1.  Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`).
2.  Create a new, empty database named `rpg_game`.
3.  Open the file `db.php` in the project folder.
4.  Ensure the settings match your local database credentials (default XAMPP settings shown below):

```php
$host = 'localhost';
$db   = 'rpg_game';
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password (empty)
```

### 3. Initialize the Game
Open your web browser and navigate to the setup script to create the necessary database tables and the tutorial world:

> **URL:** `http://localhost/rpg/setup_game.php`

You should see a "Success" message indicating tables were created.

### 4. Generate the World
To generate the main game world (procedural map), navigate to:

> **URL:** `http://localhost/rpg/setup_map.php`

Use the slider to select the map size and click **"GENERUJ ŚWIAT"**.

## 🎮 How to Play

1.  Go to `http://localhost/rpg/index.php`.
2.  Click **"ZALOGUJ SIĘ"** (Login).
3.  Use the default admin account:
    *   **Username:** `Tester`
    *   **Password:** `admin`
4.  Or click **"Zarejestruj się"** to create a new account.
5.  Select a character class and start your adventure!

## 🛡️ License

Source code is available for educational purposes. All rights reserved.