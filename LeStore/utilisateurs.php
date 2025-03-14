<?php
session_start();
include 'includes/db.php';

// Vérification si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Vérification si l'utilisateur est un administrateur
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Gestion de l'ajout d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_utilisateur'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $prenom = htmlspecialchars($_POST['prenom']);
    $identifiant = htmlspecialchars($_POST['identifiant']);
    $role = htmlspecialchars($_POST['role']);

    if (!empty($nom) && !empty($prenom) && !empty($identifiant) && !empty($role)) {
        $stmt = $conn->prepare("INSERT INTO Utilisateur (nom, prenom, mot_de_passe, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nom, $prenom, password_hash('default_password', PASSWORD_DEFAULT), $role]);
        $message = "Utilisateur ajouté avec succès.";
    } else {
        $message = "Veuillez remplir tous les champs.";
    }
}

// Gestion de la suppression d'un utilisateur
if (isset($_GET['supprimer_id'])) {
    $id = (int) $_GET['supprimer_id'];
    $stmt = $conn->prepare("DELETE FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    $message = "Utilisateur supprimé avec succès.";
}

// Récupération des utilisateurs
$stmt = $conn->query("
    SELECT 
        id_utilisateur, 
        nom, 
        prenom, 
        identifiant,
        role, 
        montant_ventes
    FROM Utilisateur
");
$utilisateurs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/utilisateurs.css">
    


    <script>
        // Script pour afficher/masquer le formulaire d'ajout
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.querySelector('.form-ajout-utilisateur');
            const toggleButton = document.querySelector('.toggle-button');

            // Assurez-vous que le formulaire est caché au chargement
            form.style.display = 'none';

            // Ajoutez un événement pour afficher/masquer le formulaire
            toggleButton.addEventListener('click', function () {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            });
        });
    </script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>Liste des utilisateurs</h1>

        <?php if (isset($message)) : ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <!-- Bouton pour afficher/masquer le formulaire -->
        <button class="toggle-button">Ajouter un utilisateur</button>

        <!-- Formulaire pour ajouter un utilisateur -->
        <form action="utilisateurs.php" method="POST" class="form-ajout-utilisateur">
            <h2>Ajouter un utilisateur</h2>
            <label for="nom">Nom :</label>
            <input type="text" id="nom" name="nom" required>

            <label for="prenom">Prénom :</label>
            <input type="text" id="prenom" name="prenom" required>

            <label for="identifiant">Identifiant :</label>
            <input type="text" id="identifiant" name="identifiant" required>

            <label for="role">Rôle :</label>
            <select id="role" name="role" required>
                <option value="utilisateur">Utilisateur</option>
                <option value="admin">Admin</option>
            </select>

            <button type="submit" name="ajouter_utilisateur">Ajouter</button>
        </form>

        <!-- Tableau des utilisateurs -->
        <table class="table-utilisateurs">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Identifiant</th>
                    <th>Rôle</th>
                    <th>Total des ventes (€)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $user) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id_utilisateur']); ?></td>
                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                        <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                        <td><?php echo htmlspecialchars($user['identifiant']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo number_format($user['montant_ventes'], 2, ',', ' '); ?> €</td>
                        <td>
                            <a href="utilisateurs.php?supprimer_id=<?php echo $user['id_utilisateur']; ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                <button class="delete">Supprimer</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>