SELECT id, label, schema_x, schema_y FROM station
WHERE label IN (
    'Porte des Lilas', 'Saint-Fargeau', 'Pelleport', 'Gambetta',
    'Louis Blanc', 'Jaurès', 'Bolivar', 'Buttes Chaumont', 'Botzaris',
    'Place des Fêtes', 'Danube', 'Pré-Saint-Gervais', 'Pré Saint-Gervais'
)
ORDER BY label;
