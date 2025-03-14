<?php
// Informations de connexion à la base de données
$host = 'localhost'; // Adresse du serveur MySQL
$dbname = 'LeStore'; // Nom de la base de données
$username = 'root'; // Nom d'utilisateur MySQL (par défaut, "root" pour XAMPP)
$password = ''; // Mot de passe MySQL (par défaut, vide pour XAMPP)

try {
    // Création de l'objet PDO pour la connexion
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Configuration des options PDO pour afficher les erreurs
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Gestion des erreurs de connexion
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
