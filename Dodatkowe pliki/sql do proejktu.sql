-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Lis 16, 2026 at 12:13 PM
-- Wersja serwera: 10.11.16-MariaDB
-- Wersja PHP: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `turnieje`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `druzyna`
--

CREATE TABLE `druzyna` (
  `id_druzyny` int(11) NOT NULL,
  `nazwa` varchar(30) NOT NULL,
  `id_wlasciciela` int(11) NOT NULL,
  `data_utworzenia` date NOT NULL DEFAULT curdate()
) ;

--
-- Zrzut danych tabeli `druzyna`
--

INSERT INTO `druzyna` (`id_druzyny`, `nazwa`, `id_wlasciciela`, `data_utworzenia`) VALUES
(1, 'Lider fc', 1, '2026-11-16'),
(2, 'Cs group', 3, '2026-11-16');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `liga`
--

CREATE TABLE `liga` (
  `id_ligi` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `limit_druzyn` int(11) NOT NULL DEFAULT 32,
  `status` varchar(20) NOT NULL DEFAULT 'zapisy',
  `data_utworzenia` date NOT NULL DEFAULT curdate()
) ;

--
-- Zrzut danych tabeli `liga`
--

INSERT INTO `liga` (`id_ligi`, `nazwa`, `limit_druzyn`, `status`, `data_utworzenia`) VALUES
(1, 'NEXORA PRO LEAGUE', 32, 'zapisy', '2026-11-16');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `liga_mecz`
--

CREATE TABLE `liga_mecz` (
  `id_meczu` int(11) NOT NULL,
  `id_ligi` int(11) NOT NULL,
  `id_druzyny_a` int(11) NOT NULL,
  `id_druzyny_b` int(11) NOT NULL,
  `wynik_a` int(11) DEFAULT NULL,
  `wynik_b` int(11) DEFAULT NULL,
  `id_zwyciezcy` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'oczekujący'
) ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `liga_zgloszenie`
--

