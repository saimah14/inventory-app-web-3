<?php
// Load konfigurasi database dan helper
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

// Pastikan user sudah login
checkAuth();

// --- 1. AMBIL INPUT QUERY STRING ---
$search   = trim($_GET['search'] ?? '');
$category = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$sort     = $_GET['sort'] ?? 'name';
$dir      = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

// --- 2. ALLOWLIST SORTING (Pencegahan SQL Injection) ---
$allowedSorts = [
    'name'  => 'p.name',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'sku'   => 'p.sku'
];
$orderBy = $allowedSorts[$sort] ?? 'p.name';

// --- 3. PAGINATION ---
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$offset  = ($page - 1) * $perPage;

// --- 4. SUSUN WHERE CLAUSE DAN BINDING PARAMETER ---
$whereConditions = [];
$params          = [];

if ($search !== '') {
    $whereConditions[] = "(p.name LIKE :search OR p.sku LIKE :search)";
    $params['search']  = "%{$search}%";
}

if ($category) {
    $whereConditions[] = "p.category_id = :category_id";
    $params['category_id'] = $category;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// --- 5. HITUNG TOTAL DATA (untuk Pagination) ---
$countSql = "SELECT COUNT(*) FROM products p $whereClause";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// --- 6. AMBIL DATA PRODUK DENGAN JOIN KATEGORI ---
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        JOIN categories c ON c.id = p.category_id 
        $whereClause 
        ORDER BY $orderBy $dir 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind parameter search/filter
foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
// Bind parameter pagination sebagai INTEGER
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar kategori untuk dropdown filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Produk</title>
</head>
<body>

    <h2>Daftar Produk</h2>
    <p>
        <a href="/dashboard.php">← Kembali ke Dashboard</a> | 
        <a href="/products/create.php">+ Tambah Produk</a>
    </p>

    <!-- FORM SEARCH & FILTER -->
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Cari nama atau SKU..." value="<?= e($search) ?>">
        
        <select name="category_id">
            <option value="">-- Semua Kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Cari & Filter</button>
        <a href="/products/index.php">Reset</a>
    </form>

    <br>

    <!-- TABEL LISTING PRODUK -->
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>SKU</th>
                <th>
                    <a href="?search=<?= urlencode($search) ?>&category_id=<?= $category ?>&sort=name&dir=<?= ($sort === 'name' && $dir === 'ASC') ? 'DESC' : 'ASC' ?>">
                        Nama Produk <?= $sort === 'name' ? ($dir === 'ASC' ? '▲' : '▼') : '' ?>
                    </a>
                </th>
                <th>Kategori</th>
                <th>
                    <a href="?search=<?= urlencode($search) ?>&category_id=<?= $category ?>&sort=price&dir=<?= ($sort === 'price' && $dir === 'ASC') ? 'DESC' : 'ASC' ?>">
                        Harga <?= $sort === 'price' ? ($dir === 'ASC' ? '▲' : '▼') : '' ?>
                    </a>
                </th>
                <th>
                    <a href="?search=<?= urlencode($search) ?>&category_id=<?= $category ?>&sort=stock&dir=<?= ($sort === 'stock' && $dir === 'ASC') ? 'DESC' : 'ASC' ?>">
                        Stok <?= $sort === 'stock' ? ($dir === 'ASC' ? '▲' : '▼') : '' ?>
                    </a>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="5" align="center">Data produk tidak ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e($p['sku']) ?></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['category_name']) ?></td>
                        <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                        <td>
                            <?= e($p['stock']) ?>
                            <?php if ($p['stock'] <= $p['minimum_stock']): ?>
                                <strong style="color:red;">(Stok Rendah!)</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <br>

    <!-- NAVIGASI PAGINATION -->
    <div>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category_id=<?= $category ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
               style="<?= $i === $page ? 'font-weight:bold; text-decoration:none;' : '' ?>">
                [<?= $i ?>]
            </a>
        <?php endfor; ?>
    </div>

</body>
</html>