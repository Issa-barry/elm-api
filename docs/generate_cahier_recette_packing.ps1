$ErrorActionPreference = 'Stop'

function Add-Case {
    param(
        [Parameter(Mandatory = $true)][string]$Module,
        [Parameter(Mandatory = $true)][string]$SousModule,
        [Parameter(Mandatory = $true)][string]$Priorite,
        [Parameter(Mandatory = $true)][string]$Type,
        [Parameter(Mandatory = $true)][string]$Preconditions,
        [Parameter(Mandatory = $true)][string]$Etapes,
        [Parameter(Mandatory = $true)][string]$Donnees,
        [Parameter(Mandatory = $true)][string]$ResultatAttendu
    )

    $script:CaseIndex++
    $script:Cases.Add([pscustomobject]@{
            ID              = ('PACK-{0:d3}' -f $script:CaseIndex)
            Module          = $Module
            SousModule      = $SousModule
            Priorite        = $Priorite
            Type            = $Type
            Preconditions   = $Preconditions
            Etapes          = $Etapes
            Donnees         = $Donnees
            ResultatAttendu = $ResultatAttendu
        })
}

$script:CaseIndex = 0
$script:Cases = New-Object System.Collections.Generic.List[object]

# 1) Acces et navigation
Add-Case 'Acces et navigation' 'Ouverture ecran' 'P1' 'Fonctionnel' 'Utilisateur connecte avec permission packings.create' 'Ouvrir menu Packing puis Nouveau packing' 'N/A' 'La page packings-new se charge sans erreur.'
Add-Case 'Acces et navigation' 'Controle permission' 'P1' 'Securite' 'Utilisateur sans permission packings.create' 'Tenter acces direct URL #/packings/packings-new' 'N/A' 'Acces refuse (403 ou redirection autorisee).'
Add-Case 'Acces et navigation' 'Lien menu' 'P2' 'Fonctionnel' 'Utilisateur connecte' 'Cliquer entree menu Packing puis creation' 'N/A' 'Le bon ecran de creation est affiche.'
Add-Case 'Acces et navigation' 'Fil d ariane' 'P3' 'UX' 'Page packings-new ouverte' 'Verifier breadcrumb' 'N/A' 'Breadcrumb affiche packings / packings-new coherent.'
Add-Case 'Acces et navigation' 'Chargement initial' 'P1' 'UX' 'Page packings-new ouverte' 'Observer formulaire au premier rendu' 'N/A' 'Tous les champs et libelles attendus sont visibles.'
Add-Case 'Acces et navigation' 'Champs obligatoires' 'P1' 'UX' 'Page packings-new ouverte' 'Verifier presence indicateur obligatoire (*)' 'N/A' 'Les champs requis sont clairement identifies.'
Add-Case 'Acces et navigation' 'Etat bouton sauver' 'P2' 'UX' 'Page packings-new ouverte' 'Sans renseigner les champs requis, observer bouton Enregistrer' 'N/A' 'Bouton desactive ou soumission bloquee avec messages clairs.'
Add-Case 'Acces et navigation' 'Navigation clavier' 'P3' 'Accessibilite' 'Page packings-new ouverte' 'Utiliser touche Tab depuis premier champ jusqu au bouton' 'N/A' 'Ordre de tabulation logique et visible.'
Add-Case 'Acces et navigation' 'Responsive mobile' 'P2' 'UX' 'Navigateur en largeur 360px' 'Ouvrir page et parcourir formulaire' 'Viewport mobile' 'Formulaire utilisable sans chevauchement ni champ coupe.'
Add-Case 'Acces et navigation' 'Responsive desktop' 'P3' 'UX' 'Navigateur largeur >= 1440px' 'Ouvrir page et observer alignements' 'Viewport desktop' 'Alignement propre, champs lisibles, pas de zone vide anormale.'
Add-Case 'Acces et navigation' 'Rafraichissement F5' 'P3' 'Robustesse' 'Formulaire rempli partiellement' 'Appuyer F5 puis revenir a la page' 'N/A' 'Pas de crash front; comportement de reset/restauration est coherent.'
Add-Case 'Acces et navigation' 'Changement usine' 'P1' 'Fonctionnel' 'Utilisateur multi-usines avec selecteur usine visible' 'Changer usine puis revenir sur creation packing' 'Usine A puis Usine B' 'Le contexte actif est pris en compte pour les donnees chargees.'

