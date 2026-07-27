-- Dates d'ouverture par desserte (station x ligne), sourcees depuis fr.wikipedia.org
-- (Liste des stations du metro de Paris + articles Ligne N du metro de Paris).
-- Lignes non couvertes (pas de donnees desserte/troncon importees) : 3b, 7b, 15, 16.

-- LIGNE 1
UPDATE desserte SET date_ouverture = '1992-04-01' WHERE id = 1; -- La Défense
UPDATE desserte SET date_ouverture = '1992-04-01' WHERE id = 2; -- Esplanade de la Défense
UPDATE desserte SET date_ouverture = '1937-04-29' WHERE id = 3; -- Pont de Neuilly
UPDATE desserte SET date_ouverture = '1937-04-29' WHERE id = 4; -- Les Sablons
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 5; -- Porte Maillot
UPDATE desserte SET date_ouverture = '1900-09-01' WHERE id = 6; -- Argentine
UPDATE desserte SET date_ouverture = '1900-09-01' WHERE id = 7; -- Charles de Gaulle — Étoile
UPDATE desserte SET date_ouverture = '1900-08-13' WHERE id = 8; -- George V
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 9; -- Franklin D. Roosevelt
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 10; -- Champs-Élysées — Clemenceau
UPDATE desserte SET date_ouverture = '1900-08-13' WHERE id = 11; -- Concorde
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 13; -- Palais Royal — Musée du Louvre
UPDATE desserte SET date_ouverture = '1900-08-13' WHERE id = 14; -- Louvre — Rivoli
UPDATE desserte SET date_ouverture = '1900-08-06' WHERE id = 15; -- Châtelet
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 16; -- Hôtel de Ville
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 18; -- Bastille
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 19; -- Gare de Lyon
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 21; -- Nation
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 22; -- Porte de Vincennes
UPDATE desserte SET date_ouverture = '1934-03-24' WHERE id = 24; -- Bérault
UPDATE desserte SET date_ouverture = '1934-03-24' WHERE id = 25; -- Château de Vincennes

-- LIGNE 2
UPDATE desserte SET date_ouverture = '1900-12-13' WHERE id = 26; -- Porte Dauphine
UPDATE desserte SET date_ouverture = '1900-12-13' WHERE id = 28; -- Charles de Gaulle — Étoile
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 29; -- Ternes
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 30; -- Courcelles
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 31; -- Monceau
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 32; -- Villiers
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 33; -- Rome
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 34; -- Place de Clichy
UPDATE desserte SET date_ouverture = '1902-10-21' WHERE id = 35; -- Blanche
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 36; -- Pigalle
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 37; -- Anvers
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 38; -- Barbès — Rochechouart
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 39; -- La Chapelle
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 40; -- Stalingrad
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 41; -- Jaurès
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 42; -- Colonel Fabien
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 43; -- Belleville
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 44; -- Couronnes
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 45; -- Ménilmontant
UPDATE desserte SET date_ouverture = '1903-02-25' WHERE id = 46; -- Père Lachaise
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 47; -- Philippe Auguste
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 48; -- Alexandre Dumas
UPDATE desserte SET date_ouverture = '1903-04-02' WHERE id = 49; -- Avron
UPDATE desserte SET date_ouverture = '1903-04-02' WHERE id = 50; -- Nation

-- LIGNE 3
UPDATE desserte SET date_ouverture = '1937-09-24' WHERE id = 51; -- Pont de Levallois — Bécon
UPDATE desserte SET date_ouverture = '1937-09-24' WHERE id = 52; -- Anatole France
UPDATE desserte SET date_ouverture = '1937-09-24' WHERE id = 53; -- Louise Michel
UPDATE desserte SET date_ouverture = '1911-02-15' WHERE id = 54; -- Porte de Champerret
UPDATE desserte SET date_ouverture = '1910-05-23' WHERE id = 55; -- Pereire
UPDATE desserte SET date_ouverture = '1910-05-23' WHERE id = 56; -- Malesherbes
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 58; -- Europe
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 60; -- Havre — Caumartin
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 61; -- Opéra
UPDATE desserte SET date_ouverture = '1904-11-03' WHERE id = 62; -- Quatre-Septembre
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 63; -- Bourse
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 66; -- Arts et Métiers
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 69; -- Parmentier
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 71; -- Père Lachaise
UPDATE desserte SET date_ouverture = '1905-01-25' WHERE id = 72; -- Gambetta
UPDATE desserte SET date_ouverture = '1971-04-02' WHERE id = 73; -- Porte de Bagnolet
UPDATE desserte SET date_ouverture = '1971-04-02' WHERE id = 74; -- Gallieni

