-- Dopisek do bazy: Ogłoszenia + NEXORA PRO LEAGUE
-- Można uruchomić na istniejącej bazie: NIE kasuje Twoich danych (tylko CREATE TABLE IF NOT EXISTS).
USE turnieje;

-- Liga (na start jedna: NEXORA PRO LEAGUE, max 32 drużyny)
CREATE TABLE IF NOT EXISTS liga (
  id_ligi         INT AUTO_INCREMENT PRIMARY KEY,
  nazwa           VARCHAR(100) NOT NULL,
  limit_druzyn    INT NOT NULL DEFAULT 32,
  status          VARCHAR(20) NOT NULL DEFAULT 'zapisy',
  data_utworzenia DATE NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT chk_liga_limit  CHECK (limit_druzyn BETWEEN 9 AND 32),
  CONSTRAINT chk_liga_status CHECK (status IN ('zapisy','w trakcie','zakończona'))
) ENGINE=InnoDB;

-- Drużyny zgłoszone do ligi
CREATE TABLE IF NOT EXISTS liga_zgloszenie (
  id_ligi         INT NOT NULL,
  id_druzyny      INT NOT NULL,
  data_zgloszenia DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (id_ligi, id_druzyny),
  CONSTRAINT fk_lz_liga    FOREIGN KEY (id_ligi)    REFERENCES liga(id_ligi) ON DELETE CASCADE,
  CONSTRAINT fk_lz_druzyna FOREIGN KEY (id_druzyny) REFERENCES druzyna(id_druzyny)
) ENGINE=InnoDB;

-- Mecze ligowe (zawsze BO1, bez remisów)
CREATE TABLE IF NOT EXISTS liga_mecz (
  id_meczu     INT AUTO_INCREMENT PRIMARY KEY,
  id_ligi      INT NOT NULL,
  id_druzyny_a INT NOT NULL,
  id_druzyny_b INT NOT NULL,
  wynik_a      INT NULL,
  wynik_b      INT NULL,
  id_zwyciezcy INT NULL,
  status       VARCHAR(20) NOT NULL DEFAULT 'oczekujący',
  CONSTRAINT chk_lm_status  CHECK (status IN ('oczekujący','rozegrany')),
  CONSTRAINT chk_lm_rozne   CHECK (id_druzyny_a <> id_druzyny_b),
  CONSTRAINT chk_lm_wyniki  CHECK (
    (wynik_a IS NULL AND wynik_b IS NULL) OR
    (wynik_a >= 0 AND wynik_b >= 0 AND wynik_a <> wynik_b)),
  CONSTRAINT fk_lm_liga  FOREIGN KEY (id_ligi)      REFERENCES liga(id_ligi) ON DELETE CASCADE,
  CONSTRAINT fk_lm_a     FOREIGN KEY (id_druzyny_a) REFERENCES druzyna(id_druzyny),
  CONSTRAINT fk_lm_b     FOREIGN KEY (id_druzyny_b) REFERENCES druzyna(id_druzyny),
  CONSTRAINT fk_lm_zw    FOREIGN KEY (id_zwyciezcy) REFERENCES druzyna(id_druzyny)
) ENGINE=InnoDB;

-- Ogłoszenia (opcjonalnie powiązane z turniejem albo ligą)
CREATE TABLE IF NOT EXISTS ogloszenie (
  id_ogloszenia    INT AUTO_INCREMENT PRIMARY KEY,
  tytul            VARCHAR(150) NOT NULL,
  tresc            TEXT NOT NULL,
  data_publikacji  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_autora        INT NOT NULL,
  id_turnieju      INT NULL,
  id_ligi          INT NULL,
  CONSTRAINT fk_og_autor   FOREIGN KEY (id_autora)   REFERENCES uzytkownik(id_uzytkownika),
  CONSTRAINT fk_og_turniej FOREIGN KEY (id_turnieju) REFERENCES turniej(id_turnieju) ON DELETE SET NULL,
  CONSTRAINT fk_og_liga    FOREIGN KEY (id_ligi)     REFERENCES liga(id_ligi) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tworzymy ligę NEXORA PRO LEAGUE, jeśli jeszcze jej nie ma
INSERT INTO liga (nazwa, limit_druzyn)
SELECT 'NEXORA PRO LEAGUE', 32 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM liga);
