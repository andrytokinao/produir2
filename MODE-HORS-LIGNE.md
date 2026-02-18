# 📴 Mode Hors Ligne - PRODUIR 2 Fanadihadiana

## ✅ Fonctionnalités Disponibles Hors Ligne

Le formulaire d'enquête **PRODUIR 2** est maintenant **entièrement fonctionnel hors ligne** !

### Ce qui fonctionne SANS connexion Internet :

1. ✅ **Authentification** - Les 3 utilisateurs (admin, enqueteur, supervisor)
2. ✅ **Formulaire complet** - Tous les 18 sections du questionnaire
3. ✅ **Saisie de données** - Tous les champs, listes déroulantes, cases à cocher
4. ✅ **Listes déroulantes cascades** - District → Commune → Fokontany (via FKT.xlsx local)
5. ✅ **Auto-sauvegarde** - Brouillon automatique toutes les 2 secondes
6. ✅ **Signatures électroniques** - Les 2 zones de signature fonctionnent
7. ✅ **Acquisition GPS** - Latitude, longitude et précision
8. ✅ **Validation des données** - Masques de saisie (téléphone, carte d'identité)
9. ✅ **Export JSON** - Téléchargement des données en format JSON
10. ✅ **Impression** - Impression du formulaire complet

### Limitations en mode hors ligne :

⚠️ **Cartes GPS** - Les cartes OpenStreetMap ne s'affichent pas MAIS :
- Les coordonnées GPS sont **toujours enregistrées** correctement
- Un message de fallback affiche les coordonnées : latitude, longitude, précision
- Les données GPS sont **complètement fonctionnelles** dans le JSON exporté

## 🔧 Configuration Requise

### Fichiers nécessaires :

```
Produir2/
├── index.html                  ✅ Formulaire principal
├── FKT.xlsx                    ✅ Données District/Commune/Fokontany
└── libs/
    └── xlsx/
        └── xlsx.full.min.js    ✅ Bibliothèque de lecture Excel (locale)
```

## 📋 Utilisation Hors Ligne

### 1. Préparation

1. Copiez le dossier `Produir2` complet sur votre ordinateur ou clé USB
2. Assurez-vous que tous les fichiers sont présents (voir structure ci-dessus)

### 2. Lancement

- **Ouvrez simplement** `index.html` dans votre navigateur :
  - Double-clic sur le fichier
  - OU clic droit → "Ouvrir avec" → Chrome/Firefox/Edge
  
- **Aucun serveur web requis !**

### 3. Connexion

Utilisez l'un des comptes :

| Utilisateur  | Mot de passe   |
|--------------|----------------|
| admin        | produir2024    |
| enqueteur    | enquete123     |
| supervisor   | super123       |

### 4. Remplissage du formulaire

1. Sélectionnez le **District** → La liste des **Communes** se charge automatiquement
2. Sélectionnez la **Commune** → La liste des **Fokontany** se charge
3. Remplissez tous les champs nécessaires
4. Les données sont **auto-sauvegardées** toutes les 2 secondes

### 5. GPS (hors ligne)

- Le bouton **GPS** fonctionne toujours !
- Les coordonnées sont enregistrées avec précision
- La carte ne s'affiche pas, mais un message confirme l'enregistrement :
  ```
  🗺️ Position GPS enregistrée
  Latitude: -18.xxxxx
  Longitude: 47.xxxxx
  Précision: 5m
  ```

### 6. Export des données

- Cliquez sur **"Hanolotra"** à la fin du formulaire
- Un fichier JSON est automatiquement téléchargé :
  ```
  PRODUIR2_Fanadihadiana_2026-02-18T14-30-00.json
  ```
- Ce fichier contient **TOUTES** les données saisies, y compris les GPS

## 🎨 Polices Système

Le formulaire utilise maintenant des **polices système natives** au lieu de Google Fonts :

- **Texte principal** : Segoe UI, Roboto, Helvetica (polices modernes)
- **Titres** : Georgia, Times New Roman (polices classiques)

Ces polices sont **préinstallées** sur Windows, Mac et Linux, donc **aucune connexion requise**.

## 💾 Données et Stockage

### Auto-sauvegarde (LocalStorage)

- Les données sont sauvegardées automatiquement dans le navigateur
- Même si vous fermez la page, vos données restent
- À la prochaine connexion, le brouillon est restauré automatiquement

### Export JSON

Chaque soumission génère un fichier JSON avec :
- ✅ Tous les champs (même vides)
- ✅ Date et heure de soumission
- ✅ Nom de l'enquêteur
- ✅ Coordonnées GPS avec précision
- ✅ Signatures électroniques (format base64)
- ✅ Métadonnées (version, nom du formulaire)

## 🔍 Vérification du Mode Hors Ligne

### Test simple :

1. **Activez le mode avion** sur votre ordinateur
2. Ouvrez `index.html` dans le navigateur
3. Connectez-vous avec un compte
4. Remplissez quelques champs
5. Testez l'acquisition GPS
6. Soumettez le formulaire

**✅ Si le JSON se télécharge, le mode hors ligne fonctionne parfaitement !**

## 🆘 Dépannage

### Problème : Les listes District/Commune/Fokontany sont vides

**Solution :**
- Vérifiez que `FKT.xlsx` est présent dans le même dossier que `index.html`
- Ouvrez la console du navigateur (F12) et cherchez des erreurs

### Problème : Erreur "XLSX is not defined"

**Solution :**
- Vérifiez que le dossier `libs/xlsx/` existe
- Vérifiez que `xlsx.full.min.js` est présent dans ce dossier

### Problème : Le GPS ne fonctionne pas

**Solution :**
- Sur ordinateur, le GPS nécessite une connexion (WiFi ou GPS externe)
- Sur mobile/tablette, le GPS fonctionne toujours hors ligne
- Vous pouvez saisir les coordonnées manuellement si nécessaire

### Problème : Le brouillon ne se restaure pas

**Solution :**
- Assurez-vous d'utiliser le **même navigateur**
- Ne videz pas le cache/cookies du navigateur
- Le brouillon est stocké localement par navigateur

## 📱 Utilisation Mobile

Le formulaire est **entièrement responsive** et fonctionne sur :
- 📱 Smartphones (iOS, Android)
- 📱 Tablettes
- 💻 Ordinateurs portables
- 🖥️ Ordinateurs de bureau

**Recommandation pour le terrain :**
- Utilisez une tablette ou smartphone avec GPS intégré
- Chargez la page une fois connecté à Internet
- Ensuite, le formulaire fonctionne 100% hors ligne

## 🔒 Sécurité et Confidentialité

- ✅ Toutes les données restent **locales** sur votre appareil
- ✅ Aucune transmission de données vers Internet
- ✅ Les brouillons sont stockés dans le **navigateur** uniquement
- ✅ L'export JSON est un fichier **local** sur votre ordinateur

## 📊 Collecte de Données sur le Terrain

### Workflow recommandé :

```
1. 📋 Enquêteur remplit le formulaire hors ligne
   ↓
2. 💾 Export automatique du fichier JSON
   ↓
3. 📤 Envoi manuel du JSON par email/USB quand connexion disponible
   ↓
4. 🗃️ Centralisation des données au bureau
```

## ✨ Avantages du Mode Hors Ligne

1. 🌍 **Travail sur le terrain** sans connexion Internet
2. ⚡ **Rapidité** - Pas de latence réseau
3. 💰 **Économie** - Pas de consommation de données mobiles
4. 🔋 **Autonomie** - Moins de batterie consommée
5. 🛡️ **Fiabilité** - Fonctionne même dans les zones reculées

---

**Version du formulaire :** 1.0  
**Date de mise à jour :** 18 février 2026  
**Mode hors ligne :** ✅ Activé
