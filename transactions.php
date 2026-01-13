<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$message = '';

// Handle stock out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'stock_out') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $location_id = intval($_POST['location_id']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        $ref = 'STOCK-OUT-' . time();
        
        // Check if enough stock
        $check = $conn->query("SELECT SUM(quantity) as total FROM inventory WHERE product_id = $product_id AND location_id = $location_id");
        $stock = $check->fetch_assoc()['total'];
        
        if ($stock >= $quantity) {
            // Update inventory (simplified - deduct from first available batch)
            $conn->query("UPDATE inventory SET quantity = quantity - $quantity WHERE product_id = $product_id AND location_id = $location_id AND quantity > 0 LIMIT 1");
            
            // Record transaction
            $stmt = $conn->prepare("INSERT INTO transactions (product_id, transaction_type, quantity, location_id, reference_number, notes, user_id) VALUES (?, 'out', ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissi", $product_id, $quantity, $location_id, $ref, $notes, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = '<div class="alert alert-success">Stock removed successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Insufficient stock! Available: ' . $stock . '</div>';
        }
    }
}

// Get all transactions
$transactions = $conn->query("SELECT t.*, p.name as product_name, p.sku, 
    l.name as location_name, u.full_name as user_name 
    FROM transactions t 
    JOIN products p ON t.product_id = p.id 
    LEFT JOIN storage_locations l ON t.location_id = l.id 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.transaction_date DESC 
    LIMIT 100");

$products = $conn->query("SELECT * FROM products ORDER BY name");
$locations = $conn->query("SELECT * FROM storage_locations ORDER BY name");

include 'header.php';
?>

<div class="card">
    <h2>Stock Transactions</h2>
    <?php echo $message; ?>
    
    <button onclick="document.getElementById('stockOutForm').style.display='block'" class="btn btn-warning">- Stock Out</button>
    
    <div id="stockOutForm" style="display:none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3>Stock Out</h3>
        <form method="POST">
            <input type="hidden" name="action" value="stock_out">
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
                    <label>Location *</label>
                    <select name="location_id" required>
                        <option value="">Select Location</option>
                        <?php 
                        $locations->data_seek(0);
                        while ($l = $locations->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $l['id']; ?>"><?php echo $l['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Sale, wastage, etc.">
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-warning">Process Stock Out</button>
                <button type="button" onclick="document.getElementById('stockOutForm').style.display='none'" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>
 
<div class="card">
    <h2>Transaction History</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref#</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>Batch</th>
                    <th>User</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i', strtotime($row['transaction_date'])); ?></td>
                    <td><?php echo $row['reference_number']; ?></td>
                    <td><strong><?php echo $row['product_name']; ?></strong><br><small><?php echo $row['sku']; ?></small></td>
                    <td><span class="badge badge-<?php echo $row['transaction_type']; ?>"><?php echo strtoupper($row['transaction_type']); ?></span></td>
                    <td><strong><?php echo $row['quantity']; ?></strong></td>
                    <td><?php echo $row['location_name']; ?></td>
                    <td><?php echo $row['batch_number'] ?? 'N/A'; ?></td>
                    <td><?php echo $row['user_name']; ?></td>
                    <td><?php echo $row['notes'] ?? ''; ?></td>
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