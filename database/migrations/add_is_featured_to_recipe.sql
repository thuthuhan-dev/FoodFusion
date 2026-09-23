-- Adds the is_featured flag used by the homepage "Featured" section
-- (index.php). Featured status is set by hand with an UPDATE statement
-- like the one below — there is no toggle button anywhere in the app.
--
-- Run this once against the FoodFusion database (e.g. via phpMyAdmin's
-- SQL tab, or `mysql -u <user> -p foodfusion < database/migrations/add_is_featured_to_recipe.sql`).

ALTER TABLE RECIPE
  ADD COLUMN is_featured TINYINT(1) NULL DEFAULT 0;

-- Example: feature a specific recipe by its recipe_id.
-- UPDATE RECIPE SET is_featured = 1 WHERE recipe_id = 3;

-- Example: unfeature it again.
-- UPDATE RECIPE SET is_featured = 0 WHERE recipe_id = 3;
