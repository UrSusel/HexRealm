<?php
$host = 'localhost';
$db   = 'rpg_game'; // Baza, którą stworzyliśmy w MariaDB
$user = 'root';     // Użytkownik, któremu daliśmy uprawnienia
$pass = '';         // Brak hasła, tak jak ustawialiśmy

try {
    // Łączenie z bazą MySQL/MariaDB za pomocą PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    // Ustawienie raportowania błędów bazy danych
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Jeśli autor używa zmiennej $conn w innych plikach zamiast $pdo, dodajemy to:
    $conn = $pdo; 
    
} catch (PDOException $e) {
    die("Błąd bazy: " . $e->getMessage());
}
?>