# 2) Prestataire (machiniste)
Add-Case 'Prestataire' 'Chargement liste' 'P1' 'Fonctionnel' 'Au moins un machiniste existe dans usine courante' 'Ouvrir dropdown Machiniste' 'N/A' 'La liste se charge et contient les prestataires attendus.'
Add-Case 'Prestataire' 'Selection valide' 'P1' 'Fonctionnel' 'Liste machinistes disponible' 'Selectionner un machiniste puis sortir du champ' 'Machiniste existant' 'Le machiniste choisi reste selectionne.'
Add-Case 'Prestataire' 'Recherche par nom' 'P2' 'Fonctionnel' 'Dropdown avec fonction recherche' 'Saisir un fragment du nom dans le filtre' 'Ex: dia' 'La liste est filtree correctement.'
Add-Case 'Prestataire' 'Recherche par prenom' 'P2' 'Fonctionnel' 'Dropdown avec fonction recherche' 'Saisir un fragment du prenom' 'Ex: ali' 'Le bon prestataire est retrouve.'
Add-Case 'Prestataire' 'Aucun resultat' 'P3' 'UX' 'Dropdown avec recherche active' 'Chercher une valeur inexistante' 'zzzzzz' 'Message Aucun resultat ou liste vide explicite.'
Add-Case 'Prestataire' 'Champ vide soumission' 'P1' 'Validation' 'Tous les autres champs valides' 'Laisser Machiniste vide puis Enregistrer' 'prestataire vide' 'Message: Le prestataire est obligatoire.'
Add-Case 'Prestataire' 'Suppression selection' 'P2' 'Validation' 'Machiniste deja selectionne' 'Effacer selection puis soumettre' 'prestataire vide' 'Soumission refusee avec erreur prestataire obligatoire.'
Add-Case 'Prestataire' 'Type invalide payload' 'P1' 'Validation API' 'Utiliser DevTools pour modifier requete' 'Envoyer prestataire_id non entier' 'prestataire_id=abc' 'Erreur 422: prestataire invalide.'
Add-Case 'Prestataire' 'ID inexistant payload' 'P1' 'Validation API' 'Utiliser DevTools pour modifier requete' 'Envoyer prestataire_id inexistant' 'prestataire_id=999999' 'Erreur 422: prestataire selectionne introuvable.'
Add-Case 'Prestataire' 'Cross-usine payload' 'P1' 'Securite' '2 usines + id prestataire usine differente connu' 'Envoyer id prestataire d une autre usine' 'prestataire_id autre usine' 'Erreur 422 exists avec protection cross-usine.'
Add-Case 'Prestataire' 'Prestataire archive/inactif' 'P2' 'Fonctionnel' 'Prestataire inactif present en base' 'Ouvrir dropdown' 'N/A' 'Le comportement respecte la regle metier (masque ou marque clairement).'
Add-Case 'Prestataire' 'Grande volumetrie' 'P3' 'Performance UX' '>=100 prestataires dans usine' 'Ouvrir dropdown puis scroll complet' 'N/A' 'Liste reste fluide sans blocage.'
Add-Case 'Prestataire' 'Caracteres speciaux affichage' 'P3' 'UX' 'Prestataire avec apostrophe ou tiret existe' 'Selectionner et afficher valeur' 'Nom ex: O CAMARA-SY' 'Affichage correct sans corruption de texte.'
Add-Case 'Prestataire' 'Reset apres changement usine' 'P1' 'Fonctionnel' 'Machiniste selectionne en usine A' 'Basculer vers usine B' 'N/A' 'La selection precedente est invalidee si non appartenant a usine B.'

# 3) Date
Add-Case 'Date' 'Valeur par defaut' 'P1' 'Fonctionnel' 'Ouvrir page de creation' 'Observer champ Date au chargement' 'N/A' 'Date du jour pre-remplie.'
Add-Case 'Date' 'Date passee' 'P2' 'Fonctionnel' 'Formulaire valide' 'Choisir date hier puis soumettre' 'Date = J-1' 'Creation acceptee si autres champs valides.'
Add-Case 'Date' 'Date future' 'P2' 'Fonctionnel' 'Formulaire valide' 'Choisir date future puis soumettre' 'Date = J+1' 'Creation acceptee si regle metier autorise les dates futures.'
Add-Case 'Date' 'Annee bissextile valide' 'P3' 'Validation' 'Formulaire valide' 'Saisir 29/02/2024 puis soumettre' '29/02/2024' 'Date acceptee.'
Add-Case 'Date' 'Date impossible' 'P1' 'Validation' 'Formulaire valide' 'Saisir 31/02/2026 puis soumettre' '31/02/2026' 'Erreur date invalide.'
Add-Case 'Date' 'Date vide' 'P1' 'Validation' 'Formulaire valide hors date' 'Effacer date puis Enregistrer' 'date vide' 'Message: La date est obligatoire.'
Add-Case 'Date' 'Texte libre invalide' 'P1' 'Validation' 'Formulaire valide hors date' 'Saisir texte non date' 'abc' 'Erreur: date invalide.'
Add-Case 'Date' 'Format local vers API' 'P2' 'Integrite donnee' 'DevTools reseau ouvert' 'Soumettre date depuis datepicker' 'Affichage 28/02/2026' 'Payload API coherent avec format date attendu.'
Add-Case 'Date' 'Timezone' 'P2' 'Integrite donnee' 'Fuseau navigateur non UTC' 'Creer packing puis verifier date retour API' 'N/A' 'Pas de decalage jour-1/jour+1 non desire.'
Add-Case 'Date' 'Date tres ancienne' 'P3' 'Validation' 'Formulaire valide' 'Saisir 01/01/1970' '1970-01-01' 'Comportement explicite (accepte ou message metier).'
Add-Case 'Date' 'Date tres future' 'P3' 'Validation' 'Formulaire valide' 'Saisir 01/01/2100' '2100-01-01' 'Comportement explicite (accepte ou message metier).'
Add-Case 'Date' 'Espaces autour date' 'P3' 'Validation' 'Saisie manuelle autorisee' 'Entrer date avec espaces puis blur' ' 28/02/2026 ' 'Date normalisee ou erreur claire.'
Add-Case 'Date' 'Ouverture calendrier' 'P3' 'UX' 'Champ date visible' 'Cliquer icone calendrier' 'N/A' 'Datepicker s ouvre correctement.'
Add-Case 'Date' 'Saisie clavier + blur' 'P3' 'UX' 'Champ date editable' 'Saisir date valide puis perdre focus' '27/02/2026' 'Valeur conservee et validee.'

