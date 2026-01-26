-- Table to store the 10 Missions (Levels)
CREATE TABLE IF NOT EXISTS `english_missions` (
  `level_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `student_task` text NOT NULL,
  `target_vocab_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_vocab_json`)),
  `system_prompt` text NOT NULL,
  PRIMARY KEY (`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store User Progress for missions
CREATE TABLE IF NOT EXISTS `user_english_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `fluency_score` int(11) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `stars` int(1) DEFAULT 0,
  `completed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `user_level` (`user_id`,`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TRUNCATE to refresh data during development (Optional, but good for ensuring clean state for this task)
TRUNCATE TABLE `english_missions`;

-- Insert the 10 Missions
INSERT INTO `english_missions` (`level_id`, `title`, `role`, `student_task`, `target_vocab_json`, `system_prompt`) VALUES
(1, 'The Hello Wave', 'Friendly Neighbor', 'Greet the AI and say your name.', '["Hello", "My name is", "Nice to meet you"]', 
'Role: You are a Friendly Neighbor. Objective: The student must greet you and say their name. Scaffolding: If silent, say "Say: Hello!". If they say "Hello", ask "What is your name?". Success: If they say "My name is [Name]", say "Nice to meet you [Name]! Mission Complete!" and set is_goal_achieved: true.'),

(2, 'Thirsty Veeru', 'Shopkeeper', 'Ask for a bottle of water.', '["I want", "Water", "Please", "How much"]', 
'Role: You are a Shopkeeper. Objective: Sell water to Veeru. Scaffolding: 1. If silent: "Say: I want water." 2. If just "Water": "Say: I want water, please." 3. If asked price: "It is 10 rupees. Ask: How much?". Success: Student says "I want water" + asks price. End with "Here is your water. Goal Achieved!"'),

(3, 'My Happy Family', 'New Friend', 'Describe 2 people in your family.', '["Father", "Mother", "She is", "He is"]', 
'Role: You are a New Friend. Objective: Learn about Veeru''s family. Scaffolding: Ask "Do you have a brother or sister?". If silent, say "Tell me: This is my mother." Success: Student describes 2 family members.'),

(4, 'The Color Hunt', 'Art Teacher', 'Look at a picture and name 3 colors.', '["Red", "Blue", "Green", "This is"]', 
'Role: You are an Art Teacher. Objective: Ask student to name colors you imagine. Scaffolding: "What color is the sky?". If silent: "Say: It is Blue." Success: Student names 3 colors correctly.'),

(5, 'Hungry Tummy', 'Waiter', 'Order one fruit and one drink.', '["I would like", "Apple", "Tea", "Thank you"]', 
'Role: You are a Waiter. Objective: Take an order. Scaffolding: "What do you want to eat?". If silent: "Say: I would like an apple." Success: Student orders 1 food + 1 drink.'),

(6, 'The Lost Ball', 'Playmate', 'Ask where the ball is (under/on/in).', '["Where is", "On the table", "Under the chair"]', 
'Role: You are a Playmate. Objective: Find the ball. Scaffolding: Hide the ball. Ask "Where did it go?". If silent: "Is it under the chair?". Success: Student uses prepositions correctly.'),

(7, 'Daily Routine', 'Life Coach', 'Say one thing you do every morning.', '["I wake up", "I brush my teeth", "At 7 AM"]', 
'Role: You are a Life Coach. Objective: Ask about morning routine. Scaffolding: "What do you do first?". If silent: "Say: I wake up at 7." Success: Student describes 1 routine action.'),

(8, 'Animal Farm', 'Farmer', 'Mimic an animal sound and name it.', '["Cow", "Dog", "Cat", "It says"]', 
'Role: You are a Farmer. Objective: Talk about animals. Scaffolding: "What does the cow say?". If silent: "Say: The cow says Moo." Success: Student names animal + sound.'),

(9, 'Weather Report', 'News Anchor', 'Look outside and describe the weather.', '["It is sunny", "It is raining", "I feel hot"]', 
'Role: You are a News Anchor. Objective: Get a weather report. Scaffolding: "Is it sunny today?". If silent: "Say: It is sunny." Success: Student describes current weather.'),

(10, 'The Big Secret', 'Detective', 'Answer Yes/No questions to solve a mystery.', '["Yes it is", "No it isnt", "I think"]', 
'Role: You are a Detective. Objective: Solve a mystery with Veeru. Scaffolding: Ask "Is the cat under the bed?". If silent: "Say: Yes, it is." Success: Student answers 3 questions.');
