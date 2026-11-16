-- Nexora: dwie pełne drużyny testowe (kapitan + 4 graczy każda)
-- Można uruchomić wielokrotnie (INSERT IGNORE). Wymaga bazy z tabelami z turnieje.sql.
-- Wszystkie konta testowe mają hasło: Admin123!  (to samo hasło co konto admina z turnieje.sql)
USE turnieje;

-- 1. Konta (10 sztuk): alpha1..alpha5@test.local oraz bravo1..bravo5@test.local
INSERT IGNORE INTO uzytkownik (email, nick, haslo_hash, rola) VALUES
 ('alpha1@test.local','Alpha_Kapitan','$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('alpha2@test.local','Alpha_Gracz2', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('alpha3@test.local','Alpha_Gracz3', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('alpha4@test.local','Alpha_Gracz4', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('alpha5@test.local','Alpha_Gracz5', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('bravo1@test.local','Bravo_Kapitan','$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('bravo2@test.local','Bravo_Gracz2', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('bravo3@test.local','Bravo_Gracz3', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('bravo4@test.local','Bravo_Gracz4', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik'),
 ('bravo5@test.local','Bravo_Gracz5', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK','uzytkownik');

-- 2. Profile zawodników
INSERT IGNORE INTO zawodnik (id_uzytkownika, nick, imie, nazwisko)
SELECT id_uzytkownika, nick, 'Test', 'Testowy' FROM uzytkownik WHERE email LIKE '%@test.local';

-- 3. Drużyny (właściciel = konto kapitana)
INSERT IGNORE INTO druzyna (nazwa, id_wlasciciela)
SELECT 'Test Alpha', id_uzytkownika FROM uzytkownik WHERE email = 'alpha1@test.local';
INSERT IGNORE INTO druzyna (nazwa, id_wlasciciela)
SELECT 'Test Bravo', id_uzytkownika FROM uzytkownik WHERE email = 'bravo1@test.local';

-- 4. Składy: pierwszy zawodnik (…1@) kapitanem, pozostali czterej graczami
INSERT IGNORE INTO sklad (id_druzyny, id_zawodnika, rola)
SELECT d.id_druzyny, z.id_zawodnika, IF(u.email = 'alpha1@test.local', 'kapitan', 'gracz')
FROM druzyna d
JOIN uzytkownik u ON u.email LIKE 'alpha_@test.local'
JOIN zawodnik z ON z.id_uzytkownika = u.id_uzytkownika
WHERE d.nazwa = 'Test Alpha';

INSERT IGNORE INTO sklad (id_druzyny, id_zawodnika, rola)
SELECT d.id_druzyny, z.id_zawodnika, IF(u.email = 'bravo1@test.local', 'kapitan', 'gracz')
FROM druzyna d
JOIN uzytkownik u ON u.email LIKE 'bravo_@test.local'
JOIN zawodnik z ON z.id_uzytkownika = u.id_uzytkownika
WHERE d.nazwa = 'Test Bravo';

-- 5. Wpisy w rankingu
INSERT IGNORE INTO ranking (id_druzyny)
SELECT id_druzyny FROM druzyna WHERE nazwa IN ('Test Alpha', 'Test Bravo');

-- Sprawdzenie: powinno zwrócić 2 drużyny po 5 osób (1 kapitan + 4 graczy)
SELECT d.nazwa, s.rola, COUNT(*) AS liczba
FROM druzyna d JOIN sklad s ON s.id_druzyny = d.id_druzyny
WHERE d.nazwa IN ('Test Alpha', 'Test Bravo')
GROUP BY d.nazwa, s.rola ORDER BY d.nazwa, s.rola;

/* Sprzątanie po testach (odkomentuj, żeby usunąć wszystko, co dodał ten skrypt):
DELETE FROM druzyna WHERE nazwa IN ('Test Alpha', 'Test Bravo');
DELETE FROM uzytkownik WHERE email LIKE '%@test.local';
*/