# 4) Rouleaux
Add-Case 'Rouleaux' 'Valeur par defaut' 'P1' 'Fonctionnel' 'Ouvrir page creation' 'Observer champ Rouleaux' 'N/A' 'Valeur initiale conforme au front (souvent 0).'
Add-Case 'Rouleaux' 'Valeur minimale positive' 'P1' 'Fonctionnel' 'Stock rouleaux >= 1' 'Saisir 1 puis soumettre' '1' 'Creation acceptee.'
Add-Case 'Rouleaux' 'Limite stock exacte' 'P1' 'Fonctionnel' 'Stock connu = N' 'Saisir N puis soumettre' 'nb_rouleaux = stock actuel' 'Creation acceptee avec stock resultant 0.'
Add-Case 'Rouleaux' 'Valeur zero' 'P2' 'Fonctionnel' 'Formulaire valide' 'Saisir 0 puis soumettre' '0' 'Creation acceptee; pas de decrement stock.'
Add-Case 'Rouleaux' 'Valeur negative front' 'P1' 'Validation' 'Champ numerique actif' 'Entrer -1' '-1' 'Blocage front ou message de validation.'
Add-Case 'Rouleaux' 'Valeur negative payload' 'P1' 'Validation API' 'Modifier requete via DevTools' 'Envoyer nb_rouleaux=-1' '-1' 'Erreur 422 min: ne peut pas etre negatif.'
Add-Case 'Rouleaux' 'Valeur decimale front' 'P1' 'Validation' 'Champ numerique actif' 'Entrer 1.5' '1.5' 'Blocage front ou message entier requis.'
Add-Case 'Rouleaux' 'Valeur decimale payload' 'P1' 'Validation API' 'Modifier requete via DevTools' 'Envoyer nb_rouleaux=1.5' '1.5' 'Erreur 422: doit etre un entier.'
Add-Case 'Rouleaux' 'Texte alphabetique' 'P1' 'Validation' 'Champ editable' 'Saisir abc' 'abc' 'Valeur refusee par front/API.'
Add-Case 'Rouleaux' 'Texte alphanumerique' 'P1' 'Validation' 'Champ editable' 'Saisir 12a' '12a' 'Valeur refusee.'
Add-Case 'Rouleaux' 'Espaces autour entier' 'P3' 'Validation' 'Champ editable' 'Saisir  5  puis soumettre' ' 5 ' 'Valeur normalisee en entier 5 ou erreur claire.'
Add-Case 'Rouleaux' 'Zeros a gauche' 'P3' 'Validation' 'Champ editable' 'Saisir 0007' '0007' 'Valeur interpretee en 7.'
Add-Case 'Rouleaux' 'Champ vide' 'P1' 'Validation' 'Autres champs valides' 'Vider rouleaux puis soumettre' 'vide' 'Erreur: nombre de rouleaux obligatoire.'
Add-Case 'Rouleaux' 'Stock insuffisant' 'P1' 'Metier' 'Stock connu < valeur testee' 'Saisir nb > stock puis soumettre' 'nb_rouleaux=stock+1' 'Erreur metier stock insuffisant.'
Add-Case 'Rouleaux' 'Produit rouleau non configure' 'P1' 'Metier' 'Parametre produit_rouleau_id vide' 'Saisir nb>0 puis soumettre' 'nb_rouleaux=1' 'Erreur: produit rouleau non configure.'
Add-Case 'Rouleaux' 'Concurrence stock' 'P1' 'Robustesse' 'Deux utilisateurs sur meme stock limite' 'Soumettre en parallele nb cumul > stock' 'N/A' 'Un des deux est refuse avec message stock insuffisant.'
Add-Case 'Rouleaux' 'Valeur tres grande' 'P2' 'Robustesse' 'Stock tres eleve en preprod' 'Saisir valeur tres grande' '1000000' 'Appli reste stable; validation metier coherente.'
Add-Case 'Rouleaux' 'Recalcul montant live' 'P2' 'UX' 'Prix deja renseigne' 'Modifier nb_rouleaux' 'ex: 3 -> 8' 'Montant total se met a jour immediatement.'

