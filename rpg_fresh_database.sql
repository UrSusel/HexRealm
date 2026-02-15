-- RPG Game Database - Fresh Setup
-- This SQL script creates a complete RPG database with quests, guilds, reputation system
-- Compatible with MySQL 5.7+

/* ========== DISABLE FOREIGN KEY CHECKS ========== */
SET FOREIGN_KEY_CHECKS = 0;

/* ========== CREATE TABLES ========== */

CREATE TABLE IF NOT EXISTS `worlds` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `is_tutorial` boolean DEFAULT FALSE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(50) NOT NULL,
  `base_hp` int(11) DEFAULT 100,
  `base_energy` int(11) DEFAULT 10,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `characters` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT 'Nameless',
  `class_id` int(11) DEFAULT NULL,
  `hp` int(11) DEFAULT 100,
  `max_hp` int(11) DEFAULT 100,
  `energy` int(11) DEFAULT 10,
  `max_energy` int(11) DEFAULT 10,
  `base_attack` int(11) DEFAULT 1,
  `base_defense` int(11) DEFAULT 0,
  `stat_points` int(11) DEFAULT 0,
  `skill_points` int(11) DEFAULT 0,
  `pos_x` int(11) DEFAULT 0,
  `pos_y` int(11) DEFAULT 0,
  `world_id` int(11) DEFAULT 1,
  `tutorial_completed` boolean DEFAULT FALSE,
  `xp` int(11) DEFAULT 0,
  `max_xp` int(11) DEFAULT 100,
  `level` int(11) DEFAULT 1,
  `steps_buffer` int(11) DEFAULT 0,
  `in_combat` boolean DEFAULT FALSE,
  `enemy_hp` int(11) DEFAULT 0,
  `enemy_max_hp` int(11) DEFAULT 0,
  `combat_state` text DEFAULT NULL,
  `gold` int(11) DEFAULT 0,
  `duel_id` int(11) DEFAULT NULL,
  `last_seen` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`world_id`) REFERENCES `worlds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `power` int(11) DEFAULT 0,
  `optimal_class_id` int(11),
  `icon` varchar(10),
  `price` int(11) DEFAULT 10,
  `rarity` varchar(20) DEFAULT 'common',
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `character_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `is_equipped` boolean DEFAULT FALSE,
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_char_item` (`character_id`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `duel_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `challenger_id` int(11),
  `target_id` int(11),
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `active_duels` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `player1_id` int(11),
  `player2_id` int(11),
  `current_turn_id` int(11),
  `combat_state` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `turn_start_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `map_tiles` (
  `world_id` int(11) NOT NULL,
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  PRIMARY KEY (`world_id`, `x`, `y`),
  FOREIGN KEY (`world_id`) REFERENCES `worlds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `saved_positions` (
  `character_id` int(11) NOT NULL,
  `world_id` int(11) NOT NULL,
  `pos_x` int(11) DEFAULT 0,
  `pos_y` int(11) DEFAULT 0,
  PRIMARY KEY (`character_id`, `world_id`),
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`world_id`) REFERENCES `worlds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `shop_type` varchar(50) DEFAULT NULL,
  `required_items` json DEFAULT NULL,
  `reward_gold` int(11) DEFAULT 0,
  `reward_reputation` int(11) DEFAULT 1,
  `min_level` int(11) DEFAULT 1,
  `max_level` int(11) DEFAULT NULL,
  `guild_required` boolean DEFAULT FALSE,
  `repeatable` boolean DEFAULT FALSE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `character_quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `character_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `progress` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_char_quest_active` (`character_id`, `quest_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reputation` (
  `character_id` int(11) NOT NULL PRIMARY KEY,
  `points` int(11) DEFAULT 0,
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `guilds` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `description` text,
  `required_reputation` int(11) DEFAULT 10,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `guild_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `guild_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `rank` varchar(50) DEFAULT 'member',
  `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`guild_id`) REFERENCES `guilds`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_char_guild` (`character_id`, `guild_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_settings` (
  `setting_key` varchar(100) PRIMARY KEY,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ========== INSERT INITIAL DATA ========== */

-- Insert Tutorial World
INSERT INTO `worlds` (`id`, `name`, `width`, `height`, `is_tutorial`) VALUES
(1, 'Tutorial Island', 15, 15, TRUE)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `width`=VALUES(`width`), `height`=VALUES(`height`), `is_tutorial`=VALUES(`is_tutorial`);

-- Insert Test User
INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'Tester', 'admin')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`), `password`=VALUES(`password`);

-- Insert Classes
INSERT INTO `classes` (`id`, `name`, `base_hp`, `base_energy`, `description`) VALUES
(1, 'Warrior', 150, 8, 'Master of the sword.'),
(2, 'Mage', 80, 12, 'Wields magic.'),
(3, 'Rogue', 100, 10, 'Fast and agile.')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `base_hp`=VALUES(`base_hp`), `base_energy`=VALUES(`base_energy`), `description`=VALUES(`description`);

-- Insert Items (Weapons, Armor, Consumables, Drops)
INSERT INTO `items` (`id`, `name`, `type`, `power`, `optimal_class_id`, `icon`, `price`, `rarity`, `description`) VALUES
(1, 'Rusty Sword', 'weapon', 10, 1, '⚔️', 350, 'common', 'A basic rusty sword.'),
(2, 'Old Staff', 'weapon', 12, 2, '🪄', 350, 'common', 'A wooden staff.'),
(3, 'Dagger', 'weapon', 9, 3, '🗡️', 350, 'common', 'Sharp but small.'),
(4, 'Leather Jacket', 'armor', 5, 3, '👕', 450, 'common', 'Basic protection.'),
(5, 'Plate Armor', 'armor', 15, 1, '🛡️', 450, 'common', 'Heavy iron armor.'),
(6, 'Apprentice Robe', 'armor', 3, 2, '👘', 450, 'common', 'Cloth robe.'),
(7, 'Health Potion', 'consumable', 50, NULL, '🧪', 100, 'uncommon', 'Heals 50 HP'),
(8, 'Bandage', 'consumable', 20, NULL, '🩹', 20, 'common', 'Heals 20 HP'),
(20, 'Rat Tail', 'drop', 0, NULL, '🐀', 12, 'common', 'A tail from a sewer rat.'),
(21, 'Goblin Ear', 'drop', 0, NULL, '👂', 36, 'uncommon', 'A trophy from a goblin.'),
(22, 'Bandit Insignia', 'drop', 0, NULL, '🎖️', 75, 'rare', 'Stolen from a desert bandit.'),
(23, 'Lava Core', 'drop', 0, NULL, '🔥', 150, 'rare', 'Warm to the touch.'),
(24, 'Demon Horn', 'drop', 0, NULL, '😈', 360, 'very_rare', 'Radiates dark energy.')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `price`=VALUES(`price`), `rarity`=VALUES(`rarity`), `description`=VALUES(`description`);

-- Insert Tutorial Character
INSERT INTO `characters` (`id`, `user_id`, `name`, `hp`, `max_hp`, `energy`, `max_energy`, `base_attack`, `world_id`, `tutorial_completed`) VALUES
(1, 1, 'Hero', 100, 100, 10, 10, 1, 1, 0)
ON DUPLICATE KEY UPDATE `user_id`=VALUES(`user_id`), `name`=VALUES(`name`), `hp`=VALUES(`hp`), `max_hp`=VALUES(`max_hp`), `energy`=VALUES(`energy`), `max_energy`=VALUES(`max_energy`), `base_attack`=VALUES(`base_attack`), `world_id`=VALUES(`world_id`), `tutorial_completed`=VALUES(`tutorial_completed`);

-- Insert Tutorial Inventory (Consumables)
INSERT INTO `inventory` (`character_id`, `item_id`, `quantity`) VALUES
(1, 7, 3),
(1, 8, 3)
ON DUPLICATE KEY UPDATE `quantity`=VALUES(`quantity`);

-- Insert Guilds
INSERT INTO `guilds` (`id`, `name`, `description`, `required_reputation`) VALUES
(1, 'Warriors Guild', 'A guild for brave warriors seeking glory in battle.', 10),
(2, 'Mages Collegium', 'An academy for arcane practitioners and scholars.', 10),
(3, 'Thieves Brotherhood', 'A secretive organization of rogues and assassins.', 10),
(4, 'Merchants Union', 'A trading guild for those who prefer gold to glory.', 15)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`), `required_reputation`=VALUES(`required_reputation`);

-- Insert Quests (22 total: 7 standard + 15 guild repeatable)
INSERT INTO `quests` (`id`, `title`, `description`, `shop_type`, `required_items`, `reward_gold`, `reward_reputation`, `min_level`, `max_level`, `guild_required`, `repeatable`) VALUES
(1, 'Rat Problem', 'The village sewers are infested with rats. Bring me 5 rat tails.', 'blacksmith', '[{"id": 20, "quantity": 5}]', 30, 1, 1, 10, FALSE, TRUE),
(2, 'Goblin Hunt', 'Goblins are raiding travelers. Collect 3 goblin ears as proof.', 'blacksmith', '[{"id": 21, "quantity": 3}]', 80, 2, 3, 10, FALSE, TRUE),
(3, 'Desert Bandits', 'Clear the desert road of bandits. Bring 2 insignias.', 'armorer', '[{"id": 22, "quantity": 2}]', 150, 3, 8, 10, FALSE, TRUE),
(4, 'Lava Cores Needed', 'I need lava cores for my research. Bring me 1.', 'clergy', '[{"id": 23, "quantity": 1}]', 300, 4, 12, NULL, FALSE, FALSE),
(5, 'Demon Threat', 'A demon has been sighted. Bring its horn as proof of death.', 'clergy', '[{"id": 24, "quantity": 1}]', 500, 5, 15, NULL, FALSE, FALSE),
(6, 'Leather Supplies', 'I need materials. Bring me 10 rat tails.', 'leathersmith', '[{"id": 20, "quantity": 10}]', 40, 1, 1, 8, FALSE, FALSE),
(7, 'Scavenger Hunt', 'Collect various monster parts: 2 rat tails, 2 goblin ears.', 'leathersmith', '[{"id": 20, "quantity": 2}, {"id": 21, "quantity": 2}]', 100, 2, 5, 12, FALSE, FALSE),
(8, 'Guild Contract: Goblin Extermination', 'The guild needs goblin ears for a bounty. Collect 10 ears.', 'blacksmith', '[{"id": 21, "quantity": 10}]', 800, 5, 10, NULL, TRUE, TRUE),
(9, 'Guild Contract: Bandit Cleanup', 'Clear the roads of bandits. Bring 5 insignias.', 'armorer', '[{"id": 22, "quantity": 5}]', 1500, 8, 12, NULL, TRUE, TRUE),
(10, 'Guild Contract: Lava Core Collection', 'The guild needs lava cores for enchanting. Bring 3.', 'clergy', '[{"id": 23, "quantity": 3}]', 2500, 10, 15, NULL, TRUE, TRUE),
(11, 'Guild Contract: Demon Slayer', 'Hunt demons for the guild. Bring 2 demon horns.', 'clergy', '[{"id": 24, "quantity": 2}]', 5000, 15, 18, NULL, TRUE, TRUE),
(12, 'Guild Contract: Supply Run', 'Gather materials for guild crafters: 15 rat tails, 8 goblin ears.', 'leathersmith', '[{"id": 20, "quantity": 15}, {"id": 21, "quantity": 8}]', 1200, 7, 10, NULL, TRUE, TRUE),
(13, 'Guild Contract: Rat Invasion', 'The rats have returned stronger. Bring 20 rat tails.', 'blacksmith', '[{"id": 20, "quantity": 20}]', 500, 6, 5, NULL, TRUE, TRUE),
(14, 'Guild Contract: Goblin Uprising', 'Goblins are multiplying. Collect 15 goblin ears.', 'blacksmith', '[{"id": 21, "quantity": 15}]', 900, 7, 6, NULL, TRUE, TRUE),
(15, 'Guild Contract: Bandit Kings', 'The bandit kings must fall. Bring 8 insignias.', 'armorer', '[{"id": 22, "quantity": 8}]', 1800, 9, 10, NULL, TRUE, TRUE),
(16, 'Guild Contract: Lava Harvesting', 'We need more lava cores for weapons. Bring 5.', 'clergy', '[{"id": 23, "quantity": 5}]', 3000, 8, 12, NULL, TRUE, TRUE),
(17, 'Guild Contract: Demon Hunt', 'Demons are spreading chaos. Bring 4 demon horns.', 'clergy', '[{"id": 24, "quantity": 4}]', 4500, 10, 14, NULL, TRUE, TRUE),
(18, 'Guild Contract: Mixed Materials', 'Gather 8 rat tails, 6 goblin ears, and 3 insignias.', 'leathersmith', '[{"id": 20, "quantity": 8}, {"id": 21, "quantity": 6}, {"id": 22, "quantity": 3}]', 2000, 8, 8, NULL, TRUE, TRUE),
(19, 'Guild Contract: Monster Mastery', 'Prove your worth: 10 rat tails, 10 goblin ears, 5 insignias, 2 lava cores.', 'leathersmith', '[{"id": 20, "quantity": 10}, {"id": 21, "quantity": 10}, {"id": 22, "quantity": 5}, {"id": 23, "quantity": 2}]', 3500, 12, 12, NULL, TRUE, TRUE),
(20, 'Guild Contract: Legendary Hunt', 'Hunt the most dangerous creatures: 5 lava cores and 3 demon horns.', 'clergy', '[{"id": 23, "quantity": 5}, {"id": 24, "quantity": 3}]', 6000, 14, 16, NULL, TRUE, TRUE),
(21, 'Guild Contract: Final Trial', 'The ultimate challenge: 25 rat tails, 15 goblin ears, 10 insignias, 3 lava cores, 2 demon horns.', 'leathersmith', '[{"id": 20, "quantity": 25}, {"id": 21, "quantity": 15}, {"id": 22, "quantity": 10}, {"id": 23, "quantity": 3}, {"id": 24, "quantity": 2}]', 5000, 15, 18, NULL, TRUE, TRUE),
(22, 'Guild Contract: Elite Extermination', 'Eliminate elite monsters: 12 goblin ears, 6 lava cores, and 4 demon horns.', 'clergy', '[{"id": 21, "quantity": 12}, {"id": 23, "quantity": 6}, {"id": 24, "quantity": 4}]', 7000, 16, 17, NULL, TRUE, TRUE)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `description`=VALUES(`description`), `required_items`=VALUES(`required_items`), `reward_gold`=VALUES(`reward_gold`), `reward_reputation`=VALUES(`reward_reputation`), `guild_required`=VALUES(`guild_required`), `repeatable`=VALUES(`repeatable`), `max_level`=VALUES(`max_level`);

-- Insert Tutorial Reputation
INSERT INTO `reputation` (`character_id`, `points`) VALUES
(1, 0)
ON DUPLICATE KEY UPDATE `points`=VALUES(`points`);

/* ========== RE-ENABLE FOREIGN KEY CHECKS ========== */
SET FOREIGN_KEY_CHECKS = 1;

/* ========== SETUP COMPLETE ========== */
-- Database setup complete!
-- All tables created, quests (22), guilds (4), items (24), and default user/character inserted.
-- Ready for game server connection.
