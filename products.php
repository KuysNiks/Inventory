<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $sku = sanitize($_POST['sku']);
            $name = sanitize($_POST['name']);
            $category_id = intval($_POST['category_id']);
            $unit = sanitize($_POST['unit']);
            $reorder_level = intval($_POST['reorder_level']);
            $storage_temp = !empty($_POST['storage_temp']) ? floatval($_POST['storage_temp']) : null;
            $rice_variety = sanitize($_POST['rice_variety']);
            $description = sanitize($_POST['description']);
            
            $stmt = $conn->prepare("INSERT INTO products (sku, name, category_id, unit, reorder_level, storage_temp, rice_variety, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissdss", $sku, $name, $category_id, $unit, $reorder_level, $storage_temp, $rice_variety, $description);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Product added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM products WHERE id = $id");
            $message = '<div class="alert alert-success">Product deleted successfully!</div>';
        }
    }
}

// Get all products
$products = $conn->query("SELECT p.*, c.name as category_name, 
    COALESCE(SUM(i.quantity), 0) as total_stock 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN inventory i ON p.id = i.product_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC");

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

include 'header.php';
?>

<div class="card">
    <h2>Product Management</h2>
    <?php echo $message; ?>
    
    <button onclick="document.getElementById('addForm').style.display='block'" class="btn btn-primary">+ Add New Product</button>
    
    <div id="addForm" style="display:none; margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3>Add New Product</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>SKU *</label>
                    <input type="text" name="sku" required>
                </div>
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <input type="text" name="unit" placeholder="kg, pcs, box" required>
                </div>
                <div class="form-group">
                    <label>Reorder Level *</label>
                    <input type="number" name="reorder_level" value="10" required>
                </div>
                <div class="form-group">
                    <label>Storage Temperature (°C)</label>
                    <input type="number" step="0.01" name="storage_temp" placeholder="-18">
                </div>
                <div class="form-group">
                    <label>Rice Variety</label>
                    <input type="text" name="rice_variety" placeholder="Jasmine, BukoPandan, etc.">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Save Product</button>
                <button type="button" onclick="document.getElementById('addForm').style.display='none'" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <h2>All Products</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Stock</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $products->fetch_assoc()): ?>
                <tr class="<?php echo $row['total_stock'] <= $row['reorder_level'] ? 'out-of-stock' : ''; ?>">
                    <td><?php echo $row['sku']; ?></td>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><?php echo $row['category_name']; ?></td>
                    <td><?php echo $row['unit']; ?></td>
                    <td><strong><?php echo $row['total_stock']; ?></strong></td>
                    <td><?php echo $row['reorder_level']; ?></td>
                    <td>
                        <?php if ($row['total_stock'] == 0): ?>
                            <span class="badge" style="background: #e74c3c; color: white;">OUT OF STOCK</span>
                        <?php elseif ($row['total_stock'] <= $row['reorder_level']): ?>
                            <span class="badge" style="background: #f39c12; color: white;">LOW STOCK</span>
                        <?php else: ?>
                            <span class="badge" style="background: #2ecc71; color: white;">IN STOCK</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-buttons">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
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