# 5) Prix par rouleau
Add-Case 'Prix par rouleau' 'Valeur initiale' 'P1' 'Fonctionnel' 'Ouvrir page creation' 'Observer champ Prix/rouleau' 'N/A' 'Valeur initiale conforme parametre ou front.'
Add-Case 'Prix par rouleau' 'Valeur zero' 'P2' 'Fonctionnel' 'Formulaire valide' 'Saisir prix 0 puis soumettre' '0' 'Creation acceptee avec montant calcule.'
Add-Case 'Prix par rouleau' 'Valeur 1' 'P1' 'Fonctionnel' 'Formulaire valide' 'Saisir prix 1 puis soumettre' '1' 'Creation acceptee.'
Add-Case 'Prix par rouleau' 'Valeur elevee' 'P2' 'Robustesse' 'Formulaire valide' 'Saisir prix tres eleve' '100000000' 'Creation acceptee si entier valide.'
Add-Case 'Prix par rouleau' 'Valeur negative front' 'P1' 'Validation' 'Champ numerique actif' 'Entrer -1' '-1' 'Blocage front ou message negatif interdit.'
Add-Case 'Prix par rouleau' 'Valeur negative payload' 'P1' 'Validation API' 'Modifier requete DevTools' 'Envoyer prix_par_rouleau=-1' '-1' 'Erreur 422 min.'
Add-Case 'Prix par rouleau' 'Valeur decimale front' 'P1' 'Validation' 'Champ numerique actif' 'Entrer 2.5' '2.5' 'Blocage front ou message entier requis.'
Add-Case 'Prix par rouleau' 'Valeur decimale payload' 'P1' 'Validation API' 'Modifier requete DevTools' 'Envoyer prix_par_rouleau=2.5' '2.5' 'Erreur 422 integer.'
Add-Case 'Prix par rouleau' 'Texte alphabetique' 'P1' 'Validation' 'Champ editable' 'Entrer abc' 'abc' 'Valeur refusee.'
Add-Case 'Prix par rouleau' 'Symbole devise' 'P2' 'Validation' 'Champ editable' 'Entrer GNF 500' 'GNF 500' 'Valeur refusee ou nettoyee en nombre pur.'
Add-Case 'Prix par rouleau' 'Espaces autour entier' 'P3' 'Validation' 'Champ editable' 'Entrer  500  puis soumettre' ' 500 ' 'Valeur normalisee en 500 ou message clair.'
Add-Case 'Prix par rouleau' 'Zeros a gauche' 'P3' 'Validation' 'Champ editable' 'Entrer 000500' '000500' 'Valeur interpretee en 500.'
Add-Case 'Prix par rouleau' 'Champ vide (UI)' 'P1' 'Validation' 'Autres champs valides' 'Vider prix puis soumettre depuis UI' 'vide' 'Comportement attendu defini: blocage front ou fallback serveur.'
Add-Case 'Prix par rouleau' 'Null en payload' 'P1' 'Validation API' 'Modifier requete DevTools' 'Envoyer prix_par_rouleau=null' 'null' 'Serveur applique prix par defaut parametre.'
Add-Case 'Prix par rouleau' 'Absent du payload' 'P1' 'Validation API' 'Modifier requete DevTools' 'Supprimer champ prix_par_rouleau' 'champ absent' 'Serveur applique prix par defaut parametre.'
Add-Case 'Prix par rouleau' 'Recalcul montant live' 'P2' 'UX' 'Rouleaux deja renseigne' 'Modifier prix' 'ex: 500 -> 650' 'Montant total se met a jour immediatement.'
Add-Case 'Prix par rouleau' 'Separateur millier' 'P3' 'Validation' 'Champ editable' 'Entrer 1 000' '1 000' 'Comportement explicite (normalisation ou erreur).'
Add-Case 'Prix par rouleau' 'Valeur tres grande limite int' 'P2' 'Robustesse' 'Formulaire valide' 'Entrer 2147483647' '2147483647' 'Pas de crash front; retour validation metier coherent.'

# 6) Calcul montant
Add-Case 'Calcul montant' 'Cas nul' 'P1' 'Calcul' 'Rouleaux=0 et Prix=0' 'Observer bloc Montant total' '0 x 0' 'Montant affiche 0 GNF.'
Add-Case 'Calcul montant' 'Cas simple 1' 'P1' 'Calcul' 'Rouleaux=1 Prix=500' 'Observer montant' '1 x 500' 'Montant affiche 500 GNF.'
Add-Case 'Calcul montant' 'Cas simple 2' 'P1' 'Calcul' 'Rouleaux=5 Prix=500' 'Observer montant' '5 x 500' 'Montant affiche 2500 GNF.'
Add-Case 'Calcul montant' 'Cas arbitraire' 'P2' 'Calcul' 'Rouleaux=123 Prix=456' 'Observer montant' '123 x 456' 'Montant affiche 56088 GNF.'
Add-Case 'Calcul montant' 'Update par nb' 'P2' 'UX' 'Prix fixe a 500' 'Changer nb_rouleaux de 2 a 4' '2->4' 'Montant passe de 1000 a 2000.'
Add-Case 'Calcul montant' 'Update par prix' 'P2' 'UX' 'Rouleaux fixes a 3' 'Changer prix de 500 a 700' '500->700' 'Montant passe de 1500 a 2100.'
Add-Case 'Calcul montant' 'Update rapide double champ' 'P3' 'UX' 'Formulaire ouvert' 'Modifier nb puis prix rapidement' '8 et 650' 'Montant final coherent sans retard visible.'
Add-Case 'Calcul montant' 'Lecture seule' 'P1' 'Securite UI' 'Formulaire ouvert' 'Verifier que montant ne peut pas etre saisi manuellement' 'N/A' 'Champ montant non editable cote front.'
Add-Case 'Calcul montant' 'Injection montant payload' 'P1' 'Securite API' 'Modifier requete DevTools' 'Envoyer montant arbitraire' 'montant=1 alors 10x500' 'Erreur 422: montant calcule par serveur.'
Add-Case 'Calcul montant' 'Cohérence post-creation' 'P1' 'Integrite donnee' 'Creer packing valide' 'Verifier montant en liste/detail/API' 'nb et prix connus' 'Montant persiste = nb_rouleaux * prix_par_rouleau.'
Add-Case 'Calcul montant' 'Grande valeur sans overflow' 'P2' 'Robustesse' 'Utiliser grandes valeurs valides' 'nb=50000 prix=40000' '2 000 000 000' 'Affichage et sauvegarde restent coherents.'
Add-Case 'Calcul montant' 'Pas de decimales' 'P3' 'Calcul' 'Plusieurs saisies effectuees' 'Observer rendu montant' 'N/A' 'Montant reste entier sans decimales.'

