# Dépannage Backoffice Produir2

## Erreur : "MySQL server has gone away" lors de l'import

### Cause
Cette erreur se produit lorsque le fichier JSON est trop volumineux (> 1MB) à cause des images base64 dans les signatures.

### Solution appliquée

#### 1. Configuration MySQL modifiée
- **Fichier**: `C:\xampp\mysql\bin\my.ini`
- **Modification**: `max_allowed_packet = 64M` (au lieu de 1M)
- **Backup créé**: `my.ini.backup_[timestamp]`

#### 2. Code PHP optimisé
- **Fichier**: `db.php`
- La connexion PDO n'essaie plus de modifier `max_allowed_packet` (read-only)
- La configuration se base uniquement sur `my.ini`

### ⚠️ IMPORTANT: Redémarrage MySQL requis

Pour que la nouvelle configuration soit appliquée, vous DEVEZ redémarrer MySQL :

1. Ouvrez **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`)
2. Cliquez sur **"Stop"** à côté de MySQL
3. Attendez 3-5 secondes
4. Cliquez sur **"Start"** à côté de MySQL
5. Vérifiez que le voyant est vert

### Vérification

Après le redémarrage de MySQL, l'import de fichiers JSON jusqu'à 64MB devrait fonctionner sans erreur.

### Si le problème persiste

1. Vérifiez que `my.ini` contient bien `max_allowed_packet=64M`
2. Assurez-vous que MySQL a été redémarré APRÈS la modification
3. Vérifiez les logs MySQL : `C:\xampp\mysql\data\mysql_error.log`

### Taille du fichier actuel
- **EP14**: ~21.44 MB
- **EP15**: ~[à vérifier] MB

Les deux fichiers devraient maintenant pouvoir être importés.

---
**Date de modification**: 25/02/2026
