<?php
// Démarrer la session
session_start();

// Inclure le fichier de connexion à la base de données
include 'includes/db2.php';
include 'includes/header.php';

// Récupération des produits et des utilisateurs depuis la base de données
try {
    // Ne récupérer que les produits dont la quantité en stock est supérieure à 0
    $produits = $db->query("SELECT * FROM Produits WHERE quantite_stock > 0")->fetchAll(PDO::FETCH_ASSOC);
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

    // Vérifier si le produit existe et s'il est en stock
    foreach ($produits as $produit) {
        if ($produit['id_produit'] == $idProduit && $produit['quantite_stock'] > 0) {
            if (!isset($_SESSION['panier'][$idProduit])) {
                $_SESSION['panier'][$idProduit] = 1; // Initialiser avec une quantité de 1
            } else {
                // Vérifier que la quantité ajoutée ne dépasse pas le stock disponible
                if ($_SESSION['panier'][$idProduit] < $produit['quantite_stock']) {
                    $_SESSION['panier'][$idProduit]++;
                }
            }
            break;
        }
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
        foreach ($produits as $produit) {
            if ($produit['id_produit'] == $idProduit) {
                // Vérifier que la quantité demandée ne dépasse pas le stock disponible
                if ($quantite > 0 && $quantite <= $produit['quantite_stock']) {
                    $_SESSION['panier'][$idProduit] = $quantite;
                } else {
                    unset($_SESSION['panier'][$idProduit]); // Supprimer le produit si la quantité est invalide
                }
                break;
            }
        }
    }
    header('Location: ventes.php');
    exit;
}

// Calculer le montant total
$montantTotal = 0;
$beneficeTotal = 0; // Nouveau : calcul du bénéfice total
foreach ($_SESSION['panier'] as $idProduit => $quantite) {
    foreach ($produits as $produit) {
        if ($produit['id_produit'] == $idProduit) {
            $montantTotal += $produit['prix_vente'] * $quantite;
            $beneficeTotal += ($produit['prix_vente'] - $produit['prix_achat']) * $quantite; // Calcul du bénéfice
        }
    }
}

