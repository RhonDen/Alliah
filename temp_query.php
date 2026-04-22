<?php
require_once 'c:/xampp/htdocs/alliah/includes/bootstrap.php';

$result = createWalkInClient($pdo, 'Test', 'Guest', '', '0999-123-4567', 28);
var_dump($result);
if ($result['success']) {
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, mobile, password, LENGTH(password) AS len FROM users WHERE id = ?');
    $stmt->execute([$result['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    var_dump($row);
}
