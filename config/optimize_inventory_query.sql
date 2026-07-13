-- Optimization: Add composite index to speed up inventory export query
-- This index covers the WHERE clause and JOIN conditions optimally

-- If index doesn't exist, create composite index on inventory table
-- This combines the WHERE filter (quantity > 0) with JOIN conditions
CREATE INDEX idx_inventory_quantity_shelf_product 
ON inventory(quantity, shelf_id, product_id);

-- Optional: If you frequently query specific quantity ranges, this helps:
CREATE INDEX idx_inventory_shelf_product_quantity 
ON inventory(shelf_id, product_id, quantity);

-- Verify indexes were created:
-- SHOW INDEX FROM inventory;
-- EXPLAIN SELECT ... (your query) to check if index is being used
