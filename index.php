<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();

/* =======================
   DASHBOARD STATISTICS
   ======================= */

$stats = [];

// Total products
$stats['total_products'] = $conn->query(
    "SELECT COUNT(*) AS count FROM products"
)->fetch_assoc()['count'];

// Total inventory quantity
$stats['total_inventory'] = $conn->query(
    "SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory"
)->fetch_assoc()['total'];

// Low stock products count (STRICT MODE SAFE)
$lowStockResult = $conn->query("
    SELECT COUNT(*) AS count FROM (
        SELECT p.id
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        GROUP BY p.id
        HAVING COALESCE(SUM(i.quantity), 0) <= MIN(p.reorder_level)
    ) AS low_stock
");

$stats['low_stock'] = $lowStockResult->fetch_assoc()['count'];

// Expiring inventory within 30 days
$stats['expiring_soon'] = $conn->query("
    SELECT COUNT(*) AS count
    FROM inventory
    WHERE expiration_date IS NOT NULL
      AND expiration_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
")->fetch_assoc()['count'];

/* =======================
   RECENT TRANSACTIONS
   ======================= */

$recent_transactions = $conn->query("
    SELECT 
        t.*,
        p.name AS product_name,
        u.full_name AS user_name
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    JOIN users u ON t.user_id = u.id
    ORDER BY t.transaction_date DESC
    LIMIT 10
");

/* =======================
   LOW STOCK PRODUCTS LIST
   ======================= */

$low_stock_products = $conn->query("
    SELECT 
        p.name,
        p.sku,
        MIN(p.reorder_level) AS reorder_level,
        COALESCE(SUM(i.quantity), 0) AS current_stock
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    GROUP BY p.id
    HAVING current_stock <= reorder_level
    ORDER BY current_stock ASC
    LIMIT 10
");

include 'header.php';
?>

<div class="dashboard">
    <h1>Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <h3><?= $stats['total_products']; ?></h3>
                <p>Total Products</p>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3><?= number_format($stats['total_inventory']); ?></h3>
                <p>Total Inventory Items</p>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <h3><?= $stats['low_stock']; ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon">⏰</div>
            <div class="stat-info">
                <h3><?= $stats['expiring_soon']; ?></h3>
                <p>Expiring Soon (30 days)</p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Recent Transactions</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recent_transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?= formatDate($row['transaction_date']); ?></td>
                            <td><?= htmlspecialchars($row['product_name']); ?></td>
                            <td>
                                <span class="badge badge-<?= $row['transaction_type']; ?>">
                                    <?= strtoupper($row['transaction_type']); ?>
                                </span>
                            </td>
                            <td><?= $row['quantity']; ?></td>
                            <td><?= htmlspecialchars($row['user_name']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-section">
            <h2>Low Stock Alert</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Current</th>
                            <th>Reorder Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $low_stock_products->fetch_assoc()): ?>
                        <tr class="<?= $row['current_stock'] == 0 ? 'out-of-stock' : ''; ?>">
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= htmlspecialchars($row['sku']); ?></td>
                            <td><strong><?= $row['current_stock']; ?></strong></td>
                            <td><?= $row['reorder_level']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
$conn->close();
?>