-- LIGNE 4
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 75; -- Porte de Clignancourt
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 77; -- Marcadet — Poissonniers
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 78; -- Château Rouge
UPDATE desserte SET date_ouverture = '1907-11-15' WHERE id = 80; -- Gare du Nord
UPDATE desserte SET date_ouverture = '1907-11-15' WHERE id = 81; -- Gare de l'Est
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 82; -- Château d'Eau
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 85; -- Étienne Marcel
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 86; -- Les Halles
UPDATE desserte SET date_ouverture = '1908-04-21' WHERE id = 87; -- Châtelet
UPDATE desserte SET date_ouverture = '1910-01-09' WHERE id = 88; -- Cité
UPDATE desserte SET date_ouverture = '1910-01-09' WHERE id = 90; -- Odéon
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 94; -- Montparnasse — Bienvenüe
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 96; -- Raspail
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 97; -- Denfert-Rochereau
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 98; -- Mouton-Duvernet
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 99; -- Alésia
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 100; -- Porte d'Orléans
UPDATE desserte SET date_ouverture = '2013-03-23' WHERE id = 101; -- Mairie de Montrouge
UPDATE desserte SET date_ouverture = '2022-01-13' WHERE id = 102; -- Barbara

-- LIGNE 5
UPDATE desserte SET date_ouverture = '1942-10-12' WHERE id = 106; -- Église de Pantin
UPDATE desserte SET date_ouverture = '1942-10-12' WHERE id = 107; -- Hoche
UPDATE desserte SET date_ouverture = '1942-10-12' WHERE id = 108; -- Porte de Pantin
UPDATE desserte SET date_ouverture = '1947-03-21' WHERE id = 109; -- Ourcq
UPDATE desserte SET date_ouverture = '1942-10-12' WHERE id = 110; -- Laumière
UPDATE desserte SET date_ouverture = '1903-02-23' WHERE id = 111; -- Jaurès
UPDATE desserte SET date_ouverture = '1907-11-15' WHERE id = 113; -- Gare du Nord
UPDATE desserte SET date_ouverture = '1907-11-15' WHERE id = 114; -- Gare de l'Est
UPDATE desserte SET date_ouverture = '1906-12-17' WHERE id = 115; -- Jacques Bonsergent
UPDATE desserte SET date_ouverture = '1907-01-15' WHERE id = 117; -- Oberkampf
UPDATE desserte SET date_ouverture = '1906-07-28' WHERE id = 120; -- Bastille
UPDATE desserte SET date_ouverture = '1906-07-13' WHERE id = 121; -- Quai de la Rapée
UPDATE desserte SET date_ouverture = '1906-06-02' WHERE id = 122; -- Gare d'Austerlitz
UPDATE desserte SET date_ouverture = '1906-06-02' WHERE id = 124; -- Campo-Formio
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 125; -- Place d'Italie

-- LIGNE 6
UPDATE desserte SET date_ouverture = '1900-10-02' WHERE id = 126; -- Charles de Gaulle — Étoile
UPDATE desserte SET date_ouverture = '1900-10-02' WHERE id = 127; -- Kléber
UPDATE desserte SET date_ouverture = '1900-10-02' WHERE id = 128; -- Boissière
UPDATE desserte SET date_ouverture = '1900-10-02' WHERE id = 129; -- Trocadéro
UPDATE desserte SET date_ouverture = '1903-11-06' WHERE id = 130; -- Passy
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 131; -- Bir-Hakeim
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 132; -- Dupleix
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 133; -- La Motte-Picquet — Grenelle
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 134; -- Cambronne
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 136; -- Pasteur
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 137; -- Montparnasse — Bienvenüe
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 138; -- Edgar Quinet
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 139; -- Raspail
UPDATE desserte SET date_ouverture = '1909-10-30' WHERE id = 140; -- Denfert-Rochereau
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 142; -- Glacière
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 143; -- Corvisart
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 144; -- Place d'Italie
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 145; -- Nationale
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 146; -- Chevaleret
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 147; -- Quai de la Gare
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 148; -- Bercy
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 149; -- Dugommier
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 150; -- Daumesnil
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 151; -- Bel-Air
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 152; -- Picpus
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 153; -- Nation

