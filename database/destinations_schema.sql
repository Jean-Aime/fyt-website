-- Destinations Management Schema

-- Create countries table
CREATE TABLE IF NOT EXISTS countries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(3) UNIQUE NOT NULL,
    continent VARCHAR(50) NOT NULL,
    currency VARCHAR(10),
    language VARCHAR(100),
    timezone VARCHAR(100),
    visa_required BOOLEAN DEFAULT FALSE,
    description TEXT,
    travel_advisory TEXT,
    best_time_to_visit TEXT,
    travel_facts TEXT,
    visa_tips TEXT,
    flag_image VARCHAR(255),
    gallery_images JSON,
    map_latitude DECIMAL(10, 8),
    map_longitude DECIMAL(11, 8),
    map_zoom INT DEFAULT 6,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_countries_status (status),
    INDEX idx_countries_continent (continent),
    INDEX idx_countries_code (code)
);

-- Create regions table
CREATE TABLE IF NOT EXISTS regions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    climate TEXT,
    attractions TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    INDEX idx_regions_country (country_id),
    INDEX idx_regions_status (status)
);

-- Add country_id and region_id to tours table if not exists
ALTER TABLE tours 
ADD COLUMN IF NOT EXISTS country_id INT,
ADD COLUMN IF NOT EXISTS region_id INT;

-- Add foreign key constraints for tours
ALTER TABLE tours 
ADD CONSTRAINT fk_tours_country 
FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL;

ALTER TABLE tours 
ADD CONSTRAINT fk_tours_region 
FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL;

-- Insert sample countries
INSERT IGNORE INTO countries (name, code, continent, currency, language, timezone, description, map_latitude, map_longitude) VALUES
('Rwanda', 'RWA', 'Africa', 'RWF', 'Kinyarwanda, French, English', 'Africa/Kigali', 'The Land of a Thousand Hills, known for mountain gorillas and stunning landscapes.', -1.9403, 29.8739),
('Kenya', 'KEN', 'Africa', 'KES', 'Swahili, English', 'Africa/Nairobi', 'Famous for the Great Migration and diverse wildlife in the Maasai Mara.', -0.0236, 37.9062),
('Tanzania', 'TZA', 'Africa', 'TZS', 'Swahili, English', 'Africa/Dar_es_Salaam', 'Home to Serengeti National Park and Mount Kilimanjaro.', -6.3690, 34.8888),
('Uganda', 'UGA', 'Africa', 'UGX', 'English, Luganda', 'Africa/Kampala', 'The Pearl of Africa, known for gorilla trekking and the source of the Nile.', 1.3733, 32.2903),
('South Africa', 'ZAF', 'Africa', 'ZAR', 'Afrikaans, English, Zulu', 'Africa/Johannesburg', 'Rainbow Nation with diverse landscapes from Cape Town to Kruger National Park.', -30.5595, 22.9375),
('Botswana', 'BWA', 'Africa', 'BWP', 'English, Setswana', 'Africa/Gaborone', 'Home to the Okavango Delta and exceptional wildlife viewing.', -22.3285, 24.6849),
('Namibia', 'NAM', 'Africa', 'NAD', 'English, Afrikaans', 'Africa/Windhoek', 'Known for the Namib Desert and dramatic landscapes.', -22.9576, 18.4904),
('Zambia', 'ZMB', 'Africa', 'ZMW', 'English', 'Africa/Lusaka', 'Famous for Victoria Falls and walking safaris.', -13.1339, 27.8493);

-- Insert sample regions for Rwanda
INSERT IGNORE INTO regions (country_id, name, description, climate, attractions) VALUES
((SELECT id FROM countries WHERE code = 'RWA'), 'Northern Province', 'Home to Volcanoes National Park and mountain gorillas', 'Cool highland climate', 'Volcanoes National Park, Mountain Gorillas, Golden Monkeys'),
((SELECT id FROM countries WHERE code = 'RWA'), 'Eastern Province', 'Known for Akagera National Park and savanna wildlife', 'Warm savanna climate', 'Akagera National Park, Big Five, Lake Ihema'),
((SELECT id FROM countries WHERE code = 'RWA'), 'Southern Province', 'Features Nyungwe Forest and chimpanzee tracking', 'Cool forest climate', 'Nyungwe Forest, Chimpanzees, Canopy Walk'),
((SELECT id FROM countries WHERE code = 'RWA'), 'Western Province', 'Lake Kivu region with beautiful lakeside towns', 'Moderate lakeside climate', 'Lake Kivu, Gisenyi, Kibuye'),
((SELECT id FROM countries WHERE code = 'RWA'), 'Kigali City', 'Capital city with modern amenities and cultural sites', 'Moderate highland climate', 'Genocide Memorial, Markets, Cultural Centers');

-- Insert sample regions for Kenya
INSERT IGNORE INTO regions (country_id, name, description, climate, attractions) VALUES
((SELECT id FROM countries WHERE code = 'KEN'), 'Maasai Mara', 'World-famous for the Great Migration', 'Savanna climate', 'Great Migration, Big Five, Maasai Culture'),
((SELECT id FROM countries WHERE code = 'KEN'), 'Amboseli', 'Known for large elephant herds and Mount Kilimanjaro views', 'Semi-arid climate', 'Elephants, Mount Kilimanjaro Views, Maasai Villages'),
((SELECT id FROM countries WHERE code = 'KEN'), 'Samburu', 'Unique wildlife and cultural experiences', 'Arid climate', 'Special Five, Samburu Culture, Ewaso River'),
((SELECT id FROM countries WHERE code = 'KEN'), 'Coastal Region', 'Beautiful beaches and Swahili culture', 'Tropical coastal climate', 'Diani Beach, Malindi, Swahili Culture');

-- Create indexes for better performance
CREATE INDEX idx_countries_name ON countries(name);
CREATE INDEX idx_regions_name ON regions(name);
CREATE INDEX idx_tours_country ON tours(country_id);
CREATE INDEX idx_tours_region ON tours(region_id);
