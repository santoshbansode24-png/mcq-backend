-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: veeru_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `badges`
--

DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) NOT NULL,
  `criteria_type` varchar(50) NOT NULL,
  `criteria_value` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`badge_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badges`
--

LOCK TABLES `badges` WRITE;
/*!40000 ALTER TABLE `badges` DISABLE KEYS */;
INSERT INTO `badges` VALUES (1,'Night Owl','Study after 10 PM','🌙','time_of_day','22:00','2025-12-01 16:47:49'),(2,'Streak Master','Login 7 days in a row','🔥','login_streak','7','2025-12-01 16:47:49'),(3,'Quiz Whiz','Score 100% on 5 quizzes','🏆','perfect_scores','5','2025-12-01 16:47:49');
/*!40000 ALTER TABLE `badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` enum('video','note','question') NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookmarks`
--

LOCK TABLES `bookmarks` WRITE;
/*!40000 ALTER TABLE `bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chapters` (
  `chapter_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `chapter_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `chapter_order` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chapter_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_chapter_subject` (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapters`
--

LOCK TABLES `chapters` WRITE;
/*!40000 ALTER TABLE `chapters` DISABLE KEYS */;
INSERT INTO `chapters` VALUES (1,1,'Real Numbers','Introduction to real numbers and their properties',1,'2025-11-29 18:17:04'),(2,1,'Polynomials','Understanding polynomials and algebraic expressions',2,'2025-11-29 18:17:04'),(3,1,'Linear Equations','Solving linear equations in two variables',3,'2025-11-29 18:17:04'),(4,1,'Quadratic Equations','Quadratic equations and their solutions',4,'2025-11-29 18:17:04'),(5,1,'Arithmetic Progressions','Sequences and series',5,'2025-11-29 18:17:04'),(6,2,'Chemical Reactions','Types of chemical reactions',1,'2025-11-29 18:17:04'),(7,2,'Acids, Bases and Salts','Properties and reactions',2,'2025-11-29 18:17:04'),(8,2,'Light - Reflection and Refraction','Optical phenomena',3,'2025-11-29 18:17:04'),(9,8,'noun','',1,'2025-11-30 08:02:03'),(10,8,'VERB','',1,'2025-12-01 18:32:28'),(11,13,'VERB','',1,'2025-12-19 05:35:57'),(12,13,'noun','',2,'2025-12-19 05:36:06'),(13,17,'सूर्य, चंद्र आणि पृथ्वी','',1,'2025-12-19 17:34:59'),(14,18,'विद्युत','',1,'2025-12-23 14:46:47'),(15,12,'EARLY HUMANS','',1,'2026-01-04 10:48:23');
/*!40000 ALTER TABLE `chapters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`class_id`),
  UNIQUE KEY `class_name` (`class_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'Class 1','2025-11-29 18:17:04'),(2,'Class 2','2025-11-29 18:17:04'),(3,'Class 3','2025-11-29 18:17:04'),(4,'Class 4','2025-11-29 18:17:04'),(5,'Class 5','2025-11-29 18:17:04'),(6,'Class 6','2025-11-29 18:17:04'),(7,'Class 7','2025-11-29 18:17:04'),(8,'Class 8','2025-11-29 18:17:04'),(9,'Class 9','2025-11-29 18:17:04'),(10,'Class 10','2025-11-29 18:17:04'),(11,'Class 11','2025-11-29 18:17:04'),(12,'Class 12','2025-11-29 18:17:04');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flashcards`
--

DROP TABLE IF EXISTS `flashcards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flashcards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `question_front` text NOT NULL,
  `answer_back` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chapter_id` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flashcards`
--

LOCK TABLES `flashcards` WRITE;
/*!40000 ALTER TABLE `flashcards` DISABLE KEYS */;
INSERT INTO `flashcards` VALUES (1,13,'GEOGRAPHY','General','What is the capital of France?','Paris','2025-12-29 17:29:26'),(2,13,'GEOGRAPHY','General','Define Photosynthesis','Process used by plants to convert light into energy','2025-12-29 17:29:26'),(3,13,'GEOGRAPHY','General','What is the capital of France?','Paris','2025-12-29 17:46:55'),(4,13,'GEOGRAPHY','General','Define Photosynthesis','Process used by plants to convert light into energy','2025-12-29 17:46:55'),(5,13,'GEOGRAPHY','General','how many planets in our solar system are','8','2025-12-29 17:47:41'),(6,14,'SCIENCE','General','What is the capital of France?','Paris','2025-12-29 17:57:47'),(7,14,'SCIENCE','General','Define Photosynthesis','Process used by plants to convert light into energy','2025-12-29 17:57:47'),(11,15,'SOCIAL STUDIES','General','Who were early humans?','The first people who lived long ago.','2026-01-04 19:30:31'),(12,15,'SOCIAL STUDIES','General','What type of life did early humans lead?','Nomadic life.','2026-01-04 19:30:31'),(13,15,'SOCIAL STUDIES','General','Where did early humans live?','In caves and on tree branches.','2026-01-04 19:30:31'),(14,15,'SOCIAL STUDIES','General','What tools did early humans mostly use?','Stone tools.','2026-01-04 19:30:31'),(15,15,'SOCIAL STUDIES','General','What were early humans called?','Hunters and gatherers.','2026-01-04 19:30:31'),(16,15,'SOCIAL STUDIES','General','What did early humans hunt?','Animals and birds.','2026-01-04 19:30:31'),(17,15,'SOCIAL STUDIES','General','What did early humans gather?','Fruits, roots, and eggs.','2026-01-04 19:30:31'),(18,15,'SOCIAL STUDIES','General','What material was used to make early tools?','Stone.','2026-01-04 19:30:31'),(19,15,'SOCIAL STUDIES','General','What discovery helped humans cook food?','Fire.','2026-01-04 19:30:31'),(20,15,'SOCIAL STUDIES','General','What discovery helped humans stay warm?','Fire.','2026-01-04 19:30:31'),(21,15,'SOCIAL STUDIES','General','What did early humans eat?','Meat, fruits, and roots.','2026-01-04 19:30:31'),(22,15,'SOCIAL STUDIES','General','What covered early humans’ bodies?','Leaves, bark, and animal skin.','2026-01-04 19:30:31'),(23,15,'SOCIAL STUDIES','General','What helped humans carry heavy loads?','Wheel.','2026-01-04 19:30:31'),(24,15,'SOCIAL STUDIES','General','What activity started farming?','Growing crops.','2026-01-04 19:30:31'),(25,15,'SOCIAL STUDIES','General','What did seeds grow into?','Plants.','2026-01-04 19:30:31'),(26,15,'SOCIAL STUDIES','General','Which invention helped make pots?','Wheel.','2026-01-04 19:30:31'),(27,15,'SOCIAL STUDIES','General','What is farming?','Growing crops.','2026-01-04 19:30:31'),(28,15,'SOCIAL STUDIES','General','What kind of life started after farming?','Settled life.','2026-01-04 19:30:31'),(29,15,'SOCIAL STUDIES','General','What age is known for stone tools?','Stone Age.','2026-01-04 19:30:31'),(30,15,'SOCIAL STUDIES','General','What protected humans from wild animals?','Fire.','2026-01-04 19:30:31'),(31,15,'SOCIAL STUDIES','General','Early humans lived in cities. (True/False)','False','2026-01-04 19:30:31'),(32,15,'SOCIAL STUDIES','General','Early humans led a nomadic life. (True/False)','True','2026-01-04 19:30:31'),(33,15,'SOCIAL STUDIES','General','Stone was used to make tools. (True/False)','True','2026-01-04 19:30:31'),(34,15,'SOCIAL STUDIES','General','Early humans wore cotton clothes. (True/False)','False','2026-01-04 19:30:31'),(35,15,'SOCIAL STUDIES','General','Fire helped humans cook food. (True/False)','True','2026-01-04 19:30:31'),(36,15,'SOCIAL STUDIES','General','Early humans depended on machines. (True/False)','False','2026-01-04 19:30:31'),(37,15,'SOCIAL STUDIES','General','Farming helped humans live in one place. (True/False)','True','2026-01-04 19:30:31'),(38,15,'SOCIAL STUDIES','General','Wheel was invented before stone tools. (True/False)','False','2026-01-04 19:30:31'),(39,15,'SOCIAL STUDIES','General','Early humans were hunters and gatherers. (True/False)','True','2026-01-04 19:30:31'),(40,15,'SOCIAL STUDIES','General','Caves protected humans from animals. (True/False)','True','2026-01-04 19:30:31'),(41,15,'SOCIAL STUDIES','General','Early humans used plastic tools. (True/False)','False','2026-01-04 19:30:31'),(42,15,'SOCIAL STUDIES','General','Fire helped protect from wild animals. (True/False)','True','2026-01-04 19:30:31'),(43,15,'SOCIAL STUDIES','General','Early humans stored food in refrigerators. (True/False)','False','2026-01-04 19:30:31'),(44,15,'SOCIAL STUDIES','General','Seeds grow into plants. (True/False)','True','2026-01-04 19:30:31'),(45,15,'SOCIAL STUDIES','General','Wheel helped in transport. (True/False)','True','2026-01-04 19:30:31'),(46,15,'SOCIAL STUDIES','General','Early humans lived in ______.','caves','2026-01-04 19:30:31'),(47,15,'SOCIAL STUDIES','General','Early humans led a ______ life.','nomadic','2026-01-04 19:30:31'),(48,15,'SOCIAL STUDIES','General','Most early tools were made of ______.','stone','2026-01-04 19:30:31'),(49,15,'SOCIAL STUDIES','General','Early humans were called hunters and ______.','gatherers','2026-01-04 19:30:31'),(50,15,'SOCIAL STUDIES','General','Fire was discovered by rubbing two ______.','stones','2026-01-04 19:30:31'),(51,15,'SOCIAL STUDIES','General','Fire helped humans cook ______.','meat','2026-01-04 19:30:31'),(52,15,'SOCIAL STUDIES','General','Early humans covered their bodies with leaves and ______.','animal skin','2026-01-04 19:30:31'),(53,15,'SOCIAL STUDIES','General','Growing crops is called ______.','farming','2026-01-04 19:30:31'),(54,15,'SOCIAL STUDIES','General','The invention of ______ helped carry heavy loads.','wheel','2026-01-04 19:30:31'),(55,15,'SOCIAL STUDIES','General','Seeds grow into new ______.','plants','2026-01-04 19:30:31'),(56,15,'SOCIAL STUDIES','General','Early humans hunted animals for ______.','food','2026-01-04 19:30:31'),(57,15,'SOCIAL STUDIES','General','The wheel helped in making ______.','pots','2026-01-04 19:30:31'),(58,15,'SOCIAL STUDIES','General','Early humans used sharp stones to ______ animals.','hunt','2026-01-04 19:30:31'),(59,15,'SOCIAL STUDIES','General','After farming, humans lived a ______ life.','settled','2026-01-04 19:30:31'),(60,15,'SOCIAL STUDIES','General','Fire helped humans keep ______.','warm','2026-01-04 19:30:31');
/*!40000 ALTER TABLE `flashcards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maths_scores`
--

DROP TABLE IF EXISTS `maths_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maths_scores` (
  `score_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`score_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maths_scores`
--

LOCK TABLES `maths_scores` WRITE;
/*!40000 ALTER TABLE `maths_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `maths_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mcq_attempts`
--

DROP TABLE IF EXISTS `mcq_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mcq_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `mcq_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `selected_answer` varchar(1) DEFAULT NULL,
  `correct_answer` varchar(1) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `mcq_id` (`mcq_id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `idx_user_chapter` (`user_id`,`chapter_id`),
  KEY `idx_user_mcq` (`user_id`,`mcq_id`),
  KEY `idx_correctness` (`user_id`,`chapter_id`,`is_correct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mcq_attempts`
--

LOCK TABLES `mcq_attempts` WRITE;
/*!40000 ALTER TABLE `mcq_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `mcq_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mcq_vocab_link`
--

DROP TABLE IF EXISTS `mcq_vocab_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mcq_vocab_link` (
  `link_id` int(11) NOT NULL AUTO_INCREMENT,
  `mcq_id` int(11) NOT NULL,
  `word_id` int(11) NOT NULL,
  `relevance_score` decimal(3,2) DEFAULT 1.00 COMMENT 'How relevant the word is to the question',
  `link_type` enum('keyword','context','answer') DEFAULT 'context',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `unique_mcq_word` (`mcq_id`,`word_id`),
  KEY `idx_mcq` (`mcq_id`),
  KEY `idx_word` (`word_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mcq_vocab_link`
--

LOCK TABLES `mcq_vocab_link` WRITE;
/*!40000 ALTER TABLE `mcq_vocab_link` DISABLE KEYS */;
/*!40000 ALTER TABLE `mcq_vocab_link` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mcqs`
--

DROP TABLE IF EXISTS `mcqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mcqs` (
  `mcq_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_answer` enum('a','b','c','d') NOT NULL,
  `explanation` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mcq_id`),
  KEY `idx_chapter_id` (`chapter_id`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_mcq_chapter` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mcqs`
--

LOCK TABLES `mcqs` WRITE;
/*!40000 ALTER TABLE `mcqs` DISABLE KEYS */;
INSERT INTO `mcqs` VALUES (1,1,'Which of the following is a rational number?','???2','??','0.5','???3','c','0.5 can be expressed as 1/2, which is a ratio of integers','easy','2025-11-29 18:17:04'),(2,1,'The decimal expansion of 22/7 is:','Terminating','Non-terminating and repeating','Non-terminating and non-repeating','None of these','b','22/7 is a rational number with non-terminating repeating decimal','medium','2025-11-29 18:17:04'),(3,1,'Which is an irrational number?','0.333...','???16','???5','7/3','c','???5 cannot be expressed as a ratio of integers','easy','2025-11-29 18:17:04'),(4,1,'The product of a non-zero rational and an irrational number is:','Always rational','Always irrational','Can be rational or irrational','Always zero','b','Product of rational and irrational is always irrational','medium','2025-11-29 18:17:04'),(5,1,'HCF of 26 and 91 is:','13','26','91','1','a','26 = 2 ?? 13, 91 = 7 ?? 13, so HCF is 13','easy','2025-11-29 18:17:04'),(6,2,'The degree of polynomial 5x?? + 4x?? + 7x is:','1','2','3','7','c','The highest power of x is 3','easy','2025-11-29 18:17:04'),(7,2,'A polynomial of degree 2 is called:','Linear','Quadratic','Cubic','Biquadratic','b','Degree 2 polynomial is quadratic','easy','2025-11-29 18:17:04'),(8,2,'Zero of polynomial p(x) = 2x + 5 is:','-5/2','5/2','-2/5','2/5','a','Set 2x + 5 = 0, then x = -5/2','medium','2025-11-29 18:17:04'),(9,9,'Which word is a noun?','Run','Apple','Quickly','Blue','b','','medium','2025-11-30 08:02:26'),(10,9,'A noun is a ___','Name of a person, place, or thing','Describing word','Action word','Sound word','a','','medium','2025-11-30 08:02:26'),(11,9,'Which of these is a place noun?','Dog','Park','Jump','Red','b','','medium','2025-11-30 08:02:26'),(12,9,'Which word is a person noun?','Teacher','Running','Happy','Slowly','a','','medium','2025-11-30 08:02:26'),(13,9,'Which of these is a thing noun?','Laugh','Table','Bright','Swim','b','','medium','2025-11-30 08:02:26'),(14,9,'What type of noun is &#039;India&#039;?','Common noun','Proper noun','Action noun','Sound noun','b','','medium','2025-11-30 08:02:26'),(15,9,'Which noun is in this sentence: &#039;The cat sleeps.&#039;','Sleeps','The','Cat','None','c','','medium','2025-11-30 08:02:26'),(16,9,'Which word is NOT a noun?','City','Dance','Girl','Book','b','','medium','2025-11-30 08:02:26'),(17,9,'Which is a proper noun?','Boy','River','Ravi','Car','c','','medium','2025-11-30 08:02:26'),(18,9,'Which noun refers to an animal?','Elephant','Delhi','Chair','Blue','a','','medium','2025-11-30 08:02:26'),(23,10,'Choose the correct form of the verb: I _____ to school every day.','go','goes','going','gone','a','','easy','2025-12-01 18:40:08'),(24,10,'Choose the correct verb: She _____ a book right now.','read','reads','is reading','reading','c','','medium','2025-12-01 18:40:08'),(25,10,'Select the past tense of &#039;eat&#039;.','eated','ate','eaten','eats','b','','medium','2025-12-01 18:40:08'),(26,10,'Fill in the blank: They _____ playing football yesterday.','is','are','were','was','c','','medium','2025-12-01 18:40:08'),(27,10,'Choose the correct infinitive: He wants _____ a doctor.','become','became','becoming','becomes','a','','medium','2025-12-01 18:40:08'),(28,10,'Pick the correct form: I have _____ my homework.','do','did','done','doing','c','','medium','2025-12-01 18:40:08'),(29,10,'Select the present continuous form: She _____ dinner now.','cook','cooks','is cooking','cooked','c','','medium','2025-12-01 18:40:08'),(30,10,'Choose the correct verb: We _____ to the party tomorrow.','go','went','will go','gone','c','','medium','2025-12-01 18:40:08'),(31,10,'Pick the correct past participle of &#039;write&#039;.','write','wrote','written','writing','c','','medium','2025-12-01 18:40:08'),(32,10,'Choose the correct auxiliary: _____ you like tea?','Do','Does','Did','Doing','a','','medium','2025-12-01 18:40:08'),(33,10,'Fill in the blank: He _____ the piano when he was young.','play','plays','played','playing','c','','medium','2025-12-01 18:40:08'),(34,10,'Select the correct verb: The cake _____ delicious.','is','are','was','were','a','','medium','2025-12-01 18:40:08'),(35,10,'Choose the correct form: I _____ my keys yesterday.','lose','lost','have lost','losing','b','','medium','2025-12-01 18:40:08'),(36,10,'Pick the correct verb: Birds _____ in the sky.','fly','flies','flew','flown','a','','medium','2025-12-01 18:40:08'),(37,10,'Choose the correct continuous tense: They _____ for the bus now.','wait','waits','are waiting','were waiting','c','','medium','2025-12-01 18:40:08'),(38,10,'Select the correct modal: You _____ finish your work.','must','might','will','am','a','','medium','2025-12-01 18:40:08'),(39,10,'Fill in the blank: She _____ to music every evening.','listen','listens','listened','listening','b','','medium','2025-12-01 18:40:08'),(40,10,'Choose the correct verb: He _____ a letter yesterday.','write','wrote','writes','written','b','','medium','2025-12-01 18:40:08'),(41,10,'Pick the correct past tense: I _____ a movie last night.','watch','watched','watches','watching','b','','medium','2025-12-01 18:40:08'),(42,10,'Select correct form: The sun _____ in the east.','rise','rises','rose','rising','b','','medium','2025-12-01 18:40:08'),(43,12,'Which word is a noun?','Run','Blue','Boy','Quickly','c','','medium','2025-12-19 05:36:24'),(44,12,'A noun is the name of a ________.','Action','Person, place, animal, or thing','Quality','Sound','b','','medium','2025-12-19 05:36:24'),(45,12,'Which of the following is a noun?','Happy','Jump','School','Fast','c','','medium','2025-12-19 05:36:24'),(46,12,'Which word names an animal?','Chair','Lion','Pencil','Book','b','','medium','2025-12-19 05:36:24'),(47,12,'Which word is the name of a place?','City','Run','Red','Tall','a','','medium','2025-12-19 05:36:24'),(48,12,'Identify the noun in the sentence: The cat is sleeping.','Sleeping','Is','The','Cat','d','','medium','2025-12-19 05:36:25'),(49,12,'Which of the following is a thing?','Teacher','Delhi','Pen','Cow','c','','medium','2025-12-19 05:36:25'),(50,12,'Which word is NOT a noun?','Apple','Girl','Play','Dog','c','','medium','2025-12-19 05:36:25'),(51,12,'Which word names a person?','Doctor','Park','Table','Ball','a','','medium','2025-12-19 05:36:25'),(52,12,'Which of the following is a noun?','Sing','Sweet','River','Slowly','c','','medium','2025-12-19 05:36:25'),(53,12,'Identify the noun in the sentence: Rahul has a book.','Has','A','Book','Has a','c','','medium','2025-12-19 05:36:25'),(54,12,'Which word is the name of a place?','Teacher','School','Pencil','Dog','b','','medium','2025-12-19 05:36:25'),(55,12,'Which of these words is a noun?','Run','Beautiful','Car','Fast','c','','medium','2025-12-19 05:36:25'),(56,12,'Which word names an object?','Chair','Sleep','Laugh','Eat','a','','medium','2025-12-19 05:36:25'),(57,12,'Identify the noun in the sentence: The boy plays football.','Plays','Football','The','Plays football','b','','medium','2025-12-19 05:36:25'),(58,12,'Which word is a noun?','Hot','Drink','Bottle','Quickly','c','','medium','2025-12-19 05:36:25'),(59,12,'Which of the following is a noun?','Jumping','Tall','Mother','Slowly','c','','medium','2025-12-19 05:36:25'),(60,12,'Which word names an animal?','Horse','Table','Chair','Pen','a','','medium','2025-12-19 05:36:25'),(61,12,'Which word is NOT a noun?','City','River','Blue','Teacher','c','','medium','2025-12-19 05:36:25'),(62,12,'Which of the following is a noun?','Write','House','Clean','Fast','b','','medium','2025-12-19 05:36:25'),(63,11,'Choose the correct form of the verb: I _____ to school every day.','go','goes','going','gone','b','','medium','2025-12-19 05:36:49'),(64,11,'Choose the correct verb: She _____ a book right now.','read','reads','is reading','reading','c','','medium','2025-12-19 05:36:49'),(65,11,'Select the past tense of &#039;eat&#039;.','eated','ate','eaten','eats','b','','medium','2025-12-19 05:36:49'),(66,11,'Fill in the blank: They _____ playing football yesterday.','is','are','were','was','c','','medium','2025-12-19 05:36:49'),(67,11,'Choose the correct infinitive: He wants _____ a doctor.','become','became','becoming','becomes','a','','medium','2025-12-19 05:36:49'),(68,11,'Pick the correct form: I have _____ my homework.','do','did','done','doing','d','','medium','2025-12-19 05:36:49'),(69,11,'Select the present continuous form: She _____ dinner now.','cook','cooks','is cooking','cooked','c','','medium','2025-12-19 05:36:49'),(70,11,'Choose the correct verb: We _____ to the party tomorrow.','go','went','will go','gone','c','','medium','2025-12-19 05:36:49'),(71,11,'Pick the correct past participle of &#039;write&#039;.','write','wrote','written','writing','b','','medium','2025-12-19 05:36:49'),(72,11,'Choose the correct auxiliary: _____ you like tea?','Do','Does','Did','Doing','a','','medium','2025-12-19 05:36:49'),(73,11,'Fill in the blank: He _____ the piano when he was young.','play','plays','played','playing','b','','medium','2025-12-19 05:36:49'),(74,11,'Select the correct verb: The cake _____ delicious.','is','are','was','were','a','','medium','2025-12-19 05:36:49'),(75,11,'Choose the correct form: I _____ my keys yesterday.','lose','lost','have lost','losing','b','','medium','2025-12-19 05:36:49'),(76,11,'Pick the correct verb: Birds _____ in the sky.','fly','flies','flew','flown','a','','medium','2025-12-19 05:36:49'),(77,11,'Choose the correct continuous tense: They _____ for the bus now.','wait','waits','are waiting','were waiting','c','','medium','2025-12-19 05:36:49'),(78,11,'Select the correct modal: You _____ finish your work.','must','might','will','am','a','','medium','2025-12-19 05:36:49'),(79,11,'Fill in the blank: She _____ to music every evening.','listen','listens','listened','listening','b','','medium','2025-12-19 05:36:49'),(80,11,'Choose the correct verb: He _____ a letter yesterday.','write','wrote','writes','written','b','','medium','2025-12-19 05:36:49'),(81,11,'Pick the correct past tense: I _____ a movie last night.','watch','watched','watches','watching','b','','medium','2025-12-19 05:36:49'),(82,11,'Select correct form: The sun _____ in the east.','rise','rises','rose','rising','b','','medium','2025-12-19 05:36:49'),(83,15,'Early humans searched for food, water, and shelter because they lived a:','Comfortable life','Nomadic life','Modern life','City life','b','Early humans moved from place to place in search of basic needs, so their life was nomadic.','medium','2026-01-04 20:14:17'),(84,15,'Which place provided natural shelter to early humans?','Houses','Flats','Caves','Schools','c','Caves protected early humans from wild animals and harsh weather.','medium','2026-01-04 20:14:17'),(85,15,'Early humans mostly depended on nature for:','Machines','Technology','Food and shelter','Money','c','They depended on nature for food, water, and shelter.','medium','2026-01-04 20:14:17'),(86,15,'What was the main reason early humans hunted animals?','For fun','For food','For trade','For clothes','b','Early humans hunted animals mainly to get food.','medium','2026-01-04 20:14:17'),(87,15,'Which item was NOT part of early humans’ food?','Fruits','Roots','Eggs','Packaged food','d','Packaged food did not exist in early human times.','medium','2026-01-04 20:14:17'),(88,15,'Early humans used animal skin mainly to:','Make tools','Cover their body','Build houses','Cook food','b','Animal skin was used to cover their bodies.','medium','2026-01-04 20:14:17'),(89,15,'What helped early humans dig out roots from the ground?','Fire','Sharp stones','Wheel','Hands only','b','Sharp stone tools were used to dig out roots.','medium','2026-01-04 20:14:17'),(90,15,'Why did early humans tie sharp stones to wooden sticks?','To decorate','To hunt safely','To cook food','To make fire','b','This helped them hunt animals from a distance safely.','medium','2026-01-04 20:14:17'),(91,15,'Which period of human history is known for stone tools?','Iron Age','Bronze Age','Stone Age','Modern Age','c','Stone tools were mainly used during the Stone Age.','medium','2026-01-04 20:14:17'),(92,15,'Fire helped early humans mainly by:','Making machines','Giving light and warmth','Building houses','Growing crops','b','Fire provided warmth, light, and protection.','medium','2026-01-04 20:14:17'),(93,15,'Which discovery helped humans cook meat?','Wheel','Fire','Stone tools','Farming','b','Fire made cooking meat possible.','medium','2026-01-04 20:14:17'),(94,15,'Fire also protected early humans from:','Cold weather','Wild animals','Darkness','All of these','d','Fire was useful in many ways including protection and warmth.','medium','2026-01-04 20:14:17'),(95,15,'What did early humans do with seeds before learning farming?','Stored them','Sold them','Threw them away','Cooked them','c','They threw seeds away before realising they could grow plants.','medium','2026-01-04 20:14:17'),(96,15,'Farming began when early humans noticed that seeds:','Melted','Disappeared','Grew into plants','Turned to food','c','They observed seeds growing into new plants.','medium','2026-01-04 20:14:17'),(97,15,'What does farming mainly involve?','Hunting','Trading','Growing crops','Fishing','c','Farming means growing crops for food.','medium','2026-01-04 20:14:17'),(98,15,'Which invention helped in making pottery?','Fire','Stone tools','Wheel','Farming','c','The wheel was used to make pots, known as pottery.','medium','2026-01-04 20:14:17'),(99,15,'Which object inspired the invention of the wheel?','Stone','Rolling log','River','Animal','b','A rolling log gave early humans the idea of the wheel.','medium','2026-01-04 20:14:17'),(100,15,'Why was the wheel important for early humans?','It gave light','It helped carry loads','It made fire','It grew crops','b','The wheel made carrying heavy loads easier.','medium','2026-01-04 20:14:17'),(101,15,'The invention of wheel helped humans mainly in:','Hunting','Travelling and transport','Sleeping','Cooking','b','Wheel improved movement and transport.','medium','2026-01-04 20:14:17'),(102,15,'Which activity reduced the need for constant movement?','Hunting','Nomadic life','Farming','Gathering','c','Farming allowed humans to stay in one place.','medium','2026-01-04 20:14:17'),(103,15,'Settled life means:','Moving daily','Living in one place','Living in caves only','Living in forests','b','Settled life means staying in one place.','medium','2026-01-04 20:14:17'),(104,15,'Which discovery came after stone tools?','Fire','Writing','Computers','Cities','a','Fire was discovered after the use of stone tools.','medium','2026-01-04 20:14:17'),(105,15,'Early humans improved their lives by:','Avoiding tools','Making discoveries','Living alone','Staying hungry','b','New discoveries improved early human life.','medium','2026-01-04 20:14:17'),(106,15,'Which of the following best describes early humans?','Machine users','Hunters and gatherers','City dwellers','Farm owners','b','They survived by hunting and gathering food.','medium','2026-01-04 20:14:17'),(107,15,'What kind of clothes did early humans wear?','Stitched clothes','Modern clothes','Natural coverings','Uniforms','c','They used leaves, bark, and animal skin.','medium','2026-01-04 20:14:17'),(108,15,'Which item shows early humans had creative skills?','Caves','Pottery','Hunting','Gathering','b','Pottery shows creativity and skill.','medium','2026-01-04 20:14:17'),(109,15,'Which invention helped in weaving clothes?','Fire','Wheel','Stone','Farming','b','The wheel was also used in weaving.','medium','2026-01-04 20:14:17'),(110,15,'Which of these helped in the development of civilisation?','Fire','Farming','Wheel','All of these','d','All these discoveries together developed civilisation.','medium','2026-01-04 20:14:17'),(111,15,'Why is fire considered a major discovery?','It gave food','It changed human life','It made tools','It built houses','b','Fire brought major changes in daily life.','medium','2026-01-04 20:14:17'),(112,15,'Which discovery helped humans live safely at night?','Fire','Wheel','Farming','Caves','a','Fire provided light and protection at night.','medium','2026-01-04 20:14:17'),(113,15,'Which natural material helped early humans make their first shelters?','Bricks','Branches of trees','Cement','Metal sheets','b','Early humans lived on tree branches and in caves using natural surroundings as shelter.','medium','2026-01-04 20:14:32'),(114,15,'Why did early humans not live in one place permanently?','They liked travelling','They had no food storage','They followed animals and food','They built cities','c','They moved to find food, water, and shelter.','medium','2026-01-04 20:14:32'),(115,15,'What type of tools did early humans use first?','Metal tools','Stone tools','Plastic tools','Wooden tools only','b','Stone tools were the earliest tools used by humans.','medium','2026-01-04 20:14:32'),(116,15,'What helped early humans cut meat easily?','Fire','Sharp stones','Wheel','Hands','b','Sharp stone tools were used to cut meat.','medium','2026-01-04 20:14:32'),(117,15,'Which activity shows early humans depended on animals?','Pottery','Hunting','Farming','Weaving','b','Early humans hunted animals for food.','medium','2026-01-04 20:14:32'),(118,15,'Which discovery helped humans protect themselves during winter?','Wheel','Fire','Stone tools','Caves','b','Fire helped early humans keep warm.','medium','2026-01-04 20:14:32'),(119,15,'What did early humans use fire for first?','Cooking rice','Burning leaves','Making machines','Growing crops','b','They first used fire to burn leaves using sparks.','medium','2026-01-04 20:14:32'),(120,15,'Which discovery slowly reduced hunting as the main activity?','Wheel','Fire','Farming','Pottery','c','Farming provided a regular food supply.','medium','2026-01-04 20:14:32'),(121,15,'What did early humans learn by observing plants?','Trading','Farming','Hunting','Weaving','b','They learned farming by observing seeds grow into plants.','medium','2026-01-04 20:14:32'),(122,15,'Which invention helped in carrying hunted animals easily?','Fire','Pottery','Wheel','Stone knife','c','The wheel helped transport heavy loads.','medium','2026-01-04 20:14:32'),(123,15,'What type of life did early humans live before farming?','Settled life','Nomadic life','City life','Village life','b','Before farming, humans lived a nomadic life.','medium','2026-01-04 20:14:32'),(124,15,'Which discovery helped humans stay in one place for a long time?','Fire','Stone tools','Farming','Hunting','c','Farming allowed humans to live a settled life.','medium','2026-01-04 20:14:32'),(125,15,'Which tool was made by tying stone to a stick?','Hammer','Spear','Pot','Wheel','b','Stones tied to sticks helped in hunting.','medium','2026-01-04 20:14:32'),(126,15,'Why were caves safe places for early humans?','They were comfortable','They were dark','They protected from animals','They were warm','c','Caves protected humans from wild animals.','medium','2026-01-04 20:14:32'),(127,15,'Which invention helped in making pots round in shape?','Fire','Stone','Wheel','Wood','c','The wheel helped shape pots evenly.','medium','2026-01-04 20:14:32'),(128,15,'Which activity shows early humans were creative?','Hunting','Living in caves','Pottery making','Gathering fruits','c','Pottery making shows creativity.','medium','2026-01-04 20:14:32'),(129,15,'Which discovery helped humans improve daily life the most?','Fire','Wheel','Farming','All of these','d','All discoveries together improved daily life.','medium','2026-01-04 20:14:32'),(130,15,'What kind of food did early humans cook after discovering fire?','Fruits','Roots','Meat','Seeds','c','Fire allowed early humans to cook meat.','medium','2026-01-04 20:14:32'),(131,15,'Why was fire important at night?','To sleep','To cook only','For light and safety','To farm','c','Fire gave light and protection at night.','medium','2026-01-04 20:14:32'),(132,15,'Which statement is correct about early humans?','They lived in cities','They used modern tools','They depended on nature','They wore stitched clothes','c','Early humans depended completely on nature.','medium','2026-01-04 20:14:32');
/*!40000 ALTER TABLE `mcqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mental_math_progress`
--

DROP TABLE IF EXISTS `mental_math_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mental_math_progress` (
  `user_id` int(11) NOT NULL,
  `current_level` int(11) DEFAULT 1,
  `total_problems` int(11) DEFAULT 0,
  `avg_accuracy` float DEFAULT 0,
  `avg_speed` float DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mental_math_progress`
--

LOCK TABLES `mental_math_progress` WRITE;
/*!40000 ALTER TABLE `mental_math_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `mental_math_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `note_type` enum('pdf','html') DEFAULT 'html',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `idx_chapter_id` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (1,1,'Real Numbers - Complete Notes',NULL,'<h1>Real Numbers</h1><p>Real numbers include all rational and irrational numbers...</p>','html','2025-11-29 18:17:04'),(2,2,'Polynomials Study Guide',NULL,'<h1>Polynomials</h1><p>A polynomial is an expression consisting of variables and coefficients...</p>','html','2025-11-29 18:17:04'),(3,13,'सूर्य, चंद्र आणि पृथ्वी','uploads/notes/1766166465____________________________________________________.pdf','','pdf','2025-12-19 17:47:45'),(4,13,'sun, moon and earth','uploads/notes/1766501947____________________________________________________.pdf','','pdf','2025-12-23 14:59:07'),(5,14,'ELECTRICITY','uploads/notes/1766502046_The_Electricity_Sketchbook.pdf','','pdf','2025-12-23 15:00:46'),(6,14,'electricity','uploads/notes/1766504305_Acid_Base_Detectives.pdf','','pdf','2025-12-23 15:38:25'),(7,15,'EARLY HUMANS','https://drive.google.com/file/d/15yqdggcmsWQUvmTSJ7arqKXOlcKrxQpH/view?usp=sharing','','pdf','2026-01-04 10:48:47'),(8,15,'EARLY HUMANS 1','https://drive.google.com/uc?export=download&id=15yqdggcmsWQUvmTSJ7arqKXOlcKrxQpH','','pdf','2026-01-09 16:34:08');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,10,'Homework Assignment','Complete Chapter 1 exercises by Friday. Focus on problems 1-10.','2025-11-29 18:17:04'),(2,2,10,'Test Announcement','Unit test on Real Numbers and Polynomials scheduled for next Monday.','2025-11-29 18:17:04');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quick_revision`
--

DROP TABLE IF EXISTS `quick_revision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quick_revision` (
  `revision_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `key_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`key_points`)),
  `summary` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`revision_id`),
  KEY `chapter_id` (`chapter_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quick_revision`
--

LOCK TABLES `quick_revision` WRITE;
/*!40000 ALTER TABLE `quick_revision` DISABLE KEYS */;
INSERT INTO `quick_revision` VALUES (1,13,'Geography Quick Revision','[{\"q\":\"What is the capital of France?\",\"a\":\"Paris\"},{\"q\":\"What process converts light to energy?\",\"a\":\"Photosynthesis\"},{\"q\":\"How many planets are in the solar system?\",\"a\":\"8 planets\"},{\"q\":\"How much of Earth surface is water?\",\"a\":\"71%\"},{\"q\":\"What is the highest peak on Earth?\",\"a\":\"Mount Everest\"}]','This chapter covers basic geography concepts including capitals, natural processes, and planetary facts.','2025-12-30 19:29:35'),(13,15,'EARLY HUMANS','[{\"q\":\"\\ufeffQuestion\",\"a\":\"Answer\"},{\"q\":\"Who were early humans?\",\"a\":\"Early humans were the first people who lived long ago. They depended on nature for food, shelter, and protection.\"},{\"q\":\"What kind of life did early humans lead?\",\"a\":\"Early humans led a nomadic life. They moved from place to place in search of food, water, and shelter.\"},{\"q\":\"Why did early humans live a nomadic life?\",\"a\":\"Early humans did not know farming. They moved to find food, water, and safe shelter.\"},{\"q\":\"Where did early humans live?\",\"a\":\"Early humans lived in caves and on branches of trees to protect themselves from wild animals and bad weather.\"},{\"q\":\"What were early humans called and why?\",\"a\":\"Early humans were called hunters and gatherers because they hunted animals and gathered fruits, roots, and eggs.\"},{\"q\":\"What food did early humans eat?\",\"a\":\"Early humans ate meat of animals and birds, fruits, roots, and eggs collected from nature.\"},{\"q\":\"What clothes did early humans wear?\",\"a\":\"Early humans did not wear stitched clothes. They used leaves, tree bark, and animal skin to cover their bodies.\"},{\"q\":\"What tools did early humans use?\",\"a\":\"Early humans mostly used stone tools. Sometimes tools were also made from animal bones and horns.\"},{\"q\":\"Why were stone tools important?\",\"a\":\"Stone tools helped early humans hunt animals, cut meat, and dig roots from the ground.\"},{\"q\":\"How did early humans improve their tools?\",\"a\":\"They tied sharp stones to wooden sticks. This helped them hunt animals from a distance.\"},{\"q\":\"How was fire discovered?\",\"a\":\"Fire was discovered when early humans rubbed two stones together and produced sparks.\"},{\"q\":\"What were the uses of fire?\",\"a\":\"Fire was used for cooking food, keeping warm, giving light, and protecting from wild animals.\"},{\"q\":\"Why was the discovery of fire important?\",\"a\":\"Fire improved daily life and played a major role in the development of human civilisation.\"},{\"q\":\"How did early humans begin farming?\",\"a\":\"Early humans noticed that seeds thrown on the ground grew into plants. This led to farming.\"},{\"q\":\"What is farming?\",\"a\":\"Farming means growing crops for food. It helped humans produce their own food.\"},{\"q\":\"How did farming change human life?\",\"a\":\"Farming allowed humans to stay in one place and start a settled life.\"},{\"q\":\"How was the wheel invented?\",\"a\":\"Early humans saw a log rolling down a hill. This gave them the idea of the wheel.\"},{\"q\":\"What were the uses of the wheel?\",\"a\":\"The wheel helped in carrying heavy loads, making carts, pottery, and weaving clothes.\"},{\"q\":\"Why was the wheel an important invention?\",\"a\":\"The wheel made work easier and improved transport and daily life.\"},{\"q\":\"What is pottery?\",\"a\":\"Pottery is the art of making pots. Early humans used the wheel to make pots.\"},{\"q\":\"What is a settled life?\",\"a\":\"A settled life means living in one place permanently instead of moving from place to place.\"},{\"q\":\"How did farming and the wheel help humans live a settled life?\",\"a\":\"Farming provided regular food and the wheel made daily work easier.\"},{\"q\":\"Which discoveries helped in the development of civilisation?\",\"a\":\"The discovery of fire, farming, and the invention of the wheel helped develop civilisation.\"},{\"q\":\"Why are early humans associated with the Stone Age?\",\"a\":\"Early humans mainly used stone tools. Therefore, this period is called the Stone Age.\"},{\"q\":\"How did caves help early humans?\",\"a\":\"Caves gave shelter and protected early humans from wild animals and harsh weather.\"},{\"q\":\"How did early humans protect themselves from wild animals?\",\"a\":\"Early humans used fire and lived in safe places like caves.\"},{\"q\":\"What changes occurred in human life over time?\",\"a\":\"Humans discovered fire, farming, and the wheel which improved their lifestyle.\"},{\"q\":\"Why is it important to study early human life?\",\"a\":\"It helps us understand how human civilisation developed step by step.\"}]','EARLY HUMANS','2026-01-04 19:42:38');
/*!40000 ALTER TABLE `quick_revision` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_mental_math_progress`
--

DROP TABLE IF EXISTS `student_mental_math_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_mental_math_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `level` int(11) DEFAULT 1,
  `total_sets_completed` int(11) DEFAULT 0,
  `total_correct_answers` int(11) DEFAULT 0,
  `last_played` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_mental_math_progress`
--

LOCK TABLES `student_mental_math_progress` WRITE;
/*!40000 ALTER TABLE `student_mental_math_progress` DISABLE KEYS */;
INSERT INTO `student_mental_math_progress` VALUES (1,4,7,9,78,'2026-01-08 17:18:53');
/*!40000 ALTER TABLE `student_mental_math_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_progress`
--

DROP TABLE IF EXISTS `student_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chapter_id` int(11) DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `mcq_score` int(11) DEFAULT NULL,
  `total_mcq` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`progress_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_chapter_id` (`chapter_id`),
  KEY `idx_progress_user_chapter` (`user_id`,`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_progress`
--

LOCK TABLES `student_progress` WRITE;
/*!40000 ALTER TABLE `student_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`subject_id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_subject_class` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,10,'Mathematics','Advanced mathematics for Class 10','2025-11-29 18:17:04'),(2,10,'Science','Physics, Chemistry, and Biology','2025-11-29 18:17:04'),(3,10,'English','English language and literature','2025-11-29 18:17:04'),(4,10,'Social Studies','History, Geography, and Civics','2025-11-29 18:17:04'),(5,9,'Mathematics','Mathematics for Class 9','2025-11-29 18:17:04'),(6,9,'Science','Science fundamentals','2025-11-29 18:17:04'),(7,9,'English','English basics','2025-11-29 18:17:04'),(8,1,'ENGLISH','','2025-11-30 08:00:26'),(9,1,'MATHS','','2025-11-30 08:01:43'),(10,1,'SCIENCE','','2025-11-30 08:01:47'),(11,1,'SOCIAL STUDIES','','2025-12-01 18:32:15'),(12,3,'SOCIAL STUDIES','','2025-12-18 17:39:41'),(13,3,'ENGLISH','','2025-12-18 17:39:46'),(14,3,'SCIENCE','','2025-12-18 17:39:51'),(15,6,'ENGLISH','','2025-12-18 17:40:13'),(16,3,'MATHS','','2025-12-18 17:40:18'),(17,7,'GEOGRAPHY','','2025-12-19 17:34:16'),(18,7,'SCIENCE','','2025-12-23 14:46:19');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `plan_id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,'Free Trial',0.00,7,'7-day free trial','Access to basic content, Limited MCQs',1,'2025-11-29 18:17:04'),(2,'Monthly Plan',99.00,30,'Monthly subscription','Full access to all content, Unlimited MCQs, AI Tutor',1,'2025-11-29 18:17:04'),(3,'Yearly Plan',999.00,365,'Annual subscription with discount','Full access, Unlimited MCQs, AI Tutor, Priority Support, Offline Downloads',1,'2025-11-29 18:17:04');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_badges`
--

DROP TABLE IF EXISTS `user_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_badges` (
  `user_badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_badge_id`),
  UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  KEY `badge_id` (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_badges`
--

LOCK TABLES `user_badges` WRITE;
/*!40000 ALTER TABLE `user_badges` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_vocab_progress`
--

DROP TABLE IF EXISTS `user_vocab_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_vocab_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `word_id` int(11) NOT NULL,
  `easiness_factor` decimal(4,2) DEFAULT 2.50 COMMENT 'EF: 1.3 to 2.5+, affects interval growth',
  `interval_days` int(11) DEFAULT 1 COMMENT 'Days until next review',
  `repetitions` int(11) DEFAULT 0 COMMENT 'Number of successful repetitions',
  `next_review_date` date NOT NULL,
  `review_count` int(11) DEFAULT 0 COMMENT 'Total times reviewed',
  `correct_count` int(11) DEFAULT 0 COMMENT 'Times answered correctly',
  `mastery_status` enum('New','Learning','Review','Mastered') DEFAULT 'New',
  `average_rating` decimal(3,2) DEFAULT 0.00 COMMENT 'Average of all ratings (1-5)',
  `last_rating` int(11) DEFAULT 0 COMMENT 'Last rating given (1-5)',
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_reviewed_at` timestamp NULL DEFAULT NULL,
  `mastered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `unique_user_word` (`user_id`,`word_id`),
  KEY `word_id` (`word_id`),
  KEY `idx_user_next_review` (`user_id`,`next_review_date`),
  KEY `idx_mastery_status` (`mastery_status`),
  KEY `idx_next_review` (`next_review_date`),
  KEY `idx_user_status_date` (`user_id`,`mastery_status`,`next_review_date`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_vocab_progress`
--

LOCK TABLES `user_vocab_progress` WRITE;
/*!40000 ALTER TABLE `user_vocab_progress` DISABLE KEYS */;
INSERT INTO `user_vocab_progress` VALUES (1,4,1,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:32','2026-01-08 17:30:56',NULL),(2,4,2,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:35','2026-01-08 17:30:59',NULL),(3,4,3,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:36','2026-01-08 17:31:01',NULL),(4,4,4,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:38','2026-01-08 17:31:04',NULL),(5,4,5,2.50,6,2,'2026-01-14',2,2,'Learning',4.00,4,'2026-01-08 17:30:41','2026-01-08 17:31:08',NULL),(6,4,6,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:43','2026-01-08 17:31:10',NULL),(7,4,7,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:45','2026-01-08 17:31:12',NULL),(8,4,8,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:46','2026-01-08 17:31:17',NULL),(9,4,9,2.50,6,2,'2026-01-14',2,2,'Learning',4.00,4,'2026-01-08 17:30:48','2026-01-08 17:31:19',NULL),(10,4,10,2.18,1,1,'2026-01-09',2,1,'Learning',3.00,4,'2026-01-08 17:30:50','2026-01-08 17:31:22',NULL),(11,4,11,1.86,1,0,'2026-01-10',2,0,'Learning',2.00,2,'2026-01-08 17:43:04','2026-01-09 15:57:40',NULL),(12,4,12,2.50,6,2,'2026-01-15',2,2,'Learning',4.00,4,'2026-01-08 17:43:09','2026-01-09 15:57:41',NULL),(13,4,13,2.50,1,1,'2026-01-09',1,1,'Learning',4.00,4,'2026-01-08 17:43:15','2026-01-08 17:43:15',NULL),(14,4,14,2.50,1,1,'2026-01-09',1,1,'Learning',4.00,4,'2026-01-08 17:43:18','2026-01-08 17:43:18',NULL),(15,4,15,2.50,1,1,'2026-01-09',1,1,'Learning',4.00,4,'2026-01-08 17:43:25','2026-01-08 17:43:25',NULL);
/*!40000 ALTER TABLE `user_vocab_progress` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_user_stats_on_progress
AFTER UPDATE ON user_vocab_progress
FOR EACH ROW
BEGIN
    DECLARE total_learned INT;
    DECLARE in_progress INT;
    DECLARE mastered INT;
    
    SELECT COUNT(*) INTO total_learned
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id;
    
    SELECT COUNT(*) INTO in_progress
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id AND mastery_status IN ('Learning', 'Review');
    
    SELECT COUNT(*) INTO mastered
    FROM user_vocab_progress
    WHERE user_id = NEW.user_id AND mastery_status = 'Mastered';
    
    UPDATE user_vocab_stats
    SET 
        total_words_learned = total_learned,
        words_in_progress = in_progress,
        words_mastered = mastered,
        updated_at = CURRENT_TIMESTAMP
    WHERE user_id = NEW.user_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `user_vocab_stats`
--

DROP TABLE IF EXISTS `user_vocab_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_vocab_stats` (
  `stats_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_words_learned` int(11) DEFAULT 0 COMMENT 'Words added to learning list',
  `words_in_progress` int(11) DEFAULT 0 COMMENT 'Currently learning',
  `words_mastered` int(11) DEFAULT 0 COMMENT 'Fully mastered words',
  `total_reviews` int(11) DEFAULT 0 COMMENT 'Total review sessions',
  `total_correct` int(11) DEFAULT 0 COMMENT 'Total correct answers',
  `accuracy_percentage` decimal(5,2) DEFAULT 0.00,
  `current_streak` int(11) DEFAULT 0 COMMENT 'Current daily streak',
  `longest_streak` int(11) DEFAULT 0 COMMENT 'Best streak achieved',
  `last_practice_date` date DEFAULT NULL,
  `total_study_time_minutes` int(11) DEFAULT 0,
  `average_session_time_minutes` decimal(5,2) DEFAULT 0.00,
  `level` int(11) DEFAULT 1 COMMENT 'User level (1-100)',
  `experience_points` int(11) DEFAULT 0 COMMENT 'XP for gamification',
  `badges_earned` text DEFAULT NULL COMMENT 'JSON array of badge IDs',
  `has_premium_access` tinyint(1) DEFAULT 0,
  `premium_expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_set` int(11) DEFAULT 1 COMMENT 'Current set user is learning',
  `sets_completed` int(11) DEFAULT 0 COMMENT 'Number of sets completed',
  `highest_set_unlocked` int(11) DEFAULT 1 COMMENT 'Highest set unlocked',
  `total_words_mastered` int(11) DEFAULT 0,
  `streak_days` int(11) DEFAULT 0,
  PRIMARY KEY (`stats_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_streak` (`current_streak`),
  KEY `idx_mastered` (`words_mastered`),
  KEY `idx_level` (`level`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_vocab_stats`
--

LOCK TABLES `user_vocab_stats` WRITE;
/*!40000 ALTER TABLE `user_vocab_stats` DISABLE KEYS */;
INSERT INTO `user_vocab_stats` VALUES (1,4,15,15,0,27,0,0.00,0,0,'2026-01-09',0,0.00,1,150,NULL,0,NULL,'2026-01-08 17:30:27','2026-01-09 15:57:41',2,1,2,0,0);
/*!40000 ALTER TABLE `user_vocab_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `push_token` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `phone` varchar(20) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subscription_status` enum('active','inactive') DEFAULT 'inactive',
  `subscription_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `login_streak` int(11) DEFAULT 0,
  `school_name` varchar(255) DEFAULT NULL,
  `board` enum('CBSE','State Board') DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_user_class` (`class_id`,`user_type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin User','admin@example.com',NULL,NULL,'$2y$10$3MPEgIf60wlKGIvrCS8pf.GWlszIe3DqK3J87bF2DJFsM1tFbCA2q','admin',NULL,NULL,'active',NULL,'2025-11-29 18:17:04',NULL,0,NULL,NULL,NULL),(2,'John Teacher','teacher@example.com',NULL,NULL,'$2y$10$dyvWuAp2oUjxwRynToEAse1W5DbYrU43WvjaagiTUqato9iodiemK','teacher','1234567890',NULL,'inactive',NULL,'2025-11-29 18:17:04',NULL,0,NULL,NULL,NULL),(4,'Test Student','student@example.com',NULL,NULL,'$2y$10$LrwycdfxN0fhxkz2gnb36Oo4Xr22dR0lM1CziPA1m5t/0/gQPOo9q','student',NULL,3,'active','2030-12-31','2025-12-06 16:26:03','2026-01-09 23:18:26',1,NULL,NULL,NULL),(5,'Viraj Bansode','santoshbansode24@gmail.com',NULL,NULL,'$2y$10$JaGeaSePJ0wUumfX38Mw5exk/fP1LrrMlWb1OUd0S0Ef6fI0xzC5.','student',NULL,3,'active','2026-02-08','2026-01-09 18:21:33','2026-01-10 00:12:17',1,'Bankatlal lahoti english school latur','CBSE','7755952198');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_user_due_words`
--

DROP TABLE IF EXISTS `v_user_due_words`;
/*!50001 DROP VIEW IF EXISTS `v_user_due_words`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_user_due_words` AS SELECT
 1 AS `user_id`,
  1 AS `word_id`,
  1 AS `word`,
  1 AS `definition`,
  1 AS `example_sentence`,
  1 AS `difficulty_level`,
  1 AS `mastery_status`,
  1 AS `review_count`,
  1 AS `next_review_date`,
  1 AS `category_name` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_user_vocab_summary`
--

DROP TABLE IF EXISTS `v_user_vocab_summary`;
/*!50001 DROP VIEW IF EXISTS `v_user_vocab_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_user_vocab_summary` AS SELECT
 1 AS `user_id`,
  1 AS `name`,
  1 AS `total_words`,
  1 AS `mastered_words`,
  1 AS `current_streak`,
  1 AS `longest_streak`,
  1 AS `accuracy`,
  1 AS `level` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `videos`
--

DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `videos` (
  `video_id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`video_id`),
  KEY `idx_chapter_id` (`chapter_id`),
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`chapter_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `videos`
--

LOCK TABLES `videos` WRITE;
/*!40000 ALTER TABLE `videos` DISABLE KEYS */;
INSERT INTO `videos` VALUES (1,15,'EARLY HUMANS','https://youtu.be/aBrRfw2Ifkc','','','2026-01-04 19:31:56');
/*!40000 ALTER TABLE `videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vocab_categories`
--

DROP TABLE IF EXISTS `vocab_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vocab_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `access_level` enum('Free','Premium') DEFAULT 'Free',
  `description` text DEFAULT NULL,
  `icon_emoji` varchar(10) DEFAULT '­ƒôÜ',
  `word_count` int(11) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`),
  KEY `idx_access_level` (`access_level`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1651 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vocab_categories`
--

LOCK TABLES `vocab_categories` WRITE;
/*!40000 ALTER TABLE `vocab_categories` DISABLE KEYS */;
INSERT INTO `vocab_categories` VALUES (1,'Core Academic Words','Free','Essential words for academic success','­ƒôû',0,1,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(2,'MCQ Starter Set','Free','Common words found in MCQ questions','­ƒÄ»',0,2,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(3,'Daily Essentials','Free','Everyday vocabulary for students','ÔÿÇ´©Å',25,3,1,'2026-01-03 06:39:13','2026-01-03 06:44:03'),(4,'GRE Preparation','Premium','Advanced words for GRE exam','­ƒÄô',0,4,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(5,'SAT Preparation','Premium','Essential SAT vocabulary','­ƒôØ',0,5,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(6,'Advanced Academic','Premium','College-level academic vocabulary','­ƒÅø´©Å',0,6,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(7,'Scientific Terms','Premium','Science and research vocabulary','­ƒö¼',0,7,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(8,'Business English','Premium','Professional and business terms','­ƒÆ╝',0,8,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(9,'Literary Words','Premium','Advanced literary vocabulary','­ƒôÜ',0,9,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(10,'Idioms & Phrases','Premium','Common English idioms','­ƒÆ¼',0,10,1,'2026-01-03 06:39:13','2026-01-03 06:39:13'),(11,'General','Free',NULL,'­ƒôÜ',1640,0,1,'2026-01-08 17:25:43','2026-01-08 17:29:30');
/*!40000 ALTER TABLE `vocab_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vocab_review_history`
--

DROP TABLE IF EXISTS `vocab_review_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vocab_review_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `word_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL COMMENT 'User rating: 1-5',
  `time_taken_seconds` int(11) DEFAULT 0,
  `was_correct` tinyint(1) DEFAULT 0,
  `previous_interval` int(11) DEFAULT NULL,
  `new_interval` int(11) DEFAULT NULL,
  `previous_easiness` decimal(4,2) DEFAULT NULL,
  `new_easiness` decimal(4,2) DEFAULT NULL,
  `review_type` enum('scheduled','extra','failed') DEFAULT 'scheduled',
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_user_date` (`user_id`,`reviewed_at`),
  KEY `idx_word` (`word_id`),
  CONSTRAINT `vocab_review_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `vocab_review_history_ibfk_2` FOREIGN KEY (`word_id`) REFERENCES `vocab_words` (`word_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vocab_review_history`
--

LOCK TABLES `vocab_review_history` WRITE;
/*!40000 ALTER TABLE `vocab_review_history` DISABLE KEYS */;
INSERT INTO `vocab_review_history` VALUES (1,4,1,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-03 06:54:45'),(2,4,1,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:21:56'),(3,4,2,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:21:58'),(4,4,3,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:22:02'),(5,4,4,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:22:07'),(6,4,5,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:22:08'),(7,4,6,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:22:11'),(8,4,1,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:32'),(9,4,2,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:35'),(10,4,3,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:36'),(11,4,4,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:38'),(12,4,5,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:30:41'),(13,4,6,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:43'),(14,4,7,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:45'),(15,4,8,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:46'),(16,4,9,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:30:48'),(17,4,10,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:30:50'),(18,4,1,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:30:56'),(19,4,2,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:30:59'),(20,4,3,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:01'),(21,4,4,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:04'),(22,4,5,4,0,1,1,6,2.50,2.50,'scheduled','2026-01-08 17:31:08'),(23,4,6,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:10'),(24,4,7,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:12'),(25,4,8,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:17'),(26,4,9,4,0,1,1,6,2.50,2.50,'scheduled','2026-01-08 17:31:19'),(27,4,10,4,0,1,1,1,2.18,2.18,'scheduled','2026-01-08 17:31:22'),(28,4,11,2,0,0,1,1,2.50,2.18,'scheduled','2026-01-08 17:43:04'),(29,4,12,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:43:09'),(30,4,13,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:43:15'),(31,4,14,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:43:18'),(32,4,15,4,0,1,1,1,2.50,2.50,'scheduled','2026-01-08 17:43:25'),(33,4,11,2,0,0,1,1,2.18,1.86,'scheduled','2026-01-09 15:57:40'),(34,4,12,4,0,1,1,6,2.50,2.50,'scheduled','2026-01-09 15:57:41');
/*!40000 ALTER TABLE `vocab_review_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vocab_words`
--

DROP TABLE IF EXISTS `vocab_words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vocab_words` (
  `word_id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL,
  `definition` text NOT NULL,
  `example_sentence` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `pronunciation_text` varchar(100) DEFAULT NULL,
  `audio_file_url` varchar(255) DEFAULT NULL,
  `difficulty_level` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  `usage_frequency` enum('Common','Moderate','Rare') DEFAULT 'Moderate',
  `synonyms` text DEFAULT NULL COMMENT 'Comma-separated synonyms',
  `antonyms` text DEFAULT NULL COMMENT 'Comma-separated antonyms',
  `word_type` varchar(50) DEFAULT NULL COMMENT 'noun, verb, adjective, etc.',
  `etymology` text DEFAULT NULL COMMENT 'Word origin/history',
  `mnemonic_hint` text DEFAULT NULL COMMENT 'Memory aid',
  `is_active` tinyint(1) DEFAULT 1,
  `times_reviewed` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `definition_marathi` text DEFAULT NULL COMMENT 'Definition in Marathi language',
  `set_number` int(11) DEFAULT 1,
  `level_name` varchar(50) DEFAULT 'Beginner',
  `options` text DEFAULT NULL COMMENT 'JSON array of MCQ options',
  `correct_answer` varchar(100) DEFAULT NULL COMMENT 'Correct answer for MCQ',
  PRIMARY KEY (`word_id`),
  KEY `idx_word` (`word`),
  KEY `idx_category` (`category_id`),
  KEY `idx_difficulty` (`difficulty_level`),
  KEY `idx_active` (`is_active`),
  KEY `idx_category_difficulty` (`category_id`,`difficulty_level`),
  FULLTEXT KEY `ft_word_definition` (`word`,`definition`),
  CONSTRAINT `vocab_words_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `vocab_categories` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vocab_words`
--

LOCK TABLES `vocab_words` WRITE;
/*!40000 ALTER TABLE `vocab_words` DISABLE KEYS */;
INSERT INTO `vocab_words` VALUES (1,'Happy','Feeling or showing pleasure or contentment.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:30:56','आनंदी, समाधानी.',1,'Beginner','{\"A\":\"Sad\",\"B\":\"Joyful\",\"C\":\"Angry\",\"D\":\"Tired\"}','B'),(2,'Big','Of considerable size, extent, or intensity.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:30:59','मोठा, विशाल.',1,'Beginner','{\"A\":\"Large\",\"B\":\"Small\",\"C\":\"Tiny\",\"D\":\"Weak\"}','A'),(3,'Fast','Moving or capable of moving at high speed.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:01','वेगवान, जलद.',1,'Beginner','{\"A\":\"Slow\",\"B\":\"Lazy\",\"C\":\"Quick\",\"D\":\"Late\"}','C'),(4,'Beautiful','Pleasing the senses or mind aesthetically.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:04','सुंदर, देखणा.',1,'Beginner','{\"A\":\"Ugly\",\"B\":\"Pretty\",\"C\":\"Dirty\",\"D\":\"Mean\"}','B'),(5,'Start','To cause something to happen or exist.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:08','सुरुवात करणे.',1,'Beginner','{\"A\":\"Stop\",\"B\":\"End\",\"C\":\"Begin\",\"D\":\"Finish\"}','C'),(6,'Rich','Having a great deal of money or assets.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:10','श्रीमंत, धनवान.',1,'Beginner','{\"A\":\"Poor\",\"B\":\"Weak\",\"C\":\"Wealthy\",\"D\":\"Sick\"}','C'),(7,'Smart','Having or showing a quick-witted intelligence.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:12','हुशार, बुद्धिमान.',1,'Beginner','{\"A\":\"Dumb\",\"B\":\"Clever\",\"C\":\"Slow\",\"D\":\"Dull\"}','B'),(8,'Angry','Feeling or showing strong annoyance or hostility.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:17','रागावलेला, संतापलेला.',1,'Beginner','{\"A\":\"Calm\",\"B\":\"Happy\",\"C\":\"Mad\",\"D\":\"Glad\"}','C'),(9,'Small','Of a size that is less than normal or usual.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:19','लहान, छोटा.',1,'Beginner','{\"A\":\"Huge\",\"B\":\"Giant\",\"C\":\"Tiny\",\"D\":\"Tall\"}','C'),(10,'Help','To make it easier for someone to do something.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-08 17:31:22','मदत करणे, सहाय्य करणे.',1,'Beginner','{\"A\":\"Hurt\",\"B\":\"Ignore\",\"C\":\"Block\",\"D\":\"Assist\"}','D'),(11,'Quiet','Making little or no noise.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-09 15:57:40','शांत, निमूट.',2,'Beginner','{\"A\":\"Loud\",\"B\":\"Noisy\",\"C\":\"Silent\",\"D\":\"Wild\"}','C'),(12,'Correct','Free from error; in accordance with fact or truth.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,2,'2026-01-08 17:29:30','2026-01-09 15:57:41','बरोबर, अचूक.',2,'Beginner','{\"A\":\"Wrong\",\"B\":\"False\",\"C\":\"Right\",\"D\":\"Bad\"}','C'),(13,'Difficult','Needing much effort or skill to accomplish.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,1,'2026-01-08 17:29:30','2026-01-08 17:43:15','कठीण, अवघड.',2,'Beginner','{\"A\":\"Easy\",\"B\":\"Simple\",\"C\":\"Hard\",\"D\":\"Soft\"}','C'),(14,'End','A final part of something.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,1,'2026-01-08 17:29:30','2026-01-08 17:43:18','शेवट, अंत.',2,'Beginner','{\"A\":\"Start\",\"B\":\"Begin\",\"C\":\"Finish\",\"D\":\"Open\"}','C'),(15,'Kind','Having or showing a friendly, generous, and considerate nature.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,1,'2026-01-08 17:29:30','2026-01-08 17:43:25','दयाळू, प्रेमळ.',2,'Beginner','{\"A\":\"Mean\",\"B\":\"Cruel\",\"C\":\"Nice\",\"D\":\"Rude\"}','C'),(16,'Listen','Give attention to sound.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','ऐकणे.',2,'Beginner','{\"A\":\"Speak\",\"B\":\"Hear\",\"C\":\"Talk\",\"D\":\"Run\"}','B'),(17,'Look','Direct one\'s gaze toward someone or something.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पाहणे, बघणे.',2,'Beginner','{\"A\":\"Hear\",\"B\":\"Smell\",\"C\":\"See\",\"D\":\"Touch\"}','C'),(18,'Neat','Arranged in an orderly, tidy way.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','नेटका, व्यवस्थित.',2,'Beginner','{\"A\":\"Messy\",\"B\":\"Dirty\",\"C\":\"Tidy\",\"D\":\"Rough\"}','C'),(19,'Page','One side of a sheet of paper in a book.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पान, पृष्ठ.',2,'Beginner','{\"A\":\"Book\",\"B\":\"Sheet\",\"C\":\"Pen\",\"D\":\"Cover\"}','B'),(20,'Scared','Fearful; frightened.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','घाबरलेला.',2,'Beginner','{\"A\":\"Brave\",\"B\":\"Afraid\",\"C\":\"Bold\",\"D\":\"Happy\"}','B'),(21,'True','In accordance with fact or reality.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','खरे, सत्य.',3,'Beginner','{\"A\":\"Fake\",\"B\":\"Real\",\"C\":\"Lie\",\"D\":\"False\"}','B'),(22,'Yell','Give a loud, sharp cry.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','ओरडणे, किंचाळणे.',3,'Beginner','{\"A\":\"Whisper\",\"B\":\"Shout\",\"C\":\"Talk\",\"D\":\"Sing\"}','B'),(23,'Zero','No quantity or number.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शून्य, काहीही नाही.',3,'Beginner','{\"A\":\"All\",\"B\":\"Many\",\"C\":\"Nothing\",\"D\":\"One\"}','C'),(24,'Dull','Lacking interest or excitement.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कंटाळवाणे, मंद.',3,'Beginner','{\"A\":\"Fun\",\"B\":\"Boring\",\"C\":\"Sharp\",\"D\":\"Bright\"}','B'),(25,'Famous','Known about by many people.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रसिद्ध.',3,'Beginner','{\"A\":\"Unknown\",\"B\":\"Hidden\",\"C\":\"Well-known\",\"D\":\"Secret\"}','C'),(26,'Intelligent','Having or showing intelligence.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बुद्धिमान.',3,'Beginner','{\"A\":\"Stupid\",\"B\":\"Smart\",\"C\":\"Silly\",\"D\":\"Crazy\"}','B'),(27,'Joy','A feeling of great pleasure and happiness.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','आनंद, हर्ष.',3,'Beginner','{\"A\":\"Sadness\",\"B\":\"Pain\",\"C\":\"Happiness\",\"D\":\"Fear\"}','C'),(28,'Keep','Have or retain possession of.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','ठेवणे, बाळगणे.',3,'Beginner','{\"A\":\"Drop\",\"B\":\"Hold\",\"C\":\"Lose\",\"D\":\"Throw\"}','B'),(29,'Near','At or to a short distance away.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','जवळ, नजीक.',3,'Beginner','{\"A\":\"Far\",\"B\":\"Distant\",\"C\":\"Close\",\"D\":\"Away\"}','C'),(30,'Quick','Moving fast or doing something in a short time.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','चपळ, जलद.',3,'Beginner','{\"A\":\"Slow\",\"B\":\"Lazy\",\"C\":\"Fast\",\"D\":\"Late\"}','C'),(31,'Action','The fact or process of doing something.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कृती, कार्य.',4,'Beginner','{\"A\":\"Rest\",\"B\":\"Sleep\",\"C\":\"Deed\",\"D\":\"Thought\"}','C'),(32,'Active','Engaging or ready to engage in physically energetic pursuits.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सक्रिय, कार्यमग्न.',4,'Beginner','{\"A\":\"Lazy\",\"B\":\"Busy\",\"C\":\"Idle\",\"D\":\"Slow\"}','B'),(33,'Alarm','An anxious awareness of danger.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धोक्याची सूचना, इशारा.',4,'Beginner','{\"A\":\"Comfort\",\"B\":\"Warning\",\"C\":\"Gift\",\"D\":\"Peace\"}','B'),(34,'Anger','A strong feeling of annoyance, displeasure, or hostility.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','राग, क्रोध.',4,'Beginner','{\"A\":\"Joy\",\"B\":\"Rage\",\"C\":\"Love\",\"D\":\"Calm\"}','B'),(35,'Answer','A thing said, written, or done to deal with or as a reaction to a question.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','उत्तर, प्रतिसाद.',4,'Beginner','{\"A\":\"Ask\",\"B\":\"Reply\",\"C\":\"Question\",\"D\":\"Doubt\"}','B'),(36,'Arrive','Reach a place at the end of a journey or a stage in a journey.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पोहोचणे, आगमन होणे.',4,'Beginner','{\"A\":\"Leave\",\"B\":\"Depart\",\"C\":\"Reach\",\"D\":\"Go\"}','C'),(37,'Ask','Say something in order to obtain an answer or some information.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विचारणे.',4,'Beginner','{\"A\":\"Tell\",\"B\":\"Inquire\",\"C\":\"Answer\",\"D\":\"Give\"}','B'),(38,'Blank','Not filled or written on.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','रिकामी, कोरे.',4,'Beginner','{\"A\":\"Full\",\"B\":\"Written\",\"C\":\"Empty\",\"D\":\"Busy\"}','C'),(39,'Bright','Giving out or reflecting a lot of light.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','तेजस्वी, चमकदार.',4,'Beginner','{\"A\":\"Dark\",\"B\":\"Dull\",\"C\":\"Shiny\",\"D\":\"Dim\"}','C'),(40,'Build','Construct (something, typically something large) by putting parts together.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बांधणे, निर्माण करणे.',4,'Beginner','{\"A\":\"Destroy\",\"B\":\"Break\",\"C\":\"Construct\",\"D\":\"Ruins\"}','C'),(41,'Busy','Having a great deal to do.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','व्यस्त, कामात असलेला.',5,'Beginner','{\"A\":\"Free\",\"B\":\"Occupied\",\"C\":\"Idle\",\"D\":\"Lazy\"}','B'),(42,'Buy','Obtain in exchange for payment.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','खरेदी करणे.',5,'Beginner','{\"A\":\"Sell\",\"B\":\"Give\",\"C\":\"Purchase\",\"D\":\"Lose\"}','C'),(43,'Calm','Not showing or feeling nervousness, anger, or other emotions.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शांत.',5,'Beginner','{\"A\":\"Wild\",\"B\":\"Peaceful\",\"C\":\"Angry\",\"D\":\"Loud\"}','B'),(44,'Center','The middle point of a circle or sphere.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','मध्य, केंद्र.',5,'Beginner','{\"A\":\"Edge\",\"B\":\"Middle\",\"C\":\"Side\",\"D\":\"Corner\"}','B'),(45,'Change','Make or become different.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बदल, बदलणे.',5,'Beginner','{\"A\":\"Keep\",\"B\":\"Stay\",\"C\":\"Alter\",\"D\":\"Hold\"}','C'),(46,'Cheap','Costing little money or less than is usual or expected.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','स्वस्त.',5,'Beginner','{\"A\":\"Costly\",\"B\":\"Rich\",\"C\":\"Inexpensive\",\"D\":\"High\"}','C'),(47,'Choice','An act of selecting or making a decision when faced with two or more possibilities.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','निवड, पर्याय.',5,'Beginner','{\"A\":\"Force\",\"B\":\"Option\",\"C\":\"Rule\",\"D\":\"Law\"}','B'),(48,'Choose','Pick out or select (someone or something) as being the best.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','निवडणे.',5,'Beginner','{\"A\":\"Reject\",\"B\":\"Select\",\"C\":\"Drop\",\"D\":\"Lose\"}','B'),(49,'Close','Move or cause to move so as to cover an opening.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बंद करणे.',5,'Beginner','{\"A\":\"Open\",\"B\":\"Shut\",\"C\":\"Start\",\"D\":\"Free\"}','B'),(50,'Common','Occurring, found, or done often; prevalent.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सामान्य, नेहमीचा.',5,'Beginner','{\"A\":\"Rare\",\"B\":\"Usual\",\"C\":\"Unique\",\"D\":\"Odd\"}','B'),(51,'Connect','Bring together or into contact so that a link is established.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','जोडणे, एकत्र करणे.',6,'Beginner','{\"A\":\"Separate\",\"B\":\"Join\",\"C\":\"Break\",\"D\":\"Cut\"}','B'),(52,'Copy','A thing made to be similar or identical to another.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रत, नकल.',6,'Beginner','{\"A\":\"Original\",\"B\":\"Duplicate\",\"C\":\"Model\",\"D\":\"Create\"}','B'),(53,'Crazy','Mentally deranged, especially as manifested in a wild or aggressive way.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वेडा, अस्ताव्यस्त.',6,'Beginner','{\"A\":\"Sane\",\"B\":\"Wise\",\"C\":\"Insane\",\"D\":\"Calm\"}','C'),(54,'Create','Bring (something) into existence.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','निर्माण करणे, बनवणे.',6,'Beginner','{\"A\":\"Destroy\",\"B\":\"Make\",\"C\":\"Break\",\"D\":\"Kill\"}','B'),(55,'Crowd','A large number of people gathered together.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','गर्दी, जमाव.',6,'Beginner','{\"A\":\"Individual\",\"B\":\"Group\",\"C\":\"Empty\",\"D\":\"One\"}','B'),(56,'Cry','Shed tears, especially as an expression of distress or pain.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','रडणे.',6,'Beginner','{\"A\":\"Laugh\",\"B\":\"Smile\",\"C\":\"Weep\",\"D\":\"Joke\"}','C'),(57,'Dark','With little or no light.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','अंधार, काळोख.',6,'Beginner','{\"A\":\"Light\",\"B\":\"Bright\",\"C\":\"Dim\",\"D\":\"White\"}','C'),(58,'Dead','No longer alive.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','मृत, निर्जीव.',6,'Beginner','{\"A\":\"Alive\",\"B\":\"Living\",\"C\":\"Lifeless\",\"D\":\"Active\"}','C'),(59,'Delicious','Highly pleasant to the taste.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','स्वादिष्ट, चवदार.',6,'Beginner','{\"A\":\"Bad\",\"B\":\"Tasty\",\"C\":\"Sour\",\"D\":\"Bitter\"}','B'),(60,'Dirty','Covered or marked with an unclean substance.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','घाणेरडा, अस्वच्छ.',6,'Beginner','{\"A\":\"Clean\",\"B\":\"Pure\",\"C\":\"Filthy\",\"D\":\"Neat\"}','C'),(61,'Divide','Separate or be separated into parts.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विभागणे, वाटणी करणे.',7,'Beginner','{\"A\":\"Join\",\"B\":\"Split\",\"C\":\"Unite\",\"D\":\"Mix\"}','B'),(62,'Dream','A series of thoughts, images, and sensations occurring in a person\'s mind during sleep.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','स्वप्न.',7,'Beginner','{\"A\":\"Reality\",\"B\":\"Fact\",\"C\":\"Fantasy\",\"D\":\"Truth\"}','C'),(63,'Drop','Let or make (something) fall vertically.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पडणे, खाली टाकणे.',7,'Beginner','{\"A\":\"Pick\",\"B\":\"Lift\",\"C\":\"Fall\",\"D\":\"Hold\"}','C'),(64,'Early','Happening or done before the usual or expected time.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','लवकर.',7,'Beginner','{\"A\":\"Late\",\"B\":\"Delayed\",\"C\":\"Soon\",\"D\":\"After\"}','C'),(65,'Easy','Achieved without great effort; presenting few difficulties.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सोपे, सुलभ.',7,'Beginner','{\"A\":\"Hard\",\"B\":\"Difficult\",\"C\":\"Simple\",\"D\":\"Complex\"}','C'),(66,'Eat','Put (food) into the mouth and chew and swallow it.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','खाणे.',7,'Beginner','{\"A\":\"Starve\",\"B\":\"Consume\",\"C\":\"Drink\",\"D\":\"Cook\"}','B'),(67,'Empty','Containing nothing; not filled or occupied.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','रिकामे.',7,'Beginner','{\"A\":\"Full\",\"B\":\"Vacant\",\"C\":\"Crowded\",\"D\":\"Packed\"}','B'),(68,'Enjoy','Take delight or pleasure in (an activity or occasion).','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','आनंद घेणे, आवडणे.',7,'Beginner','{\"A\":\"Hate\",\"B\":\"Like\",\"C\":\"Dislike\",\"D\":\"Bore\"}','B'),(69,'Enter','Come or go into (a place).','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रवेश करणे.',7,'Beginner','{\"A\":\"Exit\",\"B\":\"Access\",\"C\":\"Leave\",\"D\":\"Depart\"}','B'),(70,'Equal','Being the same in quantity, size, degree, or value.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','समान, सारखे.',7,'Beginner','{\"A\":\"Different\",\"B\":\"Same\",\"C\":\"Uneven\",\"D\":\"Varied\"}','B'),(71,'Evil','Profoundly immoral and malevolent.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वाईट, दुष्ट.',8,'Beginner','{\"A\":\"Good\",\"B\":\"Kind\",\"C\":\"Wicked\",\"D\":\"Holy\"}','C'),(72,'Exit','Go out of or leave a place.','',11,NULL,NULL,'Easy','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बाहेर पडणे.',8,'Beginner','{\"A\":\"Enter\",\"B\":\"Leave\",\"C\":\"Come\",\"D\":\"Stay\"}','B'),(73,'Brave','Ready to face and endure danger or pain.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शूर, धाडसी.',8,'Beginner','{\"A\":\"Scared\",\"B\":\"Courageous\",\"C\":\"Weak\",\"D\":\"Shy\"}','B'),(74,'Funny','Causing laughter or amusement.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विनोदी, मजेशीर.',8,'Beginner','{\"A\":\"Serious\",\"B\":\"Sad\",\"C\":\"Humorous\",\"D\":\"Boring\"}','C'),(75,'Idea','A thought or suggestion as to a possible course of action.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कल्पना, विचार.',8,'Beginner','{\"A\":\"Thing\",\"B\":\"Concept\",\"C\":\"Rock\",\"D\":\"Hand\"}','B'),(76,'Mistake','An action or judgment that is misguided or wrong.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','चूक, गल्लत.',8,'Beginner','{\"A\":\"Fact\",\"B\":\"Error\",\"C\":\"Truth\",\"D\":\"Right\"}','B'),(77,'Old','Having lived for a long time; no longer young.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','जुने, प्राचीन.',8,'Beginner','{\"A\":\"New\",\"B\":\"Fresh\",\"C\":\"Young\",\"D\":\"Ancient\"}','D'),(78,'Run','Move at a speed faster than a walk.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धावणे.',8,'Beginner','{\"A\":\"Walk\",\"B\":\"Sit\",\"C\":\"Sprint\",\"D\":\"Sleep\"}','C'),(79,'Teach','Show or explain to (someone) how to do something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शिकवणे.',8,'Beginner','{\"A\":\"Learn\",\"B\":\"Study\",\"C\":\"Educate\",\"D\":\"Hide\"}','C'),(80,'Use','Take, hold, or deploy (something) as a means of accomplishing a purpose.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वापरणे.',8,'Beginner','{\"A\":\"Waste\",\"B\":\"Drop\",\"C\":\"Utilize\",\"D\":\"Save\"}','C'),(81,'Value','The importance, worth, or usefulness of something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','किंमत, मूल्य.',9,'Beginner','{\"A\":\"Cost\",\"B\":\"Trash\",\"C\":\"Worth\",\"D\":\"Waste\"}','C'),(82,'Want','Have a desire to possess or do (something).','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पाहिजे असणे, इच्छा असणे.',9,'Beginner','{\"A\":\"Hate\",\"B\":\"Desire\",\"C\":\"Have\",\"D\":\"Give\"}','B'),(83,'Candid','Truthful and straightforward.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','निष्कपट, स्पष्टवक्ता.',9,'Beginner','{\"A\":\"Lying\",\"B\":\"Secret\",\"C\":\"Honest\",\"D\":\"Fake\"}','C'),(84,'Eager','Strongly wanting to do or have something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','उत्सुक.',9,'Beginner','{\"A\":\"Bored\",\"B\":\"Keen\",\"C\":\"Slow\",\"D\":\"Calm\"}','B'),(85,'Gigantic','Of very great size or extent.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रचंड, अवाढव्य.',9,'Beginner','{\"A\":\"Tiny\",\"B\":\"Small\",\"C\":\"Huge\",\"D\":\"Short\"}','C'),(86,'Halt','Bring or come to an abrupt stop.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','थांबणे, विराम.',9,'Beginner','{\"A\":\"Go\",\"B\":\"Run\",\"C\":\"Stop\",\"D\":\"Move\"}','C'),(87,'Lucky','Having, bringing, or resulting from good luck.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','नशीबवान.',9,'Beginner','{\"A\":\"Sad\",\"B\":\"Fortunate\",\"C\":\"Bad\",\"D\":\"Poor\"}','B'),(88,'Modern','Relating to the present or recent times.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','आधुनिक, नवीन.',9,'Beginner','{\"A\":\"Old\",\"B\":\"Ancient\",\"C\":\"New\",\"D\":\"Past\"}','C'),(89,'Odd','Different from what is usual or expected.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विचित्र, वेगळा.',9,'Beginner','{\"A\":\"Normal\",\"B\":\"Usual\",\"C\":\"Strange\",\"D\":\"Same\"}','C'),(90,'Polite','Having or showing behavior that is respectful.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विनम्र, सभ्य.',9,'Beginner','{\"A\":\"Rude\",\"B\":\"Respectful\",\"C\":\"Mean\",\"D\":\"Bad\"}','B'),(91,'Rare','Not occurring very often.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','दुर्मिळ.',10,'Beginner','{\"A\":\"Common\",\"B\":\"Uncommon\",\"C\":\"Usual\",\"D\":\"Daily\"}','B'),(92,'Ability','Possession of the means or skill to do something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','क्षमता, कौशल्य.',10,'Beginner','{\"A\":\"Lack\",\"B\":\"Skill\",\"C\":\"Fear\",\"D\":\"Weakness\"}','B'),(93,'Accurate','Correct in all details; exact.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','अचूक, नेमका.',10,'Beginner','{\"A\":\"Wrong\",\"B\":\"Vague\",\"C\":\"Precise\",\"D\":\"False\"}','C'),(94,'Admire','Regard with respect or warm approval.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कौतुक करणे, आदर करणे.',10,'Beginner','{\"A\":\"Hate\",\"B\":\"Respect\",\"C\":\"Ignore\",\"D\":\"Mock\"}','B'),(95,'Advice','Guidance or recommendations offered with regard to prudent future action.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सल्ला, मार्गदर्शन.',10,'Beginner','{\"A\":\"Guidance\",\"B\":\"Silence\",\"C\":\"Trick\",\"D\":\"Lie\"}','A'),(96,'Allow','Give (someone) permission to do something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','परवानगी देणे.',10,'Beginner','{\"A\":\"Ban\",\"B\":\"Stop\",\"C\":\"Permit\",\"D\":\"Deny\"}','C'),(97,'Amuse','Cause (someone) to find something funny or enjoyable.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','करमणूक करणे, मनोरंजन करणे.',10,'Beginner','{\"A\":\"Bore\",\"B\":\"Anger\",\"C\":\"Entertain\",\"D\":\"Tire\"}','C'),(98,'Annoy','Irritate (someone); make (someone) a little angry.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','त्रास देणे, चिडवणे.',10,'Beginner','{\"A\":\"Please\",\"B\":\"Help\",\"C\":\"Irritate\",\"D\":\"Soothe\"}','C'),(99,'Argue','Give reasons or cite evidence in support of an idea, action, or theory.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वाद घालणे.',10,'Beginner','{\"A\":\"Agree\",\"B\":\"Dispute\",\"C\":\"Accept\",\"D\":\"Nod\"}','B'),(100,'Attack','Take aggressive action against.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','हल्ला करणे.',10,'Beginner','{\"A\":\"Defend\",\"B\":\"Assault\",\"C\":\"Protect\",\"D\":\"Guard\"}','B'),(101,'Attempt','Make an effort to achieve or complete.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रयत्न करणे.',11,'Beginner','{\"A\":\"Give up\",\"B\":\"Quit\",\"C\":\"Try\",\"D\":\"Fail\"}','C'),(102,'Awful','Very bad or unpleasant.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','भयंकर, वाईट.',11,'Beginner','{\"A\":\"Good\",\"B\":\"Terrible\",\"C\":\"Nice\",\"D\":\"Great\"}','B'),(103,'Baggage','Personal belongings packed in suitcases for traveling.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सामान, ओझे.',11,'Beginner','{\"A\":\"Luggage\",\"B\":\"People\",\"C\":\"Ticket\",\"D\":\"Food\"}','A'),(104,'Basic','Forming an essential foundation or starting point.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','मूळ, पायाभूत.',11,'Beginner','{\"A\":\"Extra\",\"B\":\"Complex\",\"C\":\"Fundamental\",\"D\":\"Advanced\"}','C'),(105,'Battle','A sustained fight between large, organized armed forces.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','लढाई, युद्ध.',11,'Beginner','{\"A\":\"Peace\",\"B\":\"Fight\",\"C\":\"Hug\",\"D\":\"Truce\"}','B'),(106,'Believe','Accept (something) as true; feel sure of the truth of.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विश्वास ठेवणे.',11,'Beginner','{\"A\":\"Doubt\",\"B\":\"Trust\",\"C\":\"Deny\",\"D\":\"Lie\"}','B'),(107,'Blend','Mix (a substance) with another substance so that they combine together.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','मिसळणे, एकजीव करणे.',11,'Beginner','{\"A\":\"Separate\",\"B\":\"Divide\",\"C\":\"Mix\",\"D\":\"Sort\"}','C'),(108,'Bold','Showing an ability to take risks; confident and courageous.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धाडसी, धीट.',11,'Beginner','{\"A\":\"Shy\",\"B\":\"Daring\",\"C\":\"Weak\",\"D\":\"Afraid\"}','B'),(109,'Brief','Of short duration.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','संक्षिप्त, थोडक्यात.',11,'Beginner','{\"A\":\"Long\",\"B\":\"Short\",\"C\":\"Tall\",\"D\":\"Huge\"}','B'),(110,'Broad','Having an ample distance from side to side.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','रुंद, विस्तृत.',11,'Beginner','{\"A\":\"Narrow\",\"B\":\"Wide\",\"C\":\"Thin\",\"D\":\"Tight\"}','B'),(111,'Careful','Making sure of avoiding potential danger, mishap, or harm.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सावध, काळजीपूर्वक.',12,'Beginner','{\"A\":\"Rash\",\"B\":\"Cautious\",\"C\":\"Fast\",\"D\":\"Blind\"}','B'),(112,'Cheat','Act dishonestly or unfairly in order to gain an advantage.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','फसवणूक करणे.',12,'Beginner','{\"A\":\"Help\",\"B\":\"Deceive\",\"C\":\"Trust\",\"D\":\"Give\"}','B'),(113,'Check','Examine (something) in order to determine its accuracy.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','तपासणे.',12,'Beginner','{\"A\":\"Guess\",\"B\":\"Verify\",\"C\":\"Ignore\",\"D\":\"Miss\"}','B'),(114,'Clear','Easy to perceive, understand, or interpret.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','स्पष्ट, स्वच्छ.',12,'Beginner','{\"A\":\"Foggy\",\"B\":\"Transparent\",\"C\":\"Dark\",\"D\":\"Muddy\"}','B'),(115,'Collect','Bring or gather together.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','गोळा करणे.',12,'Beginner','{\"A\":\"Scatter\",\"B\":\"Gather\",\"C\":\"Throw\",\"D\":\"Lost\"}','B'),(116,'Combine','Unite; merge.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','एकत्र करणे.',12,'Beginner','{\"A\":\"Split\",\"B\":\"Unite\",\"C\":\"Break\",\"D\":\"Cut\"}','B'),(117,'Comfort','A state of physical ease and freedom from pain or constraint.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','आराम, सुख.',12,'Beginner','{\"A\":\"Pain\",\"B\":\"Ease\",\"C\":\"Stress\",\"D\":\"Hurt\"}','B'),(118,'Complain','Express dissatisfaction or annoyance about a state of affairs or an event.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','तक्रार करणे.',12,'Beginner','{\"A\":\"Praise\",\"B\":\"Grumble\",\"C\":\"Clap\",\"D\":\"Smile\"}','B'),(119,'Complete','Having all the necessary or appropriate parts.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पूर्ण, संपूर्ण.',12,'Beginner','{\"A\":\"Part\",\"B\":\"Whole\",\"C\":\"Half\",\"D\":\"Broken\"}','B'),(120,'Construct','Build or erect (something, typically a building, road, or machine).','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बांधकाम करणे, रचणे.',12,'Beginner','{\"A\":\"Destroy\",\"B\":\"Build\",\"C\":\"Demolish\",\"D\":\"Ruin\"}','B'),(121,'Courage','The ability to do something that frightens one.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धैर्य, हिंमत.',13,'Beginner','{\"A\":\"Fear\",\"B\":\"Cowardice\",\"C\":\"Bravery\",\"D\":\"Panic\"}','C'),(122,'Crash','Collide violently with an obstacle or another vehicle.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धडक, टक्कर.',13,'Beginner','{\"A\":\"Safety\",\"B\":\"Collision\",\"C\":\"Flight\",\"D\":\"Repair\"}','B'),(123,'Cruel','Willfully causing pain or suffering to others.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','क्रूर, दुष्ट.',13,'Beginner','{\"A\":\"Kind\",\"B\":\"Mean\",\"C\":\"Nice\",\"D\":\"Gentle\"}','B'),(124,'Cure','Relieve (a person or animal) of the symptoms of a disease or condition.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','बरा करणे, उपचार.',13,'Beginner','{\"A\":\"Infect\",\"B\":\"Heal\",\"C\":\"Hurt\",\"D\":\"Injure\"}','B'),(125,'Damage','Physical harm caused to something in such a way as to impair its value.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','नुकसान, हानी.',13,'Beginner','{\"A\":\"Repair\",\"B\":\"Fix\",\"C\":\"Harm\",\"D\":\"Build\"}','C'),(126,'Danger','The possibility of suffering harm or injury.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','धोका.',13,'Beginner','{\"A\":\"Safety\",\"B\":\"Peril\",\"C\":\"Secure\",\"D\":\"Peace\"}','B'),(127,'Decide','Come to a resolution in the mind as a result of consideration.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','निर्णय घेणे, ठरवणे.',13,'Beginner','{\"A\":\"Hesitate\",\"B\":\"Choose\",\"C\":\"Wait\",\"D\":\"Delay\"}','B'),(128,'Defeat','Win a victory over (someone) in a battle or other contest.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पराभव करणे, हरवणे.',13,'Beginner','{\"A\":\"Lose\",\"B\":\"Beat\",\"C\":\"Surrender\",\"D\":\"Fail\"}','B'),(129,'Defend','Resist an attack made on (someone or something).','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','रक्षण करणे, बचाव करणे.',13,'Beginner','{\"A\":\"Attack\",\"B\":\"Harm\",\"C\":\"Protect\",\"D\":\"Hit\"}','C'),(130,'Describe','Give an account in words of (someone or something).','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वर्णन करणे.',13,'Beginner','{\"A\":\"Hide\",\"B\":\"Confuse\",\"C\":\"Explain\",\"D\":\"Ignore\"}','C'),(131,'Destroy','Put an end to the existence of (something) by damaging or attacking it.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','नाश करणे, उध्वस्त करणे.',14,'Beginner','{\"A\":\"Create\",\"B\":\"Build\",\"C\":\"Ruin\",\"D\":\"Fix\"}','C'),(132,'Disagree','Have or express a different opinion.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','असहमत असणे.',14,'Beginner','{\"A\":\"Agree\",\"B\":\"Accept\",\"C\":\"Differ\",\"D\":\"Same\"}','C'),(133,'Disappear','Cease to be visible.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','गायब होणे, नाहीसे होणे.',14,'Beginner','{\"A\":\"Appear\",\"B\":\"Vanish\",\"C\":\"Show\",\"D\":\"See\"}','B'),(134,'Doubt','A feeling of uncertainty or lack of conviction.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शंका, संशय.',14,'Beginner','{\"A\":\"Belief\",\"B\":\"Trust\",\"C\":\"Mistrust\",\"D\":\"Faith\"}','C'),(135,'Dry','Free from moisture or liquid; not wet.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कोरडा, सुका.',14,'Beginner','{\"A\":\"Wet\",\"B\":\"Soaked\",\"C\":\"Arid\",\"D\":\"Damp\"}','C'),(136,'Effect','A change that is a result or consequence of an action or other cause.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','परिणाम.',14,'Beginner','{\"A\":\"Cause\",\"B\":\"Start\",\"C\":\"Result\",\"D\":\"Origin\"}','C'),(137,'Enemy','A person who is actively opposed or hostile to someone or something.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','शत्रू.',14,'Beginner','{\"A\":\"Friend\",\"B\":\"Ally\",\"C\":\"Foe\",\"D\":\"Partner\"}','C'),(138,'Enough','As much or as many as required.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','पुरेसे.',14,'Beginner','{\"A\":\"Lacking\",\"B\":\"Sufficient\",\"C\":\"Need\",\"D\":\"Want\"}','B'),(139,'Entire','With no part left out; whole.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','संपूर्ण, सगळे.',14,'Beginner','{\"A\":\"Part\",\"B\":\"Piece\",\"C\":\"Whole\",\"D\":\"Half\"}','C'),(140,'Escape','Break free from confinement or control.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','सुटका होणे, पळून जाणे.',14,'Beginner','{\"A\":\"Stay\",\"B\":\"Flee\",\"C\":\"Wait\",\"D\":\"Capture\"}','B'),(141,'Event','A thing that happens, especially one of importance.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','घटना, प्रसंग.',15,'Beginner','{\"A\":\"Idea\",\"B\":\"Incident\",\"C\":\"Thought\",\"D\":\"Dream\"}','B'),(142,'Exact','Not approximated in any way; accurate.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','नेमका, तंतोतंत.',15,'Beginner','{\"A\":\"Guess\",\"B\":\"Precise\",\"C\":\"Rough\",\"D\":\"Wrong\"}','B'),(143,'Excite','Cause very strong feelings of enthusiasm.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','उत्तेजित करणे.',15,'Beginner','{\"A\":\"Bore\",\"B\":\"Calm\",\"C\":\"Thrill\",\"D\":\"Tire\"}','C'),(144,'Expand','Become or make larger or more extensive.','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विस्तार करणे, वाढवणे.',15,'Beginner','{\"A\":\"Shrink\",\"B\":\"Grow\",\"C\":\"Reduce\",\"D\":\"Small\"}','B'),(145,'Protect','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Attack\",\"B\":\"Protect\",\"C\":\"Ignore\",\"D\":\"Damage\"}','B'),(146,'Proud','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Ashamed\",\"B\":\"Proud\",\"C\":\"Sad\",\"D\":\"Angry\"}','B'),(147,'Prove','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Guess\",\"B\":\"Prove\",\"C\":\"Hide\",\"D\":\"Doubt\"}','B'),(148,'Provide','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Take\",\"B\":\"Provide\",\"C\":\"Steal\",\"D\":\"Keep\"}','B'),(149,'Public','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Private\",\"B\":\"Public\",\"C\":\"Secret\",\"D\":\"Hidden\"}','B'),(150,'Publish','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',15,'Beginner','{\"A\":\"Read\",\"B\":\"Publish\",\"C\":\"Hide\",\"D\":\"Burn\"}','B'),(151,'Punish','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Reward\",\"B\":\"Punish\",\"C\":\"Help\",\"D\":\"Praise\"}','B'),(152,'Purpose','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Mistake\",\"B\":\"Purpose\",\"C\":\"Accident\",\"D\":\"Time\"}','B'),(153,'Quality','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Size\",\"B\":\"Quality\",\"C\":\"Price\",\"D\":\"Name\"}','B'),(154,'Quantity','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Color\",\"B\":\"Quantity\",\"C\":\"Quality\",\"D\":\"Shape\"}','B'),(155,'Raise','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Drop\",\"B\":\"Raise\",\"C\":\"Push\",\"D\":\"Fall\"}','B'),(156,'Range','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Point\",\"B\":\"Range\",\"C\":\"Spot\",\"D\":\"Line\"}','B'),(157,'Rate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Name\",\"B\":\"Rate\",\"C\":\"Shape\",\"D\":\"Color\"}','B'),(158,'Reach','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Leave\",\"B\":\"Reach\",\"C\":\"Start\",\"D\":\"Miss\"}','B'),(159,'React','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Ignore\",\"B\":\"React\",\"C\":\"Sleep\",\"D\":\"Wait\"}','B'),(160,'Realize','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',16,'Beginner','{\"A\":\"Forget\",\"B\":\"Realize\",\"C\":\"Lose\",\"D\":\"Miss\"}','B'),(161,'Reason','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Result\",\"B\":\"Reason\",\"C\":\"Lie\",\"D\":\"Dream\"}','B'),(162,'Receive','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Give\",\"B\":\"Receive\",\"C\":\"Send\",\"D\":\"Throw\"}','B'),(163,'Recognize','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Forget\",\"B\":\"Recognize\",\"C\":\"Ignore\",\"D\":\"Meet\"}','B'),(164,'Recommend','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Criticize\",\"B\":\"Recommend\",\"C\":\"Hate\",\"D\":\"Stop\"}','B'),(165,'Record','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Erase\",\"B\":\"Record\",\"C\":\"Speak\",\"D\":\"Forget\"}','B'),(166,'Recover','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Worsen\",\"B\":\"Recover\",\"C\":\"Sick\",\"D\":\"Die\"}','B'),(167,'Refuse','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Agree\",\"B\":\"Refuse\",\"C\":\"Accept\",\"D\":\"Start\"}','B'),(168,'Regret','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Enjoy\",\"B\":\"Regret\",\"C\":\"Plan\",\"D\":\"Like\"}','B'),(169,'Regular','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Rare\",\"B\":\"Regular\",\"C\":\"Never\",\"D\":\"Once\"}','B'),(170,'Reject','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',17,'Beginner','{\"A\":\"Accept\",\"B\":\"Reject\",\"C\":\"Keep\",\"D\":\"Love\"}','B'),(171,'Relate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Separate\",\"B\":\"Relate\",\"C\":\"Divide\",\"D\":\"Cut\"}','B'),(172,'Relax','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Stress\",\"B\":\"Relax\",\"C\":\"Work\",\"D\":\"Run\"}','B'),(173,'Release','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Capture\",\"B\":\"Release\",\"C\":\"Hold\",\"D\":\"Keep\"}','B'),(174,'Rely','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Doubt\",\"B\":\"Rely\",\"C\":\"Ignore\",\"D\":\"Leave\"}','B'),(175,'Remain','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Leave\",\"B\":\"Remain\",\"C\":\"Go\",\"D\":\"Move\"}','B'),(176,'Remember','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Forget\",\"B\":\"Remember\",\"C\":\"Lose\",\"D\":\"Miss\"}','B'),(177,'Remind','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Forget\",\"B\":\"Remind\",\"C\":\"Ignore\",\"D\":\"Hide\"}','B'),(178,'Remove','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Add\",\"B\":\"Remove\",\"C\":\"Keep\",\"D\":\"Stay\"}','B'),(179,'Repair','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Break\",\"B\":\"Repair\",\"C\":\"Destroy\",\"D\":\"Lose\"}','B'),(180,'Repeat','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',18,'Beginner','{\"A\":\"Stop\",\"B\":\"Repeat\",\"C\":\"Silence\",\"D\":\"End\"}','B'),(181,'Reply','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Ask\",\"B\":\"Reply\",\"C\":\"Listen\",\"D\":\"Ignore\"}','B'),(182,'Report','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Blank\",\"B\":\"Report\",\"C\":\"Silence\",\"D\":\"Secret\"}','B'),(183,'Request','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Command\",\"B\":\"Request\",\"C\":\"Order\",\"D\":\"Demand\"}','B'),(184,'Require','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Hate\",\"B\":\"Require\",\"C\":\"Give\",\"D\":\"Sell\"}','B'),(185,'Rescue','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Harm\",\"B\":\"Rescue\",\"C\":\"Leave\",\"D\":\"Trap\"}','B'),(186,'Respect','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Disrespect\",\"B\":\"Respect\",\"C\":\"Insult\",\"D\":\"Joke\"}','B'),(187,'Responsible','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Careless\",\"B\":\"Responsible\",\"C\":\"Lazy\",\"D\":\"Free\"}','B'),(188,'Rest','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Work\",\"B\":\"Rest\",\"C\":\"Run\",\"D\":\"Stress\"}','B'),(189,'Result','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Cause\",\"B\":\"Result\",\"C\":\"Start\",\"D\":\"Origin\"}','B'),(190,'Retire','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',19,'Beginner','{\"A\":\"Start\",\"B\":\"Retire\",\"C\":\"Hire\",\"D\":\"Join\"}','B'),(191,'Return','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Leave\",\"B\":\"Return\",\"C\":\"Exit\",\"D\":\"Stay\"}','B'),(192,'Reveal','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Hide\",\"B\":\"Reveal\",\"C\":\"Cover\",\"D\":\"Secret\"}','B'),(193,'Revenge','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Forgiveness\",\"B\":\"Revenge\",\"C\":\"Love\",\"D\":\"Peace\"}','B'),(194,'Accomplish','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Fail\",\"B\":\"Accomplish\",\"C\":\"Lose\",\"D\":\"Drop\"}','B'),(195,'Adapt','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Stay\",\"B\":\"Adapt\",\"C\":\"Refuse\",\"D\":\"Ignore\"}','B'),(196,'Adequate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Scarce\",\"B\":\"Adequate\",\"C\":\"Insufficient\",\"D\":\"Empty\"}','B'),(197,'Admit','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Deny\",\"B\":\"Admit\",\"C\":\"Hide\",\"D\":\"Lie\"}','B'),(198,'Adopt','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Abandon\",\"B\":\"Adopt\",\"C\":\"Reject\",\"D\":\"Sell\"}','B'),(199,'Advantage','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Obstacle\",\"B\":\"Advantage\",\"C\":\"Disadvantage\",\"D\":\"Problem\"}','B'),(200,'Advertise','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',20,'Beginner','{\"A\":\"Hide\",\"B\":\"Advertise\",\"C\":\"Buy\",\"D\":\"Steal\"}','B'),(201,'Affect','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Ignore\",\"B\":\"Affect\",\"C\":\"Sleep\",\"D\":\"Wait\"}','B'),(202,'Afford','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Steal\",\"B\":\"Afford\",\"C\":\"Borrow\",\"D\":\"Owe\"}','B'),(203,'Agency','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Home\",\"B\":\"Agency\",\"C\":\"Park\",\"D\":\"Street\"}','B'),(204,'Agent','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Enemy\",\"B\":\"Agent\",\"C\":\"Boss\",\"D\":\"Stranger\"}','B'),(205,'Aggressive','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Calm\",\"B\":\"Aggressive\",\"C\":\"Shy\",\"D\":\"Peaceful\"}','B'),(206,'Agriculture','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Industry\",\"B\":\"Agriculture\",\"C\":\"Mining\",\"D\":\"Fishing\"}','B'),(207,'Aid','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Hinder\",\"B\":\"Aid\",\"C\":\"Stop\",\"D\":\"Block\"}','B'),(208,'Alter','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Keep\",\"B\":\"Alter\",\"C\":\"Fix\",\"D\":\"Stay\"}','B'),(209,'Alternative','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Choice\",\"B\":\"Alternative\",\"C\":\"Force\",\"D\":\"Rule\"}','B'),(210,'Amaze','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',21,'Beginner','{\"A\":\"Bore\",\"B\":\"Amaze\",\"C\":\"Calm\",\"D\":\"Tire\"}','B'),(211,'Ambition','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Laziness\",\"B\":\"Ambition\",\"C\":\"Fear\",\"D\":\"Doubt\"}','B'),(212,'Analyze','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Guess\",\"B\":\"Analyze\",\"C\":\"Ignore\",\"D\":\"Forget\"}','B'),(213,'Ancient','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Modern\",\"B\":\"Ancient\",\"C\":\"New\",\"D\":\"Future\"}','B'),(214,'Anniversary','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Birthday\",\"B\":\"Anniversary\",\"C\":\"Week\",\"D\":\"Today\"}','B'),(215,'Announce','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Whisper\",\"B\":\"Announce\",\"C\":\"Hide\",\"D\":\"Keep\"}','B'),(216,'Annual','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Daily\",\"B\":\"Annual\",\"C\":\"Weekly\",\"D\":\"Monthly\"}','B'),(217,'Anticipate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Doubt\",\"B\":\"Anticipate\",\"C\":\"Ignore\",\"D\":\"Miss\"}','B'),(218,'Anxiety','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Calm\",\"B\":\"Anxiety\",\"C\":\"Confidence\",\"D\":\"Joy\"}','B'),(219,'Apologize','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Celebrate\",\"B\":\"Apologize\",\"C\":\"Boast\",\"D\":\"Laugh\"}','B'),(220,'Appeal','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',22,'Beginner','{\"A\":\"Command\",\"B\":\"Appeal\",\"C\":\"Force\",\"D\":\"Take\"}','B'),(221,'Appear','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Vanish\",\"B\":\"Appear\",\"C\":\"Hide\",\"D\":\"Leave\"}','B'),(222,'Applause','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Booing\",\"B\":\"Applause\",\"C\":\"Silence\",\"D\":\"Noise\"}','B'),(223,'Appreciate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Ignore\",\"B\":\"Appreciate\",\"C\":\"Scorn\",\"D\":\"Hate\"}','B'),(224,'Approach','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Leave\",\"B\":\"Approach\",\"C\":\"Retreat\",\"D\":\"Avoid\"}','B'),(225,'Approve','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Reject\",\"B\":\"Approve\",\"C\":\"Dislike\",\"D\":\"Deny\"}','B'),(226,'Approximate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Exact\",\"B\":\"Approximate\",\"C\":\"Wrong\",\"D\":\"Far\"}','B'),(227,'Architect','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Doctor\",\"B\":\"Architect\",\"C\":\"Teacher\",\"D\":\"Driver\"}','B'),(228,'Arrest','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Free\",\"B\":\"Arrest\",\"C\":\"Help\",\"D\":\"Run\"}','B'),(229,'Artificial','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Natural\",\"B\":\"Artificial\",\"C\":\"Real\",\"D\":\"Organic\"}','B'),(230,'Ashamed','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',23,'Beginner','{\"A\":\"Proud\",\"B\":\"Ashamed\",\"C\":\"Happy\",\"D\":\"Confident\"}','B'),(231,'Aspect','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Whole\",\"B\":\"Aspect\",\"C\":\"Total\",\"D\":\"All\"}','B'),(232,'Assemble','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Scatter\",\"B\":\"Assemble\",\"C\":\"Disperse\",\"D\":\"Lose\"}','B'),(233,'Assess','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Ignore\",\"B\":\"Assess\",\"C\":\"Guess\",\"D\":\"Miss\"}','B'),(234,'Assign','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Keep\",\"B\":\"Assign\",\"C\":\"Take\",\"D\":\"Refuse\"}','B'),(235,'Assist','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Hinder\",\"B\":\"Assist\",\"C\":\"Stop\",\"D\":\"Block\"}','B'),(236,'Associate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Separate\",\"B\":\"Associate\",\"C\":\"Divide\",\"D\":\"Cut\"}','B'),(237,'Assume','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Know\",\"B\":\"Assume\",\"C\":\"Prove\",\"D\":\"Show\"}','B'),(238,'Atmosphere','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Ground\",\"B\":\"Atmosphere\",\"C\":\"Water\",\"D\":\"Core\"}','B'),(239,'Attach','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Separate\",\"B\":\"Attach\",\"C\":\"Cut\",\"D\":\"Divide\"}','B'),(240,'Attend','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',24,'Beginner','{\"A\":\"Miss\",\"B\":\"Attend\",\"C\":\"Leave\",\"D\":\"Skip\"}','B'),(241,'Attention','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Neglect\",\"B\":\"Attention\",\"C\":\"Ignore\",\"D\":\"Sleep\"}','B'),(242,'Attitude','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Height\",\"B\":\"Attitude\",\"C\":\"Weight\",\"D\":\"Size\"}','B'),(243,'Attract','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Repel\",\"B\":\"Attract\",\"C\":\"Push\",\"D\":\"Force\"}','B'),(244,'Audience','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Player\",\"B\":\"Audience\",\"C\":\"Actor\",\"D\":\"Leader\"}','B'),(245,'Author','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Reader\",\"B\":\"Author\",\"C\":\"Seller\",\"D\":\"Buyer\"}','B'),(246,'Authority','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Weakness\",\"B\":\"Authority\",\"C\":\"Fear\",\"D\":\"Doubt\"}','B'),(247,'Available','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Taken\",\"B\":\"Available\",\"C\":\"Lost\",\"D\":\"Gone\"}','B'),(248,'Average','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Extreme\",\"B\":\"Average\",\"C\":\"Best\",\"D\":\"Worst\"}','B'),(249,'Award','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Fine\",\"B\":\"Award\",\"C\":\"Bill\",\"D\":\"Tax\"}','B'),(250,'Aware','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',25,'Beginner','{\"A\":\"Ignorant\",\"B\":\"Aware\",\"C\":\"Blind\",\"D\":\"Lost\"}','B'),(251,'Background','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Foreground\",\"B\":\"Background\",\"C\":\"Center\",\"D\":\"Top\"}','B'),(252,'Balance','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Fall\",\"B\":\"Balance\",\"C\":\"Trip\",\"D\":\"Slip\"}','B'),(253,'Ban','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Allow\",\"B\":\"Ban\",\"C\":\"Permit\",\"D\":\"Let\"}','B'),(254,'Barrier','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Road\",\"B\":\"Barrier\",\"C\":\"Door\",\"D\":\"Path\"}','B'),(255,'Base','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Top\",\"B\":\"Base\",\"C\":\"Peak\",\"D\":\"Side\"}','B'),(256,'Beauty','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Ugliness\",\"B\":\"Beauty\",\"C\":\"Dirt\",\"D\":\"Mess\"}','B'),(257,'Belief','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Doubt\",\"B\":\"Belief\",\"C\":\"Lies\",\"D\":\"Fear\"}','B'),(258,'Belong','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Lost\",\"B\":\"Belong\",\"C\":\"Sell\",\"D\":\"Give\"}','B'),(259,'Beneath','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Above\",\"B\":\"Beneath\",\"C\":\"Over\",\"D\":\"Top\"}','B'),(260,'Benefit','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',26,'Beginner','{\"A\":\"Loss\",\"B\":\"Benefit\",\"C\":\"Cost\",\"D\":\"Fine\"}','B'),(261,'Beyond','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Near\",\"B\":\"Beyond\",\"C\":\"Close\",\"D\":\"Here\"}','B'),(262,'Bill','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Gift\",\"B\":\"Bill\",\"C\":\"Card\",\"D\":\"Note\"}','B'),(263,'Bitter','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Sweet\",\"B\":\"Bitter\",\"C\":\"Salty\",\"D\":\"Bland\"}','B'),(264,'Blame','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Praise\",\"B\":\"Blame\",\"C\":\"Thank\",\"D\":\"Help\"}','B'),(265,'Blind','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Sighted\",\"B\":\"Blind\",\"C\":\"Deaf\",\"D\":\"Mute\"}','B'),(266,'Block','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Open\",\"B\":\"Block\",\"C\":\"Clear\",\"D\":\"Allow\"}','B'),(267,'Blood','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Water\",\"B\":\"Blood\",\"C\":\"Oil\",\"D\":\"Ink\"}','B'),(268,'Boil','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Freeze\",\"B\":\"Boil\",\"C\":\"Cool\",\"D\":\"Melt\"}','B'),(269,'Bond','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Fight\",\"B\":\"Bond\",\"C\":\"Wall\",\"D\":\"Gap\"}','B'),(270,'Border','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',27,'Beginner','{\"A\":\"Center\",\"B\":\"Border\",\"C\":\"Middle\",\"D\":\"Heart\"}','B'),(271,'Bother','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Relax\",\"B\":\"Bother\",\"C\":\"Ignore\",\"D\":\"Sleep\"}','B'),(272,'Brain','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Heart\",\"B\":\"Brain\",\"C\":\"Lung\",\"D\":\"Stomach\"}','B'),(273,'Branch','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Root\",\"B\":\"Branch\",\"C\":\"Leaf\",\"D\":\"Seed\"}','B'),(274,'Brand','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Copy\",\"B\":\"Brand\",\"C\":\"Fake\",\"D\":\"Generic\"}','B'),(275,'Breath','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Food\",\"B\":\"Breath\",\"C\":\"Water\",\"D\":\"Blood\"}','B'),(276,'Brilliant','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Stupid\",\"B\":\"Brilliant\",\"C\":\"Dull\",\"D\":\"Slow\"}','B'),(277,'Budget','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Dream\",\"B\":\"Budget\",\"C\":\"Wish\",\"D\":\"Guess\"}','B'),(278,'Burst','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Fix\",\"B\":\"Burst\",\"C\":\"Close\",\"D\":\"Seal\"}','B'),(279,'Calculate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Guess\",\"B\":\"Calculate\",\"C\":\"Write\",\"D\":\"Read\"}','B'),(280,'Campaign','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',28,'Beginner','{\"A\":\"Rest\",\"B\":\"Campaign\",\"C\":\"Sleep\",\"D\":\"Walk\"}','B'),(281,'Cancel','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Start\",\"B\":\"Cancel\",\"C\":\"Begin\",\"D\":\"Go\"}','B'),(282,'Candidate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Voter\",\"B\":\"Candidate\",\"C\":\"Judge\",\"D\":\"Boss\"}','B'),(283,'Capacity','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Weight\",\"B\":\"Capacity\",\"C\":\"Height\",\"D\":\"Color\"}','B'),(284,'Capture','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Release\",\"B\":\"Capture\",\"C\":\"Free\",\"D\":\"Drop\"}','B'),(285,'Careless','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Careful\",\"B\":\"Careless\",\"C\":\"Safe\",\"D\":\"Wise\"}','B'),(286,'Category','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Single\",\"B\":\"Category\",\"C\":\"Mess\",\"D\":\"Mix\"}','B'),(287,'Cease','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Start\",\"B\":\"Cease\",\"C\":\"Begin\",\"D\":\"Grow\"}','B'),(288,'Celebration','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Mourning\",\"B\":\"Celebration\",\"C\":\"Work\",\"D\":\"Test\"}','B'),(289,'Cell','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Bone\",\"B\":\"Cell\",\"C\":\"Hair\",\"D\":\"Skin\"}','B'),(290,'Ceremony','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',29,'Beginner','{\"A\":\"Game\",\"B\":\"Ceremony\",\"C\":\"Joke\",\"D\":\"Nap\"}','B'),(291,'Certain','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Unsure\",\"B\":\"Certain\",\"C\":\"Vague\",\"D\":\"Lost\"}','B'),(292,'Chain','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Rope\",\"B\":\"Chain\",\"C\":\"String\",\"D\":\"Wire\"}','B'),(293,'Chairman','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Member\",\"B\":\"Chairman\",\"C\":\"Guest\",\"D\":\"Clerk\"}','B'),(294,'Challenge','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Gift\",\"B\":\"Challenge\",\"C\":\"Help\",\"D\":\"Peace\"}','B'),(295,'Chamber','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Hut\",\"B\":\"Chamber\",\"C\":\"Box\",\"D\":\"Cage\"}','B'),(296,'Character','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Clothes\",\"B\":\"Character\",\"C\":\"House\",\"D\":\"Car\"}','B'),(297,'Charge','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Pay\",\"B\":\"Charge\",\"C\":\"Give\",\"D\":\"Gift\"}','B'),(298,'Charity','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Bank\",\"B\":\"Charity\",\"C\":\"Shop\",\"D\":\"Mall\"}','B'),(299,'Chart','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Book\",\"B\":\"Chart\",\"C\":\"Word\",\"D\":\"Song\"}','B'),(300,'Chase','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',30,'Beginner','{\"A\":\"Run away\",\"B\":\"Chase\",\"C\":\"Wait\",\"D\":\"Sit\"}','B'),(301,'Cheer','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Cry\",\"B\":\"Cheer\",\"C\":\"Boo\",\"D\":\"Sleep\"}','B'),(302,'Chemical','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Food\",\"B\":\"Chemical\",\"C\":\"Animal\",\"D\":\"Plant\"}','B'),(303,'Chest','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Back\",\"B\":\"Chest\",\"C\":\"Leg\",\"D\":\"Head\"}','B'),(304,'Chief','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Follower\",\"B\":\"Chief\",\"C\":\"Child\",\"D\":\"Servant\"}','B'),(305,'Childhood','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Adulthood\",\"B\":\"Childhood\",\"C\":\"Old age\",\"D\":\"Teenage\"}','B'),(306,'Church','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"School\",\"B\":\"Church\",\"C\":\"Bank\",\"D\":\"Park\"}','B'),(307,'Circle','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Square\",\"B\":\"Circle\",\"C\":\"Triangle\",\"D\":\"Line\"}','B'),(308,'Circumstance','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Dream\",\"B\":\"Circumstance\",\"C\":\"Lie\",\"D\":\"Game\"}','B'),(309,'Citizen','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Alien\",\"B\":\"Citizen\",\"C\":\"Tourist\",\"D\":\"Visitor\"}','B'),(310,'Claim','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',31,'Beginner','{\"A\":\"Deny\",\"B\":\"Claim\",\"C\":\"Ask\",\"D\":\"Hide\"}','B'),(311,'Clarify','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Confuse\",\"B\":\"Clarify\",\"C\":\"Mix\",\"D\":\"Hide\"}','B'),(312,'Classic','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Modern\",\"B\":\"Classic\",\"C\":\"New\",\"D\":\"Cheap\"}','B'),(313,'Clerk','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Boss\",\"B\":\"Clerk\",\"C\":\"Guard\",\"D\":\"Cook\"}','B'),(314,'Clever','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Stupid\",\"B\":\"Clever\",\"C\":\"Slow\",\"D\":\"Dull\"}','B'),(315,'Client','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Server\",\"B\":\"Client\",\"C\":\"Worker\",\"D\":\"Boss\"}','B'),(316,'Climate','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Weather\",\"B\":\"Climate\",\"C\":\"Rain\",\"D\":\"Sun\"}','B'),(317,'Climb','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Fall\",\"B\":\"Climb\",\"C\":\"Drop\",\"D\":\"Sit\"}','B'),(318,'Cloth','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Paper\",\"B\":\"Cloth\",\"C\":\"Wood\",\"D\":\"Metal\"}','B'),(319,'Clue','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Answer\",\"B\":\"Clue\",\"C\":\"Lie\",\"D\":\"Question\"}','B'),(320,'Coach','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',32,'Beginner','{\"A\":\"Student\",\"B\":\"Coach\",\"C\":\"Player\",\"D\":\"Fan\"}','B'),(321,'Coal','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',33,'Beginner','{\"A\":\"Gold\",\"B\":\"Coal\",\"C\":\"Silver\",\"D\":\"Iron\"}','B'),(322,'Coast','No definition','',11,NULL,NULL,'Medium','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','',33,'Beginner','{\"A\":\"Desert\",\"B\":\"Coast\",\"C\":\"Mountain\",\"D\":\"Forest\"}','B'),(323,'Abundant','Existing or available in large quantities.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','विपुल, भरपूर.',33,'Beginner','{\"A\":\"Scarce\",\"B\":\"Plentiful\",\"C\":\"Empty\",\"D\":\"Rare\"}','B'),(324,'Behavior','The way in which one acts or conducts oneself.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','वर्तन, वागणूक.',33,'Beginner','{\"A\":\"Thought\",\"B\":\"Conduct\",\"C\":\"Dream\",\"D\":\"Speech\"}','B'),(325,'Confess','Admit or state that one has committed a crime or is at fault in some way.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','कबूल करणे.',33,'Beginner','{\"A\":\"Hide\",\"B\":\"Admit\",\"C\":\"Deny\",\"D\":\"Lie\"}','B'),(326,'Disaster','A sudden event, such as an accident or a natural catastrophe.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','आपत्ती, संकट.',33,'Beginner','{\"A\":\"Success\",\"B\":\"Calamity\",\"C\":\"Luck\",\"D\":\"Joy\"}','B'),(327,'Enormous','Very large in size, quantity, or extent.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','प्रचंड, अवाढव्य.',33,'Beginner','{\"A\":\"Tiny\",\"B\":\"Small\",\"C\":\"Massive\",\"D\":\"Little\"}','C'),(328,'Examine','Inspect (someone or something) in detail to determine their nature or condition.','',11,NULL,NULL,'Hard','Moderate',NULL,NULL,NULL,NULL,NULL,1,0,'2026-01-08 17:29:30','2026-01-08 17:29:30','तपासणी करणे, पाहणी करणे.',33,'Beginner','{\"A\":\"Ignore\",\"B\":\"Inspect\",\"C\":\"Miss\",\"D\":\"Skip\"}','B');
/*!40000 ALTER TABLE `vocab_words` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_category_count_insert
AFTER INSERT ON vocab_words
FOR EACH ROW
BEGIN
    UPDATE vocab_categories 
    SET word_count = word_count + 1 
    WHERE category_id = NEW.category_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_category_count_delete
AFTER DELETE ON vocab_words
FOR EACH ROW
BEGIN
    UPDATE vocab_categories 
    SET word_count = word_count - 1 
    WHERE category_id = OLD.category_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `v_user_due_words`
--

/*!50001 DROP VIEW IF EXISTS `v_user_due_words`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_user_due_words` AS select `uvp`.`user_id` AS `user_id`,`uvp`.`word_id` AS `word_id`,`vw`.`word` AS `word`,`vw`.`definition` AS `definition`,`vw`.`example_sentence` AS `example_sentence`,`vw`.`difficulty_level` AS `difficulty_level`,`uvp`.`mastery_status` AS `mastery_status`,`uvp`.`review_count` AS `review_count`,`uvp`.`next_review_date` AS `next_review_date`,`vc`.`category_name` AS `category_name` from ((`user_vocab_progress` `uvp` join `vocab_words` `vw` on(`uvp`.`word_id` = `vw`.`word_id`)) join `vocab_categories` `vc` on(`vw`.`category_id` = `vc`.`category_id`)) where `uvp`.`next_review_date` <= curdate() and `vw`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_user_vocab_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_user_vocab_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_user_vocab_summary` AS select `u`.`user_id` AS `user_id`,`u`.`name` AS `name`,coalesce(`uvs`.`total_words_learned`,0) AS `total_words`,coalesce(`uvs`.`words_mastered`,0) AS `mastered_words`,coalesce(`uvs`.`current_streak`,0) AS `current_streak`,coalesce(`uvs`.`longest_streak`,0) AS `longest_streak`,coalesce(`uvs`.`accuracy_percentage`,0) AS `accuracy`,coalesce(`uvs`.`level`,1) AS `level` from (`users` `u` left join `user_vocab_stats` `uvs` on(`u`.`user_id` = `uvs`.`user_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-10 17:24:11
