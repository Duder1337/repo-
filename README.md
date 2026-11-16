#repo
System Zarządzania Turniejami
Cel projektu: Stworzenie webowej aplikacji do automatyzacji organizacji turniejów sportowych i e-sportowych, która zastępuje ręczne prowadzenie rozgrywek w arkuszach kalkulacyjnych.
Obsługa ról i kont: Podział użytkowników na Kapitanów (tworzenie drużyn, zgłaszanie składów) oraz Administratorów (zarządzanie zawodami i wynikami).
Zarządzanie drużynami: Moduł pozwalający Kapitanowi na rejestrację zespołu, wyszukiwanie graczy w bazie MySQL i kompletowanie składu.
Tworzenie i publikacja turniejów: Panel dla Administratora do dodawania wydarzeń, ustawiania dyscypliny, daty startu, limitu miejsc i otwierania zapisów.
Interaktywna drabinka pucharowa: Graficzna wizualizacja meczów w JavaScript, generująca pary po zamknięciu zapisów i pokazująca aktualne rundy.
Rejestracja wyników i ranking: Moduł wprowadzania wyników z automatycznym awansem zwycięzcy do kolejnej rundy oraz aktualizacją tabeli ligowej.
Stos technologiczny i bezpieczeństwo: Realizacja w technologii LAMP (HTML, CSS, JS, PHP, MySQL) z responsywnym interfejsem oraz zabezpieczeniami przed SQL Injection i XSS.




# NEXORA ESPORTS – Lista planowanych funkcjonalności i zmian

Poniżej przedstawiono zakres rozwoju platformy NEXORA ESPORTS. Celem jest rozbudowanie serwisu o nowe funkcje, poprawienie działania istniejących modułów oraz stworzenie kompletnego systemu zarządzania rozgrywkami e-sportowymi.

## 1\. Zakładka „Ogłoszenia”

Dodanie nowej zakładki umożliwiającej publikowanie ważnych informacji dotyczących platformy, lig i turniejów. Ogłoszenia będą mogły zawierać komunikaty organizacyjne, informacje o zapisach, zmianach terminów oraz aktualnościach.

## 2\. System rywalizacji – NEXORA PRO LEAGUE

Utworzenie ligi o nazwie NEXORA PRO LEAGUE, w której będzie mogło uczestniczyć maksymalnie 16 drużyn.

Zasady rozgrywek:

- Maksymalnie 16 drużyn.
- Każda drużyna rozgrywa 8 meczów.
- Wszystkie mecze rozgrywane są w formacie BO1.
- Za zwycięstwo drużyna otrzymuje 3 punkty.
- Za porażkę drużyna otrzymuje 0 punktów.
- Wyniki spotkań są uwzględniane w tabeli ligowej.
- Tabela prezentuje między innymi liczbę punktów, zwycięstw, porażek i rozegranych meczów.

## 3\. Ograniczenie przynależności zawodników do drużyn

Wprowadzenie zasady, zgodnie z którą jeden zawodnik może należeć wyłącznie do jednej drużyny jednocześnie.

System powinien automatycznie sprawdzać, czy dany zawodnik jest już przypisany do innego zespołu. Jeśli tak, dodanie go do kolejnej drużyny powinno być niemożliwe.

## 4\. Uprawnienia kapitana drużyny

Wprowadzenie systemu uprawnień, w którym zarządzanie drużyną jest dostępne wyłącznie dla jej kapitana.

Kapitan będzie mógł:

- Edytować informacje o drużynie.
- Zarządzać składem.
- Wysyłać zaproszenia do zawodników.
- Zarządzać zgłoszeniami drużyny do rozgrywek.

Pozostali użytkownicy będą mogli jedynie przeglądać profil drużyny i jej statystyki.

## 5\. System zaproszeń do drużyny

Zastąpienie automatycznego dodawania zawodników systemem zaproszeń.

Nowy mechanizm będzie działał następująco:

1. Kapitan wysyła zaproszenie do wybranego zawodnika.
2. Zawodnik otrzymuje powiadomienie.
3. Może zaakceptować lub odrzucić zaproszenie.
4. Po zaakceptowaniu zostaje przypisany do drużyny.

System musi również sprawdzać, czy zapraszany zawodnik nie należy już do innego zespołu.

## 6\. Profile zawodników

Utworzenie indywidualnych profili zawodników zawierających:

- Nick zawodnika.
- Zdjęcie profilowe.
- Historię rozegranych turniejów.
- Liczbę zwycięstw i porażek.
- Indywidualne statystyki.
- Aktualną przynależność do drużyny.

Profil będzie dostępny do przeglądania przez innych użytkowników platformy.

## 7\. Statystyki drużyn

Rozbudowanie profili drużyn o szczegółowe statystyki dotyczące ich wyników.

Profil drużyny będzie prezentował:

