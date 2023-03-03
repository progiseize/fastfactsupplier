# FastFactSupplier

[comment]: <> (TODO)
[comment]: <> (Lang sur pages config)
[comment]: <> (extrafields v15+)
[comment]: <> (Extrafields : type unique ne fonctionnent pas)
[comment]: <> (Extrafields : Prise en compte de la taille des champs)
[comment]: <> (Verification des infos fournisseurs sur societeinfo ?)
[comment]: <> (3.0 -> Interopérabilité ScanInvoice)

***
### 2.7.1 (03/03/2023)
* MAJ - CSS externalisé - Feuille de style commune module PROGISEIZE, nécéssite la version 1.4+
* FIX - remove var_dump

### 2.7 (30/11/2022)
* MAJ - Compatibilité MAIN_SECURITY_CSRF_TOKEN_RENEWAL_ON_EACH_CALL 
* FIX - Compatibilité extrafields v15+ 
* MAJ - Nombreuses mises à jour & corrections code 

### 2.6.4 (29/09/2022)
* MAJ - Modification des permissions, pensez à verifier les droits utilisateurs

### 2.6.3 (01/06/2022) 
* MAJ - Récupération des taux de TVA défini par le pays de la société
* NEW - Affichage mises à jour pages modules

### 2.6.2 (29/03/2022) 
* FIX - Suppression Hooks Descripteur

### 2.6.1 (28/03/2022) 
* FIX - Suppression doublons JS et CSS

### 2.6 (17/03/2022) 
* FIX - Vérification du paramètre de sécurité 'Taille maximum des fichiers envoyés'. Si égal à 0, l'upload de fichier est désactivé
* FIX - Correction bug si saisie des nombres avec virgule 
* NEW - Liaison projets factures - projets lignes de facture

### 2.5 (10/01/2022) 
* FIX - Mise à jour de sécurité importante

### 2.4 (25/11/2021)
* NEW - Option pour saisie : Affichage des 2 champs HT et TTC 
* FIX - Mise à jour d'un lien obsolète
* MAJ - Corrections CSS et descripteur

### 2.3 (25/10/2021)
* NEW - Traduction page options complète
* NEW - Traduction page saisie complète
* NEW - Ajout du numéro de facture dans le message confirmation
* NEW - Option pour choisir entre saisie du montant HT ou TTC
* NEW - Ajout des montants HT, TVA sous les lignes de factures
* NEW - Définir un compte bancaire par défaut. Si compte défini, possibilité de le modifier sur la page de saisie
* FIX - Enregistrement du champ projets
* FIX - Pouvoir supprimer la valeur du champ projets
* FIX - Masquage du titre des extrafields si aucun champ visible
* MAJ - Modification du nom de la page principale fournifact.php -> index.php
* MAJ - Le champ ffs_uploadfile se remplit également avec les fichiers liés
* MAJ - Mise à jour du système des extrafields - Meilleure compatibilité + bug fix
* MAJ - Mise à jour droits utilisateurs

### 2.2 (04/05/2021)
* NEW - Nouvelle option -> remplir un customfield ffs_uploadfile à true si upload d'un fichier à travers le formulaire
* FIX - Corrections enregistrement custom_field lignes de factures