-- LIGNE 7
UPDATE desserte SET date_ouverture = '1987-05-06' WHERE id = 154; -- La Courneuve — 8 Mai 1945
UPDATE desserte SET date_ouverture = '1979-10-04' WHERE id = 155; -- Fort d'Aubervilliers
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 157; -- Porte de la Villette
UPDATE desserte SET date_ouverture = '1910-11-11' WHERE id = 158; -- Corentin Cariou
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 159; -- Crimée
UPDATE desserte SET date_ouverture = '1910-11-06' WHERE id = 160; -- Riquet
UPDATE desserte SET date_ouverture = '1910-11-23' WHERE id = 162; -- Louis Blanc
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 163; -- Château-Landon
UPDATE desserte SET date_ouverture = '1907-11-15' WHERE id = 164; -- Gare de l'Est
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 165; -- Poissonnière
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 166; -- Cadet
UPDATE desserte SET date_ouverture = '1911-06-06' WHERE id = 167; -- Le Peletier
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 168; -- Chaussée d'Antin — La Fayette
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 169; -- Opéra
UPDATE desserte SET date_ouverture = '1916-07-01' WHERE id = 170; -- Pyramides
UPDATE desserte SET date_ouverture = '1916-07-01' WHERE id = 171; -- Palais Royal — Musée du Louvre
UPDATE desserte SET date_ouverture = '1926-04-19' WHERE id = 172; -- Pont Neuf
UPDATE desserte SET date_ouverture = '1926-04-16' WHERE id = 173; -- Pont Marie
UPDATE desserte SET date_ouverture = '1900-08-06' WHERE id = 174; -- Châtelet
UPDATE desserte SET date_ouverture = '1930-06-03' WHERE id = 175; -- Sully — Morland
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 176; -- Jussieu
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 177; -- Place Monge
UPDATE desserte SET date_ouverture = '1930-02-15' WHERE id = 179; -- Les Gobelins
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 180; -- Place d'Italie
UPDATE desserte SET date_ouverture = '1930-03-07' WHERE id = 182; -- Maison Blanche
UPDATE desserte SET date_ouverture = '1982-12-10' WHERE id = 183; -- Le Kremlin-Bicêtre
UPDATE desserte SET date_ouverture = '1982-12-10' WHERE id = 184; -- Villejuif — Léo Lagrange
UPDATE desserte SET date_ouverture = '1982-12-10' WHERE id = 185; -- Villejuif — Paul Vaillant-Couturier
UPDATE desserte SET date_ouverture = '1985-02-28' WHERE id = 186; -- Villejuif — Louis Aragon
UPDATE desserte SET date_ouverture = '1930-03-07' WHERE id = 187; -- Porte d'Italie
UPDATE desserte SET date_ouverture = '1930-03-07' WHERE id = 188; -- Porte de Choisy
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 189; -- Porte d'Ivry
UPDATE desserte SET date_ouverture = '1946-05-01' WHERE id = 190; -- Pierre et Marie Curie
UPDATE desserte SET date_ouverture = '1946-05-01' WHERE id = 191; -- Mairie d'Ivry

