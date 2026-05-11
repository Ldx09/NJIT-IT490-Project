CREATE TABLE IF NOT EXISTS user_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vin VARCHAR(32) NOT NULL,
    car_make VARCHAR(64) NOT NULL,
    model VARCHAR(64) NOT NULL,
    vehicle_trim VARCHAR(64) DEFAULT '',
    color VARCHAR(64) DEFAULT '',
    year INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_vin (user_id, vin),
    INDEX (user_id),
    INDEX (car_make, model, year)
);
