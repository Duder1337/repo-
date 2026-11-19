-- Tabela ze statystykami zawodników (udział w meczach). Symulacja tworzy ją automatycznie,
-- jeśli jej brakuje, ale możesz też uruchomić ten skrypt ręcznie (nie kasuje danych).
USE turnieje;

CREATE TABLE IF NOT EXISTS udzial_zawodnika (
  id_udzialu        INT AUTO_INCREMENT PRIMARY KEY,
  id_zawodnika      INT NOT NULL,
  id_druzyny        INT NOT NULL,
  id_meczu_ligi     INT NULL,
  id_meczu_turnieju INT NULL,
  wygrana           TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_udzial_zawodnik (id_zawodnika),
  KEY idx_udzial_turniej  (id_meczu_turnieju),
  KEY idx_udzial_liga     (id_meczu_ligi)
) ENGINE=InnoDB;
