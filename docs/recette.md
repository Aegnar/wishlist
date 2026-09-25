# Recette manuelle — V1

À dérouler dans le navigateur après chaque déploiement important. Cocher chaque ligne.

## Connexion
- [ ] Toute URL sans être connecté renvoie vers la page de connexion
- [ ] Mauvais mot de passe → « Identifiants invalides. »
- [ ] 6ᵉ échec en moins de 15 min → « Trop de tentatives »
- [ ] Connexion OK ; « Rester connecté » conserve la session après fermeture du navigateur
- [ ] Déconnexion

## Produits
- [ ] Ajout complet (titre, lien, magasin, prix « 12,50 », quantité, priorité, tags, description)
- [ ] Ajout avec photo envoyée depuis un fichier (aperçu avant envoi)
- [ ] Ajout avec photo par URL d'image publique
- [ ] URL d'image locale (http://127.0.0.1/…) refusée avec un message clair
- [ ] Titre vide → message sous le champ, saisie conservée
- [ ] Modification : valeurs préremplies, remplacement et suppression de la photo
- [ ] Tags : autocomplétion, Entrée / virgule, suppression d'une pastille

## Liste
- [ ] Ordre par défaut : Haute → Aucune → Basse
- [ ] Recherche texte, filtre par tags, par priorité, tris
- [ ] Totaux estimés (prix × quantité) et nombre de produits sans prix
- [ ] Bascule cartes / tableau mémorisée

## Achat
- [ ] « Marquer acheté » : date du jour et prix prérempli ; date future refusée
- [ ] Produit visible dans « Achetés » avec date, acheteur, prix payé ; total dépensé correct
- [ ] « Remettre à acheter »
- [ ] Suppression avec confirmation (photo supprimée du disque)

## Commentaires
- [ ] Ajout sans rechargement, retours à la ligne conservés, HTML affiché littéralement
- [ ] Suppression de son propre commentaire ; pas de bouton sur ceux des autres (sauf admin)

## Administration
- [ ] Création d'un compte ; identifiant déjà pris refusé
- [ ] Réinitialisation d'un mot de passe
- [ ] Désactivation d'un compte → la personne est déconnectée ; impossible de se désactiver soi-même
- [ ] Renommer / fusionner / supprimer un tag
- [ ] Un utilisateur non admin reçoit 403 sur /admin
- [ ] Mon compte : changement de mot de passe
- [ ] Changement de mot de passe → les autres appareils sont déconnectés

## Mobile (PWA)
- [ ] Affichage correct à 375 px de large, sans défilement horizontal
- [ ] « Ajouter à l'écran d'accueil » : icône et ouverture en plein écran
- [ ] Hors connexion → page « Pas de connexion »

## Exploitation
- [ ] `php bin/db.php check` → « Intégrité : OK »
- [ ] `php bin/db.php upgrade` sur une base à jour → « Base déjà à jour »
- [ ] Une sauvegarde est présente dans storage/backups/
- [ ] Console du navigateur sans erreur (notamment CSP)
