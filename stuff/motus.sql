-- Tables du jeu Motus (partie 4)
-- À importer après native_php_restful_api.sql (elles référencent la table `user`)

SET NAMES utf8mb4;

-- Mots à deviner (6 lettres, majuscules, sans accent)
DROP TABLE IF EXISTS `guess`;
DROP TABLE IF EXISTS `game`;
DROP TABLE IF EXISTS `word`;

CREATE TABLE `word` (
  `id` int NOT NULL AUTO_INCREMENT,
  `word` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une partie entre deux joueurs
--   player1_id        : celui qui lance la partie (il joue en premier)
--   current_player_id : joueur dont c'est le tour
--   turn_started_at   : début du tour en cours (pour le délai de 8 secondes)
--   status            : in_progress, won (mot trouvé), draw (8 propositions sans trouver : aucun gagnant)
CREATE TABLE `game` (
  `id` int NOT NULL AUTO_INCREMENT,
  `player1_id` int NOT NULL,
  `player2_id` int NOT NULL,
  `word` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_player_id` int NOT NULL,
  `status` enum('in_progress','won','draw') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `winner_id` int DEFAULT NULL,
  `turn_started_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_game_player1` (`player1_id`),
  KEY `fk_game_player2` (`player2_id`),
  CONSTRAINT `fk_game_player1` FOREIGN KEY (`player1_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_game_player2` FOREIGN KEY (`player2_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une proposition dans une partie
--   word   : mot proposé, NULL si le joueur a dépassé les 8 secondes
--   result : une lettre par case, R = rouge (bien placée), J = jaune (mal placée), B = bleu (absente)
CREATE TABLE `guess` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int NOT NULL,
  `player_id` int NOT NULL,
  `word` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_guess_game` (`game_id`),
  KEY `fk_guess_player` (`player_id`),
  CONSTRAINT `fk_guess_game` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guess_player` FOREIGN KEY (`player_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `word` (`word`) VALUES
('BALLON'), ('BANANE'), ('BATEAU'), ('BOUGIE'), ('BUREAU'), ('CADEAU'), ('CAHIER'), ('CAMION'),
('CANARD'), ('CASTOR'), ('CERISE'), ('CHAISE'), ('CHEVAL'), ('CINEMA'), ('CITRON'), ('COUSIN'),
('CRAYON'), ('DESERT'), ('ETOILE'), ('GATEAU'), ('GIRAFE'), ('JARDIN'), ('LAPINS'), ('LIVRES'),
('MAISON'), ('MARCHE'), ('MIROIR'), ('MOTEUR'), ('MOUCHE'), ('MOUTON'), ('NATURE'), ('NUAGES'),
('OISEAU'), ('ORANGE'), ('PAPIER'), ('PLAGES'), ('PLANTE'), ('POULET'), ('RADEAU'), ('RENARD'),
('REQUIN'), ('SAISON'), ('SOLEIL'), ('TOMATE'), ('VALISE'), ('VOYAGE');
