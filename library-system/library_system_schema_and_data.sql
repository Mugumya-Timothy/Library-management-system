
-- Create database and use it
DROP DATABASE IF EXISTS library;
CREATE DATABASE library;
USE library;

-- Drop tables if they exist
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS fines;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS staff_login;
DROP TABLE IF EXISTS transactions;

-- Create books table
CREATE TABLE books (
    book_id INT PRIMARY KEY,
    title VARCHAR(255),
    author VARCHAR(255),
    genre VARCHAR(100),
    price DECIMAL(10,2),
    year INT,
    availability BOOLEAN
);

-- Create fines table
CREATE TABLE fines (
    fine_id INT PRIMARY KEY,
    member_id INT,
    amount DECIMAL(10,2),
    date_issued DATE,
    FOREIGN KEY (member_id) REFERENCES members(member_id)
);

-- Create members table
CREATE TABLE members (
    member_id INT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    membership_date DATE
);

-- Create staff table
CREATE TABLE staff (
    staff_id INT PRIMARY KEY,
    name VARCHAR(255),
    role VARCHAR(100),
    email VARCHAR(255)
);

-- Create staff_login table
CREATE TABLE staff_login (
    login_id INT PRIMARY KEY,
    staff_id INT,
    username VARCHAR(100),
    password VARCHAR(100),
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
);

-- Create transactions table
CREATE TABLE transactions (
    transaction_id INT PRIMARY KEY,
    book_id INT,
    member_id INT,
    date_borrowed DATE,
    date_returned DATE,
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (member_id) REFERENCES members(member_id)
);

-- Insert sample data into books
INSERT INTO books (book_id, title, author, genre, price, year, availability) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 10.99, 1925, TRUE),
(2, '1984', 'George Orwell', 'Dystopian', 8.99, 1949, TRUE),
(3, 'To Kill a Mockingbird', 'Harper Lee', 'Fiction', 12.50, 1960, FALSE),
(4, 'The Catcher in the Rye', 'J.D. Salinger', 'Fiction', 11.00, 1951, TRUE),
(5, 'Moby-Dick', 'Herman Melville', 'Adventure', 9.50, 1851, TRUE);

-- Insert sample data into members
INSERT INTO members (member_id, name, email, membership_date) VALUES
(1, 'Alice Smith', 'alice@example.com', '2021-01-10'),
(2, 'Bob Johnson', 'bob@example.com', '2022-03-15'),
(3, 'Carol Williams', 'carol@example.com', '2023-06-20');

-- Insert sample data into staff
INSERT INTO staff (staff_id, name, role, email) VALUES
(1, 'David Brown', 'Librarian', 'david@example.com'),
(2, 'Eva Green', 'Assistant', 'eva@example.com');

-- Insert sample data into staff_login
INSERT INTO staff_login (login_id, staff_id, username, password) VALUES
(1, 1, 'dbrown', 'password123'),
(2, 2, 'egreen', 'securepass');

-- Insert sample data into transactions
INSERT INTO transactions (transaction_id, book_id, member_id, date_borrowed, date_returned) VALUES
(1, 1, 1, '2024-01-01', '2024-01-15'),
(2, 2, 2, '2024-02-10', NULL),
(3, 3, 3, '2024-03-05', '2024-03-20');

-- Insert sample data into fines
INSERT INTO fines (fine_id, member_id, amount, date_issued) VALUES
(1, 1, 5.00, '2024-01-20'),
(2, 3, 7.50, '2024-03-25');
