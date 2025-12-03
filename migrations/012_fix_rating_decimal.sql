-- Migration: Fix Rating Decimal Precision
-- Description: Change my_rating from DECIMAL(2,1) to DECIMAL(3,1) to support 10.0
-- Date: 2024-12-03

ALTER TABLE user_movies MODIFY my_rating DECIMAL(3,1);
ALTER TABLE user_tv_shows MODIFY my_rating DECIMAL(3,1);
ALTER TABLE user_books MODIFY my_rating DECIMAL(3,1);
ALTER TABLE user_games MODIFY my_rating DECIMAL(3,1);
