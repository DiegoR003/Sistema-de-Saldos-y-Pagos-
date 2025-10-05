<?php
require_once __DIR__.'/../App/bd.php';
$pdo = db();
echo 'OK MySQL vers: '. $pdo->query('select version()')->fetchColumn();
?>