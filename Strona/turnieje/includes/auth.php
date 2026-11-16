<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Kodowanie danych na stronie (ochrona przed XSS)
function e($tekst): string {
    return htmlspecialchars((string)$tekst, ENT_QUOTES, 'UTF-8');
}

function zalogowany(): bool { return isset($_SESSION['uzytkownik']); }
function uzytkownik(): ?array { return $_SESSION['uzytkownik'] ?? null; }
function maRole(string $rola): bool { return zalogowany() && $_SESSION['uzytkownik']['rola'] === $rola; }

function wymagajLogowania(): void {
    if (!zalogowany()) { header('Location: ' . BASE_URL . '/login.php'); exit; }
}
// Sprawdzanie roli po stronie serwera (WN-03)
function wymagajRoli(string $rola): void {
    wymagajLogowania();
    if (!maRole($rola)) { http_response_code(403); exit('Brak dostępu.'); }
}

// Prosta ochrona CSRF dla formularzy
function csrfPole(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrfSprawdz(): void {
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(400); exit('Nieprawidłowy token formularza.');
    }
}