### 2.1 (29/04/2021)
* FIX - Modification de la requête des projets (Les projets clôturés n'apparaissent plus. Les projets liés à un tiers apparaissent)
* MAJ - Ajout traductions
* NEW - Le symbole de la monnaie est maintenant récupéré dans les infos de dolibarr.
* MAJ - Ajouts de contrôles sur les valeurs de montants HT envoyés
* MAJ - Page insertion des codes comptables
* MAJ - Page options
* MAJ - Masque sur les extrafields si visible 0 ou -1

### 2.0.1 (23/02/2021)
* FIX - Correction bug mineur - Affichage des champs personnalisés lignes de factures malgré option décochée

### 2.0 (04/02/2021)
* NEW - Mise à jour Graphique
* NEW - Mise en place des extrafields lignes de facture
* NEW - Compatibilité module MultiCompany

### 1.8.1 (03/06/2020)
* FIX - Correction Champ Libellé
* FIX - Correctif rétrocompatibilité <= V10
* FIX - Correctif d'affichage de l'ensemble des extrafields

### 1.8 (11/05/2020)
* MAJ - Compatible Dolibarr v11
* MAJ - Revision et optimisation du code ( PREPARATION POUR V.2 )
* NEW - Mise en place des extrafields factures fournisseurs

### 1.7.2 (11/11/2019)
* MAJ - Compatible Dolibarr v10

### 1.7.1 (29/07/2019)
* NEW - Mise en place des champs des fichiers liés

### 1.7 (29/04/2019)
* NEW - Mise à jour graphique
* MAJ - Respect du standard Dolibarr dans la recherche des fournisseurs (select2)

### 1.6.2 (19/04/2019)
* NEW - Associer la facture à un projet si le module projets est activé

### 1.6.1 (10/03/2019)
* NEW - Ajout des modes et conditions de réglement si le fournisseur existe déjà

### 1.6 (31/10/2018
* NEW - Upload des factures avec module Drag&Drop
* NEW - Ajout de la gestion des catégories sur la page d'insert

### 1.5.2 (29/10/2018)
* NEW - Création des services avec association des codes comptables en option par défaut

### 1.5.1 (02/10/2018)
* MAJ - Mise en page de la page insert.php
* NEW - Accès à cette page via les options du module
* NEW - Personnalisation par cases à cocher des codes comptables à insérer

### 1.5 (16/05/2018)
* FIX - Correction bug mineur
* MAJ - Mise au propre du nom du module

### 1.4.3 (24/04/2018)
* FIX - Condition pour l'emplacement du menu du module en fonction de la version de Dolibarr
* NEW - Succès ou erreurs gérée par l'interface de Dolibarr
* NEW - Mise au propre de la page insert.php

### 1.4.2 (13/04/2018)
* FIX - Vérification si la réference fournisseur existe (php + js)
* MAJ - Modification de l'emplacement menu du module pour dolibarr V7

### 1.4.1 (28/03/2018)
* FIX - Prise en compte javascript des flottants avc une virgule
* NEW - Option pour selectionner par défaut la redirection vers le règlement
* NEW - Option pour définir le taux de tva par défaut
* NEW - Affichage du numéro de facture (Experimental)

### 1.4 (27/03/2018)
* NEW - Calcul du total TTC lors de la saisie des lignes de factures

### 1.3 (19/03/2018)
* MAJ - Tri alphabétique des listes de sélection
* NEW - Modifications des permissions et accès

### 1.2.4 (10/03/2018)
* NEW - Nouvelle façon de choisir la / les catégories sur la page options

### 1.2.3 (07/03/2018)
* FIX - Nouvelle modification des urls afin d'eviter les problèmes d'url complexes
* NEW - Redirection vers un paiement si la case est cochée

### 1.2.2 (22/02/2018)
* FIX - Correction d'un bug d'url dans les urls vers les appels ajax.
* FIX - Correction d'un bug d'url dans les includes du fichier insert.

### 1.2.1 (15/02/2018)
* FIX - Correction d'un bug d'url dans les pages options.

### 1.2 (27/01/2018)
* MAJ - Le fichier config est déprécié, une page d'options est maintenant disponible.

### 1.1 (21/01/2018)
* MAJ - Mise à jour des fonctions selon les classes fournies par dolibarr

### 1.0.5 (21/01/2018)
* FIX - Modification de numérotation de facture : correspond à la date de facturation
* FIX - Arrondi des totaux TTC

### 1.0.4 (19/01/2018)
* FIX - Verification des www dans les url
* FIX - Ajout de vérification de l'include vers le fichier main.inc.php 

### 1.0.3 (17/01/2018)
* NEW - Tri des services de manière alphabétique
* FIX - Gestion des erreurs de saisie des montant HT: l'utilisateur peut maintenant les saisir avec un . ou une ,
* NEW - Ajout d'un champ de recherche dans la liste déroulante des produits
* NEW - Le module est traduisible via des fichiers .lang, à placer dans le dossier langs
* MAJ - Le fichier config est supprimé au profit d'un fichier config.exemple, qu'il faudra renommer et paramètrer en lors d'une installation. Cela évite d'écraser ce fichier lors d'une maj.

### 1.0.2 (17/01/2018)
* NEW - Ajout d'un fichier de configuration config.php

### 1.0.1 (04/01/2018)
* NEW - Ajout d'une page insert.php afin d'installer les codes comptables dans le plan comptable