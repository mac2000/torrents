DROP TABLE IF EXISTS items;
CREATE TABLE IF NOT EXISTS items
(
    imdb VARCHAR(20) NOT NULL,
    link VARCHAR(255) PRIMARY KEY NOT NULL,
    title VARCHAR(255) NOT NULL,
    year INT,
    poster VARCHAR(255),
    rating DECIMAL(2,1),
    genres VARCHAR(255),
    added DATETIME NOT NULL
);