-- LIGNE 8
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 192; -- Balard
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 193; -- Lourmel
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 194; -- Boucicaut
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 195; -- Félix Faure
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 196; -- Commerce
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 197; -- La Motte-Picquet — Grenelle
UPDATE desserte SET date_ouverture = '1913-07-13' WHERE id = 198; -- École Militaire
UPDATE desserte SET date_ouverture = '1913-07-13' WHERE id = 199; -- La Tour-Maubourg
UPDATE desserte SET date_ouverture = '1913-07-13' WHERE id = 200; -- Invalides
UPDATE desserte SET date_ouverture = '1900-08-13' WHERE id = 201; -- Concorde
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 202; -- Madeleine
UPDATE desserte SET date_ouverture = '1913-07-13' WHERE id = 203; -- Opéra
UPDATE desserte SET date_ouverture = '1928-06-30' WHERE id = 204; -- Richelieu — Drouot
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 205; -- Grands Boulevards
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 209; -- Filles du Calvaire
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 211; -- Chemin Vert
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 212; -- Bastille
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 213; -- Ledru-Rollin
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 214; -- Faidherbe — Chaligny
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 216; -- Montgallet
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 217; -- Daumesnil
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 218; -- Michel Bizot
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 219; -- Porte Dorée
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 220; -- Porte de Charenton
UPDATE desserte SET date_ouverture = '1942-10-05' WHERE id = 221; -- Liberté
UPDATE desserte SET date_ouverture = '1942-10-05' WHERE id = 222; -- Charenton — Écoles
UPDATE desserte SET date_ouverture = '1970-09-19' WHERE id = 224; -- Maisons-Alfort — Stade
UPDATE desserte SET date_ouverture = '1972-04-24' WHERE id = 225; -- Maisons-Alfort — Les Juilliottes
UPDATE desserte SET date_ouverture = '1973-09-24' WHERE id = 226; -- Créteil — L'Échat
UPDATE desserte SET date_ouverture = '1974-09-09' WHERE id = 227; -- Créteil — Université
UPDATE desserte SET date_ouverture = '1974-09-10' WHERE id = 228; -- Créteil — Préfecture
UPDATE desserte SET date_ouverture = '2011-10-08' WHERE id = 229; -- Pointe du Lac

-- LIGNE 9
UPDATE desserte SET date_ouverture = '1934-02-03' WHERE id = 230; -- Pont de Sèvres
UPDATE desserte SET date_ouverture = '1934-02-03' WHERE id = 231; -- Billancourt
UPDATE desserte SET date_ouverture = '1934-02-03' WHERE id = 232; -- Marcel Sembat
UPDATE desserte SET date_ouverture = '1923-09-29' WHERE id = 233; -- Porte de Saint-Cloud
UPDATE desserte SET date_ouverture = '1922-11-08' WHERE id = 234; -- Exelmans
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 235; -- Michel-Ange — Molitor
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 236; -- Michel-Ange — Auteuil
UPDATE desserte SET date_ouverture = '1922-11-08' WHERE id = 237; -- Jasmin
UPDATE desserte SET date_ouverture = '1922-11-08' WHERE id = 238; -- Ranelagh
UPDATE desserte SET date_ouverture = '1922-11-08' WHERE id = 239; -- La Muette
UPDATE desserte SET date_ouverture = '1922-11-08' WHERE id = 241; -- Trocadéro
UPDATE desserte SET date_ouverture = '1923-05-27' WHERE id = 242; -- Iéna
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 244; -- Franklin D. Roosevelt
UPDATE desserte SET date_ouverture = '1923-05-27' WHERE id = 246; -- Miromesnil
UPDATE desserte SET date_ouverture = '1904-10-19' WHERE id = 248; -- Havre — Caumartin
UPDATE desserte SET date_ouverture = '1923-06-03' WHERE id = 249; -- Chaussée d'Antin — La Fayette
UPDATE desserte SET date_ouverture = '1928-06-30' WHERE id = 250; -- Richelieu — Drouot
UPDATE desserte SET date_ouverture = '1931-05-05' WHERE id = 251; -- Grands Boulevards
UPDATE desserte SET date_ouverture = '1907-01-15' WHERE id = 255; -- Oberkampf
UPDATE desserte SET date_ouverture = '1933-12-10' WHERE id = 258; -- Charonne
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 260; -- Nation
UPDATE desserte SET date_ouverture = '1933-12-10' WHERE id = 261; -- Buzenval
UPDATE desserte SET date_ouverture = '1933-12-10' WHERE id = 262; -- Maraîchers
UPDATE desserte SET date_ouverture = '1933-12-10' WHERE id = 263; -- Porte de Montreuil
UPDATE desserte SET date_ouverture = '1937-10-14' WHERE id = 265; -- Croix de Chavaux
UPDATE desserte SET date_ouverture = '1937-10-14' WHERE id = 266; -- Mairie de Montreuil

