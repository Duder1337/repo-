<?php
// Ustawienia połączenia z bazą (domyślne dla XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'turnieje');
define('DB_USER', 'root');
define('DB_PASS', '');
// Adres aplikacji w przeglądarce (folder w htdocs)
define('BASE_URL', '/turnieje');
// Po ilu dniach anulowany turniej znika z bazy (liczone od chwili anulowania w panelu admina)
define('ANULOWANE_USUWAJ_PO_DNIACH', 7);
