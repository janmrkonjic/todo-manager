CREATE DATABASE IF NOT EXISTS todo_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE todo_manager;

CREATE TABLE IF NOT EXISTS Vloga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naziv VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS Uporabnik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uporabnisko_ime VARCHAR(50),
    email VARCHAR(100),
    geslo VARCHAR(255),
    datum_registracije DATETIME,
    vloga_id INT,
    CONSTRAINT fk_uporabnik_vloga
        FOREIGN KEY (vloga_id)
        REFERENCES Vloga(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS Skupina (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ime VARCHAR(100),
    datum_ustvarjenja DATETIME
);

CREATE TABLE IF NOT EXISTS ClaniSkupine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum_prikljucitve DATETIME,
    uporabnik_id INT,
    skupina_id INT,
    CONSTRAINT fk_clani_uporabnik
        FOREIGN KEY (uporabnik_id)
        REFERENCES Uporabnik(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_clani_skupina
        FOREIGN KEY (skupina_id)
        REFERENCES Skupina(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS Naloga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    naslov VARCHAR(100),
    opis VARCHAR(255),
    rok_izvedbe DATETIME,
    datum_ustvarjenja DATETIME,
    status VARCHAR(45),
    datum_zakljucka DATETIME
);

CREATE TABLE IF NOT EXISTS DodelitevNaloge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum_dodelitve DATETIME,
    naloga_id INT,
    uporabnik_id INT,
    skupina_id INT,
    CONSTRAINT fk_dodelitev_naloga
        FOREIGN KEY (naloga_id)
        REFERENCES Naloga(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_dodelitev_uporabnik
        FOREIGN KEY (uporabnik_id)
        REFERENCES Uporabnik(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_dodelitev_skupina
        FOREIGN KEY (skupina_id)
        REFERENCES Skupina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);
