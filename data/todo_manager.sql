-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Gostitelj: podatkovna-baza
-- Čas nastanka: 19. dec 2025 ob 19.19
-- Različica strežnika: 9.4.0
-- Različica PHP: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Zbirka podatkov: `todo_manager`
--

-- --------------------------------------------------------

--
-- Struktura tabele `ClaniSkupine`
--

CREATE TABLE `ClaniSkupine` (
  `id` int NOT NULL,
  `datum_prikljucitve` datetime NOT NULL,
  `uporabnik_id` int NOT NULL,
  `skupina_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `ClaniSkupine`
--

INSERT INTO `ClaniSkupine` (`id`, `datum_prikljucitve`, `uporabnik_id`, `skupina_id`) VALUES
(6, '2025-11-12 20:52:27', 10, 4),
(8, '2025-11-12 20:55:18', 11, 4),
(12, '2025-11-12 21:32:23', 9, 7),
(13, '2025-11-12 21:32:52', 10, 7),
(14, '2025-11-12 21:32:56', 11, 7),
(17, '2025-11-13 13:14:12', 10, 9),
(18, '2025-11-13 13:15:06', 11, 9),
(19, '2025-11-14 07:55:16', 10, 10),
(20, '2025-11-14 07:55:29', 11, 10),
(23, '2025-12-01 09:25:55', 14, 4),
(25, '2025-12-01 12:29:52', 14, 10),
(26, '2025-12-01 12:54:54', 10, 13),
(27, '2025-12-01 12:55:28', 15, 4),
(28, '2025-12-19 18:50:05', 16, 4),
(29, '2025-12-19 19:06:40', 17, 4),
(30, '2025-12-19 19:10:16', 9, 4);

-- --------------------------------------------------------

--
-- Struktura tabele `DodelitevNaloge`
--

CREATE TABLE `DodelitevNaloge` (
  `id` int NOT NULL,
  `datum_dodelitve` datetime NOT NULL,
  `naloga_id` int NOT NULL,
  `uporabnik_id` int DEFAULT NULL,
  `skupina_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `DodelitevNaloge`
--

INSERT INTO `DodelitevNaloge` (`id`, `datum_dodelitve`, `naloga_id`, `uporabnik_id`, `skupina_id`) VALUES
(38, '2025-11-18 16:16:02', 41, NULL, 10),
(39, '2025-11-18 16:16:25', 42, 10, NULL),
(40, '2025-11-18 16:16:56', 43, 10, NULL),
(41, '2025-11-18 16:17:31', 44, 11, NULL),
(42, '2025-11-18 16:22:04', 45, 11, NULL),
(43, '2025-11-18 16:29:16', 46, 10, NULL),
(44, '2025-11-18 16:34:57', 47, 10, NULL),
(45, '2025-11-18 16:35:19', 48, NULL, 10),
(46, '2025-11-18 16:43:22', 49, NULL, 10),
(48, '2025-11-18 17:36:52', 51, 10, NULL),
(50, '2025-11-24 14:08:18', 53, 12, NULL),
(52, '2025-12-01 09:02:32', 55, NULL, NULL),
(53, '2025-12-01 09:03:39', 56, 10, NULL),
(55, '2025-12-01 09:07:48', 58, 10, NULL),
(57, '2025-12-01 09:23:14', 60, 10, NULL),
(58, '2025-12-01 09:25:26', 61, 14, NULL),
(59, '2025-12-01 09:27:50', 62, NULL, 4),
(60, '2025-12-01 09:28:16', 63, 10, NULL),
(61, '2025-12-01 09:32:18', 64, 10, NULL),
(62, '2025-12-01 09:32:39', 65, 10, NULL),
(63, '2025-12-01 09:37:55', 66, NULL, 4),
(64, '2025-12-01 09:38:27', 67, NULL, 9),
(65, '2025-12-01 09:46:25', 68, NULL, 9),
(66, '2025-12-01 12:07:14', 69, NULL, 10),
(67, '2025-12-01 12:10:19', 70, 9, NULL),
(68, '2025-12-01 12:12:07', 71, 9, NULL),
(69, '2025-12-01 12:12:49', 72, 11, NULL),
(70, '2025-12-01 12:13:06', 73, 11, NULL),
(71, '2025-12-01 12:22:15', 74, 10, NULL),
(72, '2025-12-01 12:55:04', 75, NULL, 13),
(73, '2025-12-01 13:08:26', 76, 10, NULL),
(75, '2025-12-19 18:37:49', 78, 10, NULL),
(76, '2025-12-19 18:51:20', 79, NULL, 4);

-- --------------------------------------------------------

--
-- Struktura tabele `Komentar`
--

CREATE TABLE `Komentar` (
  `id` int NOT NULL,
  `naloga_id` int NOT NULL,
  `uporabnik_id` int NOT NULL,
  `besedilo` text NOT NULL,
  `datum_vnosa` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `Komentar`
--

INSERT INTO `Komentar` (`id`, `naloga_id`, `uporabnik_id`, `besedilo`, `datum_vnosa`) VALUES
(27, 45, 11, 'sdds', '2025-11-18 16:22:15'),
(29, 49, 10, 'sdssd', '2025-11-18 16:43:31'),
(34, 51, 10, 'fgdfg', '2025-11-18 19:04:46'),
(35, 51, 10, 'test', '2025-11-18 19:06:46'),
(36, 51, 10, 'sds', '2025-11-18 19:06:52'),
(37, 51, 10, 'dsdd', '2025-11-18 19:06:55'),
(38, 51, 10, 'sdsd', '2025-11-18 19:09:19'),
(40, 44, 11, 'test', '2025-11-24 13:24:18'),
(41, 60, 10, 'delaaa', '2025-12-01 09:26:49'),
(42, 70, 9, 'bdshbsdh', '2025-12-01 12:11:54'),
(43, 70, 9, 'sdasd', '2025-12-01 12:11:57'),
(44, 74, 10, 'ysx', '2025-12-01 12:54:24'),
(45, 69, 10, 'sasas', '2025-12-01 13:08:15'),
(46, 79, 16, 'komentar', '2025-12-19 18:51:47');

-- --------------------------------------------------------

--
-- Struktura tabele `Naloga`
--

CREATE TABLE `Naloga` (
  `id` int NOT NULL,
  `naslov` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `opis` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rok_izvedbe` datetime NOT NULL,
  `datum_ustvarjenja` datetime NOT NULL,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `datum_zakljucka` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `Naloga`
--

INSERT INTO `Naloga` (`id`, `naslov`, `opis`, `rok_izvedbe`, `datum_ustvarjenja`, `status`, `datum_zakljucka`) VALUES
(41, 'To je sam za vse uporabnike', 'gdsfgujgfbjdf', '2025-11-20 17:16:00', '2025-11-18 16:16:02', 'neopravljeno', NULL),
(42, 'Sam ena krneki naloga', 'To je cist nepotrebno', '2025-11-20 17:16:00', '2025-11-18 16:16:25', 'opravljeno', '2025-11-18 16:19:53'),
(43, 'Se ena naloga sam za mene', 'To je sam za mene', '2025-11-28 17:16:00', '2025-11-18 16:16:56', 'opravljeno', '2025-11-18 16:18:44'),
(44, 'Sam en brezvezen test', 'dhsbkdsbfhjhsf', '2025-11-28 17:17:00', '2025-11-18 16:17:31', 'neopravljeno', NULL),
(45, 'ddfds', 'fdfddsffddsf', '2025-11-19 17:22:00', '2025-11-18 16:22:04', 'neopravljeno', NULL),
(46, 'testna naloga', 'hjsnjcksdbjcds', '2025-11-22 17:29:00', '2025-11-18 16:29:16', 'opravljeno', '2025-11-18 16:35:02'),
(47, 'nova naloga', 'djsbfjdsbfdbkafffd', '2025-12-03 17:34:00', '2025-11-18 16:34:57', 'opravljeno', '2025-11-18 16:43:54'),
(48, 'kr neki naloga', 'dfdfgfgfgfgff', '2025-11-20 17:35:00', '2025-11-18 16:35:19', 'opravljeno', '2025-11-18 16:35:36'),
(49, 'ddd', 'ddddd', '2025-11-20 17:43:00', '2025-11-18 16:43:22', 'neopravljeno', NULL),
(51, 'efggfgfr', 'rfgfhhtztz', '2025-11-20 18:36:00', '2025-11-18 17:36:52', 'neopravljeno', NULL),
(53, 'Samo za testiranje emailov', 'To je naloga s katero lahko testiram email', '2025-11-25 15:10:00', '2025-11-24 14:08:18', 'neopravljeno', NULL),
(55, 'Testna naloga za opomnik', 'Ta naloga poteče čez natanko 24 ur. Preverjamo pošiljanje emaila.', '2025-12-02 09:02:32', '2025-12-01 09:02:32', 'neopravljeno', NULL),
(56, 'Da vidim ce dela opomnik...', 'Upam da bo delal', '2025-12-01 11:05:00', '2025-12-01 09:03:39', 'opravljeno', '2025-12-01 09:10:14'),
(58, 'Da vidim ce dela opomnik...', 'Upam da bo delalo', '2025-12-02 10:08:00', '2025-12-01 09:07:48', 'opravljeno', '2025-12-01 09:10:16'),
(60, 'Da vidim ce dela email...', 'Upam', '2025-12-10 10:23:00', '2025-12-01 09:23:14', 'neopravljeno', NULL),
(61, 'dsd', 'dgdfdfgfd', '2025-12-18 10:25:00', '2025-12-01 09:25:26', 'neopravljeno', NULL),
(62, 'dsd', 'sdsdds', '2025-12-11 10:27:00', '2025-12-01 09:27:50', 'opravljeno', '2025-12-01 12:14:01'),
(63, 'fbfggf', 'gfhgfhgfgh', '2025-12-18 10:28:00', '2025-12-01 09:28:16', 'opravljeno', '2025-12-01 09:32:57'),
(64, 'nova naloga', 'dadasad', '2025-12-16 10:32:00', '2025-12-01 09:32:18', 'neopravljeno', NULL),
(65, 'Naloga za velenjcane', 'hgthghghgh', '2025-12-17 10:32:00', '2025-12-01 09:32:39', 'opravljeno', '2025-12-01 09:32:59'),
(66, 'oooopaaaa', 'pri bavdku', '2025-12-10 10:37:00', '2025-12-01 09:37:55', 'opravljeno', '2025-12-01 12:56:29'),
(67, 'za test', 'sdasdasdsda', '2025-12-11 10:38:00', '2025-12-01 09:38:27', 'neopravljeno', NULL),
(68, 'Se ena naloga1', 'sfsdfdfsfddsfds', '2025-12-18 10:46:00', '2025-12-01 09:46:25', 'opravljeno', '2025-12-01 13:08:03'),
(69, 'blabla', 'jdsfnjsdbfjk', '2025-12-11 13:07:00', '2025-12-01 12:07:14', 'neopravljeno', NULL),
(70, 'bdhsfbhdsb', 'hbdhbvhd', '2025-12-10 13:10:00', '2025-12-01 12:10:19', 'neopravljeno', NULL),
(71, 'se ena', 'jndsfjkdsnjknsdkj', '2025-12-10 13:12:00', '2025-12-01 12:12:07', 'neopravljeno', NULL),
(72, 'testna naloga', 'To je samo ena za test', '2025-12-01 16:12:00', '2025-12-01 12:12:49', 'neopravljeno', NULL),
(73, 'se ena', 'dhijdsj', '2025-12-01 15:13:00', '2025-12-01 12:13:06', 'neopravljeno', NULL),
(74, 'Danasnja naloga', 'To je naloga za danes', '2025-12-01 18:22:00', '2025-12-01 12:22:15', 'neopravljeno', NULL),
(75, 'asdasd', 'sds', '2025-12-10 13:55:00', '2025-12-01 12:55:04', 'neopravljeno', NULL),
(76, 'sasd', 'sasas', '2025-12-26 14:08:00', '2025-12-01 13:08:26', 'neopravljeno', NULL),
(78, 'to je naloga', 'jdsnjkdj', '2025-12-26 19:37:00', '2025-12-19 18:37:49', 'neopravljeno', NULL),
(79, 'velenjska', 'sam za velenjcane', '2025-12-24 19:51:00', '2025-12-19 18:51:20', 'neopravljeno', NULL);

-- --------------------------------------------------------

--
-- Struktura tabele `Skupina`
--

CREATE TABLE `Skupina` (
  `id` int NOT NULL,
  `ime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `vodja_id` int DEFAULT NULL,
  `barva` varchar(7) DEFAULT '#17a2b8',
  `datum_ustvarjenja` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `Skupina`
--

INSERT INTO `Skupina` (`id`, `ime`, `vodja_id`, `barva`, `datum_ustvarjenja`) VALUES
(4, 'Velenjcani', 10, '#17a2b8', '2025-11-12 20:52:27'),
(7, 'testna skupina', 9, '#8df953', '2025-11-12 21:32:23'),
(9, 'testetst', 10, '#ffea00', '2025-11-13 13:14:12'),
(10, 'blabla', 10, '#ff1ac2', '2025-11-14 07:55:16'),
(13, 'yxyx', 10, '#b0b0b0', '2025-12-01 12:54:54');

-- --------------------------------------------------------

--
-- Struktura tabele `Uporabnik`
--

CREATE TABLE `Uporabnik` (
  `id` int NOT NULL,
  `uporabnisko_ime` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `geslo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `datum_registracije` datetime NOT NULL,
  `vloga_id` int DEFAULT NULL,
  `profilna_slika` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `Uporabnik`
--

INSERT INTO `Uporabnik` (`id`, `uporabnisko_ime`, `email`, `geslo`, `datum_registracije`, `vloga_id`, `profilna_slika`) VALUES
(1, 'jan', 'jan.mrkonjic2005@gmail.com', '$2y$12$x2irbyTxzfJHSV.xwhkWru83yTgAQ7ncR9cE4z7.5UEx7Gywftriy', '2025-11-12 19:12:02', 1, NULL),
(8, 'vodja', 'vodja@gmail.com', '$2y$12$A6vX0nQaSgprI0mCXd8WwueGGU7hLhbhevRXS9D6Bhh3eboTgs7qe', '2025-11-12 19:15:55', 4, NULL),
(9, 'clan', 'clan@gmail.com', '$2y$12$/DlnSBa5DZNiXXHl65omvOSDc6cINOAjGjBnhrWzdAPFAhdQnFqoi', '2025-11-12 19:16:52', 4, NULL),
(10, 'uporabnik', 'uporabnik@gmail.com', '$2y$12$MohjWpE.IcTVeUv0H4joM.CDGpe8kjMvaxSw95UxheAfuFc9WS6CK', '2025-11-12 19:17:18', 4, 'user_10_1763491692.png'),
(11, 'uporabnik2', 'uporabnik2@gmail.com', '$2y$12$q0ig02mCH5y/3Y5jWrcU5.3qZeSHj399wvwKQcquyC3KJRPUPDI8.', '2025-11-12 20:29:23', 4, NULL),
(12, 'testni_uporabnik', 'sopowe2117@moondyal.com', '$2y$12$7efd4.agp6zACxS7yRec2.KO81N6R3YpOwU.Bdd0dh8tp/yIpiRU2', '2025-11-24 14:07:45', 4, NULL),
(14, 'test', 'test@gmail.com', '$2y$12$sLbbY0ITowbwcxdDPxJGHuK2TWBbjKLS5DIespHuqdfqgLQkybG8O', '2025-12-01 09:24:57', 4, NULL),
(15, 'novi_uporabnik', 'novi@gmail.com', '$2y$12$wIBnu.ioctCl5JpPoJXKteBycfAdhpZzxAPdiY80Thl6NxXgsBqTu', '2025-12-01 10:05:15', 4, NULL),
(16, 'velenjcan', 'velenjcan@gmail.com', '$2y$12$HRChmebdmqdOS/J41A0Pzeb47zn6/q7z1VjjOZgc/VwQPyS0FE9gC', '2025-12-19 18:49:57', 4, NULL),
(17, 'Telefonski uporabnik', 'telefon@gmail.com', '$2y$12$oTFhAcl7kkFImq/MOG2Ikud8K5oo7EK.S9ZHmhqql9bfPd7QvEcVm', '2025-12-19 19:06:28', 4, NULL);

-- --------------------------------------------------------

--
-- Struktura tabele `Vloga`
--

CREATE TABLE `Vloga` (
  `id` int NOT NULL,
  `naziv` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Odloži podatke za tabelo `Vloga`
--

INSERT INTO `Vloga` (`id`, `naziv`) VALUES
(1, 'Administrator'),
(4, 'Uporabnik');

--
-- Indeksi zavrženih tabel
--

--
-- Indeksi tabele `ClaniSkupine`
--
ALTER TABLE `ClaniSkupine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_clani_uporabnik` (`uporabnik_id`),
  ADD KEY `fk_clani_skupina` (`skupina_id`);

--
-- Indeksi tabele `DodelitevNaloge`
--
ALTER TABLE `DodelitevNaloge`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dodelitev_naloga` (`naloga_id`),
  ADD KEY `fk_dodelitev_uporabnik` (`uporabnik_id`),
  ADD KEY `fk_dodelitev_skupina` (`skupina_id`);

--
-- Indeksi tabele `Komentar`
--
ALTER TABLE `Komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `naloga_id` (`naloga_id`),
  ADD KEY `uporabnik_id` (`uporabnik_id`);

--
-- Indeksi tabele `Naloga`
--
ALTER TABLE `Naloga`
  ADD PRIMARY KEY (`id`);

--
-- Indeksi tabele `Skupina`
--
ALTER TABLE `Skupina`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_skupina_vodja` (`vodja_id`);

--
-- Indeksi tabele `Uporabnik`
--
ALTER TABLE `Uporabnik`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_uporabnik_vloga` (`vloga_id`);

--
-- Indeksi tabele `Vloga`
--
ALTER TABLE `Vloga`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT zavrženih tabel
--

--
-- AUTO_INCREMENT tabele `ClaniSkupine`
--
ALTER TABLE `ClaniSkupine`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT tabele `DodelitevNaloge`
--
ALTER TABLE `DodelitevNaloge`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT tabele `Komentar`
--
ALTER TABLE `Komentar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT tabele `Naloga`
--
ALTER TABLE `Naloga`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT tabele `Skupina`
--
ALTER TABLE `Skupina`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT tabele `Uporabnik`
--
ALTER TABLE `Uporabnik`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT tabele `Vloga`
--
ALTER TABLE `Vloga`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Omejitve tabel za povzetek stanja
--

--
-- Omejitve za tabelo `ClaniSkupine`
--
ALTER TABLE `ClaniSkupine`
  ADD CONSTRAINT `fk_clani_skupina` FOREIGN KEY (`skupina_id`) REFERENCES `Skupina` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_clani_uporabnik` FOREIGN KEY (`uporabnik_id`) REFERENCES `Uporabnik` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omejitve za tabelo `DodelitevNaloge`
--
ALTER TABLE `DodelitevNaloge`
  ADD CONSTRAINT `fk_dodelitev_naloga` FOREIGN KEY (`naloga_id`) REFERENCES `Naloga` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dodelitev_skupina` FOREIGN KEY (`skupina_id`) REFERENCES `Skupina` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dodelitev_uporabnik` FOREIGN KEY (`uporabnik_id`) REFERENCES `Uporabnik` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omejitve za tabelo `Komentar`
--
ALTER TABLE `Komentar`
  ADD CONSTRAINT `komentar_ibfk_1` FOREIGN KEY (`naloga_id`) REFERENCES `Naloga` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `komentar_ibfk_2` FOREIGN KEY (`uporabnik_id`) REFERENCES `Uporabnik` (`id`) ON DELETE CASCADE;

--
-- Omejitve za tabelo `Skupina`
--
ALTER TABLE `Skupina`
  ADD CONSTRAINT `fk_skupina_vodja` FOREIGN KEY (`vodja_id`) REFERENCES `Uporabnik` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Omejitve za tabelo `Uporabnik`
--
ALTER TABLE `Uporabnik`
  ADD CONSTRAINT `fk_uporabnik_vloga` FOREIGN KEY (`vloga_id`) REFERENCES `Vloga` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
