SELECT c.id, sA.label AS stationA, lA.label AS ligneA, sB.label AS stationB, lB.label AS ligneB, c.distance, c.in_zone
FROM correspondance c
JOIN desserte dA ON dA.id = c.desserte_a_id
JOIN desserte dB ON dB.id = c.desserte_b_id
JOIN station sA ON sA.id = dA.station_id
JOIN station sB ON sB.id = dB.station_id
JOIN ligne lA ON lA.id = dA.ligne_id
JOIN ligne lB ON lB.id = dB.ligne_id
WHERE sA.label IN ('Louis Blanc','Jaurès','Porte des Lilas','Gambetta','Place des Fêtes')
   OR sB.label IN ('Louis Blanc','Jaurès','Porte des Lilas','Gambetta','Place des Fêtes')
ORDER BY sA.label;
