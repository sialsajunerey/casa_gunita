DELIMITER $$

ALTER TABLE orders MODIFY order_type ENUM('takeout','delivery') NOT NULL;
ALTER TABLE transactions MODIFY payment_method ENUM('COD','E-Payment') NOT NULL DEFAULT 'COD';

DROP PROCEDURE IF EXISTS sp_create_order$$
CREATE PROCEDURE sp_create_order(
  IN p_user_id INT,
  IN p_order_type ENUM('takeout','delivery'),
  IN p_payment_method ENUM('COD','E-Payment'),
  IN p_notes TEXT,
  IN p_house_number VARCHAR(100),
  IN p_street VARCHAR(255),
  IN p_barangay VARCHAR(255),
  IN p_city VARCHAR(255),
  IN p_items JSON
)
BEGIN
  DECLARE v_order_id INT;
  DECLARE v_i INT DEFAULT 0;
  DECLARE v_count INT;
  DECLARE v_product_id INT;
  DECLARE v_quantity INT;
  DECLARE v_unit_price DECIMAL(10,2);
  DECLARE v_options TEXT;
  DECLARE v_subtotal DECIMAL(10,2);
  DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;

  SET v_count = JSON_LENGTH(p_items);

  INSERT INTO orders (user_id, total_amount, status, order_type, notes, house_number, street, barangay, city)
  VALUES (p_user_id, 0.00, 'pending', p_order_type, p_notes, p_house_number, p_street, p_barangay, p_city);
  SET v_order_id = LAST_INSERT_ID();

  WHILE v_i < v_count DO
    SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].product_id')));
    SET v_quantity   = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].quantity')));
    SET v_unit_price = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].unit_price')));
    SET v_options    = JSON_UNQUOTE(JSON_EXTRACT(p_items, CONCAT('$[', v_i, '].options')));

    IF v_options = 'null' THEN
      SET v_options = NULL;
    END IF;

    SET v_subtotal = v_unit_price * v_quantity;

    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, options)
    VALUES (v_order_id, v_product_id, v_quantity, v_unit_price, v_subtotal, v_options);

    SET v_total = v_total + v_subtotal;
    SET v_i = v_i + 1;
  END WHILE;

  UPDATE orders
  SET total_amount = v_total
  WHERE order_id = v_order_id;

  INSERT INTO transactions (order_id, user_id, amount_paid, payment_method)
  VALUES (v_order_id, p_user_id, v_total, p_payment_method);

  SELECT v_order_id AS order_id;
END$$

DROP PROCEDURE IF EXISTS sp_update_order_status$$
CREATE PROCEDURE sp_update_order_status(
  IN p_order_id INT,
  IN p_new_status ENUM('pending','preparing','ready','completed','cancelled')
)
BEGIN
  DECLARE v_old_status ENUM('pending','preparing','ready','completed','cancelled');

  SELECT status
  INTO v_old_status
  FROM orders
  WHERE order_id = p_order_id;

  IF v_old_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
  END IF;

  IF v_old_status <> p_new_status THEN
    UPDATE orders
    SET status = p_new_status
    WHERE order_id = p_order_id;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_orders_before_insert_validate_delivery$$
CREATE TRIGGER trg_orders_before_insert_validate_delivery
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
  IF NEW.order_type = 'delivery' THEN
    IF NEW.house_number IS NULL OR NEW.house_number = ''
       OR NEW.street IS NULL OR NEW.street = ''
       OR NEW.barangay IS NULL OR NEW.barangay = ''
       OR NEW.city IS NULL OR NEW.city = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Delivery orders require a complete address';
    END IF;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_orders_after_update_audit$$
CREATE TRIGGER trg_orders_after_update_audit
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO audit_logs (admin_id, action, target_type, order_id, details)
    VALUES (
      CASE WHEN @audit_admin_id IS NULL OR @audit_admin_id = 0 THEN NULL ELSE @audit_admin_id END,
      'order_status_change',
      'order',
      NEW.order_id,
      CONCAT('Order status changed from ', OLD.status, ' to ', NEW.status)
    );
    SET @audit_admin_id = NULL;
  END IF;
END$$

DELIMITER ;
