<div class="card">
    <div class="card-header">
        <h1 class="card-title">Income Overview</h1>
        <div>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-secondary" onclick="exportCSV()">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="filter-section">
            <form method="get" action="" class="filter-form">
                <input type="hidden" name="page" value="income-overview">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="transfer" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                            <option value="qris" <?php echo isset($_GET['payment_method']) && $_GET['payment_method'] === 'qris' ? 'selected' : ''; ?>>QRIS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="paid" <?php echo isset($_GET['status']) && $_GET['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="unpaid" <?php echo isset($_GET['status']) && $_GET['status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        </select>
                    </div>
                    <div class="form-group btn-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?page=income-overview" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="income-summary">
            <?php
            // Build query conditions based on filters
            $conditions = [];
            $params = [];
            
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $conditions[] = "p.payment_date >= ?";
                $params[] = $_GET['date_from'] . ' 00:00:00';
            }
            
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $conditions[] = "p.payment_date <= ?";
                $params[] = $_GET['date_to'] . ' 23:59:59';
            }
            
            if (isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
                $conditions[] = "p.payment_method = ?";
                $params[] = $_GET['payment_method'];
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $conditions[] = "p.status = ?";
                $params[] = $_GET['status'];
            }
            
            $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // Get total income
            $query = "SELECT SUM(amount) FROM payments p $where_clause";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $total_income = $stmt->fetchColumn() ?: 0;
            
            // Get payment method breakdown
            $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total FROM payments p $where_clause GROUP BY payment_method";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $payment_methods = $stmt->fetchAll();
            ?>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-title">Total Income</div>
                        <div class="summary-value">IDR <?php echo number_format($total_income, 0, ',', '.'); ?></div>
                    </div>
                </div>
                
                <?php foreach ($payment_methods as $method): ?>
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas <?php 
                            echo $method['payment_method'] === 'cash' ? 'fa-money-bill' : 
                                ($method['payment_method'] === 'transfer' ? 'fa-university' : 'fa-qrcode'); 
                        ?>"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-title"><?php echo ucfirst($method['payment_method']); ?></div>
                        <div class="summary-value">IDR <?php echo number_format($method['total'], 0, ',', '.'); ?></div>
                        <div class="summary-subtitle"><?php echo $method['count']; ?> transactions</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table" id="payments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Room</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Get payments with pagination
                    $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
                    $per_page = 10;
                    $offset = ($page - 1) * $per_page;
                    
                    $query = "
                        SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name, r.name AS room_name
                        FROM payments p
                        JOIN tenants t ON p.tenant_id = t.id
                        JOIN users u ON t.user_id = u.id
                        JOIN rooms r ON t.room_id = r.id
                        $where_clause
                        ORDER BY p.payment_date DESC
                        LIMIT $offset, $per_page
                    ";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $payments = $stmt->fetchAll();
                    
                    // Get total count for pagination
                    $query = "SELECT COUNT(*) FROM payments p $where_clause";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $total_records = $stmt->fetchColumn();
                    $total_pages = ceil($total_records / $per_page);
                    
                    foreach ($payments as $payment):
                    ?>
                    <tr>
                        <td><?php echo $payment['id']; ?></td>
                        <td><?php echo $payment['tenant_name']; ?></td>
                        <td><?php echo $payment['room_name']; ?></td>
                        <td>IDR <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                        <td>
                            <span class="badge <?php echo $payment['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=income-overview&p=<?php echo $i; ?><?php 
                    echo isset($_GET['date_from']) ? '&date_from=' . $_GET['date_from'] : ''; 
                    echo isset($_GET['date_to']) ? '&date_to=' . $_GET['date_to'] : '';
                    echo isset($_GET['payment_method']) ? '&payment_method=' . $_GET['payment_method'] : '';
                    echo isset($_GET['status']) ? '&status=' . $_GET['status'] : '';
                ?>" class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .filter-section {
        margin-bottom: 24px;
        padding: 16px;
        background-color: var(--sidebar-bg);
        border-radius: var(--border-radius-md);
    }
    
    .filter-form {
        display: flex;
        flex-direction: column;
    }
    
    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: flex-end;
    }
    
    .form-group {
        flex: 1;
        min-width: 200px;
    }
    
    .btn-group {
        display: flex;
        gap: 8px;
        min-width: auto;
    }
    
    .summary-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .summary-card {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        background-color: var(--card-bg);
        border-radius: var(--border-radius-md);
        padding: 16px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }
    
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .summary-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        font-size: 20px;
    }
    
    .summary-content {
        flex: 1;
    }
    
    .summary-title {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .summary-value {
        font-size: 20px;
        font-weight: 600;
    }
    
    .summary-subtitle {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 24px;
    }
    
    .pagination-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        text-decoration: none;
        transition: var(--transition);
    }
    
    .pagination-link:hover {
        background-color: var(--hover-color);
    }
    
    .pagination-link.active {
        background-color: var(--accent-color);
        color: white;
    }
    
    @media (max-width: 768px) {
        .form-group {
            min-width: 100%;
        }
    }
</style>

<script>
    function printReport() {
        window.print();
    }
    
    function exportCSV() {
        // Get table data
        const table = document.getElementById('payments-table');
        const rows = table.querySelectorAll('tr');
        
        // Create CSV content
        let csv = [];
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            for (let j = 0; j < cols.length; j++) {
                // Clean the text content (remove currency symbols, etc.)
                let text = cols[j].textContent.trim();
                // Add quotes around the field
                row.push('"' + text + '"');
            }
            csv.push(row.join(','));
        }
        
        // Create and download CSV file
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'income_report.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

