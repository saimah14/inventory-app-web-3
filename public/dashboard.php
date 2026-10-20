<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// 1. Hitung Total Produk
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// 2. Hitung Nilai Total Inventory (SUM(stock * price))
$totalInventoryValue = $pdo->query("SELECT SUM(stock * price) FROM products")->fetchColumn() ?? 0;

// 3. Hitung Produk dengan Stok Rendah (stock <= minimum_stock)
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= minimum_stock")->fetchColumn();

// 4. Hitung Total Barang Masuk & Keluar
$totalIn = $pdo->query("SELECT SUM(quantity) FROM stock_movements WHERE type = 'IN'")->fetchColumn() ?? 0;
$totalOut = $pdo->query("SELECT SUM(quantity) FROM stock_movements WHERE type = 'OUT'")->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Inventory Management</title>
</head>
<body>

    <h2>Dashboard Inventory</h2>
    <p>Selamat datang, <strong><?= e($_SESSION['user']['username'] ?? 'User') ?></strong> (Role: <?= e($_SESSION['user']['role'] ?? 'Staff') ?>)</p>

    <!-- NAVIGASI UTAMA -->
    <p>
        <a href="/categories.php">Kategori Produk</a> | 
        <a href="/products/index.php">Daftar Produk</a> | 
        <a href="/stock/index.php">Kelola Stok (Transaksi)</a> | 
        <a href="/logout.php" style="color: red;">Logout</a>
    </p>

    <hr>

    <h3>Ringkasan Inventory</h3>
    <table border="1" cellpadding="10" cellspacing="0">
        <tr>
            <th>Total Produk</th>
            <td><strong><?= number_format($totalProducts) ?></strong> jenis</td>
        </tr>
        <tr>
            <th>Nilai Total Inventory</th>
            <td><strong>Rp <?= number_format($totalInventoryValue, 0, ',', '.') ?></strong></td>
        </tr>
        <tr>
            <th>Produk Stok Rendah</th>
            <td style="color: <?= $lowStockCount > 0 ? 'red' : 'black' ?>;">
                <strong><?= number_format($lowStockCount) ?></strong> produk
            </td>
        </tr>
        <tr>
            <th>Total Barang Masuk (IN)</th>
            <td style="color: green;"><strong>+<?= number_format($totalIn) ?></strong> unit</td>
        </tr>
        <tr>
            <th>Total Barang Keluar (OUT)</th>
            <td style="color: red;"><strong>-<?= number_format($totalOut) ?></strong> unit</td>
        </tr>
    </table>

</body>
</html>