# 7) Soumission et creation
Add-Case 'Creation packing' 'Succes nominal' 'P1' 'Fonctionnel' 'Tous champs valides + stock suffisant' 'Cliquer Enregistrer' 'Machiniste valide, date, nb, prix' 'Creation reussie avec message succes.'
Add-Case 'Creation packing' 'Un clic = un enregistrement' 'P1' 'Robustesse' 'Formulaire valide' 'Cliquer Enregistrer une seule fois' 'N/A' 'Un seul packing cree en base.'
Add-Case 'Creation packing' 'Double clic rapide' 'P1' 'Robustesse' 'Formulaire valide' 'Double-cliquer rapidement sur Enregistrer' 'N/A' 'Aucun doublon cree.'
Add-Case 'Creation packing' 'Etat loading bouton' 'P2' 'UX' 'Reseau simule lent' 'Cliquer Enregistrer et observer bouton' 'Slow 3G ou throttling' 'Etat loading visible et interaction bloquee pendant requete.'
Add-Case 'Creation packing' 'Redirection post creation' 'P2' 'Fonctionnel' 'Creation reussie' 'Verifier ecran apres succes' 'N/A' 'Retour sur liste ou maintien ecran selon design, sans incoherence.'
Add-Case 'Creation packing' 'Visibilite dans la liste' 'P1' 'Fonctionnel' 'Creation reussie' 'Ouvrir liste packings et rechercher entree' 'Reference ou prestataire/date' 'Nouveau packing apparait avec bonnes valeurs.'
Add-Case 'Creation packing' 'Format reference' 'P2' 'Integrite donnee' 'Creation reussie' 'Verifier reference affichee' 'N/A' 'Reference au format PACK-YYYYMMDD-XXXX.'
Add-Case 'Creation packing' 'Trace created_by' 'P2' 'Audit' 'Acces detail/API disponible' 'Verifier created_by du packing cree' 'N/A' 'created_by renseigne avec utilisateur courant.'
Add-Case 'Creation packing' 'Trace updated_by' 'P3' 'Audit' 'Creation reussie' 'Verifier updated_by du packing cree' 'N/A' 'updated_by renseigne avec utilisateur courant.'
Add-Case 'Creation packing' 'Erreur validation conserve saisie' 'P2' 'UX' 'Provoquer une erreur 422' 'Soumettre puis corriger' 'Ex: prestataire vide' 'Les autres champs deja saisis sont conserves.'
Add-Case 'Creation packing' 'Erreur serveur 500' 'P1' 'Robustesse' 'Provoquer erreur interne (env test)' 'Soumettre formulaire valide' 'N/A' 'Message erreur generique affiche sans planter l ecran.'
Add-Case 'Creation packing' 'Annulation navigation' 'P3' 'Fonctionnel' 'Formulaire partiellement rempli' 'Quitter page sans sauvegarder' 'N/A' 'Aucun packing cree sans action Enregistrer.'

# 8) Statut, facture, stock alert
Add-Case 'Statut et facture' 'Statut par defaut creation UI' 'P1' 'Metier' 'Creation via ecran standard' 'Creer packing sans statut visible' 'N/A' 'Packing cree en statut final attendu par workflow (valide par defaut).'
Add-Case 'Statut et facture' 'Creation statut a_valider payload' 'P1' 'Metier API' 'Modifier payload via DevTools' 'Envoyer statut=a_valider' 'statut=a_valider' 'Packing cree a_valider, sans facture auto.'
Add-Case 'Statut et facture' 'Creation statut valide payload' 'P1' 'Metier API' 'Modifier payload via DevTools' 'Envoyer statut=valide' 'statut=valide' 'Packing valide avec facture auto creee.'
Add-Case 'Statut et facture' 'Lien facture pour valide' 'P1' 'Integrite donnee' 'Packing valide cree' 'Verifier facture_id' 'N/A' 'facture_id non null.'
Add-Case 'Statut et facture' 'Facture montant_total' 'P1' 'Calcul metier' 'Facture auto creee' 'Verifier montant_total facture' 'N/A' 'montant_total = montant packing.'
Add-Case 'Statut et facture' 'Facture nb_packings init' 'P2' 'Calcul metier' 'Facture auto creee' 'Verifier nb_packings' 'N/A' 'nb_packings initialise a 1.'
Add-Case 'Statut et facture' 'Date facture' 'P2' 'Integrite donnee' 'Facture auto creee' 'Comparer date facture et date packing' 'N/A' 'Dates coherentes (identiques sauf regle contraire).'
Add-Case 'Statut et facture' 'Decrement stock sur valide' 'P1' 'Metier stock' 'Stock initial connu et packing valide' 'Creer packing nb=N' 'N>0' 'Stock produit rouleau diminue de N.'
Add-Case 'Statut et facture' 'Pas de decrement sur a_valider' 'P1' 'Metier stock' 'Creation via payload statut=a_valider' 'Creer packing nb=N' 'N>0' 'Stock inchange apres creation.'
Add-Case 'Statut et facture' 'Stock alert in_stock' 'P2' 'Metier stock' 'Stock restant > seuil faible' 'Creer packing valide puis inspecter reponse' 'N/A' 'stock_alert.niveau = in_stock.'
Add-Case 'Statut et facture' 'Stock alert low_stock' 'P1' 'Metier stock' 'Stock ajustable proche seuil' 'Creer packing qui fait passer stock <= seuil et >0' 'N/A' 'stock_alert.niveau = low_stock + message approprie.'
Add-Case 'Statut et facture' 'Stock alert out_of_stock' 'P1' 'Metier stock' 'Stock ajustable proche 0' 'Creer packing qui amene stock a 0' 'N/A' 'stock_alert.niveau = out_of_stock + message rupture.'

