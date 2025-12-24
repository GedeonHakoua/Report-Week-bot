CREATE TABLE IF NOT EXISTS weeks (
    id SERIAL PRIMARY KEY,
    week_start DATE,
    week_end DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS days (
    id SERIAL PRIMARY KEY,
    week_id INT REFERENCES weeks(id),
    day_name VARCHAR(10),
    meditation TEXT,
    verse TEXT,
    chapters TEXT,
    prayer_time TEXT,
    fasting TEXT,
    saturday_prayer TEXT,
    sunday_teaching TEXT
);
