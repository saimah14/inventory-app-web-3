<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/escape.php';

// Cek session login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $type      = strtoupper($_POST['type'] ?? '');
    $quantity  = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $note      = trim($_POST['note'] ?? '');
    $userId    = $_SESSION['user']['id'] ?? null;

    if (!$productId) {
        $errors[] = 'Pilih produk yang valid.';
    }
    if (!in_array($type, ['IN', 'OUT'], true)) {
        $errors[] = 'Jenis transaksi harus IN atau OUT.';
    }
    if ($quantity === false || $quantity <= 0) {
        $errors[] = 'Jumlah barang harus lebih besar dari 0.';
    }

    if (empty($errors)) {
        try {
            // DATABASE TRANSACTION
            $pdo->beginTransaction();

            // Lock produk untuk mengecek stok terkini
            $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Produk tidak ditemukan.');
            }

            $currentStock = (int)$product['stock'];

            // Validasi stok cukup jika barang keluar
            if ($type === 'OUT' && $quantity > $currentStock) {
                throw new Exception("Stok tidak mencukupi! Stok saat ini: $currentStock");
            }

            $newStock = ($type === 'IN') ? ($currentStock + $quantity) : ($currentStock - $quantity);

            // Update stok produk
            $updateStmt = $pdo->prepare('UPDATE products SET stock = :stock WHERE id = :id');
            $updateStmt->execute(['stock' => $newStock, 'id' => $productId]);

            // Catat riwayat di stock_movements
            $logStmt = $pdo->prepare('INSERT INTO stock_movements (product_id, user_id, type, quantity, note) VALUES (:product_id, :user_id, :type, :quantity, :note)');
            $logStmt->execute([
                'product_id' => $productId,
                'user_id'    => $userId,
                'type'       => $type,
                'quantity'   => $quantity,
                'note'       => $note
            ]);

            $pdo->commit();
            $successMessage = 'Transaksi stok berhasil diproses!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Ambil daftar produk untuk dropdown
$products = $pdo->query('SELECT id, sku, name, stock FROM products ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat transaksi
$historySql = "SELECT sm.*, p.name AS product_name, u.username 
               FROM stock_movements sm 
               JOIN products p ON p.id = sm.product_id 
               LEFT JOIN users u ON u.id = sm.user_id 
               ORDER BY sm.created_at DESC LIMIT 10";
$history = $pdo->query($historySql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi Stok</title>
</head>
<body>

    <h2>Kelola Stok (Barang Masuk / Keluar)</h2>
    <p><a href="/dashboard.php">← Kembali ke Dashboard</a></p>

    <?php if ($successMessage): ?>
        <p style="color: green; font-weight: bold;"><?= e($successMessage) ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <p>
            <label>Pilih Produk:</label><br>
            <select name="product_id" required>
                <option value="">-- Pilih Produk --</option>
                <?php if (empty($products)): ?>
                    <option value="" disabled>(Belum ada produk, silakan tambah produk dulu)</option>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?> | Stok: <?= e($p['stock']) ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </p>

        <p>
            <label>Jenis Transaksi:</label><br>
            <select name="type" required>
                <option value="IN">Barang Masuk (IN)</option>
                <option value="OUT">Barang Keluar (OUT)</option>
            </select>
        </p>

        <p>
            <label>Jumlah (Quantity):</label><br>
            <input type="number" name="quantity" min="1" required>
        </p>

        <p>
            <label>Catatan / Keterangan:</label><br>
            <input type="text" name="note" placeholder="Contoh: Stok dari Supplier A">
        </p>

        <button type="submit">Simpan Transaksi</button>
    </form>

    <hr>

    <h3>Riwayat Transaksi Terakhir</h3>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Produk</th>
                <th>Tipe</th>
                <th>Jumlah</th>
                <th>Petugas</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($history)): ?>
                <tr><td colspan="6" align="center">Belum ada transaksi stok.</td></tr>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h['created_at']) ?></td>
                        <td><?= e($h['product_name']) ?></td>
                        <td style="color: <?= $h['type'] === 'IN' ? 'green' : 'red' ?>;">
                            <strong><?= e($h['type']) ?></strong>
                        </td>
                        <td><?= e($h['quantity']) ?></td>
                        <td><?= e($h['username'] ?? 'System') ?></td>
                        <td><?= e($h['note'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>