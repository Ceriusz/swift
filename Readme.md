Aby uruchomić projekt należy wykonać następujące polecenia z folderu .docker:
docker compose up -d
docker-compose run php composer install
docker-compose run php php bin/console doctrine:migrations:migrate
docker-compose run php php bin/console doctrine:fixtures:load
Następnie w przeglądarce wpisujemy localhost (bez podawania portów).

Fixtury utworzą nam 5 użytkowników o następujących danych:
login: grzegorz.cerowski+0@gmail.com hasło:12#$asDF0
login: grzegorz.cerowski+1@gmail.com hasło:12#$asDF1
login: grzegorz.cerowski+2@gmail.com hasło:12#$asDF2
login: grzegorz.cerowski+3@gmail.com hasło:12#$asDF3
login: grzegorz.cerowski+4@gmail.com hasło:12#$asDF4

W folerze głównym przykładowy plik CSV z 2 użytkownikami.

Wiem, że pliku .env nie powinno się zamieszczać w repozytorium, ale dla ułatwienia rozstawienia projektu zamieściłem ten plik w repo.
Jedyne co należy w nim zmienić to hasło do mailera (linia 35):
MAILER_DSN=gmail://grzegorz.cerowski:XXX@default
w miejsce XXX należy podać hasło, które wyślę mailem