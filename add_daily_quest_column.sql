-- Add is_daily column to quests table if it doesn't exist
ALTER TABLE `quests` ADD COLUMN `is_daily` BOOLEAN DEFAULT FALSE AFTER `repeatable`;

-- Add daily_quest_state table
CREATE TABLE IF NOT EXISTS `daily_quest_state` (
  `character_id` int(11) NOT NULL PRIMARY KEY,
  `quest_id` int(11),
  `quest_date` DATE NOT NULL,
  `completed` boolean DEFAULT FALSE,
  `completed_at` timestamp NULL DEFAULT NULL,
  FOREIGN KEY (`character_id`) REFERENCES `characters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Daily Quest Templates
INSERT INTO `quests` (`id`, `title`, `description`, `shop_type`, `required_items`, `reward_gold`, `reward_reputation`, `min_level`, `max_level`, `guild_required`, `repeatable`, `is_daily`) VALUES
(100, '⭐ Daily: Rat Slayer', 'Quick challenge: Collect 3 rat tails before midnight!', 'blacksmith', '[{"id": 20, "quantity": 3}]', 50, 2, 1, NULL, FALSE, FALSE, TRUE),
(101, '⭐ Daily: Goblin Catcher', 'Daily bounty: Bring 2 goblin ears for a quick reward!', 'blacksmith', '[{"id": 21, "quantity": 2}]', 80, 2, 3, NULL, FALSE, FALSE, TRUE),
(102, '⭐ Daily: Bandit Patrol', 'Today challenge: Collect 1 bandit insignia!', 'armorer', '[{"id": 22, "quantity": 1}]', 100, 3, 5, NULL, FALSE, FALSE, TRUE),
(103, '⭐ Daily: Lava Seeker', 'Adventurer needed: Find 1 lava core!', 'clergy', '[{"id": 23, "quantity": 1}]', 150, 3, 10, NULL, FALSE, FALSE, TRUE),
(104, '⭐ Daily: Demon Hunter', 'High risk: Slay a demon and bring its horn!', 'clergy', '[{"id": 24, "quantity": 1}]', 250, 4, 13, NULL, FALSE, FALSE, TRUE)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `description`=VALUES(`description`), `is_daily`=VALUES(`is_daily`);
