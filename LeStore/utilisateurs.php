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
    $mot_de_passe = htmlspecialchars($_POST['mot_de_passe']);

    if (!empty($nom) && !empty($prenom) && !empty($identifiant) && !empty($role) && !empty($mot_de_passe)) {
        $stmt = $conn->prepare("INSERT INTO Utilisateur (nom, prenom, mot_de_passe, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nom, $prenom, password_hash($mot_de_passe, PASSWORD_DEFAULT), $role]);
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

// Récupération des utilisateurs avec leur bénéfice total
$stmt = $conn->query("
    SELECT 
        id_utilisateur, 
        nom, 
        prenom, 
        role, 
        montant_ventes, 
        montant_benefices AS benefice
    FROM Utilisateur
");
$utilisateurs = $stmt->fetchAll();

// Récupération des données de la caisse
$stmt = $conn->query("SELECT montant_total, benefice_total FROM Caisse WHERE id_caisse = 1");
$caisse = $stmt->fetch();

// Gestion de la pagination pour l'historique des ventes
$ventes_par_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // S'assurer que la page est au moins 1
$offset = ($page - 1) * $ventes_par_page;

// Récupération de l'historique des ventes avec les produits, quantités, utilisateurs et bénéfice total
$stmt = $conn->prepare("
    SELECT 
        Vente.id_vente, 
        Vente.date_vente, 
        Vente.montant_total,
        Vente.benefice_total AS benefice,
        GROUP_CONCAT(DISTINCT CONCAT(Produits.nom_produit, ' (', Detail_Vente.quantite, ')') SEPARATOR ', ') AS produits,
        GROUP_CONCAT(DISTINCT CONCAT(Utilisateur.nom, ' ', Utilisateur.prenom) SEPARATOR ', ') AS utilisateurs
    FROM Vente
    LEFT JOIN Utilisateur_Vente ON Vente.id_vente = Utilisateur_Vente.id_vente
    LEFT JOIN Utilisateur ON Utilisateur_Vente.id_utilisateur = Utilisateur.id_utilisateur
    LEFT JOIN Detail_Vente ON Vente.id_vente = Detail_Vente.id_vente
    LEFT JOIN Produits ON Detail_Vente.id_produit = Produits.id_produit
    GROUP BY Vente.id_vente
    ORDER BY Vente.date_vente DESC
    LIMIT :offset, :limit
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $ventes_par_page, PDO::PARAM_INT);
$stmt->execute();
$ventes = $stmt->fetchAll();

// Récupération du nombre total de ventes pour la pagination
$stmt = $conn->query("SELECT COUNT(*) AS total_ventes FROM Vente");
$total_ventes = $stmt->fetch()['total_ventes'];
$total_pages = ceil($total_ventes / $ventes_par_page);
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
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.querySelector('.form-ajout-utilisateur');
            const toggleButton = document.querySelector('.toggle-button');
            form.style.display = 'none';
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

        <button class="toggle-button">Ajouter un utilisateur</button>

        <form action="utilisateurs.php" method="POST" class="form-ajout-utilisateur">
            <h2>Ajouter un utilisateur</h2>
            <label for="nom">Nom :</label>
            <input type="text" id="nom" name="nom" required>

            <label for="prenom">Prénom :</label>
            <input type="text" id="prenom" name="prenom" required>

            <label for="identifiant">Identifiant :</label>
            <input type="text" id="identifiant" name="identifiant" required>

            <label for="mot_de_passe">Mot de passe :</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            
            <label for="role">Rôle :</label>
            <select id="role" name="role" required>
                <option value="utilisateur">Utilisateur</option>
                <option value="admin">Admin</option>
            </select>

            <button type="submit" name="ajouter_utilisateur">Ajouter</button>
        </form>

        <table class="table-utilisateurs">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Rôle</th>
                    <th>Total des ventes (€)</th>
                    <th>Bénéfice (€)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $user) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id_utilisateur']); ?></td>
                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                        <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo number_format($user['montant_ventes'], 2, ',', ' '); ?> €</td>
                        <td><?php echo number_format($user['benefice'], 2, ',', ' '); ?> €</td>
                        <td>
                            <a href="utilisateurs.php?supprimer_id=<?php echo $user['id_utilisateur']; ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                <button class="delete">Supprimer</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Caisse Total</h2>
        <div class="caisse-total">
            <p><strong>Montant Total des Ventes :</strong> <?php echo number_format($caisse['montant_total'], 2, ',', ' '); ?> €</p>
            <p><strong>Bénéfice Total :</strong> <?php echo number_format($caisse['benefice_total'], 2, ',', ' '); ?> €</p>
        </div>

        <h2>Historique des ventes</h2>
        <table class="table-ventes">
            <thead>
                <tr>
                    <th>ID Vente</th>
                    <th>Date</th>
                    <th>Montant Total (€)</th>
                    <th>Bénéfice (€)</th>
                    <th>Produits (Quantité)</th>
                    <th>Utilisateurs impliqués</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventes as $vente) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vente['id_vente']); ?></td>
                        <td><?php echo htmlspecialchars($vente['date_vente']); ?></td>
                        <td><?php echo number_format($vente['montant_total'], 2, ',', ' '); ?> €</td>
                        <td><?php echo number_format($vente['benefice'], 2, ',', ' '); ?> €</td>
                        <td><?php echo htmlspecialchars($vente['produits']); ?></td>
                        <td><?php echo htmlspecialchars($vente['utilisateurs']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="utilisateurs.php?page=<?php echo $page - 1; ?>">Précédent</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="utilisateurs.php?page=<?php echo $i; ?>" <?php if ($i === $page) echo 'class="active"'; ?>>
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="utilisateurs.php?page=<?php echo $page + 1; ?>">Suivant</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
