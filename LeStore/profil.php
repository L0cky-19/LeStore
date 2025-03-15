<?php
session_start();
include 'includes/db.php'; // Connexion à la base de données
include 'includes/header.php'; // Header commun

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Récupération des informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Gestion de la modification de l'identifiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouvel_identifiant'])) {
    $nouvel_identifiant = trim($_POST['nouvel_identifiant']);
    if (!empty($nouvel_identifiant)) {
        $stmt = $conn->prepare("UPDATE Utilisateur SET identifiant = :nouvel_identifiant WHERE id_utilisateur = :id_utilisateur");
        try {
            $stmt->execute([
                'nouvel_identifiant' => $nouvel_identifiant,
                'id_utilisateur' => $user_id
            ]);
            $message_identifiant = "<span class='success'>Identifiant modifié avec succès.</span>";
        } catch (PDOException $e) {
            $message_identifiant = "<span class='error'>Erreur : Cet identifiant est déjà utilisé.</span>";
        }
    } else {
        $message_identifiant = "<span class='error'>L'identifiant ne peut pas être vide.</span>";
    }
}

// Gestion de la modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ancien_mot_de_passe'], $_POST['nouveau_mot_de_passe'])) {
    $ancien_mot_de_passe = trim($_POST['ancien_mot_de_passe']);
    $nouveau_mot_de_passe = trim($_POST['nouveau_mot_de_passe']);

    if (!empty($ancien_mot_de_passe) && !empty($nouveau_mot_de_passe)) {
        // Récupérer le mot de passe actuel depuis la base de données
        $stmt = $conn->prepare("SELECT mot_de_passe FROM Utilisateur WHERE id_utilisateur = :id_utilisateur");
        $stmt->execute(['id_utilisateur' => $user_id]);
        $user = $stmt->fetch();

        // Vérification de l'ancien mot de passe
        if (password_verify($ancien_mot_de_passe, $user['mot_de_passe'])) {
            // Hachage du nouveau mot de passe
            $nouveau_mot_de_passe_hache = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);

            // Mise à jour du mot de passe
            $stmt = $conn->prepare("UPDATE Utilisateur SET mot_de_passe = :nouveau_mot_de_passe WHERE id_utilisateur = :id_utilisateur");
            $stmt->execute([
                'nouveau_mot_de_passe' => $nouveau_mot_de_passe_hache,
                'id_utilisateur' => $user_id
            ]);

            $message_mot_de_passe = "<span class='success'>Mot de passe modifié avec succès.</span>";
        } else {
            $message_mot_de_passe = "<span class='error'>Erreur : L'ancien mot de passe est incorrect.</span>";
        }
    } else {
        $message_mot_de_passe = "<span class='error'>Tous les champs sont obligatoires.</span>";
    }
}

// Récupération des informations de l'utilisateur
$stmt = $conn->prepare("SELECT nom, prenom, identifiant, montant_ventes, montant_benefices FROM Utilisateur WHERE id_utilisateur = :id_utilisateur");
$stmt->execute(['id_utilisateur' => $user_id]);
$user = $stmt->fetch();

// Récupération du nombre total de ventes pour l'utilisateur
$stmt = $conn->prepare("SELECT COUNT(Vente.id_vente) AS total_ventes FROM Utilisateur_Vente JOIN Vente ON Utilisateur_Vente.id_vente = Vente.id_vente WHERE Utilisateur_Vente.id_utilisateur = :id_utilisateur");
$stmt->execute(['id_utilisateur' => $user_id]);
$total_ventes = $stmt->fetchColumn();

// Récupération de la page actuelle (par défaut : 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$ventes_par_page = 10; // Nombre de ventes par page
$offset = ($page - 1) * $ventes_par_page; // Calcul de l'offset

// Récupération de l'historique des ventes avec limite et offset
$stmt = $conn->prepare("
    SELECT Vente.id_vente, Vente.date_vente, Vente.montant_total
    FROM Utilisateur_Vente
    JOIN Vente ON Utilisateur_Vente.id_vente = Vente.id_vente
    WHERE Utilisateur_Vente.id_utilisateur = :id_utilisateur
    ORDER BY Vente.date_vente DESC
    LIMIT :offset, :limit
");
$stmt->bindValue(':id_utilisateur', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $ventes_par_page, PDO::PARAM_INT);
$stmt->execute();
$ventes = $stmt->fetchAll();

// Calcul du nombre total de pages
$total_pages = ceil($total_ventes / $ventes_par_page);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/profil.css">
</head>
<body>
    <h1>Profil</h1>

    <!-- Affichage des informations de l'utilisateur -->
    <div class="profil-infos">
        <h2>Informations personnelles</h2>
        <p><strong>Nom :</strong> <?= htmlspecialchars($user['nom']) ?></p>
        <p><strong>Prénom :</strong> <?= htmlspecialchars($user['prenom']) ?></p>
        <p><strong>Identifiant :</strong> <?= htmlspecialchars($user['identifiant']) ?></p>

        <!-- Formulaire pour modifier l'identifiant -->
        <h3>Modifier votre identifiant</h3>
        <?php if (isset($message_identifiant)): ?>
            <p class="message"><?= $message_identifiant ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="nouvel_identifiant">Nouvel identifiant :</label>
            <input type="text" name="nouvel_identifiant" id="nouvel_identifiant" required>
            <button type="submit">Modifier</button>
        </form>

        <!-- Formulaire pour modifier le mot de passe -->
        <h3>Modifier votre mot de passe</h3>
        <?php if (isset($message_mot_de_passe)): ?>
            <p class="message"><?= $message_mot_de_passe ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="ancien_mot_de_passe">Ancien mot de passe :</label>
            <input type="password" name="ancien_mot_de_passe" id="ancien_mot_de_passe" required>
            
            <label for="nouveau_mot_de_passe">Nouveau mot de passe :</label>
            <input type="password" name="nouveau_mot_de_passe" id="nouveau_mot_de_passe" required>
            
            <button type="submit">Modifier</button>
        </form>
    </div>

    <!-- Historique des ventes -->
    <div class="historique-ventes">
        <h2>Historique des ventes</h2>
        <p><strong>Total des ventes :</strong> <?= $total_ventes ?> ventes</p>
        <p><strong>Montant total des ventes :</strong> <?= number_format($user['montant_ventes'], 2, ',', ' ') ?> €</p>
        <p><strong>Bénéfice total :</strong> <?= number_format($user['montant_benefices'], 2, ',', ' ') ?> €</p>

        <?php if (count($ventes) > 0): ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>ID Vente</th>
                        <th>Date</th>
                        <th>Montant Total (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventes as $vente): ?>
                        <tr>
                            <td><?= htmlspecialchars($vente['id_vente']) ?></td>
                            <td><?= htmlspecialchars($vente['date_vente']) ?></td>
                            <td><?= htmlspecialchars(number_format($vente['montant_total'], 2, ',', ' ')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">Précédent</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Suivant</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>Aucune vente enregistrée.</p>
        <?php endif; ?>
    </div>
</body>
</html>
