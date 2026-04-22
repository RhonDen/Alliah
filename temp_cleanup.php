<?php
$dsn = "mysql:host=localhost;dbname=alliah;charset=utf8mb4";
$pdo = new PDO($dsn, "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([12]);
echo "deleted 12\n";