- Liczbę zwycięstw.
- Liczbę porażek.
- Liczbę remisów.
- Liczbę zdobytych punktów.
- Liczbę rozegranych meczów.
- Historię spotkań.
- Aktualny skład drużyny.

## 8\. System zgłaszania problemów

Dodanie funkcji umożliwiającej użytkownikom zgłaszanie problemów związanych z działaniem platformy lub przebiegiem rozgrywek.

Użytkownik będzie mógł:

- Opisać napotkany problem.
- Wybrać kategorię zgłoszenia.
- Przesłać zgłoszenie do administratora.
- Sprawdzić status zgłoszenia.

Administrator będzie miał możliwość przeglądania zgłoszeń, odpowiadania na nie oraz zmieniania ich statusu.

## 9\. Nazwa pierwszego turnieju

Winter Championship - Europe 2025

[start.gg](<https://www.start.gg/tournament/winter-championship-europe-2025/event/brawlhalla-championship-1v1/brackets/1860842/2742139?utm_source=chatgpt.com>)

# NEXORA WINTER CUP 2026

PIERWSZY TURNIEJ PLATFORMY NEXORA ESPORTS

## 10\. Zasady turnieju NEXORA WINTER CUP 2026

Turniej będzie rozgrywany w systemie pucharowym, z udziałem 8 pierwszych zarejestrowanych drużyn.

Zasady rozgrywek:

- Udział bierze 8 pierwszych zarejestrowanych drużyn.
- Turniej rozpoczyna się od ćwierćfinałów (1/4 finału).
- Wszystkie mecze poza finałem rozgrywane są w formacie BO3.
- Finał rozgrywany jest w formacie BO5.
- Losowanie map odbywa się krótko przed rozpoczęciem meczu.
- Zwycięskie drużyny przechodzą do kolejnego etapu drabinki.

Schemat turnieju:

## Drabinka turniejowa

8 drużyn • system pucharowy

Ćwierćfinały

Mecz 1

Drużyna 1 vs 2

BO3

Mecz 2

Drużyna 3 vs 4

BO3

Mecz 3

Drużyna 5 vs 6

BO3

Mecz 4

Drużyna 7 vs 8

BO3

Półfinały

Półfinał 1

Zwycięzcy 1 i 2

BO3

Półfinał 2

Zwycięzcy 3 i 4

BO3

Finał

Wielki finał

Zwycięzcy półfinałów

BO5

Uwaga: przy 8 drużynach pierwszą rundą jest ćwierćfinał (1/4), a nie 1/8 finału.

## 11\. Kompletny panel administratora

Stworzenie rozbudowanego panelu administracyjnego, który umożliwi zarządzanie wszystkimi najważniejszymi elementami platformy NEXORA ESPORTS.

Panel powinien zawierać następujące moduły:

| Moduł | Najważniejsze funkcje |
| --- | --- |
| Dashboard | Podsumowanie działania platformy i statystyki |
| Turnieje | Tworzenie, edycja i usuwanie turniejów |
| Ligi | Zarządzanie ligami i tabelami wyników |
| Drużyny | Przeglądanie i zarządzanie zespołami |
| Zawodnicy | Zarządzanie kontami i profilami |
| Mecze | Ustalanie terminów i zatwierdzanie wyników |
| Ogłoszenia | Publikowanie i edytowanie komunikatów |
| Zgłoszenia | Obsługa problemów zgłaszanych przez użytkowników |
| Zaproszenia | Kontrola zaproszeń i członkostwa w drużynach |
| Rankingi | Zarządzanie klasyfikacją zawodników i drużyn |
| Ustawienia | Zarządzanie konfiguracją platformy |
| Dziennik działań | Historia czynności wykonywanych przez administratorów |

Panel powinien posiadać system uprawnień, dzięki któremu poszczególni administratorzy będą mieli dostęp wyłącznie do przypisanych im funkcji.

## Podsumowanie projektu

Wprowadzenie powyższych zmian pozwoli rozbudować NEXORA ESPORTS z podstawowej strony turniejowej do kompleksowej platformy e-sportowej. System będzie umożliwiał organizowanie lig i turniejów, zarządzanie drużynami, prowadzenie statystyk zawodników oraz obsługę rozgrywek przez panel administratora.

Główne cele rozwoju:

- Rozbudowa funkcjonalności platformy.
- Poprawa bezpieczeństwa i systemu uprawnień.
- Automatyzacja zapisów i zarządzania drużynami.
- Wprowadzenie profili oraz statystyk.
- Stworzenie profesjonalnego systemu organizacji turniejów.

Dokument przedstawia planowane funkcjonalności do wdrożenia w projekcie NEXORA ESPORTS.

Jeśli chcesz, mogę:

- Dodaj szczegółowy plan implementacji NEXORA PRO LEAGUE z harmonogramem
- Rozwiń opis panelu administratora o przykładowy interfejs i role użytkowników
- Zaproponuj szczegółowe zasady i proces zarządzania systemem zaproszeń i uprawnień kapitana