# 9) Securite, robustesse, performance
Add-Case 'Securite et robustesse' 'facture_id cross-usine' 'P1' 'Securite' 'Connaitre facture_id d une autre usine' 'Injecter facture_id autre usine via payload' 'facture_id externe' 'Erreur 422 facture introuvable dans contexte.'
Add-Case 'Securite et robustesse' 'Statut enum invalide' 'P1' 'Validation API' 'Modifier payload via DevTools' 'Envoyer statut=invalide' 'statut=done' 'Erreur 422 enum statut.'
Add-Case 'Securite et robustesse' 'Injection script notes' 'P2' 'Securite XSS' 'Champ notes disponible (si ecran ou payload)' 'Soumettre <script>alert(1)</script>' 'payload notes script' 'Aucun script execute; texte neutralise.'
Add-Case 'Securite et robustesse' 'Texte SQL-like' 'P3' 'Securite' 'Champs texte/search disponibles' 'Soumettre '' OR 1=1 --' 'payload texte suspect' 'Aucune execution SQL, validation normale.'
Add-Case 'Securite et robustesse' 'Mode hors ligne' 'P1' 'Robustesse' 'Desactiver connexion navigateur' 'Cliquer Enregistrer' 'offline' 'Message erreur reseau; aucun enregistrement cree.'
Add-Case 'Securite et robustesse' 'Timeout reseau et retry' 'P1' 'Robustesse' 'Simuler timeout API' 'Soumettre puis reessayer apres retour reseau' 'N/A' 'Pas de doublon apres retry.'
Add-Case 'Securite et robustesse' 'Concurrence multi-utilisateurs' 'P1' 'Robustesse' 'Deux sessions actives' 'Soumettre en parallele sur stock limite' 'N/A' 'Integrite stock preservee avec un rejet explicite si besoin.'
Add-Case 'Securite et robustesse' 'Temps de reponse creation' 'P2' 'Performance' 'Environnement local/staging stable' 'Mesurer temps click->reponse sur 20 essais' 'N/A' 'Temps median conforme objectif equipe (ex <2s local).'
Add-Case 'Securite et robustesse' 'Retour navigateur apres succes' 'P3' 'Robustesse UX' 'Creation reussie' 'Bouton Back puis Forward et eventuel re-submit' 'N/A' 'Aucune creation fantome ou duplication involontaire.'
Add-Case 'Securite et robustesse' 'Session expiree' 'P1' 'Securite' 'Session proche expiration' 'Attendre expiration puis tenter Enregistrer' 'N/A' 'Redirection login/401 et aucun packing cree.'

$outputPath = Join-Path $PSScriptRoot 'cahier_recette_packing_front.xlsx'

function ConvertTo-ExcelColumnName {
    param([int]$Index)

    $name = ''
    $n = $Index

    while ($n -gt 0) {
        $remainder = ($n - 1) % 26
        $name = [char](65 + $remainder) + $name
        $n = [int](($n - 1) / 26)
    }

    return $name
}

function Escape-Xml {
    param([AllowNull()][string]$Value)
    if ($null -eq $Value) {
        return ''
    }

    return [System.Security.SecurityElement]::Escape($Value)
}

