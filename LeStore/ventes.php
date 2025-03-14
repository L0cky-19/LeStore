<?php
// Démarrer la session
session_start();

// Inclure le fichier de connexion à la base de données
include 'includes/db2.php';
include 'includes/header.php';

// Vérifier si la connexion à la base de données est établie

// Récupération des produits et des utilisateurs depuis la base de données
try {
    $produits = $db->query("SELECT * FROM Produits")->fetchAll(PDO::FETCH_ASSOC);
    $utilisateurs = $db->query("SELECT * FROM Utilisateur")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}

// Initialiser le panier dans la session si ce n'est pas déjà fait
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Ajouter un produit au panier
if (isset($_GET['ajouter_produit'])) {
    $idProduit = (int)$_GET['ajouter_produit'];
    if (!isset($_SESSION['panier'][$idProduit])) {
        $_SESSION['panier'][$idProduit] = 1; // Initialiser avec une quantité de 1
    } else {
        $_SESSION['panier'][$idProduit]++; // Augmenter la quantité
    }
    header('Location: ventes.php');
    exit;
}

// Supprimer un produit du panier
if (isset($_GET['supprimer_produit'])) {
    $idProduit = (int)$_GET['supprimer_produit'];
    unset($_SESSION['panier'][$idProduit]);
    header('Location: ventes.php');
    exit;
}

// Vider le panier
if (isset($_GET['vider_panier'])) {
    $_SESSION['panier'] = [];
    header('Location: ventes.php');
    exit;
}

// Mettre à jour les quantités dans le panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_panier'])) {
    foreach ($_POST['quantites'] as $idProduit => $quantite) {
        if ($quantite > 0) {
            $_SESSION['panier'][$idProduit] = $quantite;
        } else {
            unset($_SESSION['panier'][$idProduit]); // Supprimer le produit si la quantité est 0
        }
    }
    header('Location: ventes.php');
    exit;
}

// Calculer le montant total
$montantTotal = 0;
foreach ($_SESSION['panier'] as $idProduit => $quantite) {
    foreach ($produits as $produit) {
        if ($produit['id_produit'] == $idProduit) {
            $montantTotal += $produit['prix_unitaire'] * $quantite;
        }
    }
}

// Finaliser la vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finaliser_vente'])) {
    $utilisateursSelectionnes = $_POST['utilisateurs'] ?? [];

    if (empty($utilisateursSelectionnes)) {
        echo "<p>Erreur : Aucun utilisateur sélectionné pour la vente.</p>";
    } else {
        try {
            // Démarrer une transaction
            $db->beginTransaction();

            // Insérer la vente dans la table "Vente"
            $stmt = $db->prepare("INSERT INTO Vente (montant_total) VALUES (:montant_total)");
            $stmt->execute(['montant_total' => $montantTotal]);
            $idVente = $db->lastInsertId();

            // Associer les produits à la vente et mettre à jour le stock
            foreach ($_SESSION['panier'] as $idProduit => $quantite) {
                $stmt = $db->prepare("SELECT prix_unitaire FROM Produits WHERE id_produit = :id_produit");
                $stmt->execute(['id_produit' => $idProduit]);
                $prixUnitaire = $stmt->fetchColumn();

                $sousTotal = $prixUnitaire * $quantite;

                $stmt = $db->prepare("INSERT INTO Detail_Vente (id_vente, id_produit, quantite, sous_total) VALUES (:id_vente, :id_produit, :quantite, :sous_total)");
                $stmt->execute([
                    'id_vente' => $idVente,
                    'id_produit' => $idProduit,
                    'quantite' => $quantite,
                    'sous_total' => $sousTotal
                ]);

                $stmt = $db->prepare("UPDATE Produits SET quantite_stock = quantite_stock - :quantite WHERE id_produit = :id_produit");
                $stmt->execute(['quantite' => $quantite, 'id_produit' => $idProduit]);
            }

            // Associer les utilisateurs à la vente
            $pourcentageParticipation = 100 / count($utilisateursSelectionnes);
            foreach ($utilisateursSelectionnes as $idUtilisateur) {
                $stmt = $db->prepare("INSERT INTO Utilisateur_Vente (id_utilisateur, id_vente, pourcentage_participation) VALUES (:id_utilisateur, :id_vente, :pourcentage_participation)");
                $stmt->execute([
                    'id_utilisateur' => $idUtilisateur,
                    'id_vente' => $idVente,
                    'pourcentage_participation' => $pourcentageParticipation
                ]);
            }

            // Ajouter le montant total à la caisse
            $stmt = $db->prepare("UPDATE Caisse SET montant_total = montant_total + :montant_total WHERE id_caisse = 1");
            $stmt->execute(['montant_total' => $montantTotal]);

            $db->commit();
            $_SESSION['panier'] = []; // Vider le panier après la vente
            echo "<p>La vente a été enregistrée avec succès !</p>";
        } catch (Exception $e) {
            $db->rollBack();
            echo "<p>Erreur : " . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Effectuer une Vente</title>
    <link rel="stylesheet" href="CSS/ventes.css">
</head>
<body>
    <div class="container-ventes">
        <h1>Effectuer une Vente</h1>

        <h2>Produits Disponibles</h2>
        <?php foreach ($produits as $produit): ?>
            <div class="produit">
                <a href="?ajouter_produit=<?= $produit['id_produit'] ?>">
                    <?= htmlspecialchars($produit['nom_produit']) ?> (Prix : <?= $produit['prix_unitaire'] ?> € | Stock : <?= $produit['quantite_stock'] ?>)
                </a>
            </div>
        <?php endforeach; ?>

        <h2>Panier</h2>
        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix Unitaire</th>
                        <th>Sous-Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['panier'] as $idProduit => $quantite): ?>
                        <?php foreach ($produits as $produit): ?>
                            <?php if ($produit['id_produit'] == $idProduit): ?>
                                <tr>
                                    <td><?= htmlspecialchars($produit['nom_produit']) ?></td>
                                    <td>
                                        <input type="number" name="quantites[<?= $idProduit ?>]" value="<?= $quantite ?>" min="0">
                                    </td>
                                    <td><?= $produit['prix_unitaire'] ?> €</td>
                                    <td><?= $produit['prix_unitaire'] * $quantite ?> €</td>
                                    <td>
                                        <a href="?supprimer_produit=<?= $idProduit ?>">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="update_panier">Mettre à jour le Panier</button>
        </form>
        <a href="?vider_panier" class="btn-danger">Vider le Panier</a>

        <h2>Montant Total : <?= $montantTotal ?> €</h2>

        <h2>Sélectionner les Utilisateurs</h2>
        <form method="POST">
            <select name="utilisateurs[]" multiple>
                <?php foreach ($utilisateurs as $utilisateur): ?>
                    <option value="<?= $utilisateur['id_utilisateur'] ?>">
                        <?= htmlspecialchars($utilisateur['nom'] . ' ' . $utilisateur['prenom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="finaliser_vente">Vendre</button>
        </form>
    </div>
</body>
</html>
