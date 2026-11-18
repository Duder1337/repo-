-- Anulowane turnieje znikają po ANULOWANE_USUWAJ_PO_DNIACH dniach (config.php).
-- Aplikacja dodaje tę kolumnę sama, ale możesz też uruchomić ten skrypt ręcznie.
USE turnieje;
ALTER TABLE turniej ADD COLUMN data_anulowania DATETIME NULL;
UPDATE turniej SET data_anulowania = NOW() WHERE status = 'anulowany' AND data_anulowania IS NULL;

-- Podtytuł, format finału i zasady turnieju (też dodawane automatycznie przy edycji turnieju w panelu admina)
ALTER TABLE turniej ADD COLUMN podtytul VARCHAR(150) NULL, ADD COLUMN format_finalu VARCHAR(3) NULL, ADD COLUMN zasady TEXT NULL;

-- Oznaczenie danych testowych (generator w panelu admina; kolumny dodawane też automatycznie)
ALTER TABLE zawodnik ADD COLUMN testowy TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE druzyna ADD COLUMN testowy TINYINT(1) NOT NULL DEFAULT 0;
