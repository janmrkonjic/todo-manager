# To-Do Manager

To-Do Manager je spletna aplikacija za učinkovito upravljanje vsakodnevnih nalog. Namenjena je tako posameznikom kot skupinam, ki želijo organizirati svoje delo, spremljati napredek in sodelovati pri skupnih projektih.

## Opis aplikacije

Aplikacija omogoča uporabnikom enostavno dodajanje, urejanje in brisanje nalog. Podpira delo v skupinah, kjer lahko vodje in člani sodelujejo na skupnih seznamih opravil. Poleg tega ponuja vpogled v statistiko opravljenih nalog in pošiljanje obvestil za neopravljene naloge.

### Glavne funkcionalnosti:

- **Upravljanje nalog:** Dodajanje, urejanje, brisanje in pregled nalog (naslov, opis, rok, status).
- **Skupinsko delo:** Ustvarjanje skupin, vloge (vodja, član), skupni seznami nalog.
- **Uporabniški računi:** Registracija, prijava in upravljanje profilov.
- **Statistika:** Grafični prikaz opravljenih nalog (uporaba Chart.js).
- **Dinamičnost:** Osveževanje podatkov brez ponovnega nalaganja strani (AJAX).
- **Obvestila:** Pošiljanje e-poštnih opomnikov za neopravljene naloge.
- **Varnost:** Varna povezava z bazo podatkov (MySQL PDO).

## Tehnologije

Projekt je zgrajen z uporabo naslednjih tehnologij:

- **Backend:** PHP (teče na Apache strežniku)
- **Frontend:** HTML, CSS, JavaScript (vključno s Chart.js)
- **Podatkovna baza:** MySQL
- **Kontejnerizacija:** Docker & Docker Compose
- **Orodja:**
  - **phpMyAdmin:** Za upravljanje podatkovne baze.
  - **Inbucket:** Za testiranje pošiljanja e-pošte (SMTP).

## Navodila za zagon

Za zagon aplikacije potrebujete nameščen [Docker](https://www.docker.com/) in [Docker Compose](https://docs.docker.com/compose/).

1. **Klonirajte repozitorij:**

   ```bash
   git clone https://github.com/janmrkonjic/todo-manager.git
   ```

2. **Zaženite kontejnerje:**
   V korenski mapi projekta (kjer se nahaja `docker-compose.yml`) zaženite ukaz:

   ```bash
   docker-compose up -d
   ```

   Ta ukaz bo zgradil in zagnal potrebne storitve (spletni strežnik, MySQL, phpMyAdmin, Inbucket).

3. **Dostop do aplikacije:**
   - **Spletna aplikacija:** [http://localhost:8000](http://localhost:8000)
   - **phpMyAdmin:** [http://localhost:8001](http://localhost:8001)
     - Uporabniško ime: `root`
     - Geslo: `superVarnoGeslo`
   - **Inbucket (E-pošta):** [http://localhost:9000](http://localhost:9000)

## Struktura projekta

- `www/`: Izvorna koda spletne aplikacije (PHP, JS, CSS).
- `data/`: Podatki podatkovne baze (trajna hramba).
- `docker-compose.yml`: Konfiguracija Docker storitev.
