-- =====================================================
-- ANALYTICS PROCEDURES FOR CASA GUNITA
-- =====================================================

-- Drop existing analytics procedures
DROP PROCEDURE IF EXISTS sp_analytics_get_kpi;
DROP PROCEDURE IF EXISTS sp_analytics_get_heatmap;
DROP PROCEDURE IF EXISTS sp_analytics_get_pie_data;
DROP PROCEDURE IF EXISTS sp_analytics_get_top_performing;
DROP PROCEDURE IF EXISTS sp_analytics_get_ranked_items;
DROP PROCEDURE IF EXISTS sp_analytics_get_total_count;

DELIMITER $$

-- =====================================================
-- KPI METRICS (Total Orders, Revenue, Peak Hour, Top Item)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_kpi` (
  IN p_date_from DATE,
  IN p_date_to DATE
)
BEGIN
  DECLARE v_order_count INT DEFAULT 0;
  DECLARE v_revenue DECIMAL(10,2) DEFAULT 0.00;
  DECLARE v_peak_hour VARCHAR(10) DEFAULT NULL;
  DECLARE v_peak_count INT DEFAULT 0;
  DECLARE v_top_item VARCHAR(100) DEFAULT NULL;
  DECLARE v_top_item_count INT DEFAULT 0;

  -- Total Orders (all statuses except cancelled)
  SELECT COUNT(*)
  INTO v_order_count
  FROM orders
  WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    AND status != 'cancelled';

  -- Total Revenue (all statuses except cancelled, in PHP we'll format)
  SELECT COALESCE(SUM(total_amount), 0)
  INTO v_revenue
  FROM orders
  WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    AND status != 'cancelled';

  -- Peak Hour (hour with most orders)
  SELECT CONCAT(LPAD(HOUR(created_at), 2, '0'), ':00'),
         COUNT(*) as hour_count
  INTO v_peak_hour, v_peak_count
  FROM orders
  WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    AND status != 'cancelled'
  GROUP BY HOUR(created_at)
  ORDER BY hour_count DESC
  LIMIT 1;

  -- Top Item (most ordered product)
  SELECT p.name, COUNT(oi.item_id)
  INTO v_top_item, v_top_item_count
  FROM order_items oi
  JOIN products p ON oi.product_id = p.product_id
  JOIN orders o ON oi.order_id = o.order_id
  WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
    AND o.status != 'cancelled'
  GROUP BY oi.product_id, p.name
  ORDER BY v_top_item_count DESC
  LIMIT 1;

  -- Return all KPI values
  SELECT
    COALESCE(v_order_count, 0) as total_orders,
    COALESCE(v_revenue, 0) as total_revenue,
    COALESCE(v_peak_hour, '—') as peak_hour,
    COALESCE(v_top_item, '—') as top_item,
    COALESCE(v_top_item_count, 0) as top_item_count;
END$$

-- =====================================================
-- HEATMAP DATA (7 rows = days of week, 24 cols = hours)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_heatmap` (
  IN p_date_from DATE,
  IN p_date_to DATE
)
BEGIN
  SELECT
    DAYNAME(created_at) as day_name,
    DAYOFWEEK(created_at) as day_num,
    HOUR(created_at) as hour,
    COUNT(*) as order_count
  FROM orders
  WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    AND status != 'cancelled'
  GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at), HOUR(created_at)
  ORDER BY DAYOFWEEK(created_at), HOUR(created_at);
END$$

