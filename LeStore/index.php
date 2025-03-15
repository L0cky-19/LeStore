<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifiant = $_POST['identifiant'];
    $mot_de_passe = $_POST['mot_de_passe'];

    // Récupération des informations de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM Utilisateur WHERE identifiant = :identifiant");
    $stmt->execute(['identifiant' => $identifiant]);
    $user = $stmt->fetch();

    if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
        // Si le mot de passe est correct
        $_SESSION['user_id'] = $user['id_utilisateur'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_role'] = $user['role'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiant ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <h1>Le Store</h1>
    <!-- Inclusion du style -->
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/connexion.css">

</head>
<body>
    <div class="login-container">
    <h1>Connexion</h1>
    <?php if (isset($error)) echo "<p class='error-message'>$error</p>"; ?>
    <form method="POST" action="">
        <input type="text" name="identifiant" placeholder="Identifiant" required>
        <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
        <p>Pas encore de compte ? Contacte <b>Lucas</b> ou <b>Selena</b>.</p>
    </form>
</div>

</body>
</html>
