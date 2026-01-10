-- Add 10 words to Set 2 for immediate testing
-- Run this in phpMyAdmin SQL tab

INSERT INTO vocab_words (word, definition, definition_marathi, set_number, level_name, category_id) VALUES
('Beautiful', 'Pleasing to the senses or mind', 'सुंदर', 2, 'Beginner', 1),
('Brave', 'Showing courage and fearlessness', 'धाडसी', 2, 'Beginner', 1),
('Clever', 'Quick to understand and learn', 'हुशार', 2, 'Beginner', 1),
('Delicious', 'Very pleasant to taste or smell', 'स्वादिष्ट', 2, 'Beginner', 1),
('Enormous', 'Very large in size or quantity', 'प्रचंड', 2, 'Beginner', 1),
('Famous', 'Known by many people', 'प्रसिद्ध', 2, 'Beginner', 1),
('Generous', 'Willing to give more than necessary', 'उदार', 2, 'Beginner', 1),
('Honest', 'Truthful and sincere', 'प्रामाणिक', 2, 'Beginner', 1),
('Important', 'Of great significance or value', 'महत्त्वाचे', 2, 'Beginner', 1),
('Joyful', 'Full of happiness and delight', 'आनंदी', 2, 'Beginner', 1);

-- Verify the words were added
SELECT * FROM vocab_words WHERE set_number = 2;