-- LIGNE 10
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 269; -- Michel-Ange — Molitor
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 270; -- Chardon-Lagache
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 271; -- Mirabeau
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 272; -- Église d'Auteuil
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 273; -- Michel-Ange — Auteuil
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 274; -- Porte d'Auteuil
UPDATE desserte SET date_ouverture = '1913-09-30' WHERE id = 275; -- Javel - Parc André Citroën
UPDATE desserte SET date_ouverture = '1913-07-13' WHERE id = 276; -- Charles Michels
UPDATE desserte SET date_ouverture = '1937-07-27' WHERE id = 278; -- La Motte-Picquet — Grenelle
UPDATE desserte SET date_ouverture = '1923-12-30' WHERE id = 280; -- Duroc
UPDATE desserte SET date_ouverture = '1925-03-10' WHERE id = 283; -- Mabillon
UPDATE desserte SET date_ouverture = '1926-02-14' WHERE id = 284; -- Odéon
UPDATE desserte SET date_ouverture = '1988-12-15' WHERE id = 285; -- Cluny — La Sorbonne
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 286; -- Maubert — Mutualité
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 287; -- Cardinal Lemoine
UPDATE desserte SET date_ouverture = '1931-04-26' WHERE id = 288; -- Jussieu
UPDATE desserte SET date_ouverture = '1939-07-12' WHERE id = 289; -- Gare d'Austerlitz

-- LIGNE 11
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 290; -- Châtelet
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 291; -- Hôtel de Ville
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 292; -- Rambuteau
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 293; -- Arts et Métiers
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 294; -- République
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 295; -- Goncourt
UPDATE desserte SET date_ouverture = '1903-01-31' WHERE id = 296; -- Belleville
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 297; -- Pyrénées
UPDATE desserte SET date_ouverture = '1935-04-28' WHERE id = 298; -- Jourdain
UPDATE desserte SET date_ouverture = '1911-07-18' WHERE id = 299; -- Place des Fêtes
UPDATE desserte SET date_ouverture = '1921-11-27' WHERE id = 301; -- Porte des Lilas
UPDATE desserte SET date_ouverture = '1937-02-17' WHERE id = 302; -- Mairie des Lilas
UPDATE desserte SET date_ouverture = '2024-06-13' WHERE id = 305; -- Montreuil — Hôpital
UPDATE desserte SET date_ouverture = '2024-06-13' WHERE id = 306; -- La Dhuys
UPDATE desserte SET date_ouverture = '2024-06-13' WHERE id = 307; -- Coteaux Beauclair

-- LIGNE 12
UPDATE desserte SET date_ouverture = '2022-05-31' WHERE id = 309; -- Mairie d'Aubervilliers
UPDATE desserte SET date_ouverture = '2022-05-31' WHERE id = 310; -- Aimé Césaire
UPDATE desserte SET date_ouverture = '1916-08-23' WHERE id = 312; -- Porte de la Chapelle
UPDATE desserte SET date_ouverture = '1916-08-23' WHERE id = 313; -- Marx Dormoy
UPDATE desserte SET date_ouverture = '1916-08-23' WHERE id = 314; -- Marcadet — Poissonniers
UPDATE desserte SET date_ouverture = '1912-10-30' WHERE id = 315; -- Jules Joffrin
UPDATE desserte SET date_ouverture = '1912-10-31' WHERE id = 316; -- Lamarck — Caulaincourt
UPDATE desserte SET date_ouverture = '1912-10-31' WHERE id = 317; -- Abbesses
UPDATE desserte SET date_ouverture = '1911-04-08' WHERE id = 318; -- Pigalle
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 320; -- Notre-Dame-de-Lorette
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 323; -- Madeleine
UPDATE desserte SET date_ouverture = '1900-08-13' WHERE id = 324; -- Concorde
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 330; -- Notre-Dame-des-Champs
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 331; -- Montparnasse — Bienvenüe
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 332; -- Falguière
UPDATE desserte SET date_ouverture = '1906-04-24' WHERE id = 333; -- Pasteur
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 336; -- Convention
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 337; -- Porte de Versailles
UPDATE desserte SET date_ouverture = '1934-03-24' WHERE id = 338; -- Corentin Celton
UPDATE desserte SET date_ouverture = '1934-03-24' WHERE id = 339; -- Mairie d'Issy

