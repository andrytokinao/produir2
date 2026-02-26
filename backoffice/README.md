# Backoffice PRODUIT2

Cette application PHP / MySQL permet d'importer des fichiers JSON générés par le formulaire `Produir2` et de les stocker dans une base de données MySQL. Elle offre aussi une interface pour consulter, télécharger ou supprimer les enregistrements.

## Pré‑requis

- XAMPP (Apache + PHP 7.x ou 8.x + MySQL)
- Base de données MySQL

## Installation

1. Copier le dossier `backoffice` dans `c:\xampp\htdocs\Produir2` (déjà fait).
2. Créer la base de données. Exemple SQL :

```sql
CREATE DATABASE produir2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE produir2_db;

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    data JSON,
    full_json JSON,
    original_filename VARCHAR(255),
    menu VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

3. Mettre à jour les identifiants dans `db.php` si nécessaire.
4. Accéder à l'interface : `http://localhost/Produir2/backoffice/`.

## Utilisation

1. Cliquer sur "Importer un fichier JSON".
2. Sélectionner le fichier exporté depuis le formulaire.
3. Les données sont stockées et accessibles depuis la liste.
4. On peut voir le détail, télécharger le JSON original ou supprimer un enregistrement.

## Développement

- `db.php` : connexion PDO
- `upload.php` : formulaire d'envoi
- `import.php` : logique d'importation et validation
- `index.php` : listing
- `view.php` / `download.php` / `delete.php` : actions auxiliaires

Vous pouvez étendre la base de données pour séparer les membres, documents, etc. si vous avez besoin de requêtes plus fines.

---

Cette application sert de démonstration ; adaptez-la à vos besoins de sécurité et de performance avant de la mettre en production.