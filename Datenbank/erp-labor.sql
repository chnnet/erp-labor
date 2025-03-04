-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: db22.variomedia.de
-- Erstellungszeit: 04. Mrz 2025 um 17:25
-- Server-Version: 8.0.41
-- PHP-Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `db48930`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer`
--

CREATE TABLE `benutzer` (
  `benutzer_id` int NOT NULL,
  `kuerzel` varchar(45) NOT NULL,
  `password` varbinary(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `hauptbuch`
--

CREATE TABLE `hauptbuch` (
  `ID` int NOT NULL,
  `journal_id` int NOT NULL DEFAULT '0',
  `buchungsdatum` date NOT NULL,
  `belegdatum` date NOT NULL,
  `kontonr` int NOT NULL,
  `soll_haben` enum('S','H') NOT NULL,
  `betrag` decimal(11,2) NOT NULL,
  `buchungstext` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `journal`
--

CREATE TABLE `journal` (
  `ID` int NOT NULL,
  `vorgang` enum('H','D','K','A','L') NOT NULL,
  `datum` datetime NOT NULL,
  `benutzer_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `journalzeile`
--

CREATE TABLE `journalzeile` (
  `ID` int NOT NULL,
  `journal_id` int NOT NULL DEFAULT '0',
  `journalzeile` int NOT NULL,
  `kontosoll` int NOT NULL,
  `kontohaben` int NOT NULL,
  `belegnr` bigint NOT NULL,
  `referenz` varchar(45) DEFAULT NULL,
  `betrag` decimal(11,2) NOT NULL DEFAULT '0.00',
  `status` enum('A','G','S') NOT NULL,
  `vorgang` enum('H','D','K','A','L') NOT NULL,
  `buchungstext` varchar(100) DEFAULT NULL,
  `buchungsdatum` date NOT NULL,
  `belegdatum` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kontenrahmen`
--

CREATE TABLE `kontenrahmen` (
  `ID` int NOT NULL,
  `bezeichnung` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kontenstamm`
--

CREATE TABLE `kontenstamm` (
  `ID` int NOT NULL,
  `ktorahmen_id` int NOT NULL,
  `kontonr` int NOT NULL,
  `bezeichnung` varchar(100) NOT NULL,
  `typ` enum('B','E','K','Z') NOT NULL,
  `klasse` int NOT NULL,
  `sammelkonto` tinyint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `nb_kassa`
--

CREATE TABLE `nb_kassa` (
  `nbk_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `nb_ust`
--

CREATE TABLE `nb_ust` (
  `nbu_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ust_buchungseinrichtungen`
--

CREATE TABLE `ust_buchungseinrichtungen` (
  `ustbuch_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ust_saetze`
--

CREATE TABLE `ust_saetze` (
  `id` int NOT NULL,
  `land` varchar(3) NOT NULL,
  `ust_code` varchar(10) DEFAULT NULL,
  `satz` decimal(5,2) DEFAULT NULL,
  `gueltig_ab` date DEFAULT NULL,
  `gueltig_bis` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  ADD PRIMARY KEY (`benutzer_id`);

--
-- Indizes für die Tabelle `hauptbuch`
--
ALTER TABLE `hauptbuch`
  ADD UNIQUE KEY `ID_UNIQUE` (`ID`),
  ADD KEY `hauptbuch_journal_idx` (`journal_id`),
  ADD KEY `hauptbuch_kontenstamm_idx` (`kontonr`);

--
-- Indizes für die Tabelle `journal`
--
ALTER TABLE `journal`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `journalzeile`
--
ALTER TABLE `journalzeile`
  ADD PRIMARY KEY (`journal_id`,`journalzeile`) USING BTREE,
  ADD UNIQUE KEY `ID_UNIQUE` (`ID`);

--
-- Indizes für die Tabelle `kontenrahmen`
--
ALTER TABLE `kontenrahmen`
  ADD UNIQUE KEY `ID_UNIQUE` (`ID`);

--
-- Indizes für die Tabelle `kontenstamm`
--
ALTER TABLE `kontenstamm`
  ADD PRIMARY KEY (`ktorahmen_id`,`kontonr`),
  ADD UNIQUE KEY `kontonr_UNIQUE` (`kontonr`),
  ADD UNIQUE KEY `ID_UNIQUE` (`ID`);

--
-- Indizes für die Tabelle `nb_kassa`
--
ALTER TABLE `nb_kassa`
  ADD PRIMARY KEY (`nbk_id`);

--
-- Indizes für die Tabelle `nb_ust`
--
ALTER TABLE `nb_ust`
  ADD PRIMARY KEY (`nbu_id`);

--
-- Indizes für die Tabelle `ust_buchungseinrichtungen`
--
ALTER TABLE `ust_buchungseinrichtungen`
  ADD PRIMARY KEY (`ustbuch_id`);

--
-- Indizes für die Tabelle `ust_saetze`
--
ALTER TABLE `ust_saetze`
  ADD PRIMARY KEY (`id`,`land`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `hauptbuch`
--
ALTER TABLE `hauptbuch`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `journal`
--
ALTER TABLE `journal`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `journalzeile`
--
ALTER TABLE `journalzeile`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kontenrahmen`
--
ALTER TABLE `kontenrahmen`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `kontenstamm`
--
ALTER TABLE `kontenstamm`
  MODIFY `ID` int NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `hauptbuch`
--
ALTER TABLE `hauptbuch`
  ADD CONSTRAINT `hauptbuch_journal` FOREIGN KEY (`journal_id`) REFERENCES `journalzeile` (`journal_id`),
  ADD CONSTRAINT `hauptbuch_kontenstamm` FOREIGN KEY (`kontonr`) REFERENCES `kontenstamm` (`kontonr`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