-- LIGNE 13
UPDATE desserte SET date_ouverture = '2008-06-14' WHERE id = 340; -- Les Courtilles
UPDATE desserte SET date_ouverture = '2008-06-14' WHERE id = 341; -- Les Agnettes
UPDATE desserte SET date_ouverture = '1980-05-09' WHERE id = 342; -- Gabriel Péri
UPDATE desserte SET date_ouverture = '1980-05-03' WHERE id = 343; -- Mairie de Clichy
UPDATE desserte SET date_ouverture = '1912-01-20' WHERE id = 344; -- Porte de Clichy
UPDATE desserte SET date_ouverture = '1912-01-20' WHERE id = 345; -- Brochant
UPDATE desserte SET date_ouverture = '1998-05-25' WHERE id = 346; -- Saint-Denis — Université
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 347; -- Basilique de Saint-Denis
UPDATE desserte SET date_ouverture = '1952-06-30' WHERE id = 349; -- Carrefour Pleyel
UPDATE desserte SET date_ouverture = '1952-06-30' WHERE id = 350; -- Mairie de Saint-Ouen
UPDATE desserte SET date_ouverture = '1952-06-30' WHERE id = 351; -- Garibaldi
UPDATE desserte SET date_ouverture = '1911-02-26' WHERE id = 352; -- Porte de Saint-Ouen
UPDATE desserte SET date_ouverture = '1911-02-26' WHERE id = 353; -- Guy Môquet
UPDATE desserte SET date_ouverture = '1902-10-07' WHERE id = 355; -- Place de Clichy
UPDATE desserte SET date_ouverture = '1911-02-26' WHERE id = 357; -- Saint-Lazare
UPDATE desserte SET date_ouverture = '1973-06-27' WHERE id = 358; -- Miromesnil
UPDATE desserte SET date_ouverture = '1975-02-18' WHERE id = 359; -- Champs-Élysées — Clemenceau
UPDATE desserte SET date_ouverture = '1975-02-18' WHERE id = 360; -- Invalides
UPDATE desserte SET date_ouverture = '1975-02-18' WHERE id = 361; -- Varenne
UPDATE desserte SET date_ouverture = '1975-02-18' WHERE id = 362; -- Saint-François-Xavier
UPDATE desserte SET date_ouverture = '1975-02-18' WHERE id = 363; -- Duroc
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 364; -- Montparnasse — Bienvenüe
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 365; -- Gaîté
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 366; -- Pernety
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 367; -- Plaisance
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 368; -- Porte de Vanves
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 369; -- Malakoff — Plateau de Vanves
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 370; -- Malakoff — Rue Étienne Dolet
UPDATE desserte SET date_ouverture = '1976-11-09' WHERE id = 371; -- Châtillon — Montrouge

-- LIGNE 14
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 372; -- Saint-Denis — Pleyel
UPDATE desserte SET date_ouverture = '2020-12-14' WHERE id = 373; -- Mairie de Saint-Ouen
UPDATE desserte SET date_ouverture = '2020-12-14' WHERE id = 374; -- Saint-Ouen
UPDATE desserte SET date_ouverture = '2021-01-28' WHERE id = 375; -- Porte de Clichy
UPDATE desserte SET date_ouverture = '2020-12-14' WHERE id = 376; -- Pont Cardinet
UPDATE desserte SET date_ouverture = '2003-12-16' WHERE id = 377; -- Saint-Lazare
UPDATE desserte SET date_ouverture = '1910-11-05' WHERE id = 378; -- Madeleine
UPDATE desserte SET date_ouverture = '1916-07-01' WHERE id = 379; -- Pyramides
UPDATE desserte SET date_ouverture = '1900-08-06' WHERE id = 380; -- Châtelet
UPDATE desserte SET date_ouverture = '1900-07-19' WHERE id = 381; -- Gare de Lyon
UPDATE desserte SET date_ouverture = '1909-03-01' WHERE id = 382; -- Bercy
UPDATE desserte SET date_ouverture = '1998-10-15' WHERE id = 383; -- Cour Saint-Émilion
UPDATE desserte SET date_ouverture = '2007-06-26' WHERE id = 385; -- Olympiades
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 386; -- Maison Blanche
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 387; -- Hôpital Bicêtre
UPDATE desserte SET date_ouverture = '2025-01-18' WHERE id = 388; -- Villejuif — Gustave Roussy
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 389; -- L'Haÿ-les-Roses
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 390; -- Chevilly-Larue
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 391; -- Thiais — Orly
UPDATE desserte SET date_ouverture = '2024-06-24' WHERE id = 392; -- Aéroport d'Orly

