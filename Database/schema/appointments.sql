-- Run when DB is ready. Same DB as auth (vehicleRecall).
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appointment_at DATETIME NOT NULL,
    title VARCHAR(255) DEFAULT '',
    type VARCHAR(50) DEFAULT 'virtual',
    location_or_link VARCHAR(500) DEFAULT '',
    status VARCHAR(50) DEFAULT 'scheduled',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (appointment_at),
    INDEX (status)
);
