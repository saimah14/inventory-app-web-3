<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/escape.php';

// Cek session login secara langsung
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// Hanya role admin yang boleh mengelola kategori
if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Akses Ditolak: Hanya Admin yang dapat mengelola kategori.');
}

$errors = [];
$successMessage = '';

// --- 1. PROSES TAMBAH KATEGORI (CREATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $errors[] = 'Nama kategori tidak boleh kosong.';
    } else {
        // Cek duplikasi nama kategori
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = :name');
        $stmtCheck->execute(['name' => $name]);
        if ($stmtCheck->fetchColumn() > 0) {
            $errors[] = 'Nama kategori tersebut sudah ada.';
        } else {
            $stmtInsert = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
            $stmtInsert->execute(['name' => $name]);
            $successMessage = 'Kategori berhasil ditambahkan!';
        }
    }
}

// --- 2. PROSES HAPUS KATEGORI (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmtDelete = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmtDelete->execute(['id' => $id]);
            $successMessage = 'Kategori berhasil dihapus!';
        } catch (PDOException $e) {
            // Menangani Foreign Key Constraint jika kategori masih dipakai di produk
            $errors[] = 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.';
        }
    }
}

// --- 3. AMBIL DAFTAR KATEGORI ---
$categories = $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kategori Produk</title>
</head>
<body>

    <h2>Kelola Kategori Produk</h2>
    <p><a href="/dashboard.php">← Kembali ke Dashboard</a></p>

    <!-- PESAN SUKSES & ERROR -->
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

    <!-- FORM TAMBAH KATEGORI -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="create">
        <p>
            <label for="name">Nama Kategori Baru:</label><br>
            <input type="text" id="name" name="name" required placeholder="Contoh: Elektronik">
            <button type="submit">Tambah Kategori</button>
        </p>
    </form>

    <hr>

    <h3>Daftar Kategori</h3>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Kategori</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="3" align="center">Belum ada kategori.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= e($cat['id']) ?></td>
                        <td><?= e($cat['name']) ?></td>
                        <td>
                            <a href="?action=delete&id=<?= $cat['id'] ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');" 
                               style="color: red;">
                                Hapus
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>