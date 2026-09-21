<?php
// Empties the DB-backed cache table (cold-cache measurements). Perf DB only.
$pdo = new PDO('mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf', 'root', '');
$pdo->exec('DELETE FROM cache'); $pdo->exec('DELETE FROM cache_locks');
