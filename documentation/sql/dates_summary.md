# Dates d'ouverture — desserte.date_ouverture

315 des 392 lignes `desserte` (lignes 1 à 14) ont ete datees et appliquees en base. 77 restent a NULL.

Sources : fr.wikipedia.org, page "Liste des stations du metro de Paris" (donne la date d'ouverture
de chaque station — utilisee pour les stations desservies par une seule ligne, ou par la ligne qui
l'a ouverte en premier), croisee avec les articles "Ligne N du metro de Paris" (donnent la
chronologie des extensions par ligne — utilisee pour dater l'arrivee d'une ligne a une station deja
ouverte par une autre ligne, cas frequent aux correspondances comme Chatelet, Nation, Republique...).

Lignes 3b, 7b, 15 et 16 : aucune donnee `desserte`/`troncon` importee pour elles (absentes du dump
source `grande.sql`), donc rien a dater.

## Stations non datees (77), par ligne

Beaucoup sont des stations dont le nom commence par R a Z : la page Wikipedia listant toutes les
stations avec leur date a ete tronquee pendant la recuperation avant d'atteindre ces lettres, et je
n'ai pas invente de date plutot que de risquer une erreur. A rechercher manuellement ou via une
nouvelle tentative de recuperation :

- **Ligne 1** : Tuileries, Saint-Paul, Reuilly — Diderot, Saint-Mande
- **Ligne 2** : Victor Hugo
- **Ligne 3** : Villiers, Saint-Lazare, Sentier, Reaumur — Sebastopol, Temple, Republique, Rue Saint-Maur
- **Ligne 4** : Simplon, Barbes — Rochechouart, Strasbourg — Saint-Denis, Reaumur — Sebastopol,
  Saint-Michel, Saint-Germain-des-Pres, Saint-Sulpice, Saint-Placide, Vavin, Bagneux — Lucie Aubrac
- **Ligne 5** : Bobigny — Pablo Picasso, Bobigny — Pantin — Raymond Queneau, Stalingrad, Republique,
  Richard-Lenoir, Breguet — Sabin, Saint-Marcel
- **Ligne 6** : Sevres — Lecourbe, Saint-Jacques
- **Ligne 7** : Aubervilliers — Pantin — Quatre Chemins, Stalingrad, Censier — Daubenton, Tolbiac
- **Ligne 8** : Bonne Nouvelle, Strasbourg — Saint-Denis, Republique, Saint-Sebastien — Froissart,
  Reuilly — Diderot, Ecole Veterinaire de Maisons-Alfort
- **Ligne 9** : Rue de la Pompe, Alma — Marceau, Saint-Philippe du Roule, Saint-Augustin, Bonne Nouvelle,
  Strasbourg — Saint-Denis, Republique, Saint-Ambroise, Voltaire, Rue des Boulets, Robespierre
- **Ligne 10** : Boulogne — Pont de Saint-Cloud, Boulogne — Jean Jaures, Avenue Emile Zola, Segur,
  Vaneau, Sevres — Babylone
- **Ligne 11** : Telegraphe, Serge Gainsbourg, Romainville — Carnot, Rosny-Bois-Perrier
- **Ligne 12** : Front Populaire, Saint-Georges, Trinite — d'Estienne d'Orves, Saint-Lazare,
  Assemblee Nationale, Solferino, Rue du Bac, Vaneau, Rennes, Volontaires, Vaugirard
- **Ligne 13** : Saint-Denis — Porte de Paris, La Fourche, Liege
- **Ligne 14** : Bibliotheque Francois Mitterrand

## Points a verifier (faible confiance, deduits par position plutot que sources directement citees)

Quelques dates ont ete deduites par recoupement (ex: une station situee entre deux stations deja
ouvertes a la meme date, sur une meme extension documentee) plutot que lues directement sur une
source nommant explicitement la station. A verifier si une precision totale est necessaire :
- Ligne 5 : Gare de Lyon, Bastille -> 1906-07-28 (deduit du texte "extension a Gare de Lyon avec
  rebroussement a Place Mazas", sans confirmation independante par station)
- Ligne 10 : plusieurs dates de la section "restructuration ouest" 1937 (La Motte-Picquet — Grenelle,
  Porte d'Auteuil, Michel-Ange — Auteuil) deduites du recit de reprise de troncon a la ligne 8, sans
  liste explicite station par station
