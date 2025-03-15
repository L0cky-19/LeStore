<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Vérifier si l'utilisateur est admin
$isAdmin = ($_SESSION['user_role'] === 'admin');

// Variable pour afficher ou cacher le formulaire d'ajout
$afficherFormulaire = isset($_GET['ajouter']);

// Gestion des actions (ajout, modification de quantité, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $produit_id = $_POST['produit_id'] ?? null;

        if ($action === 'ajouter') {
            // Ajouter un produit
            $nom_produit = $_POST['nom_produit'];
            $quantite_stock = $_POST['quantite_stock'];
            $prix_vente = $_POST['prix_vente'];
            $prix_achat = $_POST['prix_achat']; // Nouveau champ pour le prix d'achat
            $categorie = $_POST['categorie']; // Nouveau champ pour la catégorie
            $description = $_POST['description'] ?? null;

            $stmt = $conn->prepare("INSERT INTO Produits (nom_produit, description, prix_vente, prix_achat, quantite_stock, categorie) VALUES (:nom_produit, :description, :prix_vente, :prix_achat, :quantite_stock, :categorie)");
            $stmt->execute([
                'nom_produit' => $nom_produit,
                'description' => $description,
                'prix_vente' => $prix_vente,
                'prix_achat' => $prix_achat,
                'quantite_stock' => $quantite_stock,
                'categorie' => $categorie
            ]);
            // Redirection pour éviter la soumission multiple
            header("Location: produits.php");
            exit;
        } elseif ($action === 'modifier_quantite' && $produit_id) {
            // Ajouter ou retirer une quantité
            $quantite_ajoutee = (int)$_POST['quantite_ajoutee'];

            // Vérifier que le stock ne devient pas négatif
            $stmt = $conn->prepare("SELECT quantite_stock FROM Produits WHERE id_produit = :id_produit");
            $stmt->execute(['id_produit' => $produit_id]);
            $quantite_actuelle = $stmt->fetchColumn();

            if ($quantite_actuelle + $quantite_ajoutee >= 0) {
                $stmt = $conn->prepare("UPDATE Produits SET quantite_stock = quantite_stock + :quantite_ajoutee WHERE id_produit = :id_produit");
                $stmt->execute([
                    'quantite_ajoutee' => $quantite_ajoutee,
                    'id_produit' => $produit_id
                ]);
            } else {
                $message = "Erreur : La quantité ne peut pas être négative.";
            }
        } elseif ($action === 'supprimer' && $produit_id) {
            // Supprimer un produit
            $stmt = $conn->prepare("DELETE FROM Produits WHERE id_produit = :id_produit");
            $stmt->execute(['id_produit' => $produit_id]);
        }
    }
}

// Récupérer les produits disponibles
$stmt = $conn->prepare("SELECT * FROM Produits WHERE quantite_stock > 0");
$stmt->execute();
$produits_disponibles = $stmt->fetchAll();

// Récupérer les produits épuisés
$stmt = $conn->prepare("SELECT * FROM Produits WHERE quantite_stock = 0");
$stmt->execute();
$produits_epuises = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/produits.css">
</head>
<body>
    <h1>Gestion des Produits</h1>

    <?php if ($isAdmin): ?>
        <!-- Bouton pour afficher ou cacher le formulaire d'ajout -->
        <h2>Ajouter un produit</h2>
        <a href="produits.php<?= $afficherFormulaire ? '' : '?ajouter=1' ?>" class="btn-primary">
            <?= $afficherFormulaire ? 'Fermer' : 'Ajouter un produit' ?>
        </a>

        <?php if ($afficherFormulaire): ?>
            <!-- Formulaire pour ajouter un produit -->
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <input type="text" name="nom_produit" placeholder="Nom du produit" required>
                <input type="number" name="quantite_stock" placeholder="Quantité en stock" min="0" required>
                <input type="number" step="0.01" name="prix_vente" placeholder="Prix de vente (€)" min="0" required>
                <input type="number" step="0.01" name="prix_achat" placeholder="Prix d'achat (€)" min="0" required> <!-- Nouveau champ -->
                <select name="categorie" required> <!-- Nouveau champ -->
                    <option value="" disabled selected>Choisir une catégorie</option>
                    <option value="nourriture salée">Nourriture salée</option>
                    <option value="nourriture sucrée">Nourriture sucrée</option>
                    <option value="boisson">Boisson</option>
                </select>
                <textarea name="description" placeholder="Description du produit (facultatif)"></textarea>
                <button type="submit" class="btn-primary">Ajouter</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Liste des produits disponibles -->
    <h2>Liste des produits disponibles</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Quantité en Stock</th>
                <th>Prix Unitaire (€)</th>
                <th>Description</th>
                <?php if ($isAdmin): ?>
                    <th>Modifier Quantité</th>
                    <th>Supprimer</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produits_disponibles as $produit): ?>
                <tr>
                    <td><?= htmlspecialchars($produit['nom_produit']) ?></td>
                    <td><?= htmlspecialchars($produit['quantite_stock']) ?></td>
                    <td><?= htmlspecialchars(number_format($produit['prix_vente'], 2)) ?></td>
                    <td><?= htmlspecialchars($produit['description'] ?? 'N/A') ?></td>
                    <?php if ($isAdmin): ?>
                        <!-- Colonne pour modifier la quantité -->
                        <td>
                            <form method="POST">
                                <input type="hidden" name="produit_id" value="<?= $produit['id_produit'] ?>">
                                <input type="hidden" name="action" value="modifier_quantite">
                                <input type="number" name="quantite_ajoutee" placeholder="Ajout/Retrait" required>
                                <button type="submit">✔️</button>
                            </form>
                        </td>
                        <!-- Colonne pour supprimer le produit -->
                        <td>
                            <form method="POST">
                                <input type="hidden" name="produit_id" value="<?= $produit['id_produit'] ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <button type="submit">🗑️</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Liste des produits épuisés -->
    <h2>Liste des produits épuisés</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Quantité en Stock</th>
                <th>Prix Unitaire (€)</th>
                <th>Description</th>
                <?php if ($isAdmin): ?>
                    <th>Restocker</th>
                    <th>Supprimer</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produits_epuises as $produit): ?>
                <tr>
                    <td><?= htmlspecialchars($produit['nom_produit']) ?></td>
                    <td><?= htmlspecialchars($produit['quantite_stock']) ?></td>
                    <td><?= htmlspecialchars(number_format($produit['prix_vente'], 2)) ?></td>
                    <td><?= htmlspecialchars($produit['description'] ?? 'N/A') ?></td>
                    <?php if ($isAdmin): ?>
                        <!-- Colonne pour restocker le produit -->
                        <td>
                            <form method="POST">
                                <input type="hidden" name="produit_id" value="<?= $produit['id_produit'] ?>">
                                <input type="hidden" name="action" value="modifier_quantite">
                                <input type="number" name="quantite_ajoutee" placeholder="Quantité à ajouter" min="1" required>
                                <button type="submit">Restocker</button>
                            </form>
                        </td>
                        <!-- Colonne pour supprimer le produit -->
                        <td>
                            <form method="POST">
                                <input type="hidden" name="produit_id" value="<?= $produit['id_produit'] ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <button type="submit">🗑️</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
