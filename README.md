# LeStore


Code pour ajouter la base de donner en MySQL : 

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS LeStore;
USE LeStore;

-- Table Utilisateur
CREATE TABLE Utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
	identifiant VARCHAR(100) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    montant_ventes DECIMAL(10, 2) DEFAULT 0,
    role ENUM('utilisateur', 'admin') DEFAULT 'utilisateur' -- Définition des rôles
);

-- Table Produit
CREATE TABLE Produits (
    id_produit INT AUTO_INCREMENT PRIMARY KEY,
    nom_produit VARCHAR(100) NOT NULL,
    description TEXT,
    prix_unitaire DECIMAL(10, 2) NOT NULL,
    quantite_stock INT NOT NULL
);

-- Table Vente
CREATE TABLE Vente (
    id_vente INT AUTO_INCREMENT PRIMARY KEY,
    date_vente DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    montant_total DECIMAL(10, 2) NOT NULL
);

-- Table Utilisateur_Vente (relation N,N entre Utilisateur et Vente)
CREATE TABLE Utilisateur_Vente (
    id_utilisateur INT NOT NULL,
    id_vente INT NOT NULL,
    pourcentage_participation DECIMAL(5, 2) DEFAULT 100, -- Par défaut, 100% si une seule personne
    PRIMARY KEY (id_utilisateur, id_vente),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_vente) REFERENCES Vente(id_vente) ON DELETE CASCADE
);

-- Table Détail_Vente
CREATE TABLE Detail_Vente (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_vente INT NOT NULL,
    id_produit INT NOT NULL,
    quantite INT NOT NULL,
    sous_total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (id_vente) REFERENCES Vente(id_vente) ON DELETE CASCADE,
    FOREIGN KEY (id_produit) REFERENCES Produit(id_produit) ON DELETE CASCADE
);

-- Table Caisse
CREATE TABLE Caisse (
    id_caisse INT AUTO_INCREMENT PRIMARY KEY,
    montant_total DECIMAL(15, 2) DEFAULT 0 -- Caisse totale cumulée
);

-- Insertion initiale dans la table Caisse (caisse totale à 0 au départ)
INSERT INTO Caisse (montant_total) VALUES (0);