// Finaliser la vente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finaliser_vente'])) {
    $utilisateursSelectionnes = $_POST['utilisateurs'] ?? [];

    if (empty($utilisateursSelectionnes)) {
        $messageError = "Erreur : Aucun utilisateur sélectionné pour la vente.";
    } else {
        try {
            // Démarrer une transaction
            $db->beginTransaction();

            // Insérer la vente dans la table "Vente"
            $stmt = $db->prepare("INSERT INTO Vente (montant_total, benefice_total) VALUES (:montant_total, :benefice_total)");
            $stmt->execute(['montant_total' => $montantTotal,'benefice_total' => $beneficeTotal]);
            $idVente = $db->lastInsertId();


            // Associer les produits à la vente et mettre à jour le stock
            foreach ($_SESSION['panier'] as $idProduit => $quantite) {
                $stmt = $db->prepare("SELECT prix_vente, quantite_stock FROM Produits WHERE id_produit = :id_produit");
                $stmt->execute(['id_produit' => $idProduit]);
                $produit = $stmt->fetch(PDO::FETCH_ASSOC);

                // Vérifier que le stock est suffisant avant de finaliser la vente
                if ($produit['quantite_stock'] < $quantite) {
                    throw new Exception("Stock insuffisant pour le produit : " . $idProduit);
                }

                $sousTotal = $produit['prix_vente'] * $quantite;

                $stmt = $db->prepare("INSERT INTO Detail_Vente (id_vente, id_produit, quantite, sous_total, benefice) VALUES (:id_vente, :id_produit, :quantite, :sous_total, :benefice)");
                $stmt->execute([
                    'id_vente' => $idVente,
                    'id_produit' => $idProduit,
                    'quantite' => $quantite,
                    'sous_total' => $sousTotal,
                    'benefice' => $beneficeTotal
                ]);

                $stmt = $db->prepare("UPDATE Produits SET quantite_stock = quantite_stock - :quantite WHERE id_produit = :id_produit");
                $stmt->execute(['quantite' => $quantite, 'id_produit' => $idProduit]);
            }

            // Associer les utilisateurs à la vente et mettre à jour leur montant_ventes et montant_benefice
            $pourcentageParticipation = 100 / count($utilisateursSelectionnes);
            foreach ($utilisateursSelectionnes as $idUtilisateur) {
                // Insérer dans la table Utilisateur_Vente
                $stmt = $db->prepare("INSERT INTO Utilisateur_Vente (id_utilisateur, id_vente, pourcentage_participation) VALUES (:id_utilisateur, :id_vente, :pourcentage_participation)");
                $stmt->execute([
                    'id_utilisateur' => $idUtilisateur,
                    'id_vente' => $idVente,
                    'pourcentage_participation' => $pourcentageParticipation
                ]);

                // Mettre à jour le montant_ventes et le montant_benefice de chaque utilisateur
                $montantUtilisateur = ($montantTotal * $pourcentageParticipation) / 100;
                $beneficeUtilisateur = ($beneficeTotal * $pourcentageParticipation) / 100;

                $stmt = $db->prepare("
                    UPDATE Utilisateur 
                    SET montant_ventes = montant_ventes + :montant_ventes, 
                        montant_benefices = montant_benefices + :montant_benefices
                    WHERE id_utilisateur = :id_utilisateur
                ");
                $stmt->execute([
                    'montant_ventes' => $montantUtilisateur,
                    'montant_benefices' => $beneficeUtilisateur,
                    'id_utilisateur' => $idUtilisateur
                ]);
            }

            // Ajouter le montant total et le bénéfice total à la caisse
            $stmt = $db->prepare("UPDATE Caisse SET montant_total = montant_total + :montant_total, benefice_total = benefice_total + :benefice_total WHERE id_caisse = 1");
            $stmt->execute(['montant_total' => $montantTotal,'benefice_total' => $beneficeTotal]);


            $db->commit();
            $_SESSION['panier'] = []; // Vider le panier après la vente
            $messageSuccess = "La vente a été enregistrée avec succès !";
        } catch (Exception $e) {
            $db->rollBack();
            $messageError = "Erreur : " . $e->getMessage();
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

        <!-- Messages -->
        <?php if (isset($messageSuccess)): ?>
            <div class="message success"><?= $messageSuccess ?></div>
        <?php endif; ?>

        <?php if (isset($messageError)): ?>
            <div class="message error"><?= $messageError ?></div>
        <?php endif; ?>

        <h2>Produits Disponibles</h2>
        <div class="produits-categories">
            <h3>Boissons</h3>
            <?php foreach ($produits as $produit): ?>
                <?php if ($produit['categorie'] === 'boisson'): ?>
                    <div class="produit">
                        <a href="?ajouter_produit=<?= $produit['id_produit'] ?>">
                            <?= htmlspecialchars($produit['nom_produit']) ?> (Prix : <?= $produit['prix_vente'] ?> € | Stock : <?= $produit['quantite_stock'] ?>)
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3>Produits Salés</h3>
            <?php foreach ($produits as $produit): ?>
                <?php if ($produit['categorie'] === 'nourriture salée'): ?>
                    <div class="produit">
                        <a href="?ajouter_produit=<?= $produit['id_produit'] ?>">
                            <?= htmlspecialchars($produit['nom_produit']) ?> (Prix : <?= $produit['prix_vente'] ?> € | Stock : <?= $produit['quantite_stock'] ?>)
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3>Produits Sucrés</h3>
            <?php foreach ($produits as $produit): ?>
                <?php if ($produit['categorie'] === 'nourriture sucrée'): ?>
                    <div class="produit">
                        <a href="?ajouter_produit=<?= $produit['id_produit'] ?>">
                            <?= htmlspecialchars($produit['nom_produit']) ?> (Prix : <?= $produit['prix_vente'] ?> € | Stock : <?= $produit['quantite_stock'] ?>)
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

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
                                    <td><?= $produit['prix_vente'] ?> €</td>
                                    <td><?= $produit['prix_vente'] * $quantite ?> €</td>
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

        <!-- Section "Sélectionner les Utilisateurs" -->
        <div class="utilisateurs-section">
            <h2>Sélectionner les Utilisateurs</h2>
            <form method="POST">
                <div class="utilisateurs-list">
                    <?php foreach ($utilisateurs as $utilisateur): ?>
                        <div class="utilisateur">
                            <input type="checkbox" name="utilisateurs[]" value="<?= $utilisateur['id_utilisateur'] ?>" id="utilisateur-<?= $utilisateur['id_utilisateur'] ?>">
                            <label for="utilisateur-<?= $utilisateur['id_utilisateur'] ?>">
                                <?= htmlspecialchars($utilisateur['nom'] . ' ' . $utilisateur['prenom']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <h2>Montant Total : <?= $montantTotal ?> €</h2>
                <button type="submit" name="finaliser_vente" class="btn-submit">Vendre</button>
            </form>
        </div>
    </div>
</body>
<?php include 'includes/footer.php'; ?>
</html>