CREATE TABLE `liga_zgloszenie` (
  `id_ligi` int(11) NOT NULL,
  `id_druzyny` int(11) NOT NULL,
  `data_zgloszenia` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `mecz`
--

CREATE TABLE `mecz` (
  `id_meczu` int(11) NOT NULL,
  `id_turnieju` int(11) NOT NULL,
  `runda` int(11) NOT NULL,
  `nr_w_rundzie` int(11) NOT NULL,
  `id_druzyny_a` int(11) DEFAULT NULL,
  `id_druzyny_b` int(11) DEFAULT NULL,
  `wynik_a` int(11) DEFAULT NULL,
  `wynik_b` int(11) DEFAULT NULL,
  `id_zwyciezcy` int(11) DEFAULT NULL,
  `id_nastepnego_meczu` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'oczekujący'
) ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `ogloszenie`
--

CREATE TABLE `ogloszenie` (
  `id_ogloszenia` int(11) NOT NULL,
  `tytul` varchar(150) NOT NULL,
  `tresc` text NOT NULL,
  `data_publikacji` datetime NOT NULL DEFAULT current_timestamp(),
  `id_autora` int(11) NOT NULL,
  `id_turnieju` int(11) DEFAULT NULL,
  `id_ligi` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Zrzut danych tabeli `ogloszenie`
--

INSERT INTO `ogloszenie` (`id_ogloszenia`, `tytul`, `tresc`, `data_publikacji`, `id_autora`, `id_turnieju`, `id_ligi`) VALUES
(1, 'Start turnieju \"dsadsa\" 17.11.2026', 'Start', '2026-11-16 12:06:28', 2, 1, NULL);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `ranking`
--

CREATE TABLE `ranking` (
  `id_druzyny` int(11) NOT NULL,
  `punkty` int(11) NOT NULL DEFAULT 0,
  `wygrane` int(11) NOT NULL DEFAULT 0,
  `przegrane` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Zrzut danych tabeli `ranking`
--

INSERT INTO `ranking` (`id_druzyny`, `punkty`, `wygrane`, `przegrane`) VALUES
(1, 0, 0, 0),
(2, 0, 0, 0);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `sklad`
--

CREATE TABLE `sklad` (
  `id_druzyny` int(11) NOT NULL,
  `id_zawodnika` int(11) NOT NULL,
  `rola` varchar(12) NOT NULL
) ;

--
-- Zrzut danych tabeli `sklad`
--

INSERT INTO `sklad` (`id_druzyny`, `id_zawodnika`, `rola`) VALUES
(1, 1, 'gracz'),
(1, 2, 'rezerwowy'),
(1, 3, 'gracz'),
(1, 4, 'trener'),
(1, 7, 'rezerwowy'),
(1, 8, 'gracz'),
(1, 9, 'gracz'),
(1, 10, 'rezerwowy'),
(1, 11, 'kapitan'),
(2, 12, 'gracz'),
(2, 13, 'kapitan');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `turniej`
--

CREATE TABLE `turniej` (
  `id_turnieju` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `dyscyplina` varchar(60) NOT NULL DEFAULT 'Counter-Strike 2',
  `format_meczu` varchar(3) NOT NULL DEFAULT 'BO1',
  `limit_druzyn` int(11) NOT NULL,
  `data_startu` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'szkic',
  `id_administratora` int(11) NOT NULL,
  `id_zwyciezcy` int(11) DEFAULT NULL
) ;

--
-- Zrzut danych tabeli `turniej`
--

INSERT INTO `turniej` (`id_turnieju`, `nazwa`, `dyscyplina`, `format_meczu`, `limit_druzyn`, `data_startu`, `status`, `id_administratora`, `id_zwyciezcy`) VALUES
(1, 'Lider', 'Counter-Strike 2', 'BO1', 16, '2028-02-12', 'otwarty', 2, NULL);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `udzial_zawodnika`
--

CREATE TABLE `udzial_zawodnika` (
  `id_udzialu` int(11) NOT NULL,
  `id_zawodnika` int(11) NOT NULL,
  `id_druzyny` int(11) NOT NULL,
  `id_meczu_ligi` int(11) DEFAULT NULL,
  `id_meczu_turnieju` int(11) DEFAULT NULL,
  `wygrana` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `uzytkownik`
--

CREATE TABLE `uzytkownik` (
  `id_uzytkownika` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `nick` varchar(50) NOT NULL,
  `haslo_hash` varchar(255) NOT NULL,
  `rola` varchar(20) NOT NULL DEFAULT 'uzytkownik',
  `data_rejestracji` date NOT NULL DEFAULT curdate()
) ;

--
-- Zrzut danych tabeli `uzytkownik`
--

INSERT INTO `uzytkownik` (`id_uzytkownika`, `email`, `nick`, `haslo_hash`, `rola`, `data_rejestracji`) VALUES
(1, 'Dudak301@gmail.com', 'Duderowski', '$2y$12$i7D8nApjiBkayIrbiGHWfOTEA7bef84dqIOmpkSGMUNK0ChWbKW7q', 'uzytkownik', '2026-11-16'),
(2, 'admin@turnieje.local', 'Kubix', '$2y$12$pYOr4P.oSueaxh69I/ksBuarJFSn8ZYR9sFCOzQDL4N6kIo5jj1m.', 'administrator', '2026-11-16'),
(3, 'lisuumssc@gmail.com', 'Julcia', '$2y$12$c.mZHktFQgNJfd9W2dXRlOPHenPhF3eONthXhJwRBGS8hVNzNyFV.', 'uzytkownik', '2026-11-16');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `zaproszenie`
--

CREATE TABLE `zaproszenie` (
  `id_zaproszenia` int(11) NOT NULL,
  `id_druzyny` int(11) NOT NULL,
  `id_zawodnika` int(11) NOT NULL,
  `rola` varchar(12) NOT NULL DEFAULT 'gracz',
  `id_zapraszajacego` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'oczekujące',
  `data_wyslania` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Zrzut danych tabeli `zaproszenie`
--

INSERT INTO `zaproszenie` (`id_zaproszenia`, `id_druzyny`, `id_zawodnika`, `rola`, `id_zapraszajacego`, `status`, `data_wyslania`) VALUES
(1, 2, 12, 'gracz', 3, 'zaakceptowane', '2026-11-16 12:02:08');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `zawodnik`
--

CREATE TABLE `zawodnik` (
  `id_zawodnika` int(11) NOT NULL,
  `id_uzytkownika` int(11) DEFAULT NULL,
  `nick` varchar(50) NOT NULL,
  `imie` varchar(50) NOT NULL,
  `nazwisko` varchar(80) NOT NULL,
  `zdjecie` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Zrzut danych tabeli `zawodnik`
--

INSERT INTO `zawodnik` (`id_zawodnika`, `id_uzytkownika`, `nick`, `imie`, `nazwisko`, `zdjecie`) VALUES
(1, NULL, 'Shadow', 'Jan', 'Kowalski', NULL),
(2, NULL, 'Viper', 'Piotr', 'Nowak', NULL),
(3, NULL, 'Blaze', 'Anna', 'Wiśniewska', NULL),
(4, NULL, 'Frost', 'Marek', 'Wójcik', NULL),
(5, NULL, 'Rogue', 'Ola', 'Kamińska', NULL),
(6, NULL, 'Titan', 'Kamil', 'Lewandowski', NULL),
(7, NULL, 'Nova', 'Ewa', 'Zielińska', NULL),
(8, NULL, 'Hunter', 'Tomasz', 'Szymański', NULL),
(9, NULL, 'Echo', 'Michał', 'Woźniak', NULL),
(10, NULL, 'Pixel', 'Julia', 'Dąbrowska', NULL),
(11, 1, 'Duderowski', 'Krystian', 'Duda', NULL),
(12, 2, 'Kubix', 'Kuba', 'bojdasz', NULL),
(13, 3, 'Julcia', 'Alex', 'Szady', NULL);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `zgloszenie`
--

CREATE TABLE `zgloszenie` (
  `id_zgloszenia` int(11) NOT NULL,
  `id_turnieju` int(11) NOT NULL,
  `id_druzyny` int(11) NOT NULL,
  `data_zgloszenia` date NOT NULL DEFAULT curdate(),
  `status` varchar(20) NOT NULL DEFAULT 'przyjęte'
) ;

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `druzyna`
--
ALTER TABLE `druzyna`
  ADD PRIMARY KEY (`id_druzyny`),
  ADD UNIQUE KEY `nazwa` (`nazwa`),
  ADD KEY `fk_druzyna_wlasciciel` (`id_wlasciciela`);

--
-- Indeksy dla tabeli `liga`
--
ALTER TABLE `liga`
  ADD PRIMARY KEY (`id_ligi`);

--
-- Indeksy dla tabeli `liga_mecz`
--
ALTER TABLE `liga_mecz`
  ADD PRIMARY KEY (`id_meczu`),
  ADD KEY `fk_lm_liga` (`id_ligi`),
  ADD KEY `fk_lm_a` (`id_druzyny_a`),
  ADD KEY `fk_lm_b` (`id_druzyny_b`),
  ADD KEY `fk_lm_zw` (`id_zwyciezcy`);

--
-- Indeksy dla tabeli `liga_zgloszenie`
--
ALTER TABLE `liga_zgloszenie`
  ADD PRIMARY KEY (`id_ligi`,`id_druzyny`),
  ADD KEY `fk_lz_druzyna` (`id_druzyny`);

--
-- Indeksy dla tabeli `mecz`
--
ALTER TABLE `mecz`
  ADD PRIMARY KEY (`id_meczu`),
  ADD UNIQUE KEY `uq_pozycja` (`id_turnieju`,`runda`,`nr_w_rundzie`),
  ADD KEY `fk_mecz_a` (`id_druzyny_a`),
  ADD KEY `fk_mecz_b` (`id_druzyny_b`),
  ADD KEY `fk_mecz_zwyciezca` (`id_zwyciezcy`),
  ADD KEY `fk_mecz_nastepny` (`id_nastepnego_meczu`);

--
-- Indeksy dla tabeli `ogloszenie`
--
ALTER TABLE `ogloszenie`
  ADD PRIMARY KEY (`id_ogloszenia`),
  ADD KEY `fk_og_autor` (`id_autora`),
  ADD KEY `fk_og_turniej` (`id_turnieju`),
  ADD KEY `fk_og_liga` (`id_ligi`);

--
-- Indeksy dla tabeli `ranking`
--
ALTER TABLE `ranking`
  ADD PRIMARY KEY (`id_druzyny`);

--
-- Indeksy dla tabeli `sklad`
--
ALTER TABLE `sklad`
  ADD PRIMARY KEY (`id_druzyny`,`id_zawodnika`),
  ADD UNIQUE KEY `uq_zawodnik_jedna_druzyna` (`id_zawodnika`);

--
-- Indeksy dla tabeli `turniej`
--
ALTER TABLE `turniej`
  ADD PRIMARY KEY (`id_turnieju`),
  ADD KEY `fk_turniej_admin` (`id_administratora`),
  ADD KEY `fk_turniej_zwyciezca` (`id_zwyciezcy`);

--
-- Indeksy dla tabeli `udzial_zawodnika`
--
ALTER TABLE `udzial_zawodnika`
  ADD PRIMARY KEY (`id_udzialu`),
  ADD UNIQUE KEY `uq_udzial_liga` (`id_zawodnika`,`id_meczu_ligi`),
  ADD UNIQUE KEY `uq_udzial_turniej` (`id_zawodnika`,`id_meczu_turnieju`),
  ADD KEY `fk_ud_druzyna` (`id_druzyny`),
  ADD KEY `fk_ud_liga` (`id_meczu_ligi`),
  ADD KEY `fk_ud_turniej` (`id_meczu_turnieju`);

--
-- Indeksy dla tabeli `uzytkownik`
--
ALTER TABLE `uzytkownik`
  ADD PRIMARY KEY (`id_uzytkownika`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nick` (`nick`);

--
-- Indeksy dla tabeli `zaproszenie`
--
ALTER TABLE `zaproszenie`
  ADD PRIMARY KEY (`id_zaproszenia`),
  ADD KEY `idx_zapr_zawodnik` (`id_zawodnika`,`status`),
  ADD KEY `fk_zapr_druzyna` (`id_druzyny`),
  ADD KEY `fk_zapr_uzytk` (`id_zapraszajacego`);

--
-- Indeksy dla tabeli `zawodnik`
--
ALTER TABLE `zawodnik`
  ADD PRIMARY KEY (`id_zawodnika`),
  ADD UNIQUE KEY `nick` (`nick`),
  ADD UNIQUE KEY `id_uzytkownika` (`id_uzytkownika`);

--
-- Indeksy dla tabeli `zgloszenie`
--
ALTER TABLE `zgloszenie`
  ADD PRIMARY KEY (`id_zgloszenia`),
  ADD UNIQUE KEY `uq_turniej_druzyna` (`id_turnieju`,`id_druzyny`),
  ADD KEY `fk_zgl_druzyna` (`id_druzyny`);

--
-- AUTO_INCREMENT dla zrzuconych tabel
--

--
-- AUTO_INCREMENT dla tabeli `druzyna`
--
ALTER TABLE `druzyna`
  MODIFY `id_druzyny` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `liga`
--
ALTER TABLE `liga`
  MODIFY `id_ligi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `liga_mecz`
--
ALTER TABLE `liga_mecz`
  MODIFY `id_meczu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `mecz`
--
ALTER TABLE `mecz`
  MODIFY `id_meczu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `ogloszenie`
--
ALTER TABLE `ogloszenie`
  MODIFY `id_ogloszenia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT dla tabeli `turniej`
--
ALTER TABLE `turniej`
  MODIFY `id_turnieju` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `udzial_zawodnika`
--
ALTER TABLE `udzial_zawodnika`
  MODIFY `id_udzialu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `uzytkownik`
--
ALTER TABLE `uzytkownik`
  MODIFY `id_uzytkownika` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `zaproszenie`
--
ALTER TABLE `zaproszenie`
  MODIFY `id_zaproszenia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT dla tabeli `zawodnik`
--
ALTER TABLE `zawodnik`
  MODIFY `id_zawodnika` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT dla tabeli `zgloszenie`
--
ALTER TABLE `zgloszenie`
  MODIFY `id_zgloszenia` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ograniczenia dla zrzutów tabel
--

--
-- Ograniczenia dla tabeli `druzyna`
--
ALTER TABLE `druzyna`
  ADD CONSTRAINT `fk_druzyna_wlasciciel` FOREIGN KEY (`id_wlasciciela`) REFERENCES `uzytkownik` (`id_uzytkownika`);

--
-- Ograniczenia dla tabeli `liga_mecz`
--
ALTER TABLE `liga_mecz`
  ADD CONSTRAINT `fk_lm_a` FOREIGN KEY (`id_druzyny_a`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_lm_b` FOREIGN KEY (`id_druzyny_b`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_lm_liga` FOREIGN KEY (`id_ligi`) REFERENCES `liga` (`id_ligi`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lm_zw` FOREIGN KEY (`id_zwyciezcy`) REFERENCES `druzyna` (`id_druzyny`);

--
-- Ograniczenia dla tabeli `liga_zgloszenie`
--
ALTER TABLE `liga_zgloszenie`
  ADD CONSTRAINT `fk_lz_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_lz_liga` FOREIGN KEY (`id_ligi`) REFERENCES `liga` (`id_ligi`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `mecz`
--
ALTER TABLE `mecz`
  ADD CONSTRAINT `fk_mecz_a` FOREIGN KEY (`id_druzyny_a`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_mecz_b` FOREIGN KEY (`id_druzyny_b`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_mecz_nastepny` FOREIGN KEY (`id_nastepnego_meczu`) REFERENCES `mecz` (`id_meczu`),
  ADD CONSTRAINT `fk_mecz_turniej` FOREIGN KEY (`id_turnieju`) REFERENCES `turniej` (`id_turnieju`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mecz_zwyciezca` FOREIGN KEY (`id_zwyciezcy`) REFERENCES `druzyna` (`id_druzyny`);

--
-- Ograniczenia dla tabeli `ogloszenie`
--
ALTER TABLE `ogloszenie`
  ADD CONSTRAINT `fk_og_autor` FOREIGN KEY (`id_autora`) REFERENCES `uzytkownik` (`id_uzytkownika`),
  ADD CONSTRAINT `fk_og_liga` FOREIGN KEY (`id_ligi`) REFERENCES `liga` (`id_ligi`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_og_turniej` FOREIGN KEY (`id_turnieju`) REFERENCES `turniej` (`id_turnieju`) ON DELETE SET NULL;

--
-- Ograniczenia dla tabeli `ranking`
--
ALTER TABLE `ranking`
  ADD CONSTRAINT `fk_ranking_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `sklad`
--
ALTER TABLE `sklad`
  ADD CONSTRAINT `fk_sklad_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sklad_zawodnik` FOREIGN KEY (`id_zawodnika`) REFERENCES `zawodnik` (`id_zawodnika`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `turniej`
--
ALTER TABLE `turniej`
  ADD CONSTRAINT `fk_turniej_admin` FOREIGN KEY (`id_administratora`) REFERENCES `uzytkownik` (`id_uzytkownika`),
  ADD CONSTRAINT `fk_turniej_zwyciezca` FOREIGN KEY (`id_zwyciezcy`) REFERENCES `druzyna` (`id_druzyny`);

--
-- Ograniczenia dla tabeli `udzial_zawodnika`
--
ALTER TABLE `udzial_zawodnika`
  ADD CONSTRAINT `fk_ud_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ud_liga` FOREIGN KEY (`id_meczu_ligi`) REFERENCES `liga_mecz` (`id_meczu`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ud_turniej` FOREIGN KEY (`id_meczu_turnieju`) REFERENCES `mecz` (`id_meczu`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ud_zawodnik` FOREIGN KEY (`id_zawodnika`) REFERENCES `zawodnik` (`id_zawodnika`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `zaproszenie`
--
ALTER TABLE `zaproszenie`
  ADD CONSTRAINT `fk_zapr_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_zapr_uzytk` FOREIGN KEY (`id_zapraszajacego`) REFERENCES `uzytkownik` (`id_uzytkownika`),
  ADD CONSTRAINT `fk_zapr_zawodnik` FOREIGN KEY (`id_zawodnika`) REFERENCES `zawodnik` (`id_zawodnika`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `zawodnik`
--
ALTER TABLE `zawodnik`
  ADD CONSTRAINT `fk_zawodnik_uzytkownik` FOREIGN KEY (`id_uzytkownika`) REFERENCES `uzytkownik` (`id_uzytkownika`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `zgloszenie`
--
ALTER TABLE `zgloszenie`
  ADD CONSTRAINT `fk_zgl_druzyna` FOREIGN KEY (`id_druzyny`) REFERENCES `druzyna` (`id_druzyny`),
  ADD CONSTRAINT `fk_zgl_turniej` FOREIGN KEY (`id_turnieju`) REFERENCES `turniej` (`id_turnieju`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
