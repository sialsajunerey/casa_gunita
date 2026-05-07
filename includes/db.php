<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = 'localhost';
$dbname   = 'casa_gunita';
$username = 'root';
$password = '';  // XAMPP default has no password

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$customizationSchema = "CREATE TABLE IF NOT EXISTS `product_customization_groups` (
  `group_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `group_type` ENUM('single', 'addon') NOT NULL DEFAULT 'single',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_customization_options` (
  `option_id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `additional_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image` VARCHAR(255) DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`group_id`) REFERENCES `product_customization_groups`(`group_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!mysqli_multi_query($conn, $customizationSchema)) {
    error_log('Failed to ensure customization schema: ' . mysqli_error($conn));
} else {
    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));

$pricingTypeColumn = mysqli_query($conn, "SHOW COLUMNS FROM product_customization_groups LIKE 'pricing_type'");
if ($pricingTypeColumn && mysqli_num_rows($pricingTypeColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE product_customization_groups ADD COLUMN pricing_type ENUM('set_price','extra_charge') NOT NULL DEFAULT 'set_price' AFTER group_type");
}
}
