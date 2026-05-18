-- Drop and recreate users
DROP USER IF EXISTS 'app_user'@'localhost';
DROP USER IF EXISTS 'admin_user'@'localhost';

CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'password';
CREATE USER 'admin_user'@'localhost' IDENTIFIED BY 'password';

-- Grant permissions to app_user (user-facing operations)
GRANT SELECT, INSERT, UPDATE, DELETE ON `casa_gunita`.* TO 'app_user'@'localhost';
GRANT EXECUTE ON PROCEDURE `casa_gunita`.`sp_RegisterUser` TO 'app_user'@'localhost';
GRANT EXECUTE ON PROCEDURE `casa_gunita`.`sp_PlaceOrder` TO 'app_user'@'localhost';
GRANT EXECUTE ON PROCEDURE `casa_gunita`.`sp_ProcessPayment` TO 'app_user'@'localhost';
GRANT EXECUTE ON PROCEDURE `casa_gunita`.`sp_update_order_status` TO 'app_user'@'localhost';

-- Grant permissions to admin_user (admin operations with full control)
GRANT ALL PRIVILEGES ON casa_gunita.* TO 'admin_user'@'localhost' WITH GRANT OPTION;

FLUSH PRIVILEGES;
