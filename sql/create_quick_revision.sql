CREATE TABLE IF NOT EXISTS quick_revision (
    revision_id INT PRIMARY KEY AUTO_INCREMENT,
    chapter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    key_points JSON NOT NULL,
    summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
);

-- Insert sample data for testing
INSERT INTO quick_revision (chapter_id, title, key_points, summary) VALUES
(13, 'Geography Quick Revision', 
 JSON_ARRAY(
    'Capital of France is Paris',
    'Photosynthesis converts light to energy',
    'Earth has 8 planets in solar system',
    'Water covers 71% of Earth surface',
    'Mount Everest is highest peak'
 ),
 'This chapter covers basic geography concepts including capitals, natural processes, and planetary facts.'),
(14, 'Science Quick Revision',
 JSON_ARRAY(
    'Speed of light is 299,792 km/s',
    'DNA stands for Deoxyribonucleic Acid',
    'Human body has 206 bones',
    'Gravity acceleration is 9.8 m/s²'
 ),
 'Key scientific facts and formulas for quick revision before exams.');