function New-InlineCell {
    param(
        [string]$Ref,
        [AllowNull()][string]$Value,
        [int]$Style = 0
    )

    $styleAttr = if ($Style -gt 0) { " s=`"$Style`"" } else { '' }
    $escaped = Escape-Xml -Value $Value

    return "<c r=`"$Ref`" t=`"inlineStr`"$styleAttr><is><t xml:space=`"preserve`">$escaped</t></is></c>"
}

function New-FormulaCell {
    param(
        [string]$Ref,
        [string]$Formula,
        [int]$Style = 0
    )

    $styleAttr = if ($Style -gt 0) { " s=`"$Style`"" } else { '' }
    $escapedFormula = Escape-Xml -Value $Formula
    return "<c r=`"$Ref`"$styleAttr><f>$escapedFormula</f></c>"
}

function Add-WorksheetRow {
    param(
        [System.Text.StringBuilder]$Builder,
        [int]$RowNumber,
        [string[]]$CellsXml
    )

    [void]$Builder.Append("<row r=`"$RowNumber`">")
    foreach ($cell in $CellsXml) {
        [void]$Builder.Append($cell)
    }
    [void]$Builder.Append('</row>')
}

function Add-ZipTextEntry {
    param(
        [System.IO.Compression.ZipArchive]$Zip,
        [string]$EntryPath,
        [string]$Content
    )

    $entry = $Zip.CreateEntry($EntryPath)
    $stream = $entry.Open()
    $writer = New-Object System.IO.StreamWriter($stream, (New-Object System.Text.UTF8Encoding($false)))
    try {
        $writer.Write($Content)
    }
    finally {
        $writer.Dispose()
        $stream.Dispose()
    }
}

$headers = @(
    'ID',
    'Module',
    'Sous-module',
    'Priorite',
    'Type',
    'Preconditions',
    'Etapes',
    'Donnees de test',
    'Resultat attendu',
    'Resultat obtenu',
    'Statut (OK/KO/NT)',
    'Severite anomalie',
    'Capture / preuve',
    'Testeur',
    'Date execution',
    'Commentaires'
)

$casesLastRow = $script:Cases.Count + 1
$sheet1Builder = New-Object System.Text.StringBuilder

$sheet1HeaderCells = @()
for ($col = 1; $col -le $headers.Count; $col++) {
    $ref = "$(ConvertTo-ExcelColumnName -Index $col)1"
    $sheet1HeaderCells += New-InlineCell -Ref $ref -Value $headers[$col - 1] -Style 1
}
Add-WorksheetRow -Builder $sheet1Builder -RowNumber 1 -CellsXml $sheet1HeaderCells

for ($index = 0; $index -lt $script:Cases.Count; $index++) {
    $rowNum = $index + 2
    $case = $script:Cases[$index]
    $rowCells = @(
        (New-InlineCell -Ref "A$rowNum" -Value $case.ID),
        (New-InlineCell -Ref "B$rowNum" -Value $case.Module),
        (New-InlineCell -Ref "C$rowNum" -Value $case.SousModule),
        (New-InlineCell -Ref "D$rowNum" -Value $case.Priorite),
        (New-InlineCell -Ref "E$rowNum" -Value $case.Type),
        (New-InlineCell -Ref "F$rowNum" -Value $case.Preconditions),
        (New-InlineCell -Ref "G$rowNum" -Value $case.Etapes),
        (New-InlineCell -Ref "H$rowNum" -Value $case.Donnees),
        (New-InlineCell -Ref "I$rowNum" -Value $case.ResultatAttendu),
        (New-InlineCell -Ref "K$rowNum" -Value 'NT')
    )
    Add-WorksheetRow -Builder $sheet1Builder -RowNumber $rowNum -CellsXml $rowCells
}

$caseColumnWidths = @(12, 24, 26, 10, 16, 40, 38, 24, 42, 28, 15, 18, 18, 16, 14, 30)
$sheet1Cols = New-Object System.Text.StringBuilder
[void]$sheet1Cols.Append('<cols>')
for ($i = 0; $i -lt $caseColumnWidths.Count; $i++) {
    $colIdx = $i + 1
    [void]$sheet1Cols.Append("<col min=`"$colIdx`" max=`"$colIdx`" width=`"$($caseColumnWidths[$i])`" customWidth=`"1`"/>")
}
[void]$sheet1Cols.Append('</cols>')

$sheet1Xml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <dimension ref="A1:P$casesLastRow"/>
  <sheetViews>
    <sheetView workbookViewId="0">
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  $sheet1Cols
  <sheetFormatPr defaultRowHeight="15"/>
  <sheetData>$sheet1Builder</sheetData>
  <autoFilter ref="A1:P$casesLastRow"/>
</worksheet>
"@

$modules = $script:Cases | Select-Object -ExpandProperty Module -Unique
$moduleEndRow = $modules.Count + 1
$sheet2LastRow = [Math]::Max(15, $moduleEndRow)

$sheet2Rows = @{}

function Add-Sheet2Cells {
    param(
        [int]$RowNumber,
        [string[]]$CellsXml
    )

    if (-not $sheet2Rows.ContainsKey($RowNumber)) {
        $sheet2Rows[$RowNumber] = New-Object System.Collections.Generic.List[string]
    }

    foreach ($cell in $CellsXml) {
        $sheet2Rows[$RowNumber].Add($cell)
    }
}

Add-Sheet2Cells -RowNumber 1 -CellsXml @(
    (New-InlineCell -Ref 'A1' -Value 'Indicateur' -Style 1),
    (New-InlineCell -Ref 'B1' -Value 'Valeur' -Style 1),
    (New-InlineCell -Ref 'D1' -Value 'Module' -Style 1),
    (New-InlineCell -Ref 'E1' -Value 'Total' -Style 1),
    (New-InlineCell -Ref 'F1' -Value 'OK' -Style 1),
    (New-InlineCell -Ref 'G1' -Value 'KO' -Style 1),
    (New-InlineCell -Ref 'H1' -Value '% OK' -Style 1),
    (New-InlineCell -Ref 'I1' -Value '% KO' -Style 1)
)

Add-Sheet2Cells -RowNumber 2 -CellsXml @(
    (New-InlineCell -Ref 'A2' -Value 'Total cas'),
    (New-FormulaCell -Ref 'B2' -Formula 'COUNTA(Cas_de_test!$A$2:$A$9999)')
)
Add-Sheet2Cells -RowNumber 3 -CellsXml @(
    (New-InlineCell -Ref 'A3' -Value 'Cas OK'),
    (New-FormulaCell -Ref 'B3' -Formula 'COUNTIF(Cas_de_test!$K$2:$K$9999,"OK")')
)
Add-Sheet2Cells -RowNumber 4 -CellsXml @(
    (New-InlineCell -Ref 'A4' -Value 'Cas KO'),
    (New-FormulaCell -Ref 'B4' -Formula 'COUNTIF(Cas_de_test!$K$2:$K$9999,"KO")')
)
Add-Sheet2Cells -RowNumber 5 -CellsXml @(
    (New-InlineCell -Ref 'A5' -Value 'Cas NT'),
    (New-FormulaCell -Ref 'B5' -Formula 'COUNTIF(Cas_de_test!$K$2:$K$9999,"NT")')
)
Add-Sheet2Cells -RowNumber 6 -CellsXml @(
    (New-InlineCell -Ref 'A6' -Value 'Cas executes (OK+KO)'),
    (New-FormulaCell -Ref 'B6' -Formula 'B3+B4')
)
Add-Sheet2Cells -RowNumber 7 -CellsXml @(
    (New-InlineCell -Ref 'A7' -Value '% Execution'),
    (New-FormulaCell -Ref 'B7' -Formula 'IF(B2=0,0,B6/B2)' -Style 2)
)
Add-Sheet2Cells -RowNumber 8 -CellsXml @(
    (New-InlineCell -Ref 'A8' -Value '% OK (sur executes)'),
    (New-FormulaCell -Ref 'B8' -Formula 'IF(B6=0,0,B3/B6)' -Style 2)
)
Add-Sheet2Cells -RowNumber 9 -CellsXml @(
    (New-InlineCell -Ref 'A9' -Value '% KO (sur executes)'),
    (New-FormulaCell -Ref 'B9' -Formula 'IF(B6=0,0,B4/B6)' -Style 2)
)

$moduleRow = 2
foreach ($module in $modules) {
    Add-Sheet2Cells -RowNumber $moduleRow -CellsXml @(
        (New-InlineCell -Ref "D$moduleRow" -Value $module),
        (New-FormulaCell -Ref "E$moduleRow" -Formula "COUNTIF(Cas_de_test!`$B`$2:`$B`$9999,D$moduleRow)"),
        (New-FormulaCell -Ref "F$moduleRow" -Formula "COUNTIFS(Cas_de_test!`$B`$2:`$B`$9999,D$moduleRow,Cas_de_test!`$K`$2:`$K`$9999,""OK"")"),
        (New-FormulaCell -Ref "G$moduleRow" -Formula "COUNTIFS(Cas_de_test!`$B`$2:`$B`$9999,D$moduleRow,Cas_de_test!`$K`$2:`$K`$9999,""KO"")"),
        (New-FormulaCell -Ref "H$moduleRow" -Formula "IF((F$moduleRow+G$moduleRow)=0,0,F$moduleRow/(F$moduleRow+G$moduleRow))" -Style 2),
        (New-FormulaCell -Ref "I$moduleRow" -Formula "IF((F$moduleRow+G$moduleRow)=0,0,G$moduleRow/(F$moduleRow+G$moduleRow))" -Style 2)
    )
    $moduleRow++
}

Add-Sheet2Cells -RowNumber 12 -CellsXml @(
    (New-InlineCell -Ref 'A12' -Value 'Mode emploi' -Style 1)
)
Add-Sheet2Cells -RowNumber 13 -CellsXml @(
    (New-InlineCell -Ref 'A13' -Value '1. Executer les tests de Cas_de_test.')
)
Add-Sheet2Cells -RowNumber 14 -CellsXml @(
    (New-InlineCell -Ref 'A14' -Value '2. Mettre Statut sur OK, KO ou NT.')
)
Add-Sheet2Cells -RowNumber 15 -CellsXml @(
    (New-InlineCell -Ref 'A15' -Value '3. Renseigner Resultat obtenu + preuve en cas KO.')
)

$sheet2Builder = New-Object System.Text.StringBuilder
foreach ($rowNumber in ($sheet2Rows.Keys | Sort-Object)) {
    Add-WorksheetRow -Builder $sheet2Builder -RowNumber $rowNumber -CellsXml $sheet2Rows[$rowNumber].ToArray()
}

$sheet2Widths = @{
    1 = 34
    2 = 18
    4 = 26
    5 = 10
    6 = 10
    7 = 10
    8 = 12
    9 = 12
}

$sheet2Cols = New-Object System.Text.StringBuilder
[void]$sheet2Cols.Append('<cols>')
foreach ($colIdx in ($sheet2Widths.Keys | Sort-Object)) {
    [void]$sheet2Cols.Append("<col min=`"$colIdx`" max=`"$colIdx`" width=`"$($sheet2Widths[$colIdx])`" customWidth=`"1`"/>")
}
[void]$sheet2Cols.Append('</cols>')

$sheet2Xml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <dimension ref="A1:I$sheet2LastRow"/>
  <sheetFormatPr defaultRowHeight="15"/>
  $sheet2Cols
  <sheetData>$sheet2Builder</sheetData>
</worksheet>
"@

$stylesXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="0"/>
  <fonts count="2">
    <font>
      <sz val="11"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="11"/>
      <name val="Calibri"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
"@

$workbookXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Cas_de_test" sheetId="1" r:id="rId1"/>
    <sheet name="Synthese" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
"@

$workbookRelsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
"@

$rootRelsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"@

$contentTypesXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"@

$createdAt = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
$coreXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Cahier recette packing front</dc:title>
  <dc:creator>Codex</dc:creator>
  <cp:lastModifiedBy>Codex</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">$createdAt</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">$createdAt</dcterms:modified>
</cp:coreProperties>
"@

$appXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Excel</Application>
  <DocSecurity>0</DocSecurity>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>2</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="2" baseType="lpstr">
      <vt:lpstr>Cas_de_test</vt:lpstr>
      <vt:lpstr>Synthese</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
  <Company></Company>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>false</SharedDoc>
  <HyperlinksChanged>false</HyperlinksChanged>
  <AppVersion>16.0300</AppVersion>
</Properties>
"@

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path $outputPath) {
    Remove-Item $outputPath -Force
}

$fileStream = [System.IO.File]::Open($outputPath, [System.IO.FileMode]::CreateNew)
$zip = New-Object System.IO.Compression.ZipArchive($fileStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)

try {
    Add-ZipTextEntry -Zip $zip -EntryPath '[Content_Types].xml' -Content $contentTypesXml
    Add-ZipTextEntry -Zip $zip -EntryPath '_rels/.rels' -Content $rootRelsXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'docProps/core.xml' -Content $coreXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'docProps/app.xml' -Content $appXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'xl/workbook.xml' -Content $workbookXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'xl/_rels/workbook.xml.rels' -Content $workbookRelsXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'xl/styles.xml' -Content $stylesXml
    Add-ZipTextEntry -Zip $zip -EntryPath 'xl/worksheets/sheet1.xml' -Content $sheet1Xml
    Add-ZipTextEntry -Zip $zip -EntryPath 'xl/worksheets/sheet2.xml' -Content $sheet2Xml
}
finally {
    $zip.Dispose()
    $fileStream.Dispose()
}

Write-Output "Cahier de recette genere: $outputPath"
Write-Output "Nombre de cas: $($script:Cases.Count)"
