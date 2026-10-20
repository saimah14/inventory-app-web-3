<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

// Pastikan pengguna sudah login
checkAuth();

$errors = [];
$sku = '';
$name = '';
$categoryId = '';
$price = '';
$stock = '';
$minimumStock = 5; // Nilai default

// Ambil daftar kategori untuk pilihan dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $minimumStock = filter_input(INPUT_POST, 'minimum_stock', FILTER_VALIDATE_INT);

    // --- VALIDASI INPUT ---
    if ($sku === '') {
        $errors[] = 'SKU tidak boleh kosong.';
    } else {
        // Cek apakah SKU sudah ada di database (Must be unique)
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = :sku');
        $stmtCheck->execute(['sku' => $sku]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = 'SKU sudah digunakan oleh produk lain.';
        }
    }

    if ($name === '') {
        $errors[] = 'Nama produk tidak boleh kosong.';
    }

    if (!$categoryId) {
        $errors[] = 'Pilih kategori produk yang valid.';
    }

    if ($price === false || $price < 0) {
        $errors[] = 'Harga tidak boleh bernilai negatif.';
    }

    if ($stock === false || $stock < 0) {
        $errors[] = 'Stok awal tidak boleh bernilai negatif.';
    }

    if ($minimumStock === false || $minimumStock < 0) {
        $errors[] = 'Stok minimal tidak boleh bernilai negatif.';
    }

    // --- JIKA TIDAK ADA ERROR, SIMPAN KE DATABASE ---
    if (empty($errors)) {
        $sql = 'INSERT INTO products (category_id, sku, name, price, stock, minimum_stock) 
                VALUES (:category_id, :sku, :name, :price, :stock, :minimum_stock)';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'category_id'   => $categoryId,
            'sku'           => $sku,
            'name'          => $name,
            'price'         => $price,
            'stock'         => $stock,
            'minimum_stock' => $minimumStock
        ]);

        // Redirect kembali ke daftar produk setelah sukses
        header('Location: /products/index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk</title>
</head>
<body>

    <h2>Tambah Produk Baru</h2>
    <p><a href="/products/index.php">← Kembali ke Daftar Produk</a></p>

    <!-- TAMPILKAN ERROR JIKA ADA -->
    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <p>
            <label for="sku">SKU Produk:</label><br>
            <input type="text" id="sku" name="sku" value="<?= e($sku) ?>" required>
        </p>

        <p>
            <label for="name">Nama Produk:</label><br>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
        </p>

        <p>
            <label for="category_id">Kategori:</label><br>
            <select id="category_id" name="category_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="price">Harga (Rp):</label><br>
            <input type="number" step="0.01" id="price" name="price" value="<?= e((string)$price) ?>" required>
        </p>

        <p>
            <label for="stock">Stok Awal:</label><br>
            <input type="number" id="stock" name="stock" value="<?= e((string)$stock) ?>" required>
        </p>

        <p>
            <label for="minimum_stock">Stok Minimal Alert:</label><br>
            <input type="number" id="minimum_stock" name="minimum_stock" value="<?= e((string)$minimumStock) ?>" required>
        </p>

        <button type="submit">Simpan Produk</button>
    </form>

</body>
</html>