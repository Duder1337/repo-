-- Platforma turniejów CS2 – schemat bazy danych v2 (MySQL 8 / MariaDB 10.4+)
-- UWAGA: skrypt kasuje i tworzy tabele od nowa.

CREATE DATABASE IF NOT EXISTS turnieje
  CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
USE turnieje;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS mecz, zgloszenie, turniej, ranking, sklad, druzyna, zawodnik, uzytkownik;
SET FOREIGN_KEY_CHECKS = 1;

-- Konta: zwykli użytkownicy i administratorzy
CREATE TABLE uzytkownik (
  id_uzytkownika   INT AUTO_INCREMENT PRIMARY KEY,
  email            VARCHAR(190) NOT NULL UNIQUE,
  nick             VARCHAR(50)  NOT NULL UNIQUE,
  haslo_hash       VARCHAR(255) NOT NULL,
  rola             VARCHAR(20)  NOT NULL DEFAULT 'uzytkownik',
  data_rejestracji DATE NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT chk_rola CHECK (rola IN ('uzytkownik','administrator'))
) ENGINE=InnoDB;

-- Profil zawodnika (tworzony razem z kontem; dane demo mają id_uzytkownika = NULL)
CREATE TABLE zawodnik (
  id_zawodnika   INT AUTO_INCREMENT PRIMARY KEY,
  id_uzytkownika INT NULL UNIQUE,
  nick           VARCHAR(50) NOT NULL UNIQUE,
  imie           VARCHAR(50) NOT NULL,
  nazwisko       VARCHAR(80) NOT NULL,
  CONSTRAINT fk_zawodnik_uzytkownik FOREIGN KEY (id_uzytkownika)
    REFERENCES uzytkownik(id_uzytkownika) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Drużyna (właściciel = konto, które ją utworzyło; max 3 drużyny – sprawdzane w PHP)
CREATE TABLE druzyna (
  id_druzyny      INT AUTO_INCREMENT PRIMARY KEY,
  nazwa           VARCHAR(30) NOT NULL UNIQUE,
  id_wlasciciela  INT NOT NULL,
  data_utworzenia DATE NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT chk_nazwa_dl CHECK (CHAR_LENGTH(nazwa) BETWEEN 3 AND 30),
  CONSTRAINT fk_druzyna_wlasciciel FOREIGN KEY (id_wlasciciela)
    REFERENCES uzytkownik(id_uzytkownika)
) ENGINE=InnoDB;

-- Skład: 1 kapitan + 4 graczy + 0..3 rezerwowych + 0..1 trener (liczby sprawdzane w PHP)
CREATE TABLE sklad (
  id_druzyny   INT NOT NULL,
  id_zawodnika INT NOT NULL,
  rola         VARCHAR(12) NOT NULL,
  PRIMARY KEY (id_druzyny, id_zawodnika),
  CONSTRAINT chk_rola_skladu CHECK (rola IN ('kapitan','gracz','rezerwowy','trener')),
  CONSTRAINT fk_sklad_druzyna FOREIGN KEY (id_druzyny)
    REFERENCES druzyna(id_druzyny) ON DELETE CASCADE,
  CONSTRAINT fk_sklad_zawodnik FOREIGN KEY (id_zawodnika)
    REFERENCES zawodnik(id_zawodnika) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ranking (
  id_druzyny INT PRIMARY KEY,
  punkty     INT NOT NULL DEFAULT 0,
  wygrane    INT NOT NULL DEFAULT 0,
  przegrane  INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_ranking_druzyna FOREIGN KEY (id_druzyny)
    REFERENCES druzyna(id_druzyny) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Turnieje CS2 (zawsze 5 vs 5)
CREATE TABLE turniej (
  id_turnieju       INT AUTO_INCREMENT PRIMARY KEY,
  nazwa             VARCHAR(100) NOT NULL,
  dyscyplina        VARCHAR(60)  NOT NULL DEFAULT 'Counter-Strike 2',
  format_meczu      VARCHAR(3)   NOT NULL DEFAULT 'BO1',
  limit_druzyn      INT NOT NULL,
  data_startu       DATE NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'szkic',
  id_administratora INT NOT NULL,
  id_zwyciezcy      INT NULL,
  CONSTRAINT chk_limit  CHECK (limit_druzyn BETWEEN 4 AND 64),
  CONSTRAINT chk_format CHECK (format_meczu IN ('BO1','BO3','BO5')),
  CONSTRAINT chk_status_t CHECK (status IN ('szkic','otwarty','w trakcie','zakończony','anulowany')),
  CONSTRAINT fk_turniej_admin FOREIGN KEY (id_administratora) REFERENCES uzytkownik(id_uzytkownika),
  CONSTRAINT fk_turniej_zwyciezca FOREIGN KEY (id_zwyciezcy) REFERENCES druzyna(id_druzyny)
) ENGINE=InnoDB;

CREATE TABLE zgloszenie (
  id_zgloszenia   INT AUTO_INCREMENT PRIMARY KEY,
  id_turnieju     INT NOT NULL,
  id_druzyny      INT NOT NULL,
  data_zgloszenia DATE NOT NULL DEFAULT (CURRENT_DATE),
  status          VARCHAR(20) NOT NULL DEFAULT 'przyjęte',
  UNIQUE KEY uq_turniej_druzyna (id_turnieju, id_druzyny),
  CONSTRAINT chk_status_z CHECK (status IN ('przyjęte','odrzucone','wycofane')),
  CONSTRAINT fk_zgl_turniej FOREIGN KEY (id_turnieju) REFERENCES turniej(id_turnieju) ON DELETE CASCADE,
  CONSTRAINT fk_zgl_druzyna FOREIGN KEY (id_druzyny) REFERENCES druzyna(id_druzyny)
) ENGINE=InnoDB;

CREATE TABLE mecz (
  id_meczu            INT AUTO_INCREMENT PRIMARY KEY,
  id_turnieju         INT NOT NULL,
  runda               INT NOT NULL,
  nr_w_rundzie        INT NOT NULL,
  id_druzyny_a        INT NULL,
  id_druzyny_b        INT NULL,
  wynik_a             INT NULL,
  wynik_b             INT NULL,
  id_zwyciezcy        INT NULL,
  id_nastepnego_meczu INT NULL,
  status              VARCHAR(20) NOT NULL DEFAULT 'oczekujący',
  UNIQUE KEY uq_pozycja (id_turnieju, runda, nr_w_rundzie),
  CONSTRAINT chk_status_m CHECK (status IN ('oczekujący','rozegrany')),
  CONSTRAINT chk_wyniki CHECK (
    (wynik_a IS NULL AND wynik_b IS NULL) OR
    (wynik_a >= 0 AND wynik_b >= 0 AND wynik_a <> wynik_b)),
  CONSTRAINT fk_mecz_turniej FOREIGN KEY (id_turnieju) REFERENCES turniej(id_turnieju) ON DELETE CASCADE,
  CONSTRAINT fk_mecz_a FOREIGN KEY (id_druzyny_a) REFERENCES druzyna(id_druzyny),
  CONSTRAINT fk_mecz_b FOREIGN KEY (id_druzyny_b) REFERENCES druzyna(id_druzyny),
  CONSTRAINT fk_mecz_zwyciezca FOREIGN KEY (id_zwyciezcy) REFERENCES druzyna(id_druzyny),
  CONSTRAINT fk_mecz_nastepny FOREIGN KEY (id_nastepnego_meczu) REFERENCES mecz(id_meczu)
) ENGINE=InnoDB;

-- Konto administratora: admin@turnieje.local / hasło: Admin123!  (zmień je po pierwszym logowaniu)
INSERT INTO uzytkownik (email, nick, haslo_hash, rola) VALUES
 ('admin@turnieje.local', 'admin', '$2y$12$Barms3HudvugDjBpzH.SOeZSmzRijgpzWQQUog0zskoNB6Yv5CCpK', 'administrator');

-- Demo: zawodnicy bez kont (żeby było kogo dodać do składu podczas testów)
INSERT INTO zawodnik (nick, imie, nazwisko) VALUES
 ('Shadow','Jan','Kowalski'),('Viper','Piotr','Nowak'),('Blaze','Anna','Wiśniewska'),
 ('Frost','Marek','Wójcik'),('Rogue','Ola','Kamińska'),('Titan','Kamil','Lewandowski'),
 ('Nova','Ewa','Zielińska'),('Hunter','Tomasz','Szymański'),('Echo','Michał','Woźniak'),
 ('Pixel','Julia','Dąbrowska');
