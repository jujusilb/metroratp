SELECT label, COUNT(*) AS n FROM station
WHERE label IN ('Porte des Lilas','Saint-Fargeau','Pelleport','Gambetta','Louis Blanc','Jaurès','Bolivar','Buttes Chaumont','Botzaris','Place des Fêtes','Danube','Pré-Saint-Gervais')
GROUP BY label
HAVING COUNT(*) > 1;

SELECT s.label AS station, GROUP_CONCAT(l.label ORDER BY l.label) AS lignes
FROM desserte d JOIN station s ON s.id=d.station_id JOIN ligne l ON l.id=d.ligne_id
WHERE s.label IN ('Porte des Lilas','Saint-Fargeau','Pelleport','Gambetta','Louis Blanc','Jaurès','Bolivar','Buttes Chaumont','Botzaris','Place des Fêtes','Danube','Pré-Saint-Gervais')
GROUP BY s.label ORDER BY s.label;
