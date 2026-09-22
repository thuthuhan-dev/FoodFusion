-- Adds an optional short description to INGREDIENT, captured only when a
-- member types a brand-new ingredient into the recipe form's
-- "+ Add a new ingredient..." field (recipes.php / recipe-detail.php).
-- Existing ingredients are left with description = NULL and are not edited
-- by this migration or by any part of the app.
--
-- Run this once against the FoodFusion database (e.g. via phpMyAdmin's
-- SQL tab, or `mysql -u <user> -p foodfusion < database/migrations/add_description_to_ingredient.sql`).

ALTER TABLE INGREDIENT
  ADD COLUMN description VARCHAR(300) NULL DEFAULT NULL;
