<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_stock') {
        $product_id = intval($_POST['product_id']);
        $location_id = intval($_POST['location_id']);
        $quantity = intval($_POST['quantity']);
        $batch_number = sanitize($_POST['batch_number']);
        $manufacturing_date = $_POST['manufacturing_date'];
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $cost_price = floatval($_POST['cost_price']);
        $selling_price = floatval($_POST['selling_price']);
        
        // Insert inventory
        $stmt = $conn->prepare("INSERT INTO inventory (product_id, location_id, quantity, batch_number, manufacturing_date, expiration_date, cost_price, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisssdd", $product_id, $location_id, $quantity, $batch_number, $manufacturing_date, $expiration_date, $cost_price, $selling_price);
        
        if ($stmt->execute()) {
            // Record transaction
            $user_id = $_SESSION['user_id'];
            $ref = 'STOCK-IN-' . time();
            $stmt2 = $conn->prepare("INSERT INTO transactions (product_id, transaction_type, quantity, location_id, batch_number, reference_number, user_id) VALUES (?, 'in', ?, ?, ?, ?, ?)");
            $stmt2->bind_param("iiissi", $product_id, $quantity, $location_id, $batch_number, $ref, $user_id);
            $stmt2->execute();
            $stmt2->close();
            
            $message = '<div class="alert alert-success">Stock added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
}

// Get inventory with product details
$inventory = $conn->query("SELECT i.*, p.name as product_name, p.sku, p.unit, 
    l.name as location_name, c.name as category_name 
    FROM inventory i 
    JOIN products p ON i.product_id = p.id 
    JOIN storage_locations l ON i.location_id = l.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY i.last_updated DESC");

// Get products and locations for dropdowns
$products = $conn->query("SELECT * FROM products ORDER BY name");
$locations = $conn->query("SELECT * FROM storage_locations ORDER BY name");

include 'header.php';
?>

<div class="card">
    <h2>Inventory Management</h2>
    <?php echo $message; ?>
    
    <button onclick="document.getElementById('addStockForm').style.display='block'" class="btn btn-primary">+ Add Stock</button>
    
    <div id="addStockForm" style="display:none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3>Add Stock</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_stock">
            <div class="form-grid">
                <div class="form-group">
                    <label>Product *</label>
                    <select name="product_id" required>
                        <option value="">Select Product</option>
                        <?php 
                        $products->data_seek(0);
                        while ($p = $products->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?> (<?php echo $p['sku']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Storage Location *</label>
                    <select name="location_id" required>
                        <option value="">Select Location</option>
                        <?php 
                        $locations->data_seek(0);
                        while ($l = $locations->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $l['id']; ?>"><?php echo $l['name']; ?> (<?php echo $l['type']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" required>
                </div>
                <div class="form-group">
                    <label>Batch Number *</label>
                    <input type="text" name="batch_number" required>
                </div>
                <div class="form-group">
                    <label>Manufacturing Date *</label>
                    <input type="date" name="manufacturing_date" required>
                </div>
                <div class="form-group">
                    <label>Expiration Date</label>
                    <input type="date" name="expiration_date">
                </div>
                <div class="form-group">
                    <label>Cost Price *</label>
                    <input type="number" step="0.01" name="cost_price" required>
                </div>
                <div class="form-group">
                    <label>Selling Price *</label>
                    <input type="number" step="0.01" name="selling_price" required>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Add Stock</button>
                <button type="button" onclick="document.getElementById('addStockForm').style.display='none'" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <h2>Current Inventory</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Batch</th>
                    <th>Quantity</th>
                    <th>Mfg Date</th>
                    <th>Exp Date</th>
                    <th>Cost Price</th>
                    <th>Sell Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $inventory->fetch_assoc()): 
                    $exp_warning = false;
                    if ($row['expiration_date']) {
                        $days_to_exp = (strtotime($row['expiration_date']) - time()) / (60 * 60 * 24);
                        $exp_warning = $days_to_exp <= 30;
                    }
                ?>
                <tr class="<?php echo $exp_warning ? 'out-of-stock' : ''; ?>">
                    <td><strong><?php echo $row['product_name']; ?></strong></td>
                    <td><?php echo $row['sku']; ?></td>
                    <td><?php echo $row['category_name']; ?></td>
                    <td><?php echo $row['location_name']; ?></td>
                    <td><?php echo $row['batch_number']; ?></td>
                    <td><strong><?php echo $row['quantity']; ?> <?php echo $row['unit']; ?></strong></td>
                    <td><?php echo formatDate($row['manufacturing_date']); ?></td>
                    <td><?php echo $row['expiration_date'] ? formatDate($row['expiration_date']) : 'N/A'; ?></td>
                    <td><?php echo formatCurrency($row['cost_price']); ?></td>
                    <td><?php echo formatCurrency($row['selling_price']); ?></td>
                    <td>
                        <?php if ($exp_warning): ?>
                            <span class="badge" style="background: #e74c3c; color: white;">EXPIRING SOON</span>
                        <?php else: ?>
                            <span class="badge" style="background: #2ecc71; color: white;">GOOD</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include 'footer.php';
$conn->close();
?>