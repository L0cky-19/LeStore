<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
include 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord</title>
    <link rel="stylesheet" href="css/global.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <h1>Bienvenue, <?php echo $_SESSION['user_prenom']; ?> !</h1>
        <div class="dashboard-links">
            <a href="produits.php">Gestion des produits</a>
            <a href="ventes.php">Gestion des ventes</a>
            <?php if ($_SESSION['user_role'] == 'admin'): ?>
                <a href="utilisateurs.php">Gestion des utilisateurs</a>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
