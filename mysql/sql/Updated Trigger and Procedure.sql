-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2026 at 07:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `casa_gunita`
--

DROP PROCEDURE IF EXISTS sp_RegisterUser;
DROP PROCEDURE IF EXISTS sp_PlaceOrder;
DROP PROCEDURE IF EXISTS sp_ProcessPayment;
DROP PROCEDURE IF EXISTS sp_update_order_status;
DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_RegisterUser` (
  IN p_first_name VARCHAR(100),
  IN p_last_name VARCHAR(100),
  IN p_email VARCHAR(100),
  IN p_password VARCHAR(255)
)
BEGIN
  DECLARE v_existing INT DEFAULT 0;

  SELECT COUNT(*) INTO v_existing
  FROM users
  WHERE email = p_email;

  IF v_existing > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Email already registered';
  END IF;

  INSERT INTO users (first_name, last_name, email, password, role)
  VALUES (p_first_name, p_last_name, p_email, p_password, 'customer');

  SET @new_user_id = LAST_INSERT_ID();

  INSERT INTO user_access_logs (user_id, event_type)
  VALUES (@new_user_id, 'login');

  SELECT @new_user_id AS user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_PlaceOrder` (
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
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_product_id INT;
  DECLARE v_quantity INT;
  DECLARE v_unit_price DECIMAL(10,2);
  DECLARE v_options TEXT;
  DECLARE v_subtotal DECIMAL(10,2);
  DECLARE v_total DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_active_order_count INT DEFAULT 0;

  IF p_order_type NOT IN ('takeout','delivery') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order type';
  END IF;

  IF p_payment_method NOT IN ('COD','E-Payment') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid payment method';
  END IF;

  IF p_items IS NULL OR JSON_LENGTH(p_items) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order items are required';
  END IF;

  SELECT COUNT(*) INTO v_active_order_count
  FROM orders
  WHERE user_id = p_user_id
    AND status IN ('pending','preparing','ready');

  IF v_active_order_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer already has an active order';
  END IF;

  INSERT INTO orders (user_id, total_amount, status, order_type, notes, house_number, street, barangay, city)
  VALUES (p_user_id, 0.00, 'pending', p_order_type, p_notes, p_house_number, p_street, p_barangay, p_city);

  SET v_order_id = LAST_INSERT_ID();
  SET v_count = JSON_LENGTH(p_items);

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

  SELECT v_order_id AS order_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ProcessPayment` (
  IN p_order_id INT,
  IN p_user_id INT,
  IN p_amount_paid DECIMAL(10,2),
  IN p_payment_method ENUM('COD','E-Payment')
)
BEGIN
  DECLARE v_order_exists INT DEFAULT 0;
  DECLARE v_order_status ENUM('pending','preparing','ready','completed','cancelled');

  IF p_amount_paid <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Amount paid must be positive';
  END IF;

  SELECT COUNT(*) INTO v_order_exists
  FROM orders
  WHERE order_id = p_order_id;

  IF v_order_exists = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
  END IF;

  SELECT status INTO v_order_status
  FROM orders
  WHERE order_id = p_order_id;

  IF v_order_status IN ('completed','cancelled') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot process payment for completed or cancelled order';
  END IF;

  INSERT INTO transactions (order_id, user_id, amount_paid, payment_method)
  VALUES (p_order_id, p_user_id, p_amount_paid, p_payment_method);

  SELECT LAST_INSERT_ID() AS transaction_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_order_status` (IN `p_order_id` INT, IN `p_new_status` ENUM('pending','preparing','ready','completed','cancelled'))   BEGIN
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

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `audit_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` enum('login','logout','failed_login','order_status_change','order_created','order_completed_by_user','order_cancelled_by_user','menu_add','menu_edit','menu_delete','menu_hide','menu_featured','category_add','category_edit','category_delete','modifier_add','modifier_edit','modifier_delete','customization_add','customization_edit','customization_delete','customization_option_add','customization_option_edit','customization_option_delete','user_add','user_edit','user_delete','user_password_change','user_login','user_logout','user_failed_login','customer_view','other') NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `modifier_group_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modifier_groups`
--

CREATE TABLE `modifier_groups` (
  `modifier_group_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pricing_type` enum('set_price','extra_charge') NOT NULL DEFAULT 'set_price',
  `select_option` enum('single','multiple') NOT NULL DEFAULT 'single',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modifier_group_options`
--

CREATE TABLE `modifier_group_options` (
  `option_id` int(11) NOT NULL,
  `modifier_group_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `additional_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `order_type` enum('takeout','delivery') NOT NULL,
  `notes` text DEFAULT NULL,
  `house_number` varchar(100) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_BeforeOrderInsert`
BEFORE INSERT ON `orders`
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

  IF EXISTS (
    SELECT 1 FROM orders
    WHERE user_id = NEW.user_id
      AND status IN ('pending','preparing','ready')
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer already has an active order';
  END IF;
END
$$

CREATE TRIGGER `trg_AfterOrderInsert`
AFTER INSERT ON `orders`
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (customer_id, target_type, order_id, action, details)
  VALUES (
    NEW.user_id,
    'order',
    NEW.order_id,
    'order_created',
    CONCAT('Order created with status ', NEW.status)
  );
END
$$

CREATE TRIGGER `trg_AfterOrderUpdate`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO audit_logs (admin_id, target_type, order_id, action, details)
    VALUES (
      CASE WHEN @audit_admin_id IS NULL OR @audit_admin_id = 0 THEN NULL ELSE @audit_admin_id END,
      'order',
      NEW.order_id,
      'order_status_change',
      CONCAT('Order status changed from ', OLD.status, ' to ', NEW.status)
    );
    SET @audit_admin_id = NULL;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `options` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_customization_groups`
--

CREATE TABLE `product_customization_groups` (
  `group_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `group_type` enum('single','addon') NOT NULL DEFAULT 'single',
  `pricing_type` enum('set_price','extra_charge') NOT NULL DEFAULT 'set_price',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_customization_options`
--

CREATE TABLE `product_customization_options` (
  `option_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `additional_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_modifier_groups`
--

CREATE TABLE `product_modifier_groups` (
  `product_modifier_group_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `modifier_group_id` int(11) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('COD','E-Payment') NOT NULL DEFAULT 'COD',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `google_id`, `role`, `created_at`, `first_name`, `last_name`) VALUES
(1, 'admin@casagunita.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'admin', '2026-04-26 14:24:04', 'Admin', 'Admin');

-- --------------------------------------------------------

--
-- Table structure for table `user_access_logs`
--

CREATE TABLE `user_access_logs` (
  `access_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` enum('login','logout','failed_login','password_change_success','password_change_failed') NOT NULL,
  `event_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `trg_AfterUserInsert`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (customer_id, target_type, action, details)
  VALUES (
    NEW.user_id,
    'customer',
    'user_add',
    CONCAT('Customer account created: ', NEW.email)
  );
END
$$

CREATE TRIGGER `trg_AfterUserUpdate`
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
  IF NEW.password <> OLD.password THEN
    INSERT INTO audit_logs (customer_id, target_type, action, details)
    VALUES (
      NEW.user_id,
      'customer',
      'user_password_change',
      CONCAT('Password changed for ', NEW.email)
    );
  END IF;
END
$$

CREATE TRIGGER `trg_AfterUserAccessLogInsert`
AFTER INSERT ON `user_access_logs`
FOR EACH ROW
BEGIN
  CASE NEW.event_type
    WHEN 'login' THEN
      INSERT INTO audit_logs (customer_id, target_type, action, details)
      VALUES (NEW.user_id, 'customer', 'user_login', 'Customer logged in');
    WHEN 'logout' THEN
      INSERT INTO audit_logs (customer_id, target_type, action, details)
      VALUES (NEW.user_id, 'customer', 'user_logout', 'Customer logged out');
    WHEN 'failed_login' THEN
      INSERT INTO audit_logs (customer_id, target_type, action, details)
      VALUES (NEW.user_id, 'customer', 'user_failed_login', 'Customer failed login');
    WHEN 'password_change_success' THEN
      INSERT INTO audit_logs (customer_id, target_type, action, details)
      VALUES (NEW.user_id, 'customer', 'user_password_change', 'Customer password changed successfully');
    WHEN 'password_change_failed' THEN
      INSERT INTO audit_logs (customer_id, target_type, action, details)
      VALUES (NEW.user_id, 'customer', 'user_password_change', 'Customer password change failed');
  END CASE;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `fk_audit_logs_admin` (`admin_id`),
  ADD KEY `fk_audit_logs_customer` (`customer_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `fk_inventory_product` (`product_id`);

--
-- Indexes for table `modifier_groups`
--
ALTER TABLE `modifier_groups`
  ADD PRIMARY KEY (`modifier_group_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `modifier_group_options`
--
ALTER TABLE `modifier_group_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `fk_modifier_group_options_group` (`modifier_group_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `product_customization_groups`
--
ALTER TABLE `product_customization_groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_customization_options`
--
ALTER TABLE `product_customization_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `product_modifier_groups`
--
ALTER TABLE `product_modifier_groups`
  ADD PRIMARY KEY (`product_modifier_group_id`),
  ADD KEY `fk_prod_mod_grp_product` (`product_id`),
  ADD KEY `fk_prod_mod_grp_modifier` (`modifier_group_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_order` (`order_id`),
  ADD KEY `fk_transactions_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- Indexes for table `user_access_logs`
--
ALTER TABLE `user_access_logs`
  ADD PRIMARY KEY (`access_log_id`),
  ADD KEY `fk_access_logs_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modifier_groups`
--
ALTER TABLE `modifier_groups`
  MODIFY `modifier_group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modifier_group_options`
--
ALTER TABLE `modifier_group_options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_customization_groups`
--
ALTER TABLE `product_customization_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_customization_options`
--
ALTER TABLE `product_customization_options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_modifier_groups`
--
ALTER TABLE `product_modifier_groups`
  MODIFY `product_modifier_group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_access_logs`
--
ALTER TABLE `user_access_logs`
  MODIFY `access_log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `modifier_group_options`
--
ALTER TABLE `modifier_group_options`
  ADD CONSTRAINT `fk_modifier_group_options_group` FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups` (`modifier_group_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_customization_groups`
--
ALTER TABLE `product_customization_groups`
  ADD CONSTRAINT `product_customization_groups_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_customization_options`
--
ALTER TABLE `product_customization_options`
  ADD CONSTRAINT `product_customization_options_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `product_customization_groups` (`group_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_modifier_groups`
--
ALTER TABLE `product_modifier_groups`
  ADD CONSTRAINT `fk_prod_mod_grp_modifier` FOREIGN KEY (`modifier_group_id`) REFERENCES `modifier_groups` (`modifier_group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prod_mod_grp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_access_logs`
--
ALTER TABLE `user_access_logs`
  ADD CONSTRAINT `fk_access_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