-- =====================================================
-- PIE CHART DATA (By Status, Category, Time Slot, Order Type)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_pie_data` (
  IN p_date_from DATE,
  IN p_date_to DATE,
  IN p_group_by VARCHAR(50)
)
BEGIN
  IF p_group_by = 'status' THEN
    SELECT
      status as label,
      COUNT(*) as count,
      ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders 
                                WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to), 1) as percentage
    FROM orders
    WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    GROUP BY status
    ORDER BY count DESC;

  ELSEIF p_group_by = 'category' THEN
    SELECT
      COALESCE(c.name, 'Uncategorized') as label,
      COUNT(*) as count,
      ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM order_items oi
                                JOIN orders o ON oi.order_id = o.order_id
                                WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to), 1) as percentage
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
    GROUP BY c.category_id, c.name
    ORDER BY count DESC;

  ELSEIF p_group_by = 'time' THEN
    SELECT
      CASE
        WHEN HOUR(o.created_at) >= 6 AND HOUR(o.created_at) < 12 THEN 'Morning (6-12)'
        WHEN HOUR(o.created_at) >= 12 AND HOUR(o.created_at) < 17 THEN 'Afternoon (12-5)'
        WHEN HOUR(o.created_at) >= 17 AND HOUR(o.created_at) < 21 THEN 'Evening (5-9)'
        ELSE 'Night (9-6)'
      END as label,
      COUNT(*) as count,
      ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders 
                                WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to), 1) as percentage
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
    GROUP BY label
    ORDER BY count DESC;

  ELSEIF p_group_by = 'ordertype' THEN
    SELECT
      order_type as label,
      COUNT(*) as count,
      ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders 
                                WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to), 1) as percentage
    FROM orders
    WHERE DATE(created_at) BETWEEN p_date_from AND p_date_to
    GROUP BY order_type
    ORDER BY count DESC;
  END IF;
END$$

-- =====================================================
-- TOP PERFORMING LINE CHART (Last 7 days, Top N per category)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_top_performing` (
  IN p_type VARCHAR(50),
  IN p_limit INT
)
BEGIN
  DECLARE v_days_back INT DEFAULT 7;
  DECLARE v_limit INT;
  SET v_limit = p_limit * 7;

  IF p_type = 'item' THEN
    -- Top items over last 7 days, broken down by day
    SELECT
      DATE(o.created_at) as date,
      p.name as label,
      p.product_id as id,
      COUNT(oi.item_id) as order_count,
      SUM(oi.quantity) as quantity_sold,
      COALESCE(c.name, 'Uncategorized') as category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL (v_days_back - 1) DAY)
      AND o.status != 'cancelled'
    GROUP BY DATE(o.created_at), p.product_id, p.name, c.name
    ORDER BY quantity_sold DESC
    LIMIT v_limit;

  ELSEIF p_type = 'category' THEN
    -- Top categories over last 7 days, broken down by day
    SELECT
      DATE(o.created_at) as date,
      COALESCE(c.name, 'Uncategorized') as label,
      c.category_id as id,
      COUNT(oi.item_id) as order_count,
      SUM(oi.quantity) as quantity_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL (v_days_back - 1) DAY)
      AND o.status != 'cancelled'
    GROUP BY DATE(o.created_at), c.category_id, c.name
    ORDER BY quantity_sold DESC
    LIMIT v_limit;

  ELSEIF p_type = 'area' THEN
    -- Top areas (barangays) over last 7 days, broken down by day
    SELECT
      DATE(o.created_at) as date,
      COALESCE(NULLIF(o.barangay, ''), 'Unknown Area') as label,
      COALESCE(NULLIF(o.barangay, ''), 'Unknown Area') as id,
      COUNT(o.order_id) as order_count,
      COALESCE(SUM(o.total_amount), 0) as revenue,
      COALESCE(NULLIF(o.city, ''), 'Unknown District') as district
    FROM orders o
    WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL (v_days_back - 1) DAY)
      AND o.status != 'cancelled'
    GROUP BY DATE(o.created_at), o.barangay, o.city
    ORDER BY order_count DESC
    LIMIT v_limit;
  END IF;
END$$

-- =====================================================
-- RANKED BREAKDOWN (All items/categories/areas with counts)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_ranked_items` (
  IN p_date_from DATE,
  IN p_date_to DATE,
  IN p_type VARCHAR(50),
  IN p_limit INT,
  IN p_offset INT
)
BEGIN
  IF p_type = 'item' THEN
    SELECT
      p.product_id as id,
      p.name as label,
      COUNT(oi.item_id) as order_count,
      SUM(oi.quantity) as quantity_sold,
      ROUND(SUM(oi.subtotal), 2) as revenue,
      ROW_NUMBER() OVER (ORDER BY COUNT(oi.item_id) DESC) as rank,
      COALESCE(c.name, 'Uncategorized') as category
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled'
    GROUP BY p.product_id, p.name, c.name
    ORDER BY order_count DESC
    LIMIT p_limit OFFSET p_offset;

  ELSEIF p_type = 'category' THEN
    SELECT
      c.category_id as id,
      COALESCE(c.name, 'Uncategorized') as label,
      COUNT(oi.item_id) as order_count,
      SUM(oi.quantity) as quantity_sold,
      ROUND(SUM(oi.subtotal), 2) as revenue,
      ROW_NUMBER() OVER (ORDER BY COUNT(oi.item_id) DESC) as rank
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled'
    GROUP BY c.category_id, c.name
    ORDER BY order_count DESC
    LIMIT p_limit OFFSET p_offset;

  ELSEIF p_type = 'area' THEN
    SELECT
      COALESCE(NULLIF(o.barangay, ''), 'Unknown Area') as id,
      COALESCE(NULLIF(o.barangay, ''), 'Unknown Area') as label,
      COUNT(o.order_id) as order_count,
      COUNT(DISTINCT o.user_id) as unique_customers,
      ROUND(SUM(o.total_amount), 2) as revenue,
      ROW_NUMBER() OVER (ORDER BY COUNT(o.order_id) DESC) as rank,
      COALESCE(NULLIF(o.city, ''), 'Unknown District') as district
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled'
    GROUP BY o.barangay, o.city
    ORDER BY order_count DESC
    LIMIT p_limit OFFSET p_offset;
  END IF;
END$$

-- =====================================================
-- SUMMARY COUNT PROCEDURES (for pagination)
-- =====================================================
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_analytics_get_total_count` (
  IN p_date_from DATE,
  IN p_date_to DATE,
  IN p_type VARCHAR(50),
  OUT p_total_count INT
)
BEGIN
  IF p_type = 'item' THEN
    SELECT COUNT(DISTINCT oi.product_id)
    INTO p_total_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled';

  ELSEIF p_type = 'category' THEN
    SELECT COUNT(DISTINCT p.category_id)
    INTO p_total_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled';

  ELSEIF p_type = 'area' THEN
    SELECT COUNT(DISTINCT o.barangay)
    INTO p_total_count
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN p_date_from AND p_date_to
      AND o.status != 'cancelled'
      AND o.barangay IS NOT NULL;
  END IF;
END$$

DELIMITER ;
