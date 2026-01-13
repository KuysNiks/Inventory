<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();

// Stock Summary
$stock_summary = $conn->query("SELECT p.name, p.sku, c.name as category, 
    SUM(i.quantity) as total_stock, p.reorder_level,
    SUM(i.quantity * i.cost_price) as total_value
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    GROUP BY p.id
    ORDER BY total_value DESC");

// Expiring Items
$expiring = $conn->query("SELECT p.name, i.batch_number, i.quantity, i.expiration_date, l.name as location
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    JOIN storage_locations l ON i.location_id = l.id
    WHERE i.expiration_date IS NOT NULL 
    AND i.expiration_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY i.expiration_date ASC");

// Low Stock
$low_stock = $conn->query("SELECT p.name, p.sku, p.reorder_level, SUM(i.quantity) as current_stock
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    GROUP BY p.id
    HAVING current_stock <= p.reorder_level
    ORDER BY current_stock ASC");

include 'header.php';
?>

<div class="card">
    <h2>📊 Inventory Reports</h2>
</div>

<div class="card">
    <h2>Stock Summary</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Total Stock</th>
                    <th>Reorder Level</th>
                    <th>Total Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_value = 0;
                while ($row = $stock_summary->fetch_assoc()): 
                    $total_value += $row['total_value'];
                ?>
                <tr>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><?php echo $row['sku']; ?></td>
                    <td><?php echo $row['category']; ?></td>
                    <td><?php echo $row['total_stock'] ?? 0; ?></td>
                    <td><?php echo $row['reorder_level']; ?></td>
                    <td><?php echo formatCurrency($row['total_value'] ?? 0); ?></td>
                    <td>
                        <?php if ($row['total_stock'] <= $row['reorder_level']): ?>
                            <span class="badge" style="background: #f39c12; color: white;">LOW</span>
                        <?php else: ?>
                            <span class="badge" style="background: #2ecc71; color: white;">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="5">TOTAL INVENTORY VALUE</td>
                    <td><?php echo formatCurrency($total_value); ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h2>⏰ Expiring Soon (30 Days)</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Batch</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Expires On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $expiring->fetch_assoc()): ?>
                    <tr class="out-of-stock">
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['batch_number']; ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo $row['location']; ?></td>
                        <td><strong><?php echo formatDate($row['expiration_date']); ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card">
        <h2>⚠️ Low Stock Items</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $low_stock->fetch_assoc()): ?>
                    <tr class="out-of-stock">
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['sku']; ?></td>
                        <td><strong><?php echo $row['current_stock'] ?? 0; ?></strong></td>
                        <td><?php echo $row['reorder_level']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'footer.php';
$conn->close();
?>
