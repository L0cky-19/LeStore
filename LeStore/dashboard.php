<?php
session_start();
include 'includes/db.php'; // Connexion à la base de données

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Récupération des informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nom, prenom FROM Utilisateur WHERE id_utilisateur = :id_utilisateur");
$stmt->execute(['id_utilisateur' => $user_id]);
$user = $stmt->fetch();

// Si l'utilisateur n'est pas trouvé (cas improbable), rediriger vers la page de connexion
if (!$user) {
    header("Location: index.php");
    exit;
}

// Récupération du nom complet de l'utilisateur
$user_name = htmlspecialchars($user['prenom'] . ' ' . $user['nom']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <h1>Bienvenue, <?php echo $user_name; ?> !</h1>
        <p>Que souhaitez-vous faire aujourd'hui ?</p>

        <div class="dashboard-actions">
            <a href="produits.php" class="dashboard-button">
                <img src="images/stock.png" alt="Stock">
                <span>Stock disponible</span>
            </a>
            <a href="ventes.php" class="dashboard-button">
                <img src="images/vente.png" alt="Nouvelle vente">
                <span>Faire une nouvelle vente</span>
            </a>
            <a href="profil.php" class="dashboard-button">
                <img src="images/profil.png" alt="Profil">
                <span>Consulter mon profil</span>
            </a>
            <a href="logout.php" class="dashboard-button">
                <img src="images/logout.png" alt="Déconnexion">
                <span>Se déconnecter</span>
            </a>
        </div>
    </div>
</body>
</html>
