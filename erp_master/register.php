<?php
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
include "header.php";
require_once 'classes/Database.php';
require_once 'classes/Language.php';

Database::initialize();

$meldung = '';
$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($email)) {
        $fehler = Language::get('fill_all_fields');
    } else {
        $stmt = Database::$conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $fehler = Language::get('username_taken');
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $insert = Database::$conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            if ($insert->execute([$username, $hashed, $email])) {
                $meldung = Language::get('registration_success');
            } else {
                $fehler = Language::get('insert_error');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'de' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Language::get('register') ?></title>
</head>
<body>
<h1><?= Language::get('register') ?></h1>

<?php if ($meldung): ?>
  <script>alert("<?= addslashes($meldung) ?>");</script>
<?php endif; ?>

<?php if ($fehler): ?>
  <script>alert("⚠️ <?= addslashes($fehler) ?>");</script>
<?php endif; ?>


<form method="post">
    <label><?= Language::get('username') ?>: <input type="text" name="username" required></label><br>
    <label><?= Language::get('password') ?>: <input type="password" name="password" required></label><br>
    <label><?= Language::get('email') ?>: <input type="email" name="email" required></label><br><br>
    <button type="submit"><?= Language::get('register') ?></button>
</form>

<p><a href="login.php"><?= Language::get('back_to_login') ?></a></p>
</body>
</html>
