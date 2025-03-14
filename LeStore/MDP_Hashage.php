<?php
include 'includes/db.php';


// Récupérer tous les utilisateurs
$stmt = $conn->query("SELECT id_utilisateur, mot_de_passe FROM Utilisateur");
$utilisateurs = $stmt->fetchAll();

foreach ($utilisateurs as $user) {
    $id = $user['id_utilisateur'];
    $mot_de_passe = $user['mot_de_passe'];

    // Si le mot de passe n'est pas encore hashé
    if (!password_get_info($mot_de_passe)['algo']) {
        $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        // Mettre à jour le mot de passe dans la base de données
        $update = $conn->prepare("UPDATE Utilisateur SET mot_de_passe = :mot_de_passe WHERE id_utilisateur = :id");
        $update->execute(['mot_de_passe' => $mot_de_passe_hash, 'id' => $id]);
    }
}

echo "Tous les mots de passe ont été hashés avec succès.";
?>
